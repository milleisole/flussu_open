<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Document Space API endpoint.
 * Provides access to session's generated files:
 *   ?action=list&sid=...       — list generated files
 *   ?action=download&sid=...&file=...  — download a specific file
 * -------------------------------------------------------*/

require_once __DIR__ . '/../../vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Only GET allowed']);
    exit;
}

$action = $_GET['action'] ?? '';
$sid = $_GET['sid'] ?? '';

if (empty($sid)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Missing sid parameter']);
    exit;
}

// Sanitize SID: only allow UUID-like characters
if (!preg_match('/^[a-zA-Z0-9\-]+$/', $sid)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Invalid sid format']);
    exit;
}

// Verify docspace exists
$docspaceDir = $_SERVER['DOCUMENT_ROOT'] . '/../Uploads/docspace/' . $sid . '/';
if (!is_dir($docspaceDir)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'No document space found for this session']);
    exit;
}

use Flussu\Documents\DocumentSpace;

$docSpace = new DocumentSpace($sid);

switch ($action) {

    case 'remove':
        // Remove a document from the session's docspace
        header('Content-Type: application/json; charset=UTF-8');
        $docId = $_GET['id'] ?? '';
        if (empty($docId)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing id parameter']);
            exit;
        }
        $removed = $docSpace->removeDocument($docId);
        echo json_encode([
            'status'  => $removed ? 'ok' : 'error',
            'message' => $removed ? 'Document removed' : 'Document not found',
            'id'      => $docId
        ]);
        break;

    case 'documents':
        // List all uploaded documents in the session's docspace
        header('Content-Type: application/json; charset=UTF-8');
        $docs = $docSpace->getDocumentList();
        echo json_encode([
            'status' => 'ok',
            'sid'    => $sid,
            'documents' => array_map(function ($d) {
                return [
                    'id'           => $d['id'],
                    'originalName' => $d['originalName'],
                    'type'         => $d['type'],
                    'extension'    => $d['extension'],
                    'sizeBytes'    => $d['sizeBytes'],
                    'addedAt'      => $d['addedAt'],
                ];
            }, $docs)
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'list':
        // List all generated files
        header('Content-Type: application/json; charset=UTF-8');
        $files = $docSpace->getGeneratedFiles();
        echo json_encode([
            'status' => 'ok',
            'sid'    => $sid,
            'files'  => array_map(function ($f) {
                return [
                    'filename'  => $f['filename'],
                    'url'       => $f['url'],
                    'size'      => $f['size'],
                    'mimeType'  => $f['mimeType'],
                    'createdAt' => $f['createdAt'],
                ];
            }, $files)
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'download':
        // Download a specific generated file
        $filename = $_GET['file'] ?? '';
        if (empty($filename)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'error', 'message' => 'Missing file parameter']);
            exit;
        }

        $filePath = $docSpace->getGeneratedFilePath($filename);
        if (!$filePath) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit;
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filePath);
        break;

    default:
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Unknown action. Use: list, download'
        ]);
        break;
}
