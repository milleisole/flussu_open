<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Upload endpoint for Document Space.
 * Receives file uploads from the client chatbox. If a SID is provided,
 * the file is processed directly into the session's DocumentSpace.
 * Otherwise, it is saved to temp for later processing.
 * -------------------------------------------------------*/

require_once __DIR__ . '/../../vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

if (empty($_FILES['file'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

// Max 20MB
$maxSize = 20 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'message' => 'File too large (max 20MB)']);
    exit;
}

// Allowed extensions
$allowedExts = ['pdf','docx','txt','md','log','xlsx','ods','csv','jpg','jpeg','png','gif','webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts)) {
    echo json_encode(['status' => 'error', 'message' => 'File type not allowed: ' . $ext]);
    exit;
}

// Sanitize filename
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);
$safeName = preg_replace('/\.{2,}/', '.', $safeName);

// Save to temp directory first
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/../Uploads/temp/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uniqueName = uniqid('dsp_') . '_' . $safeName;
$destPath = $uploadDir . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit;
}

// Get SID from: POST param, GET param, or flussu_sid cookie
$sid = $_POST['sid'] ?? $_GET['sid'] ?? $_COOKIE['flussu_sid'] ?? '';
$docResult = null;


if (!empty($sid) && preg_match('/^[a-zA-Z0-9\-]+$/', $sid)) {
    try {
        $docSpace = new \Flussu\Documents\DocumentSpace($sid);
        $docResult = $docSpace->addDocument($destPath, $file['name']);
        // Remove temp file after processing
        if (file_exists($destPath)) {
            unlink($destPath);
        }
    } catch (\Throwable $e) {
        $docResult = ['success' => false, 'error' => $e->getMessage()];
    }
}

$response = [
    'status' => 'ok',
    'path'   => $destPath,
    'name'   => $file['name'],
    'size'   => $file['size']
];

if ($docResult !== null) {
    $response['docspace'] = $docResult;
}

echo json_encode($response);
