<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Upload endpoint for Document Space.
 * Receives file uploads from the client chatbox. A valid session SID is
 * REQUIRED (POST field "sid", GET param "sid", or "flussu_sid" cookie);
 * without it the request is rejected with HTTP 422 reason:missing_sid and
 * the temp file is deleted. With a valid SID the file is processed
 * directly into the session's DocumentSpace.
 * -------------------------------------------------------*/

require_once __DIR__ . '/../../vendor/autoload.php';

// v5.x — office-format extraction (PDFs with many pages, DOCX with embedded
// media, edge-case XLSX) can be RAM-hungry. Raise PHP budgets so the endpoint
// can finish the job; the 20MB application-level cap below still bounds input.
// XLSX/ODS now go through OpenSpout's streaming reader (constant ~30MB) so
// they should never approach this ceiling — the headroom is for PDF/DOCX.
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '120');

// v5.x — last-line defense: if a PHP fatal still escapes (extension crash,
// unforeseen library bug, OOM beyond 512M) emit parsable JSON instead of an
// empty 500 body. The JS client at flucli/client.dev.js does JSON.parse on
// every response — an empty body breaks the parser silently.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err === null) return;
    $fatal = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE];
    if (!in_array($err['type'], $fatal, true)) return;
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'Extraction failed (fatal): ' . $err['message'],
        'fatal'   => true,
    ]);
});

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
    // v5.x — surface PHP-level upload errors with a human-readable message AND
    // the effective server limits, so the calling system can self-diagnose
    // size-related failures (e.g. UPLOAD_ERR_INI_SIZE when the binary exceeds
    // upload_max_filesize / post_max_size) instead of just receiving a bare
    // numeric code.
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder on server',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
    ];
    $msg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
    echo json_encode([
        'status'  => 'error',
        'message' => $msg . ' (code ' . $file['error'] . ')',
        'limits'  => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'app_max_mb'          => 20,
        ],
    ]);
    exit;
}

// Max 20MB (application-level cap, on top of the PHP ini limits above)
$maxSize = 20 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'File too large (max 20MB)',
        'size'    => $file['size'],
    ]);
    exit;
}

// Allowed extensions
$allowedExts = [
    'pdf','docx','txt','md','log','xlsx','ods','csv','jpg','jpeg','png','gif','webp',
    'json','yaml','yml','xml','html','htm','css','js',
];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// v5.x — structured 422 rejections: extensions we explicitly recognize but
// cannot process. The caller gets an actionable reason instead of the
// generic "File type not allowed" message below.
$structuredRejections = [
    'xls' => [
        'reason'  => 'format_not_supported_use_xlsx',
        'message' => 'Legacy .xls (Excel 97-2003 binary) is not supported. Please re-save the workbook as .xlsx.',
    ],
];
if (isset($structuredRejections[$ext])) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'reason'  => $structuredRejections[$ext]['reason'],
        'message' => $structuredRejections[$ext]['message'],
    ]);
    exit;
}

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

// [DIAG_UPLOAD_v1] SID resolution introspection — investigating why
// real-user uploads (e.g. FP_su_5_anni_Canonity.xlsx 2026-05-24 10:36/10:38)
// land in Uploads/temp/ but never trigger addDocument on the workflow SID.
\Flussu\General::Log_nocaller(
    '[DIAG_UPLOAD_v1] sid_resolution'
    . ' sid=' . ($sid !== '' ? $sid : '(empty)')
    . ' from_post=' . (isset($_POST['sid']) ? '"' . $_POST['sid'] . '"' : 'no')
    . ' from_get=' . (isset($_GET['sid']) ? '"' . $_GET['sid'] . '"' : 'no')
    . ' from_cookie=' . (isset($_COOKIE['flussu_sid']) ? '"' . $_COOKIE['flussu_sid'] . '"' : 'no')
    . ' post_keys=[' . implode(',', array_keys($_POST)) . ']'
    . ' file_name=' . ($file['name'] ?? '?')
    . ' file_size=' . ($file['size'] ?? '?')
    . ' content_type=' . ($_SERVER['CONTENT_TYPE'] ?? '?')
    . ' origin=' . ($_SERVER['HTTP_ORIGIN'] ?? '?')
    . ' referer=' . ($_SERVER['HTTP_REFERER'] ?? '?'),
    true
);

