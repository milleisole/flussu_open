<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * CLASS-NAME:       DocumentSpace
 * CREATED:          2026-04-04 - Flussu v4.6
 * -------------------------------------------------------*
 * Manages a per-session document space for AI context enrichment.
 * Documents uploaded by the user are processed (text extracted,
 * images base64-encoded, spreadsheets converted to CSV) and stored
 * in a session-specific directory. The extracted content is injected
 * as context into AI prompts.
 * -------------------------------------------------------*/

namespace Flussu\Documents;

use Flussu\General;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Image;
use Flussu\Documents\Exception\EncryptedWorkbookException;

class DocumentSpace
{
    private string $_sessId;
    private string $_baseDir;

    private const MAX_PDF_PAGES = 50;
    // v5.x — bumped from 1000 to 5000 after migrating XLSX/ODS to OpenSpout's
    // streaming reader (memory-bounded). The old DOM-model phpspreadsheet
    // forced the conservative cap; with streaming the budget can cover real
    // business-plan workbooks (12 months × multi-year projections).
    private const MAX_SPREADSHEET_ROWS = 5000;
    // v5.x — bumped from 30 to 128. A real 54-sheet financial-plan workbook
    // ("FP su 5 anni Canonity.xlsx") was silently truncated to 30 sheets,
    // dropping the detail tables (investments, payroll, sales, taxes) that sit
    // after the dashboard/graph sheets. Full extraction of all 54 sheets is
    // ~511K chars (~127K tokens) — comfortably under the 200K-token context
    // budget below, which (via char-based water-filling) remains the real
    // limiter. With OpenSpout's streaming reader memory is constant regardless
    // of sheet count, so this cap now only guards against pathological files
    // with hundreds of sheets (slow iteration), not memory.
    private const MAX_SPREADSHEET_SHEETS = 128;
    private const MAX_TEXT_BYTES = 500000; // 500KB per file

    // v5.x — context budget. Previously a flat 50.000 chars (~12K tokens): the
    // first documents ate the budget and the rest were truncated, which is the
    // "non vedo tutti i dati" symptom on multi-sheet workbooks. We now target a
    // 200K-token ceiling (system invariant, intentionally NOT in .services.json)
    // converted to characters with a prudent ~4 char/token ratio. getContextForAi
    // then fair-shares this budget across documents (water-filling) so a small
    // .txt no longer starves a large .xlsx.
    private const MAX_CONTEXT_TOKENS    = 200000;
    private const CONTEXT_CHARS_PER_TOKEN = 4;
    private const MAX_CONTEXT_CHARS = self::MAX_CONTEXT_TOKENS * self::CONTEXT_CHARS_PER_TOKEN; // 800.000

    // v5.x — small-model guard: models flagged "small" in config reject file
    // attachments entirely (upload.SRV.php) and have their prompt capped to this
    // many characters (Executor::sendToAi). Public so Executor is the single
    // reference point for the cap.
    public const MAX_SMALL_MODEL_CHARS = 65535;

    private const TYPE_MAP = [
        'pdf'  => 'pdf',
        'docx' => 'docx',
        'txt'  => 'text',
        'md'   => 'text',
        'log'  => 'text',
        'json' => 'text',
        'yaml' => 'text',
        'yml'  => 'text',
        'xml'  => 'text',
        'html' => 'text',
        'htm'  => 'text',
        'css'  => 'text',
        'js'   => 'text',
        'xlsx' => 'spreadsheet',
        'ods'  => 'spreadsheet',
        'pptx' => 'presentation',
        'csv'  => 'csv',
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'webp' => 'image',
    ];

    public function __construct(string $sessId)
    {
        $this->_sessId = $sessId;
        $this->_baseDir = self::_resolveDocspaceRoot() . $sessId . "/";
        if (!is_dir($this->_baseDir)) {
            mkdir($this->_baseDir, 0775, true);
            @chmod($this->_baseDir, 0775);
        }
        $this->_touchSidDate();
    }

    /**
     * Resolve the docspace root path. Works both in HTTP context (where
     * $_SERVER['DOCUMENT_ROOT'] points to webroot/) and in CLI context
     * (where DOCUMENT_ROOT is empty — e.g. cron/Timedcall).
     */
    private static function _resolveDocspaceRoot(): string
    {
        if (!empty($_SERVER['DOCUMENT_ROOT']))
            return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . "/../Uploads/docspace/";
        // CLI fallback: DocumentSpace.php is at src/Flussu/Documents/, repo root is 3 levels up
        return dirname(__DIR__, 3) . "/Uploads/docspace/";
    }

