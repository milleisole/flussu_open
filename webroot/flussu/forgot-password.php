<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * PASSWORD RESET MANAGEMENT
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once 'inc/includebase.php';

// Imposta gli header per le risposte JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Directory per i token temporanei
$tokenDir =PROJECT_ROOT . '/../../Uploads/temp/reset_tokens';
if (!file_exists($tokenDir)) {
    mkdir($tokenDir, 0700, true);
}

/**
 * Genera un token sicuro per il reset della password
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Salva il token di reset con timestamp di scadenza
 */
function saveResetToken($email, $token, $userId) {
    global $tokenDir;
    $tokenData = [
        'email' => $email,
        'userId' => $userId,
        'token' => $token,
        'created' => time(),
        'expires' => time() + (3600 * 24) // 24 ore
    ];

    $tokenFile = $tokenDir . '/' . md5($email) . '.json';
    file_put_contents($tokenFile, json_encode($tokenData));

    // Cleanup token vecchi (più di 24 ore)
    cleanupExpiredTokens();

    return true;
}

/**
 * Verifica e recupera i dati del token
 */
function verifyResetToken($token) {
    global $tokenDir;

    $files = glob($tokenDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && $data['token'] === $token) {
            if ($data['expires'] > time()) {
                return $data;
            } else {
                // Token scaduto, eliminalo
                unlink($file);
                return false;
            }
        }
    }
    return false;
}

/**
 * Elimina il token dopo l'utilizzo
 */
function deleteResetToken($email) {
    global $tokenDir;
    $tokenFile = $tokenDir . '/' . md5($email) . '.json';
    if (file_exists($tokenFile)) {
        unlink($tokenFile);
    }
}

/**
 * Pulisce i token scaduti
 */
function cleanupExpiredTokens() {
    global $tokenDir;
    $files = glob($tokenDir . '/*.json');
    $now = time();

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && $data['expires'] < $now) {
            unlink($file);
        }
    }
}

/**
 * Invia email con il link di reset (stub - da implementare con sistema email reale)
 */
function sendResetEmail($email, $token) {
    // NOTA: Questa è una funzione stub. In produzione, integrare con un servizio email
    // come SendGrid, Mailgun, AWS SES, etc.

    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/forgot-password.php?action=verify&token=" . $token;

    // Log per debug (rimuovere in produzione)
    Flussu\General::addRowLog("[Password Reset] Token generato per $email: $resetLink");

    // TODO: Implementare invio email reale
    // Esempio:
    // $subject = "Reset Password - Flussu";
    // $message = "Clicca sul seguente link per resettare la password: $resetLink";
    // mail($email, $subject, $message);

    return [
        'sent' => true,
        'debug_link' => $resetLink, // Rimuovere in produzione!
        'note' => 'Email sending not implemented. Use debug_link for testing.'
    ];
}

// Routing basato sull'azione richiesta
$action = $_GET['action'] ?? $_POST['action'] ?? 'request';

try {
    switch ($action) {
        case 'request':
            // Step 1: Richiesta reset password via email
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $emailOrUsername = Flussu\General::getGetOrPost("emailOrUsername");
            //$input = json_decode(file_get_contents('php://input'), true);
            //$email = trim($input['email'] ?? '');

            if (empty($emailOrUsername)) {
                throw new Exception('Email or username is required', 400);
            }

            // Verifica se l'email esiste
            $user = new Flussu\Persons\User();
            $userExists = $user->load($emailOrUsername);

            if (!$userExists) {
                // Per sicurezza, non rivelare se l'email esiste o meno
                echo json_encode([
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.'
                ]);
                exit();
            }

            // Genera e salva il token
            $token = generateResetToken();
            saveResetToken($email, $token, $emailExists[1]);

            // Invia email (o mostra link per debug)
            $emailResult = sendResetEmail($user->getEmail(), $token);

            echo json_encode([
                'success' => true,
                'message' => 'Password reset link has been sent to your email.',
                'debug' => $emailResult // Rimuovere in produzione!
            ]);
            break;

        case 'verify':
            // Step 2: Verifica il token (può essere usato per mostrare form di reset)
            $token = $_GET['token'] ?? '';

            if (empty($token)) {
                throw new Exception('Token is required', 400);
            }

            $tokenData = verifyResetToken($token);

            if (!$tokenData) {
                throw new Exception('Invalid or expired token', 400);
            }

            // Qui potresti reindirizzare a una pagina HTML con form di reset
            // oppure restituire un JSON per un'app frontend
            echo json_encode([
                'success' => true,
                'message' => 'Token is valid',
                'email' => $tokenData['email'],
                'token' => $token
            ]);
            break;

        case 'reset':
            // Step 3: Reset effettivo della password
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $token = trim($input['token'] ?? '');
            $newPassword = trim($input['password'] ?? '');
            $confirmPassword = trim($input['confirm_password'] ?? '');

            if (empty($token) || empty($newPassword)) {
                throw new Exception('Token and password are required', 400);
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match', 400);
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long', 400);
            }

            // Verifica il token
            $tokenData = verifyResetToken($token);

            if (!$tokenData) {
                throw new Exception('Invalid or expired token', 400);
            }

            // Cambia la password usando il metodo statico
            $result = Flussu\Persons\User::changeUserPassword($tokenData['userId'], $newPassword);

            if (!$result) {
                throw new Exception('Failed to update password', 500);
            }

            // Elimina il token usato
            deleteResetToken($tokenData['email']);

            echo json_encode([
                'success' => true,
                'message' => 'Password has been successfully reset. You can now login with your new password.'
            ]);
            break;

        default:
            throw new Exception('Invalid action', 400);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //---------------
