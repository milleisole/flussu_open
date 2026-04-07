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
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;

class DocumentSpace
{
    private string $_sessId;
    private string $_baseDir;

    private const MAX_PDF_PAGES = 50;
    private const MAX_SPREADSHEET_ROWS = 1000;
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
        $this->_baseDir = $_SERVER['DOCUMENT_ROOT'] . "/../Uploads/docspace/" . $sessId . "/";
        if (!is_dir($this->_baseDir)) {
            mkdir($this->_baseDir, 0775, true);
            @chmod($this->_baseDir, 0775);
        }
        $this->_touchSidDate();
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
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            General::Log("DocSpace error processing $originalName: " . $e->getMessage());
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
        $baseDir = $_SERVER['DOCUMENT_ROOT'] . "/../Uploads/docspace/" . $sessId . "/";
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
        $docspaceRoot = $_SERVER['DOCUMENT_ROOT'] . "/../Uploads/docspace/";
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
        $phpWord = WordIOFactory::load($filePath);
        $md = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $md .= $this->_docxElementToMarkdown($element);
            }
        }

        return $this->_truncateText($md);
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
        $spreadsheet = SpreadsheetIOFactory::load($filePath);
        $sheetsData = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $highestRow = min($sheet->getHighestRow(), self::MAX_SPREADSHEET_ROWS);
            $highestCol = $sheet->getHighestColumn();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

            $csv = '';
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
                    $value = $cell->getFormattedValue();
                    // Escape CSV: quote values containing commas, quotes, or newlines
                    if (preg_match('/[,"\n\r]/', $value)) {
                        $value = '"' . str_replace('"', '""', $value) . '"';
                    }
                    $rowData[] = $value;
                }
                $csv .= implode(',', $rowData) . "\n";
            }

            $truncated = $highestRow < $sheet->getHighestRow();
            $sheetsData[] = [
                'name'      => $sheetName,
                'rows'      => $highestRow,
                'cols'      => $highestColIndex,
                'truncated' => $truncated,
                'csv'       => $csv,
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

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
        $manifest = $this->_loadManifest();
        $manifest[] = $entry;
        $this->_saveManifest($manifest);
    }

    private function _truncateText(string $text): string
    {
        if (strlen($text) > self::MAX_TEXT_BYTES) {
            return substr($text, 0, self::MAX_TEXT_BYTES) . "\n... [troncato a " . $this->_formatBytes(self::MAX_TEXT_BYTES) . "]";
        }
        return $text;
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