    /**
     * Add a document to the space. Processes it based on type.
     * @return array ['id'=>string, 'name'=>string, 'type'=>string, 'success'=>bool, 'error'=>string]
     */
    public function addDocument(string $filePath, string $originalName): array
    {
        $result = ['id' => '', 'name' => $originalName, 'type' => '', 'success' => false, 'error' => ''];

        if (!file_exists($filePath)) {
            $result['error'] = "File not found: $filePath";
            return $result;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $docType = self::TYPE_MAP[$ext] ?? null;
        if (!$docType) {
            $result['error'] = "Unsupported file type: $ext";
            return $result;
        }

        $docId = uniqid('doc_', true);
        $result['id'] = $docId;
        $result['type'] = $docType;

        try {
            $content = match ($docType) {
                'pdf'          => $this->_processPdf($filePath),
                'docx'         => $this->_processDocx($filePath),
                'text'         => $this->_processText($filePath),
                'spreadsheet'  => $this->_processSpreadsheet($filePath),
                'presentation' => $this->_processPptx($filePath),
                'csv'          => $this->_processCsv($filePath),
                'image'        => $this->_processImage($filePath, $docId, $ext),
            };

            // Save processed content. v5.x — images now store their OCR text
            // (same .json container as every other doc); the raw bytes live in
            // the separate .orig copy. The old .b64 dump was write-only/dead.
            $out = $this->_baseDir . $docId . ".json";
            file_put_contents($out, $content);
            @chmod($out, 0664);

            // Update manifest
            $entry = [
                'id'            => $docId,
                'originalName'  => $originalName,
                'type'          => $docType,
                'extension'     => $ext,
                'processedFile' => $docId . '.json',
                'addedAt'       => date('c'),
                'sizeBytes'     => filesize($filePath),
            ];
            if ($docType === 'image') {
                $entry['ocrChars'] = strlen($content);
            }
            $this->_updateManifest($entry);

            $this->_touchSidDate();
            $result['success'] = true;
        } catch (EncryptedWorkbookException $e) {
            // v5.x — encrypted workbooks are a HARD rejection: nothing to
            // extract and no value in registering a placeholder. Caller
            // (upload.SRV.php) maps reason=encrypted_workbook to HTTP 422.
            // Distinct from the generic-failure path below which keeps the
            // file in the session with an extractionError placeholder.
            General::Log_nocaller("DocSpace::addDocument encrypted workbook rejected ($originalName, sid={$this->_sessId})", true);
            $this->_docErrLog("REJECT encrypted_workbook", $originalName, $ext, filesize($filePath));
            $result['success'] = false;
            $result['error']   = 'ENCRYPTED_WORKBOOK';
            $result['reason']  = 'encrypted_workbook';
            return $result;
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            // v5.x — General::Log() drops the forced flag (bug in that helper),
            // so processing errors would silently never reach disk. Use
            // Log_nocaller(..., true) directly so failures are always recorded.
            General::Log_nocaller("DocSpace::addDocument error processing $originalName (sid={$this->_sessId}): $errMsg", true);
            $this->_docErrLog("ERROR extraction_failed: " . $errMsg, $originalName, $ext, @filesize($filePath) ?: 0);

            // v5.x — design rule: even when extraction fails (e.g. password-
            // protected PDF, corrupted docx, parser regression) the file must
            // still become part of the session so the LLM is told it exists.
            // Persist a human-readable placeholder as the document's content
            // and tag the manifest entry with extractionError; getContextForAi
            // renders a "estrazione non riuscita" block based on that flag.
            $humanReason = self::_humanizeExtractionError($errMsg);
            $placeholder = "[Il contenuto testuale di questo documento non è stato estratto.]\n"
                         . "Motivo: $humanReason\n"
                         . "Il file è comunque parte della sessione e può essere referenziato per nome.";
            $out = $this->_baseDir . $docId . ".json";
            @file_put_contents($out, $placeholder);
            @chmod($out, 0664);

            $entry = [
                'id'              => $docId,
                'originalName'    => $originalName,
                'type'            => $docType,
                'extension'       => $ext,
                'processedFile'   => $docId . '.json',
                'addedAt'         => date('c'),
                'sizeBytes'       => filesize($filePath),
                'extractionError' => $errMsg,
            ];
            $this->_updateManifest($entry);
            $this->_touchSidDate();

            // Mark ingestion successful: the file is in the session even if
            // text extraction didn't yield content. Callers that care about
            // extraction quality read the extractionError field.
            $result['success']         = true;
            $result['extractionError'] = $errMsg;
        }

        return $result;
    }

    public function removeDocument(string $docId): bool
    {
        $manifest = $this->_loadManifest();
        $found = false;
        $newManifest = [];
        foreach ($manifest as $entry) {
            if ($entry['id'] === $docId) {
                $found = true;
                // Delete processed file
                $pFile = $this->_baseDir . $entry['processedFile'];
                if (file_exists($pFile)) unlink($pFile);
                // Delete original copy if image
                $origPattern = $this->_baseDir . $docId . ".orig.*";
                foreach (glob($origPattern) as $origFile) {
                    unlink($origFile);
                }
            } else {
                $newManifest[] = $entry;
            }
        }
        if ($found) {
            $this->_saveManifest($newManifest);
        }
        return $found;
    }

    public function getDocumentList(): array
    {
        return $this->_loadManifest();
    }

    /**
     * Build a text context string from all documents for AI prompt injection.
     *
     * v5.x — fair-share budget. The previous loop was first-come-first-served:
     * early documents consumed the whole cap and later ones were truncated to
     * nothing (the "non vedo tutti i dati" symptom on a workbook that follows a
     * big .txt in the manifest). We now water-fill: documents under their equal
     * share are included in full and release their surplus to the larger ones,
     * so every attachment gets a fair slice of the (much larger) budget.
     */
    public function getContextForAi(int $maxChars = self::MAX_CONTEXT_CHARS): string
    {
        $manifest = $this->_loadManifest();
        if (empty($manifest)) return '';

        $open  = "--- DOCUMENTI ALLEGATI ---\n";
        $close = "--- FINE DOCUMENTI ALLEGATI ---";

        // Build a render unit per entry: fixed framing (header/footer) + an
        // optional truncatable body. Light entries (image / extraction-failed)
        // are framing-only and never truncated.
        $units = [];
        foreach ($manifest as $idx => $entry) {
            $name = $entry['originalName'];
            if (!empty($entry['extractionError'])) {
                $reason = self::_humanizeExtractionError((string)$entry['extractionError']);
                $block  = "[Documento: $name (" . strtoupper($entry['extension']) . ", " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
                $block .= "[Contenuto testuale non disponibile — $reason]\n";
                $block .= "[Il file è presente nella sessione e può essere referenziato per nome.]\n";
                $block .= "[Fine Documento: $name]\n\n";
                $units[] = ['idx' => $idx, 'fixed' => $block, 'body' => '', 'name' => $name, 'light' => true];
                continue;
            }
            if (($entry['type'] ?? '') === 'image') {
                // v5.x — OCR primary: the image's recognized text is its body and
                // is budgeted/truncated like any other document. An image with no
                // recognizable text stays a light awareness-only entry.
                $ocr = $this->_loadProcessedContent($entry['id'], 'image');
                if (trim($ocr) === '') {
                    $block  = "[Documento: $name (Immagine, " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
                    $block .= "[Nessun testo riconoscibile nell'immagine (OCR vuoto).]\n";
                    $block .= "[Fine Documento: $name]\n\n";
                    $units[] = ['idx' => $idx, 'fixed' => $block, 'body' => '', 'name' => $name, 'light' => true];
                    continue;
                }
                $header = "[Documento: $name (Immagine, " . $this->_formatBytes($entry['sizeBytes']) . " — testo estratto via OCR)]\n";
                $footer = "\n[Fine Documento: $name]\n\n";
                $units[] = [
                    'idx'   => $idx,
                    'fixed' => $header,
                    'foot'  => $footer,
                    'body'  => $ocr,
                    'name'  => $name,
                    'light' => false,
                ];
                continue;
            }
            $header = "[Documento: $name (" . strtoupper($entry['extension']) . ", " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
            $footer = "\n[Fine Documento: $name]\n\n";
            $body   = $this->_loadProcessedContent($entry['id'], $entry['type']);
            $units[] = [
                'idx'   => $idx,
                'fixed' => $header,           // header; footer kept separate below
                'foot'  => $footer,
                'body'  => $body,
                'name'  => $name,
                'light' => false,
            ];
        }

        $budget = $maxChars - strlen($open) - strlen($close);

        // Light units always render (tiny, mandatory awareness markers); reserve
        // their cost up front.
        foreach ($units as $u) {
            if ($u['light']) $budget -= strlen($u['fixed']);
        }
        if ($budget < 0) $budget = 0;

        // Water-fill the remaining budget across content units. Process in
        // ascending desired-size order so small docs take only what they need
        // and hand the surplus to the larger ones.
        $content = array_values(array_filter($units, fn($u) => !$u['light']));
        usort($content, function ($a, $b) {
            return $this->_unitDesired($a) <=> $this->_unitDesired($b);
        });

        $alloc      = [];   // idx => body char allowance
        $remaining  = $budget;
        $countLeft  = count($content);
        foreach ($content as $u) {
            $framing = strlen($u['fixed']) + strlen($u['foot']);
            $desired = $framing + strlen($u['body']);
            $share   = $countLeft > 0 ? intdiv($remaining, $countLeft) : 0;
            if ($desired <= $share) {
                $alloc[$u['idx']] = strlen($u['body']);   // full body
                $remaining -= $desired;
            } else {
                $bodyAllow = $share - $framing;
                $alloc[$u['idx']] = max(0, $bodyAllow);
                $remaining -= ($framing + $alloc[$u['idx']]);
            }
            if ($remaining < 0) $remaining = 0;
            $countLeft--;
        }

        // Render in original manifest order.
        $out = $open;
        $truncatedDocs = [];
        foreach ($units as $u) {
            if ($u['light']) {
                $out .= $u['fixed'];
                continue;
            }
            $body  = $u['body'];
            $allow = $alloc[$u['idx']] ?? 0;
            if (strlen($body) > $allow) {
                $shown = $allow > 0 ? substr($body, 0, $allow) : '';
                $body  = $shown . "\n... [troncato: mostrati " . strlen($shown) . " di " . strlen($u['body']) . " caratteri]";
                $truncatedDocs[] = $u['name'];
                $this->_docErrLog(
                    "CONTEXT_TRUNCATED shown=" . strlen($shown) . " of=" . strlen($u['body']) . " budget=" . $maxChars,
                    $u['name'], (string)($manifest[$u['idx']]['extension'] ?? ''), (int)($manifest[$u['idx']]['sizeBytes'] ?? 0)
                );
            }
            $out .= $u['fixed'] . $body . $u['foot'];
        }

        if (!empty($truncatedDocs)) {
            $out .= "[" . count($truncatedDocs) . " documento/i troncato/i per limite di contesto: " . implode(', ', $truncatedDocs) . "]\n";
        }

        $out .= $close;
        $this->_touchSidDate();
        return $out;
    }

    /** Desired full rendered size of a content render unit (header + body + footer). */
    private function _unitDesired(array $u): int
    {
        return strlen($u['fixed']) + strlen($u['foot'] ?? '') + strlen($u['body']);
    }

    /**
     * Get paths of original image files for multimodal AI providers.
     */
    public function getImagePaths(): array
    {
        $manifest = $this->_loadManifest();
        $paths = [];
        foreach ($manifest as $entry) {
            if ($entry['type'] === 'image') {
                $origPattern = $this->_baseDir . $entry['id'] . ".orig.*";
                $origFiles = glob($origPattern);
                if (!empty($origFiles)) {
                    $paths[] = $origFiles[0];
                }
            }
        }
        return $paths;
    }

    /**
     * v5.x — image paths added SINCE the last vision turn (markImagesProcessed()).
     * Vision (sending image bytes) is a one-shot on upload: a stale image left in
     * the session must not keep hijacking later, unrelated text/document requests.
     * The textual context (getContextForAi) still persists every turn, so follow-up
     * questions rely on that plus the model's earlier description.
     */
    public function getNewImagePaths(): array
    {
        $watermark = $this->_readVisionWatermark();
        $wmTs = $watermark !== '' ? strtotime($watermark) : 0;
        $manifest = $this->_loadManifest();
        $paths = [];
        foreach ($manifest as $entry) {
            if (($entry['type'] ?? '') !== 'image') continue;
            $added = $entry['addedAt'] ?? '';
            if ($wmTs > 0 && $added !== '' && strtotime($added) <= $wmTs) {
                continue; // already present at/before the last vision turn → not new
            }
            $origFiles = glob($this->_baseDir . $entry['id'] . ".orig.*");
            if (!empty($origFiles)) {
                $paths[] = $origFiles[0];
            }
        }
        return $paths;
    }

    /** v5.x — mark all images currently in the space as "seen" by a vision turn. */
    public function markImagesProcessed(): void
    {
        @file_put_contents($this->_baseDir . "vision_watermark.txt", date('c'));
    }

    /** v5.x — read the vision watermark timestamp (ISO 8601) or '' if none. */
    private function _readVisionWatermark(): string
    {
        $f = $this->_baseDir . "vision_watermark.txt";
        return is_file($f) ? trim((string)@file_get_contents($f)) : '';
    }

    /**
     * [DIAG_MULTIIMG_v1] TEMPORARY diagnostic — enumerate every image entry in
     * the manifest and classify it against the vision watermark exactly the way
     * getNewImagePaths() does. Lets a single log line show, per turn: how many
     * images exist, how many are "fresh" (would be sent), how many are excluded
     * as stale, plus each one's name / addedAt / original-bytes presence.
     * Investigating: a 3-image upload where only the first reaches the model.
     * Remove with: grep -rln 'DIAG_MULTIIMG_v1' src/ and delete tagged blocks.
     */
    public function debugImageManifest(): string
    {
        $watermark = $this->_readVisionWatermark();
        $wmTs = $watermark !== '' ? strtotime($watermark) : 0;
        $manifest = $this->_loadManifest();
        $total = 0; $fresh = 0; $stale = 0; $rows = [];
        foreach ($manifest as $entry) {
            if (($entry['type'] ?? '') !== 'image') continue;
            $total++;
            $added = $entry['addedAt'] ?? '';
            $hasOrig = !empty(glob($this->_baseDir . ($entry['id'] ?? '') . ".orig.*"));
            $isStale = ($wmTs > 0 && $added !== '' && strtotime($added) <= $wmTs);
            if ($isStale) $stale++; else $fresh++;
            $rows[] = ($entry['originalName'] ?? '?') . '(added=' . ($added !== '' ? $added : '?')
                . ',orig=' . ($hasOrig ? 'yes' : 'NO')
                . ',' . ($isStale ? 'STALE' : 'fresh') . ')';
        }
        return 'images_total=' . $total . ' fresh=' . $fresh . ' stale=' . $stale
            . ' watermark=' . ($watermark !== '' ? $watermark : '(none)')
            . ' entries=[' . implode('; ', $rows) . ']';
    }

    public function hasDocuments(): bool
    {
        $manifest = $this->_loadManifest();
        return !empty($manifest);
    }

    public function getSpaceSize(): int
    {
        $size = 0;
        if (is_dir($this->_baseDir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_baseDir, \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    // ========================
    //  PUBLIC: Active model gate
    // ========================

    /**
     * v5.x — Record the AI model active for this session. Written by
     * Executor::sendToAi on every turn (cheap scratch file, same pattern as
     * sid_date / vision_watermark). upload.SRV.php reads it back to decide
     * whether file attachments are allowed: "small" models reject uploads and
     * have their prompt capped (MAX_SMALL_MODEL_CHARS).
     */
    public function setActiveModel(string $platformName, ?string $configKey, bool $isSmall, string $modelId = ''): void
    {
        $data = [
            'platform'  => $platformName,
            'configKey' => $configKey,
            'small'     => $isSmall,
            'model'     => $modelId,
            'at'        => date('c'),
        ];
        @file_put_contents($this->_baseDir . 'active_model.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        @chmod($this->_baseDir . 'active_model.json', 0664);
    }

    /**
     * v5.x — true if the session's active model is flagged "small" (no file
     * attachments). Fail-open: if no model has been recorded yet (upload before
     * the first sendToAi) we cannot know, so we allow the attachment.
     */
    public function activeModelIsSmall(): bool
    {
        $f = $this->_baseDir . 'active_model.json';
        if (!is_file($f)) return false;
        $data = json_decode((string)@file_get_contents($f), true);
        return is_array($data) && !empty($data['small']);
    }

    // ========================
    //  PUBLIC: Generated files
    // ========================

    /**
     * Get the path to the generated files directory.
     * Creates it if it doesn't exist.
     */
    public function getGeneratedDir(): string
    {
        $dir = $this->_baseDir . 'generated/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        return $dir;
    }

    /**
     * Save a generated file (e.g. AI-generated image) into the session's generated folder.
     * @param string $data Raw file content (binary)
     * @param string $filename Desired filename (e.g. "gen_abc123.png")
     * @param string $mimeType MIME type of the file
     * @return array ['filename'=>string, 'path'=>string, 'url'=>string, 'size'=>int]
     */
    public function addGenerated(string $data, string $filename, string $mimeType = ''): array
    {
        $dir = $this->getGeneratedDir();
        $filePath = $dir . $filename;
        file_put_contents($filePath, $data);
        @chmod($filePath, 0664);
        $this->_touchSidDate();

        // Build public URL
        $filehost = $_ENV['filehost'] ?? $_ENV['server'] ?? '';
        $relativePath = '/Uploads/docspace/' . $this->_sessId . '/generated/' . $filename;
        $url = '';
        if (!empty($filehost)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $url = $protocol . '://' . $filehost . $relativePath;
        } else {
            $url = $relativePath;
        }

        return [
            'filename' => $filename,
            'path'     => $filePath,
            'url'      => $url,
            'size'     => strlen($data),
            'mimeType' => $mimeType ?: mime_content_type($filePath) ?: 'application/octet-stream',
        ];
    }

    /**
     * List all generated files in this session's docspace.
     * @return array of ['filename', 'path', 'url', 'size', 'mimeType', 'createdAt']
     */
    public function getGeneratedFiles(): array
    {
        $dir = $this->_baseDir . 'generated/';
        if (!is_dir($dir)) return [];

        $files = [];
        $filehost = $_ENV['filehost'] ?? $_ENV['server'] ?? '';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isDot() || !$item->isFile()) continue;
            $filename = $item->getFilename();
            $relativePath = '/Uploads/docspace/' . $this->_sessId . '/generated/' . $filename;
            $url = !empty($filehost) ? ($protocol . '://' . $filehost . $relativePath) : $relativePath;

            $files[] = [
                'filename'  => $filename,
                'path'      => $item->getPathname(),
                'url'       => $url,
                'size'      => $item->getSize(),
                'mimeType'  => mime_content_type($item->getPathname()) ?: 'application/octet-stream',
                'createdAt' => date('c', $item->getMTime()),
            ];
        }

        // Sort by creation date ascending
        usort($files, fn($a, $b) => strcmp($a['createdAt'], $b['createdAt']));
        return $files;
    }

    /**
     * Check if there are any generated files.
     */
    public function hasGeneratedFiles(): bool
    {
        return !empty($this->getGeneratedFiles());
    }

    /**
     * Get the absolute path of a specific generated file.
     * Returns null if the file doesn't exist.
     */
    public function getGeneratedFilePath(string $filename): ?string
    {
        // Sanitize: prevent path traversal
        $filename = basename($filename);
        $path = $this->_baseDir . 'generated/' . $filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * Delete the entire document space for a session.
     */
    public static function cleanup(string $sessId): void
    {
        $baseDir = self::_resolveDocspaceRoot() . $sessId . "/";
        if (is_dir($baseDir)) {
            self::_removeDir($baseDir);
        }
    }

    /**
     * Clean up orphaned document spaces based on sid_date file.
     * Deletes spaces where the last usage (recorded in sid_date) exceeds $maxAgeHours.
     * Safety net for sessions that ended without proper destructor call.
     * @return int Number of directories cleaned up
     */
    public static function cleanupOrphaned(int $maxAgeHours = 4): int
    {
        $docspaceRoot = self::_resolveDocspaceRoot();
        if (!is_dir($docspaceRoot)) return 0;

        $count = 0;
        $failed = 0;
        $errors = [];
        $cutoff = time() - ($maxAgeHours * 3600);
        foreach (new \DirectoryIterator($docspaceRoot) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) continue;
            $path = $dir->getPathname();
            $sidDateFile = $path . '/sid_date';
            $shouldDelete = false;
            if (file_exists($sidDateFile)) {
                $lastUsed = (int) file_get_contents($sidDateFile);
                if ($lastUsed > 0 && $lastUsed < $cutoff) $shouldDelete = true;
            } else {
                if ($dir->getMTime() < $cutoff) $shouldDelete = true;
            }
            if (!$shouldDelete) continue;

            $errs = [];
            if (self::_removeDir($path . '/', $errs)) {
                $count++;
            } else {
                $failed++;
                $errors = array_merge($errors, $errs);
            }
        }
        if ($failed > 0) {
            $msg = "DocSpace cleanupOrphaned: $failed dir(s) failed to delete. First errors: "
                 . implode(' | ', array_slice($errors, 0, 5));
            error_log($msg);
            try { General::Log($msg); } catch (\Throwable $e) {}
        }
        return $count;
    }

    /**
     * v5.x — Sweep stale uploads in Uploads/temp/dsp_* that were never bound
     * to a session (no SID at upload time and no $docspace_files reference
     * followed up). Without this the directory accumulates and slows down
     * any future addDocument scan.
     * @return int number of files removed
     */
    public static function cleanupTempUploads(int $maxAgeHours = 4): int
    {
        $tempDir = self::_resolveDocspaceRoot();
        $tempDir = rtrim(dirname(rtrim($tempDir, '/')), '/') . '/temp/';
        if (!is_dir($tempDir)) return 0;

        $count  = 0;
        $cutoff = time() - ($maxAgeHours * 3600);
        foreach (glob($tempDir . 'dsp_*') as $f) {
            if (!is_file($f)) continue;
            if (filemtime($f) >= $cutoff) continue;
            if (@unlink($f)) $count++;
        }
        return $count;
    }

    // ========================
    //  PRIVATE: Processors
    // ========================

    private function _processPdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();
        $text = '';
        $pageNum = 0;
        foreach ($pages as $page) {
            $pageNum++;
            if ($pageNum > self::MAX_PDF_PAGES) {
                $text .= "\n--- [Troncato: mostrate " . self::MAX_PDF_PAGES . " di " . count($pages) . " pagine] ---\n";
                break;
            }
            $pageText = $page->getText();
            if (!empty(trim($pageText))) {
                $text .= "--- Pagina $pageNum ---\n" . $pageText . "\n";
            }
        }
        return $this->_truncateText($text);
    }

    private function _processDocx(string $filePath): string
    {
        // PhpWord 1.x throws on unsupported embedded media (e.g. .emf images
        // referenced from word/media/). Fall back to raw XML extraction so a
        // single bad embed doesn't lose the entire document text.
        try {
            $phpWord = WordIOFactory::load($filePath);
            $md = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $md .= $this->_docxElementToMarkdown($element);
                }
            }
            return $this->_truncateText($md);
        } catch (\Throwable $e) {
            General::Log("DocSpace _processDocx fallback (PhpWord load failed: " . $e->getMessage() . "): " . basename($filePath));
            return $this->_truncateText($this->_extractDocxTextRaw($filePath));
        }
    }

