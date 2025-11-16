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
 *      This is the main entrance to the Flussu Server, a PHP script
 *      to handle all the requests to this server.
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

// Verifica se l'utente è già autenticato
if (isset($_SESSION['flussu_user_id']) && isset($_SESSION['flussu_logged_in']) && $_SESSION['flussu_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Gestione POST - Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validazione base
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        try {
            $user = new User();

            // Autentica l'utente
            $authenticated = $user->authenticate($username, $password);

            if ($authenticated && $user->getId() > 0) {
                // Login riuscito - crea sessione
                $_SESSION['flussu_logged_in'] = true;
                $_SESSION['flussu_user_id'] = $user->getId();
                $_SESSION['flussu_username'] = $user->getUserId();
                $_SESSION['flussu_email'] = $user->getEmail();
                $_SESSION['flussu_name'] = $user->getName();
                $_SESSION['flussu_surname'] = $user->getSurname();
                $_SESSION['flussu_login_time'] = time();

                // Verifica se deve cambiare password
                if ($user->mustChangePassword()) {
                    $_SESSION['flussu_must_change_password'] = true;
                    header("Location: change-password.php");
                    exit;
                }

                // Redirect alla dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Credenziali non valide';
            }
        } catch (Exception $e) {
            General::addLog("[Login Error] " . $e->getMessage());
            $error = 'Errore durante il login. Riprova.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flussu - Login</title>
    <link rel="stylesheet" href="css/flussu-admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <svg width="120" height="40" viewBox="0 0 120 40">
                    <text x="10" y="30" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#188d4d">FLUSSU</text>
                </svg>
            </div>

            <h1 class="login-title">Accedi al sistema</h1>

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

            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="username" class="form-label">Username o Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Inserisci username o email"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autocomplete="username"
                    />
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Inserisci password"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Accedi
                </button>

                <div class="text-center mt-3">
                    <a href="forgot-password.php" style="color: #188d4d; text-decoration: none;">
                        Password dimenticata?
                    </a>
                </div>
            </form>

            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 14px; margin-bottom: 10px;">
                    Non hai un account? <a href="register.php" style="color: #188d4d; text-decoration: none; font-weight: 600;">Registrati</a>
                </p>
            </div>

            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 12px;">
                    Flussu User Management System v<?php echo $v.".".$m; ?><br>
                    &copy; <?php echo date("Y"); ?> Mille Isole SRL
                </p>
            </div>
        </div>
    </div>
</body>
</html>