if (!empty($sid) && preg_match('/^[a-zA-Z0-9\-]+$/', $sid)) {
    try {
        $docSpace = new \Flussu\Documents\DocumentSpace($sid);
        $docResult = $docSpace->addDocument($destPath, $file['name']);
        // [DIAG_UPLOAD_v1] addDocument outcome inside the SID-valid branch
        \Flussu\General::Log_nocaller(
            '[DIAG_UPLOAD_v1] addDocument sid=' . $sid
            . ' name=' . $file['name']
            . ' size=' . $file['size']
            . ' result=' . json_encode($docResult),
            true
        );
        // Remove the temp copy only if DocumentSpace successfully ingested it;
        // otherwise keep it so the caller (or operator) can investigate.
        if (!empty($docResult['success']) && file_exists($destPath)) {
            unlink($destPath);
        }
    } catch (\Throwable $e) {
        $docResult = ['success' => false, 'error' => $e->getMessage()];
        // [DIAG_UPLOAD_v1] addDocument threw — capture exception path
        \Flussu\General::Log_nocaller(
            '[DIAG_UPLOAD_v1] addDocument_threw sid=' . $sid
            . ' name=' . $file['name']
            . ' exception=' . get_class($e)
            . ' message=' . $e->getMessage(),
            true
        );
    }
} else {
    // [DIAG_UPLOAD_v1] file landed in temp/ without a valid SID — smoking-gun
    // signature: client uploads but no DocumentSpace is ever touched.
    \Flussu\General::Log_nocaller(
        '[DIAG_UPLOAD_v1] no_sid_branch'
        . ' name=' . $file['name']
        . ' size=' . $file['size']
        . ' temp_path=' . $destPath
        . ' raw_sid=' . var_export($sid, true)
        . ' sid_passes_regex=' . (preg_match('/^[a-zA-Z0-9\-]+$/', $sid) ? 'yes' : 'no'),
        true
    );

    // v5.x — fail loudly when no valid SID is provided. Previously this
    // returned status:"ok" with the file orphaned in temp/ and no docspace
    // key, leading callers to believe the upload succeeded while the LLM
    // never saw the file. The endpoint contract is now: SID is required,
    // either as POST field, GET param, or flussu_sid cookie.
    if (file_exists($destPath)) @unlink($destPath);
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'reason'  => empty($sid) ? 'missing_sid' : 'invalid_sid',
        'message' => empty($sid)
            ? 'Missing session ID — provide "sid" as POST field, GET parameter, or flussu_sid cookie.'
            : 'Session ID contains invalid characters — must match ^[a-zA-Z0-9\-]+$',
        'name'    => $file['name'],
        'size'    => $file['size'],
    ]);
    exit;
}

// v5.x — if a SID was supplied the upload's contract includes "file landed in
// the session's DocumentSpace". Surface that failure as a top-level error
// instead of masking it inside docspace.success (callers were treating the
// outer status:"ok" as proof the file was ingested).
if ($docResult !== null && empty($docResult['success'])) {
    // Encrypted XLSX/ODS is a hard rejection — emit structured 422 so the
    // caller can show a clear "workbook is password-protected" message and
    // the file is removed from temp (no point retaining unreadable bytes).
    if (($docResult['reason'] ?? null) === 'encrypted_workbook') {
        if (file_exists($destPath)) @unlink($destPath);
        http_response_code(422);
        echo json_encode([
            'status'  => 'error',
            'reason'  => 'encrypted_workbook',
            'message' => 'Spreadsheet is password-protected and cannot be read. Remove the password protection and retry.',
            'name'    => $file['name'],
        ]);
        exit;
    }
    echo json_encode([
        'status'   => 'error',
        'message'  => 'File saved to temp but DocumentSpace ingestion failed: ' . ($docResult['error'] ?? 'unknown error'),
        'path'     => $destPath,
        'name'     => $file['name'],
        'size'     => $file['size'],
        'docspace' => $docResult,
    ]);
    exit;
}

$response = [
    'status' => 'ok',
    'path'   => $destPath,
    'name'   => $file['name'],
    'size'   => $file['size'],
];

if ($docResult !== null) {
    $response['docspace'] = $docResult;
}

echo json_encode($response);