    /**
     * Fallback DOCX text extraction by reading word/document.xml directly
     * from the zip container. Loses formatting but survives unsupported media.
     */
    private function _extractDocxTextRaw(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            return '';
        }
        // Insert newlines on paragraph/break tags so flowing text is readable.
        $xml = preg_replace('/<\/w:p>/i', "\n\n", $xml);
        $xml = preg_replace('/<w:br[^>]*\/>/i', "\n", $xml);
        // Strip remaining XML tags and decode entities.
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Collapse runs of blank lines.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function _docxElementToMarkdown($element): string
    {
        if ($element instanceof Title) {
            $depth = $element->getDepth();
            $hashes = str_repeat('#', min($depth, 6));
            $text = $this->_extractTextFromElement($element);
            return "$hashes $text\n\n";
        }

        if ($element instanceof TextRun) {
            $text = $this->_extractTextFromTextRun($element);
            return $text . "\n\n";
        }

        if ($element instanceof Text) {
            return $element->getText() . "\n\n";
        }

        if ($element instanceof TextBreak) {
            return "\n";
        }

        if ($element instanceof ListItem || $element instanceof ListItemRun) {
            $depth = method_exists($element, 'getDepth') ? $element->getDepth() : 0;
            $indent = str_repeat('  ', $depth);
            $text = $this->_extractTextFromElement($element);
            return "$indent- $text\n";
        }

        if ($element instanceof Table) {
            return $this->_docxTableToMarkdown($element) . "\n";
        }

        if ($element instanceof Image) {
            return "[immagine incorporata]\n\n";
        }

        // Fallback: try to extract any text
        if (method_exists($element, 'getElements')) {
            $text = '';
            foreach ($element->getElements() as $child) {
                $text .= $this->_docxElementToMarkdown($child);
            }
            return $text;
        }

        return '';
    }

