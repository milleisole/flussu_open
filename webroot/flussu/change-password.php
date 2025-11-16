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
 *
 *      Cambio Password Obbligatorio - Per password scadute
 *
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

define('PROJECT_ROOT', dirname(__DIR__, 2)."/");

require_once PROJECT_ROOT . 'vendor/autoload.php';

use Flussu\Persons\User;
use Flussu\General;
use Flussu\Config;

$dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
$dotenv->load();

if (!function_exists('config')) {
    function config(string $key,$default=null) {
        return Config::init()->get($key,$default);
    }
}

$FVP=explode(".", config("flussu.version","5.0").".".config("flussu.release","0"));
$v=$FVP[0];
$m=$FVP[1];

// Avvia sessione
session_start();

// Variabili per messaggi
$error = '';
$success = '';

// Ottieni username da URL, POST o session
$username = '';
if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
} elseif (isset($_GET['username'])) {
    $username = htmlspecialchars(trim($_GET['username']));
} elseif (isset($_SESSION['flussu_username'])) {
    $username = $_SESSION['flussu_username'];
}

/**
 * Valida password secondo i requisiti di sicurezza
 * @param string $password
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'La password deve essere di almeno 8 caratteri'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'La password deve contenere almeno una lettera maiuscola'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'La password deve contenere almeno una lettera minuscola'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'La password deve contenere almeno un numero'];
    }
    return ['valid' => true, 'message' => ''];
}

// Gestione POST - Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validazione base
    if (empty($username) || empty($currentPassword)) {
        $error = 'Inserisci username e password corrente';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Inserisci la nuova password e la conferma';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Le nuove password non corrispondono';
    } elseif ($currentPassword === $newPassword) {
        $error = 'La nuova password deve essere diversa da quella corrente';
    } else {
        // Validazione password
        $validation = validatePassword($newPassword);
        if (!$validation['valid']) {
            $error = $validation['message'];
        } else {
            try {
                $user = new User();

                // Verifica che l'utente esista e la password corrente sia corretta
                $authenticated = $user->authenticate($username, $currentPassword);

                if ($authenticated && $user->getId() > 0) {
                    // Cambia la password (temporary=false per impostare scadenza a +1 anno)
                    $changed = $user->setPassword($newPassword, false);

                    if ($changed) {
                        $success = 'Password cambiata con successo! Verrai reindirizzato al login.';

                        // Pulisci la sessione
                        session_unset();
                        session_destroy();

                        // Redirect dopo 2 secondi
                        header("refresh:2;url=login.php");
                    } else {
                        $error = 'Errore durante il cambio password. Riprova.';
                    }
                } else {
                    $error = 'Password corrente non valida';
                }
            } catch (Exception $e) {
                General::addLog("[Change Password Error] " . $e->getMessage());
                $error = 'Si è verificato un errore. Riprova più tardi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flussu - Cambio Password Obbligatorio</title>
    <link rel="stylesheet" href="css/flussu-admin.css">
    <style>
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-size: 13px;
        }
        .password-requirements ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        .password-requirements li {
            margin: 5px 0;
            color: #666;
        }
        .password-requirements li.valid {
            color: #28a745;
        }
        .password-requirements li.invalid {
            color: #dc3545;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <svg width="120" height="40" viewBox="0 0 120 40">
                    <text x="10" y="30" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#188d4d">FLUSSU</text>
                </svg>
            </div>

            <h1 class="login-title">Cambio Password Obbligatorio</h1>

            <div class="warning-box">
                <strong>⚠️ Attenzione</strong>
                La tua password è scaduta o temporanea. Per motivi di sicurezza, devi impostare una nuova password prima di poter accedere al sistema.
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="change-password.php" id="changePasswordForm">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Inserisci username"
                        value="<?php echo htmlspecialchars($username); ?>"
                        required
                        autocomplete="username"
                    />
                </div>

                <div class="form-group">
                    <label for="current_password" class="form-label">Password Corrente</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="form-control"
                        placeholder="Inserisci password corrente"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">Nuova Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-control"
                        placeholder="Inserisci nuova password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Conferma Nuova Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Conferma nuova password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div class="password-requirements">
                    <strong>La password deve contenere:</strong>
                    <ul id="passwordRequirements">
                        <li id="req-length">Almeno 8 caratteri</li>
                        <li id="req-uppercase">Almeno una lettera maiuscola</li>
                        <li id="req-lowercase">Almeno una lettera minuscola</li>
                        <li id="req-number">Almeno un numero</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Cambia Password
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="login.php" style="color: #188d4d; text-decoration: none;">
                    &larr; Torna al Login
                </a>
            </div>

            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 12px;">
                    Flussu User Management System v<?php echo $v.".".$m; ?><br>
                    &copy; <?php echo date("Y"); ?> Mille Isole SRL
                </p>
            </div>
        </div>
    </div>

    <!-- JavaScript solo per validazione visiva real-time (opzionale, non obbligatorio) -->
    <script>
        // Validazione password in tempo reale per feedback visivo
        const newPasswordInput = document.getElementById('new_password');

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;

                // Lunghezza
                const lengthReq = document.getElementById('req-length');
                if (password.length >= 8) {
                    lengthReq.className = 'valid';
                } else {
                    lengthReq.className = 'invalid';
                }

                // Maiuscola
                const uppercaseReq = document.getElementById('req-uppercase');
                if (/[A-Z]/.test(password)) {
                    uppercaseReq.className = 'valid';
                } else {
                    uppercaseReq.className = 'invalid';
                }

                // Minuscola
                const lowercaseReq = document.getElementById('req-lowercase');
                if (/[a-z]/.test(password)) {
                    lowercaseReq.className = 'valid';
                } else {
                    lowercaseReq.className = 'invalid';
                }

                // Numero
                const numberReq = document.getElementById('req-number');
                if (/[0-9]/.test(password)) {
                    numberReq.className = 'valid';
                } else {
                    numberReq.className = 'invalid';
                }
            });
        }
    </script>
</body>
</html>
