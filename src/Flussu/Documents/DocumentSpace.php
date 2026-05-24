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
    private const MAX_SPREADSHEET_SHEETS = 30;
    private const MAX_TEXT_BYTES = 500000; // 500KB per file
    private const MAX_CONTEXT_CHARS = 50000;

    private const TYPE_MAP = [
        'pdf'  => 'pdf',
        'docx' => 'docx',
        'txt'  => 'text',
        'md'   => 'text',
        'log'  => 'text',
        'xlsx' => 'spreadsheet',
        'ods'  => 'spreadsheet',
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
                'pdf'         => $this->_processPdf($filePath),
                'docx'        => $this->_processDocx($filePath),
                'text'        => $this->_processText($filePath),
                'spreadsheet' => $this->_processSpreadsheet($filePath),
                'csv'         => $this->_processCsv($filePath),
                'image'       => $this->_processImage($filePath, $docId, $ext),
            };

            // Save processed content
            if ($docType === 'image') {
                $out = $this->_baseDir . $docId . ".b64";
            } else {
                $out = $this->_baseDir . $docId . ".json";
            }
            file_put_contents($out, $content);
            @chmod($out, 0664);

            // Update manifest
            $entry = [
                'id'            => $docId,
                'originalName'  => $originalName,
                'type'          => $docType,
                'extension'     => $ext,
                'processedFile' => $docId . ($docType === 'image' ? '.b64' : '.json'),
                'addedAt'       => date('c'),
                'sizeBytes'     => filesize($filePath),
            ];
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
     */
    public function getContextForAi(int $maxChars = self::MAX_CONTEXT_CHARS): string
    {
        $manifest = $this->_loadManifest();
        if (empty($manifest)) return '';

        $context = "--- DOCUMENTI ALLEGATI ---\n";
        $totalChars = strlen($context) + 30; // reserve for closing tag
        $included = 0;

        foreach ($manifest as $entry) {
            // v5.x — extraction-failed documents: still render a header so the
            // LLM is aware the attachment exists in the session, even though
            // no text content is available.
            if (!empty($entry['extractionError'])) {
                $reason = self::_humanizeExtractionError((string)$entry['extractionError']);
                $line  = "[Documento: " . $entry['originalName'] . " (" . strtoupper($entry['extension']) . ", " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
                $line .= "[Contenuto testuale non disponibile — $reason]\n";
                $line .= "[Il file è presente nella sessione e può essere referenziato per nome.]\n";
                $line .= "[Fine Documento: " . $entry['originalName'] . "]\n\n";
                if ($totalChars + strlen($line) > $maxChars) break;
                $context .= $line;
                $totalChars += strlen($line);
                $included++;
                continue;
            }
            if ($entry['type'] === 'image') {
                // Images: just note their presence for text-only context
                $line = "[Documento: " . $entry['originalName'] . " (Immagine, " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
                $line .= "[Immagine disponibile per analisi visiva]\n";
                $line .= "[Fine Documento: " . $entry['originalName'] . "]\n\n";
                if ($totalChars + strlen($line) > $maxChars) break;
                $context .= $line;
                $totalChars += strlen($line);
            } else {
                $content = $this->_loadProcessedContent($entry['id'], $entry['type']);
                $header = "[Documento: " . $entry['originalName'] . " (" . strtoupper($entry['extension']) . ", " . $this->_formatBytes($entry['sizeBytes']) . ")]\n";
                $footer = "\n[Fine Documento: " . $entry['originalName'] . "]\n\n";
                $available = $maxChars - $totalChars - strlen($header) - strlen($footer);
                if ($available <= 0) break;

                if (strlen($content) > $available) {
                    $content = substr($content, 0, $available) . "\n... [troncato]";
                }

                $context .= $header . $content . $footer;
                $totalChars += strlen($header) + strlen($content) + strlen($footer);
            }
            $included++;
        }

        $remaining = count($manifest) - $included;
        if ($remaining > 0) {
            $context .= "[$remaining documenti omessi per limite dimensione]\n";
        }

        $context .= "--- FINE DOCUMENTI ALLEGATI ---";
        $this->_touchSidDate();
        return $context;
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

        return json_encode(['sheets' => $sheetsData], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function _processCsv(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, self::MAX_TEXT_BYTES);
        return $this->_truncateText($content);
    }

    private function _processImage(string $filePath, string $docId, string $ext): string
    {
        // Copy original for multimodal use
        $orig = $this->_baseDir . $docId . ".orig." . $ext;
        copy($filePath, $orig);
        @chmod($orig, 0664);
        // Return base64
        return base64_encode(file_get_contents($filePath));
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
        if ($type === 'image') {
            return '[base64 image data]';
        }
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