    private function _extractTextFromTextRun(TextRun $textRun): string
    {
        $result = '';
        foreach ($textRun->getElements() as $el) {
            if ($el instanceof Text) {
                $text = $el->getText();
                $font = $el->getFontStyle();
                if ($font) {
                    if (is_object($font)) {
                        if ($font->isBold()) $text = "**$text**";
                        if ($font->isItalic()) $text = "*$text*";
                    }
                }
                $result .= $text;
            } elseif ($el instanceof TextBreak) {
                $result .= "\n";
            }
        }
        return $result;
    }

    private function _extractTextFromElement($element): string
    {
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) return $text;
        }
        if (method_exists($element, 'getElements')) {
            $result = '';
            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $result .= $child->getText();
                } elseif ($child instanceof TextRun) {
                    $result .= $this->_extractTextFromTextRun($child);
                }
            }
            return $result;
        }
        return '';
    }

    private function _docxTableToMarkdown(Table $table): string
    {
        $rows = $table->getRows();
        if (empty($rows)) return '';

        $md = '';
        $isFirst = true;
        foreach ($rows as $row) {
            $cells = $row->getCells();
            $cellTexts = [];
            foreach ($cells as $cell) {
                $cellText = '';
                foreach ($cell->getElements() as $el) {
                    $cellText .= trim($this->_extractTextFromElement($el));
                }
                $cellTexts[] = str_replace('|', '\\|', $cellText);
            }
            $md .= '| ' . implode(' | ', $cellTexts) . " |\n";
            if ($isFirst) {
                $md .= '| ' . implode(' | ', array_fill(0, count($cellTexts), '---')) . " |\n";
                $isFirst = false;
            }
        }
        return $md;
    }

    private function _processText(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, self::MAX_TEXT_BYTES);
        return $this->_truncateText($content);
    }

    private function _processSpreadsheet(string $filePath): string
    {
        // v5.x — migrated from phpoffice/phpspreadsheet (DOM model, memory
        // explodes on workbooks with many sheets) to openspout/openspout
        // (streaming reader, constant memory ~30MB regardless of size).

        // Detect password-protected / OLE-compound files BEFORE handing to
        // OpenSpout: encrypted OOXML wraps the workbook in an OLE Compound
        // File whose magic bytes are D0CF11E0A1B11AE1 — the same signature
        // as legacy .xls and .doc. OpenSpout would throw a generic
        // IOException; we want the typed signal so addDocument can return
        // success:false reason:encrypted_workbook and upload.SRV.php can
        // emit a structured HTTP 422.
        $head = @file_get_contents($filePath, false, null, 0, 8);
        if ($head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw new EncryptedWorkbookException(
                'Spreadsheet is encrypted (password-protected) or in legacy OLE format'
            );
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $reader = ($ext === 'ods')
            ? new \OpenSpout\Reader\ODS\Reader()
            : new \OpenSpout\Reader\XLSX\Reader();

        // ODS encrypts at the container level with a different signature than
        // OOXML, so the magic-byte check above misses it. Promote OpenSpout's
        // generic IOException to the typed signal when its message hints at
        // encryption/password — keeps the 422 path uniform for XLSX and ODS.
        try {
            $reader->open($filePath);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'encrypt') || str_contains($msg, 'password')) {
                throw new EncryptedWorkbookException(
                    'Spreadsheet is encrypted (password-protected): ' . $e->getMessage()
                );
            }
            throw $e;
        }

        $sheetsData = [];
        $sheetCount = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetCount >= self::MAX_SPREADSHEET_SHEETS) break;
                $sheetCount++;

                $rows = 0;
                $maxCols = 0;
                $csv = '';
                $truncated = false;

                foreach ($sheet->getRowIterator() as $row) {
                    if ($rows >= self::MAX_SPREADSHEET_ROWS) {
                        $truncated = true;
                        break;
                    }
                    $cells = $row->getCells();
                    $maxCols = max($maxCols, count($cells));
                    $rowData = [];
                    foreach ($cells as $cell) {
                        // v5.x — OpenSpout's FormulaCell::getValue() returns the
                        // formula string ("=SUM(A1:A10)"), not the computed
                        // result. The cached <v> value is exposed via
                        // getComputedValue(). Without this branch the LLM
                        // would see formula text instead of numbers and could
                        // not do any quantitative analysis. Fall back to the
                        // formula text only when the file has no cached value
                        // (typically programmatically-generated workbooks that
                        // were never opened/saved in Excel/LibreOffice), so
                        // the LLM at least knows a formula is there.
                        if ($cell instanceof \OpenSpout\Common\Entity\Cell\FormulaCell) {
                            $v = $cell->getComputedValue();
                            if ($v === null) {
                                $v = $cell->getValue();
                            }
                        } else {
                            $v = $cell->getValue();
                        }
                        if ($v instanceof \DateTimeInterface) {
                            $v = $v->format('Y-m-d H:i:s');
                        } elseif ($v instanceof \DateInterval) {
                            $v = $v->format('%yY%mM%dDT%hH%iM%sS');
                        } elseif (is_bool($v)) {
                            $v = $v ? 'TRUE' : 'FALSE';
                        } else {
                            $v = (string) $v;
                        }
                        // Escape CSV: quote values containing commas, quotes, or newlines
                        if (preg_match('/[,"\n\r]/', $v)) {
                            $v = '"' . str_replace('"', '""', $v) . '"';
                        }
                        $rowData[] = $v;
                    }
                    $csv .= implode(',', $rowData) . "\n";
                    $rows++;
                }

                $sheetsData[] = [
                    'name'      => $sheet->getName(),
                    'rows'      => $rows,
                    'cols'      => $maxCols,
                    'truncated' => $truncated,
                    'csv'       => $csv,
                ];
            }
        } finally {
            $reader->close();
        }

        // v5.x — store raw CSV delimited per sheet instead of JSON_PRETTY_PRINT.
        // The old JSON encoding bloated the payload (escaped quotes/newlines +
        // indentation) so multi-sheet workbooks were truncated against the
        // context cap — the "Excel non vede tutti i dati / i CSV singoli sì"
        // symptom. Each sheet is now a named CSV block, exactly the shape that
        // worked when users uploaded the per-sheet CSVs by hand.
        $out = '';
        foreach ($sheetsData as $sd) {
            $out .= "=== FOGLIO: " . $sd['name'] . " (" . $sd['rows'] . " righe × " . $sd['cols'] . " col) ===\n";
            $out .= $sd['csv'];
            if ($sd['truncated']) {
                $out .= "... [foglio troncato a " . self::MAX_SPREADSHEET_ROWS . " righe]\n";
                $this->_docErrLog(
                    "TRUNCATED sheet '" . $sd['name'] . "' to " . self::MAX_SPREADSHEET_ROWS . " rows",
                    basename($filePath), $ext, @filesize($filePath) ?: 0
                );
            }
            $out .= "\n";
        }
        if (count($sheetsData) >= self::MAX_SPREADSHEET_SHEETS) {
            $out .= "... [workbook troncato a " . self::MAX_SPREADSHEET_SHEETS . " fogli]\n";
            $this->_docErrLog(
                "TRUNCATED workbook to " . self::MAX_SPREADSHEET_SHEETS . " sheets",
                basename($filePath), $ext, @filesize($filePath) ?: 0
            );
        }
        return $out;
    }

    // DrawingML / PresentationML namespaces (stable URIs; the prefix in the file
    // — usually a:/p:/r: — may vary, so we always match by namespace URI).
    private const NS_DRAWINGML       = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_PRESENTATIONML  = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const NS_RELATIONSHIPS   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * v5.x — PowerPoint (.pptx) → Markdown via ZipArchive (no new dependency,
     * same approach as the DOCX raw fallback). Fixes the "vede solo la prima
     * slide e poi solo i titoli" symptom: the old path had no real pptx support
     * (users were converting to PDF, where Smalot got body text from slide 1 and
     * only titles afterwards). Here EVERY text run of EVERY slide is captured,
     * grouped per paragraph, plus tables and speaker notes.
     */
    private function _processPptx(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Cannot open PPTX container (corrupt or not a zip)');
        }

        try {
            $slidePaths = $this->_pptxSlideOrder($zip);
            if (empty($slidePaths)) {
                return "[Presentazione senza slide leggibili]";
            }

            $md = '';
            $n = 0;
            foreach ($slidePaths as $slidePath) {
                $xml = $zip->getFromName($slidePath);
                if ($xml === false || $xml === '') continue;
                $n++;
                $md .= "## Slide $n\n\n";

                $dom = new \DOMDocument();
                // PPTX XML is well-formed; suppress libxml warnings on exotic content.
                if (@$dom->loadXML($xml)) {
                    $lines = [];
                    $this->_pptxWalk($dom->documentElement, $lines);
                    $md .= ($lines ? implode("\n", $lines) : "_[slide senza testo]_") . "\n\n";
                } else {
                    $md .= "_[slide non parsabile]_\n\n";
                }

                // Speaker notes — convention ppt/notesSlides/notesSlideN.xml,
                // keyed by the same number as the slide file.
                if (preg_match('#slide(\d+)\.xml$#', $slidePath, $m)) {
                    $notesXml = $zip->getFromName('ppt/notesSlides/notesSlide' . $m[1] . '.xml');
                    if ($notesXml !== false && $notesXml !== '') {
                        $ndom = new \DOMDocument();
                        if (@$ndom->loadXML($notesXml)) {
                            $nlines = [];
                            $this->_pptxWalk($ndom->documentElement, $nlines);
                            // Notes XML repeats the slide-number placeholder; keep only
                            // lines with real prose (length > 1) to avoid noise.
                            $nlines = array_values(array_filter($nlines, fn($l) => mb_strlen(trim($l)) > 1));
                            if ($nlines) {
                                $md .= "### Note\n\n" . implode("\n", $nlines) . "\n\n";
                            }
                        }
                    }
                }
            }
            return $this->_truncateText(trim($md));
        } finally {
            $zip->close();
        }
    }

    /**
     * Resolve slide rendering order: the authoritative source is the
     * <p:sldIdLst> in ppt/presentation.xml, whose r:id entries map (via
     * ppt/_rels/presentation.xml.rels) to slide targets. Falls back to numeric
     * ordering of ppt/slides/slideN.xml when those parts are missing/unreadable.
     * @return string[] zip entry paths in display order
     */
    private function _pptxSlideOrder(\ZipArchive $zip): array
    {
        $presXml = $zip->getFromName('ppt/presentation.xml');
        $relsXml = $zip->getFromName('ppt/_rels/presentation.xml.rels');

        if ($presXml !== false && $relsXml !== false) {
            $relMap = [];
            $rdom = new \DOMDocument();
            if (@$rdom->loadXML($relsXml)) {
                foreach ($rdom->getElementsByTagName('Relationship') as $rel) {
                    $id = $rel->getAttribute('Id');
                    $target = $rel->getAttribute('Target');
                    if ($id !== '' && $target !== '') $relMap[$id] = $target;
                }
            }
            $pdom = new \DOMDocument();
            if (@$pdom->loadXML($presXml) && !empty($relMap)) {
                $order = [];
                foreach ($pdom->getElementsByTagNameNS(self::NS_PRESENTATIONML, 'sldId') as $sldId) {
                    $rid = $sldId->getAttributeNS(self::NS_RELATIONSHIPS, 'id');
                    if ($rid !== '' && isset($relMap[$rid])) {
                        $order[] = $this->_pptxNormalizeTarget($relMap[$rid]);
                    }
                }
                if (!empty($order)) return $order;
            }
        }

        // Fallback: numeric sort of slide parts.
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int)$m[1]] = $name;
            }
        }
        ksort($slides);
        return array_values($slides);
    }

    /** Normalize a presentation.xml.rels Target (e.g. "slides/slide1.xml") to a zip entry path. */
    private function _pptxNormalizeTarget(string $target): string
    {
        $target = ltrim($target, '/');
        // Targets are relative to ppt/; collapse a leading "../" if present.
        if (str_starts_with($target, '../')) {
            return ltrim(substr($target, 3), '/');
        }
        return 'ppt/' . $target;
    }

    /**
     * Walk a slide/notes DOM in document order, emitting one Markdown line per
     * paragraph (<a:p>) and a Markdown grid per table (<a:tbl>). Tables are not
     * recursed into as paragraphs, so cell text isn't duplicated.
     */
    private function _pptxWalk(\DOMNode $node, array &$lines): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            $ln = $child->localName;
            if ($ln === 'tbl') {
                $this->_pptxTableToMarkdown($child, $lines);
                continue;
            }
            if ($ln === 'p') {
                $text = $this->_pptxParagraphText($child);
                if (trim($text) !== '') $lines[] = $text;
                continue;
            }
            $this->_pptxWalk($child, $lines);
        }
    }

    /** Concatenate every text run (<a:t>) inside a paragraph, in order. */
    private function _pptxParagraphText(\DOMNode $p): string
    {
        $text = '';
        foreach ($p->getElementsByTagNameNS(self::NS_DRAWINGML, 't') as $t) {
            $text .= $t->textContent;
        }
        return $text;
    }

    /** Render an <a:tbl> as a Markdown table, appending its rows to $lines. */
    private function _pptxTableToMarkdown(\DOMNode $tbl, array &$lines): void
    {
        $first = true;
        foreach ($tbl->getElementsByTagNameNS(self::NS_DRAWINGML, 'tr') as $tr) {
            $cellTexts = [];
            foreach ($tr->getElementsByTagNameNS(self::NS_DRAWINGML, 'tc') as $tc) {
                $cell = '';
                foreach ($tc->getElementsByTagNameNS(self::NS_DRAWINGML, 't') as $t) {
                    $cell .= $t->textContent;
                }
                $cellTexts[] = str_replace('|', '\\|', trim($cell));
            }
            if (empty($cellTexts)) continue;
            $lines[] = '| ' . implode(' | ', $cellTexts) . ' |';
            if ($first) {
                $lines[] = '| ' . implode(' | ', array_fill(0, count($cellTexts), '---')) . ' |';
                $first = false;
            }
        }
    }

    private function _processCsv(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, self::MAX_TEXT_BYTES);
        return $this->_truncateText($content);
    }

    private function _processImage(string $filePath, string $docId, string $ext): string
    {
        // Keep the original bytes on disk (re-download / future vision use).
        $orig = $this->_baseDir . $docId . ".orig." . $ext;
        copy($filePath, $orig);
        @chmod($orig, 0664);
        // v5.x — OCR primary: extract the text so ANY model (vision-capable or
        // not) can read image content via getContextForAi. The bytes remain in
        // .orig; the OCR text becomes the document's textual content. Empty
        // string when the image carries no recognizable text (e.g. a photo).
        $text = $this->_runOcr($filePath);
        if ($text === '') {
            $this->_docErrLog("OCR_EMPTY", basename($filePath), $ext, @filesize($filePath) ?: 0);
        }
        return $text;
    }

    /**
     * v5.x — Run Tesseract OCR on an image and return the extracted UTF-8 text.
     * Shells out to the tesseract binary (no PHP wrapper dependency). Fails soft:
     * returns '' on any error so a non-OCR'able image never breaks ingestion.
     */
    private function _runOcr(string $imagePath): string
    {
        $bin = is_executable('/usr/bin/tesseract')
            ? '/usr/bin/tesseract'
            : trim((string)@shell_exec('command -v tesseract 2>/dev/null'));
        if ($bin === '' || !is_executable($bin)) {
            $this->_docErrLog("OCR_NO_BINARY", basename($imagePath), '', 0);
            return '';
        }
        $langs = $this->_ocrLangs($bin);
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($imagePath) . ' stdout'
            . ($langs !== '' ? ' -l ' . escapeshellarg($langs) : '')
            . ' 2>/dev/null';
        $out = @shell_exec($cmd);
        return $out === null ? '' : trim((string)$out);
    }

    /**
     * v5.x — Pick the OCR language string for tesseract. Prefers "ita+eng"
     * (the documents are predominantly Italian) but degrades to whatever is
     * actually installed, so a missing language pack never aborts OCR.
     * Cached per request (tesseract --list-langs is a fork).
     */
    private function _ocrLangs(string $bin): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $list = (string)@shell_exec(escapeshellarg($bin) . ' --list-langs 2>/dev/null');
        $have = array_map('trim', preg_split('/\r?\n/', $list) ?: []);
        $want = array_values(array_filter(['ita', 'eng'], fn($l) => in_array($l, $have, true)));
        $cached = implode('+', $want); // "ita+eng" | "eng" | "" (let tesseract default)
        return $cached;
    }

    // ========================
    //  PRIVATE: Storage
    // ========================

    /**
     * Update the sid_date file with current timestamp.
     * Called on every session interaction to track last usage.
     */
    private function _touchSidDate(): void
    {
        $f = $this->_baseDir . 'sid_date';
        file_put_contents($f, (string) time());
        @chmod($f, 0664);
    }

    private function _loadProcessedContent(string $docId, string $type): string
    {
        // v5.x — images store their OCR text in the same .json container as
        // every other document (the legacy "[base64 image data]" placeholder is
        // gone; image bytes live in the .orig copy).
        $file = $this->_baseDir . $docId . ".json";
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return '';
    }

    private function _loadManifest(): array
    {
        $path = $this->_baseDir . "manifest.json";
        if (!file_exists($path)) return [];

        $fp = fopen($path, 'r');
        if (!$fp) return [];
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function _saveManifest(array $manifest): void
    {
        $path = $this->_baseDir . "manifest.json";
        $fp = fopen($path, 'c');
        if (!$fp) return;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($path, 0664);
    }

    private function _updateManifest(array $entry): void
    {
        // v5.x — atomic load-append-save under a single LOCK_EX. The previous
        // implementation (_loadManifest with LOCK_SH, then _saveManifest with
        // LOCK_EX) had a window between the two locks where two concurrent
        // uploads to the same session could both read the same baseline and
        // the later save would overwrite the earlier entry — losing files.
        $path = $this->_baseDir . "manifest.json";
        $fp = fopen($path, 'c+');
        if (!$fp) {
            General::Log("DocSpace _updateManifest: cannot open $path for atomic write");
            return;
        }
        flock($fp, LOCK_EX);
        $content  = stream_get_contents($fp);
        $manifest = json_decode((string)$content, true);
        if (!is_array($manifest)) $manifest = [];
        $manifest[] = $entry;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($path, 0664);
    }

    private function _truncateText(string $text): string
    {
        if (strlen($text) > self::MAX_TEXT_BYTES) {
            return substr($text, 0, self::MAX_TEXT_BYTES) . "\n... [troncato a " . $this->_formatBytes(self::MAX_TEXT_BYTES) . "]";
        }
        return $text;
    }

    /**
     * v5.x — translate a raw parser exception message into a short natural-language
     * reason that's safe to surface to the LLM (and to operators reading logs).
     */
    private static function _humanizeExtractionError(string $rawMessage): string
    {
        $m = strtolower($rawMessage);
        if (str_contains($m, 'secured pdf') || str_contains($m, 'password'))            return 'PDF protetto da password o restrizioni';
        if (str_contains($m, 'encrypted'))                                              return 'documento cifrato';
        if (str_contains($m, 'getcellbycolumnandrow'))                                  return 'foglio di calcolo non parsabile (incompatibilità libreria)';
        if (str_contains($m, 'invalid image') || str_contains($m, 'word/media'))        return 'documento Word con media non supportati';
        if (str_contains($m, 'corrupt') || str_contains($m, 'malformed'))               return 'file corrotto o malformato';
        if (str_contains($m, 'file not found'))                                         return 'file non trovato sul server al momento del processing';
        // v5.x — fatal-class catches via register_shutdown_function: rare on
        // the OpenSpout path but possible on DOCX/PDF DOM readers.
        if (str_contains($m, 'allowed memory') || str_contains($m, 'memory exhausted')) return 'documento troppo grande per essere elaborato (memoria insufficiente)';
        if (str_contains($m, 'maximum execution time'))                                 return 'documento troppo complesso per essere elaborato (timeout)';
        return 'errore di estrazione: ' . $rawMessage;
    }

    private function _formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . 'MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . 'KB';
        return $bytes . 'B';
    }

    /**
     * v5.x — Daily document-diagnostics log: Logs/docerr_DD.log where DD is the
     * day of month (01..31). Rolling 31-day retention "azzerato mensilmente":
     * the first write of a new month overwrites the same-numbered file left over
     * from the previous month instead of appending to it. One grep-able line per
     * event (extraction error, encrypted reject, truncation, small-model reject)
     * so an operator can see what happened when a user complains about a file.
     *
     * Purposely independent from General::Log (which is debug-gated and rotates
     * by date(d-M-Y)); this log must always be written regardless of debug flags.
     */
    private function _docErrLog(string $event, string $fileName, string $ext, int $sizeBytes): void
    {
        try {
            $logDir = !empty($_SERVER['DOCUMENT_ROOT'])
                ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/../Logs/'
                : dirname(__DIR__, 3) . '/Logs/';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

            $file = $logDir . 'docerr_' . date('d') . '.log';

            // Monthly rollover: if the existing file was last written in a
            // different month, truncate it before this write.
            $mode = 'a';
            if (is_file($file) && date('Y-m', (int)@filemtime($file)) !== date('Y-m')) {
                $mode = 'w';
            }
            $line = '[' . date('Y-m-d H:i:s') . '] sid=' . $this->_sessId
                  . ' ' . $event
                  . ' file="' . $fileName . '"'
                  . ' ext=' . strtoupper($ext)
                  . ' size=' . $this->_formatBytes($sizeBytes) . "\n";

            $fp = @fopen($file, $mode);
            if ($fp) {
                @fwrite($fp, $line);
                @fclose($fp);
                @chmod($file, 0664);
            }
        } catch (\Throwable $e) {
            // Diagnostics must never break ingestion.
        }
    }

    /**
     * Recursively delete a directory. Returns true on full success.
     * Collects per-path error messages into $errors (passed by reference).
     */
    private static function _removeDir(string $dir, array &$errors = []): bool
    {
        if (!is_dir($dir)) return true;
        $ok = true;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $p = $item->getPathname();
            if ($item->isDir()) {
                @chmod($p, 0775);
                if (!@rmdir($p)) {
                    $ok = false;
                    $e = error_get_last();
                    $errors[] = "rmdir $p: " . ($e['message'] ?? 'failed');
                }
            } else {
                @chmod($p, 0664);
                if (!@unlink($p)) {
                    $ok = false;
                    $e = error_get_last();
                    $errors[] = "unlink $p: " . ($e['message'] ?? 'failed');
                }
            }
        }
        @chmod($dir, 0775);
        if (!@rmdir($dir)) {
            $ok = false;
            $e = error_get_last();
            $errors[] = "rmdir $dir: " . ($e['message'] ?? 'failed');
        }
        return $ok;
    }

}
