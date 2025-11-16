<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - API Entry Point
 * --------------------------------------------------------------------*/

// Error reporting (disabilita in produzione)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Cambia a '0' in produzione
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../Logs/api_errors.log');

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Session-ID');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carica autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Carica configurazione
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

// Import controller
use Flussu\Controllers\UserManagementController;

try {
    // Debug mode da .env
    $debug = isset($_ENV['debug_log']) && $_ENV['debug_log'] == '1';

    // Crea controller
    $controller = new UserManagementController($debug);

    // Ottieni path dalla query string
    $path = $_GET['path'] ?? $_SERVER['REQUEST_URI'] ?? '/';

    // Rimuovi /api/flussu dal path se presente
    $path = preg_replace('#^/api/flussu#', '', $path);
    $path = preg_replace('#^/api/user-management\.php\?path=#', '', $path);

    // Rimuovi query string dal path
    if (strpos($path, '?') !== false) {
        $path = substr($path, 0, strpos($path, '?'));
    }

    // Prepara request data
    $request = [
        'path' => $path,
        'method' => $_SERVER['REQUEST_METHOD']
    ];

    // Aggiungi query parameters
    foreach ($_GET as $key => $value) {
        if ($key !== 'path') {
            $request[$key] = $value;
        }
    }

    // Esegui request
    $result = $controller->handleRequest($request);

    // Output result
    if (is_array($result)) {
        echo json_encode($result);
    } else {
        echo $result;
    }

} catch (\Exception $e) {
    // Log error
    error_log('API Error: ' . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $debug ? $e->getMessage() : 'An error occurred'
    ]);
}
