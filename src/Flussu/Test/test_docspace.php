<?php
/**
 * Test script for DocumentSpace
 * Run: php src/Flussu/Test/test_docspace.php
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Simulate DOCUMENT_ROOT for CLI
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../../../webroot';

use Flussu\Documents\DocumentSpace;

$testSid = 'test-' . uniqid();
$tempDir = __DIR__ . '/../../../Uploads/temp/';
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

echo "=== DocumentSpace Test ===\n";
echo "Test SID: $testSid\n\n";

// Create test files
$testFiles = [];

// 1. TXT file
$txtFile = $tempDir . 'test_doc.txt';
file_put_contents($txtFile, "Questo è un documento di testo di prova.\nContiene più righe.\nTerza riga del documento.");
$testFiles[] = ['path' => $txtFile, 'name' => 'test_doc.txt'];

// 2. CSV file
$csvFile = $tempDir . 'test_data.csv';
file_put_contents($csvFile, "nome,cognome,età\nMario,Rossi,30\nLuigi,Verdi,25\nAnna,Bianchi,35");
$testFiles[] = ['path' => $csvFile, 'name' => 'test_data.csv'];

// 3. Create a minimal DOCX for testing (DOCX is a ZIP with XML)
$docxFile = $tempDir . 'test_doc.docx';
$zip = new ZipArchive();
if ($zip->open($docxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Titolo del Documento</w:t></w:r></w:p>
    <w:p><w:r><w:t>Questo è un paragrafo di test con del testo.</w:t></w:r></w:p>
    <w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Testo in grassetto</w:t></w:r><w:r><w:t> e testo normale.</w:t></w:r></w:p>
  </w:body>
</w:document>');
    $zip->close();
    $testFiles[] = ['path' => $docxFile, 'name' => 'test_doc.docx'];
}

echo "--- Test 1: Create DocumentSpace and add documents ---\n";
$ds = new DocumentSpace($testSid);

foreach ($testFiles as $tf) {
    $result = $ds->addDocument($tf['path'], $tf['name']);
    $status = $result['success'] ? "OK" : "FAIL: " . $result['error'];
    echo "  Add {$tf['name']}: $status (id: {$result['id']})\n";
}

echo "\n--- Test 2: Document list ---\n";
$list = $ds->getDocumentList();
echo "  Documents in space: " . count($list) . "\n";
foreach ($list as $doc) {
    echo "  - {$doc['originalName']} ({$doc['type']}, {$doc['sizeBytes']}B)\n";
}

echo "\n--- Test 3: hasDocuments ---\n";
echo "  hasDocuments: " . ($ds->hasDocuments() ? 'true' : 'false') . "\n";

echo "\n--- Test 4: getContextForAi ---\n";
$context = $ds->getContextForAi();
echo "  Context length: " . strlen($context) . " chars\n";
echo "  --- Context preview (first 500 chars) ---\n";
echo substr($context, 0, 500) . "\n";
echo "  --- End preview ---\n";

echo "\n--- Test 5: getSpaceSize ---\n";
echo "  Space size: " . $ds->getSpaceSize() . " bytes\n";

echo "\n--- Test 6: removeDocument ---\n";
if (!empty($list)) {
    $removeId = $list[0]['id'];
    $removed = $ds->removeDocument($removeId);
    echo "  Removed {$list[0]['originalName']}: " . ($removed ? 'OK' : 'FAIL') . "\n";
    echo "  Documents after removal: " . count($ds->getDocumentList()) . "\n";
}

echo "\n--- Test 7: Generated files ---\n";
// Simulate an AI-generated image
$fakeImageData = str_repeat("\x89PNG\r\n", 100); // fake PNG-like data
$genResult = $ds->addGenerated($fakeImageData, 'gen_test_image.png', 'image/png');
echo "  addGenerated: " . ($genResult['filename'] === 'gen_test_image.png' ? 'OK' : 'FAIL') . "\n";
echo "  Generated path: " . $genResult['path'] . "\n";
echo "  Generated URL: " . $genResult['url'] . "\n";

// Simulate a second generated file
$fakePdfData = "%PDF-1.4 fake pdf content for testing";
$genResult2 = $ds->addGenerated($fakePdfData, 'report_generated.pdf', 'application/pdf');
echo "  addGenerated (PDF): " . ($genResult2['filename'] === 'report_generated.pdf' ? 'OK' : 'FAIL') . "\n";

echo "\n--- Test 8: List generated files ---\n";
$genFiles = $ds->getGeneratedFiles();
echo "  Generated files count: " . count($genFiles) . "\n";
foreach ($genFiles as $gf) {
    echo "  - {$gf['filename']} ({$gf['mimeType']}, {$gf['size']}B)\n";
}

echo "\n--- Test 9: hasGeneratedFiles ---\n";
echo "  hasGeneratedFiles: " . ($ds->hasGeneratedFiles() ? 'true' : 'false') . "\n";

echo "\n--- Test 10: getGeneratedFilePath ---\n";
$foundPath = $ds->getGeneratedFilePath('gen_test_image.png');
echo "  Found existing: " . ($foundPath !== null ? 'OK' : 'FAIL') . "\n";
$notFound = $ds->getGeneratedFilePath('nonexistent.png');
echo "  Not found for missing: " . ($notFound === null ? 'OK' : 'FAIL') . "\n";
$traversal = $ds->getGeneratedFilePath('../../etc/passwd');
echo "  Path traversal blocked: " . ($traversal === null ? 'OK' : 'FAIL') . "\n";

echo "\n--- Test 11: cleanup removes generated too ---\n";
DocumentSpace::cleanup($testSid);
$docspaceDir = $_SERVER['DOCUMENT_ROOT'] . "/../Uploads/docspace/" . $testSid . "/";
echo "  Space directory exists after cleanup: " . (is_dir($docspaceDir) ? 'YES (FAIL)' : 'NO (OK)') . "\n";

// Cleanup test files
foreach ($testFiles as $tf) {
    if (file_exists($tf['path'])) unlink($tf['path']);
}

echo "\n=== Test Complete ===\n";
