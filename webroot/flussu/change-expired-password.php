<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Change Expired Password Form - Pure PHP Backend Implementation
 * VERSION REL.: 4.5.20251118
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../vendor/autoload.php';

use Flussu\Persons\PasswordRecoveryHelper;
use Flussu\General;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

$error = '';
$success = '';
$passwordChanged = false;
$username = $_GET['username'] ?? $_POST['username'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Compila tutti i campi.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Le nuove password non coincidono.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'La nuova password deve contenere almeno 8 caratteri.';
    } elseif ($oldPassword === $newPassword) {
        $error = 'La nuova password deve essere diversa da quella vecchia.';
    } else {
        // Validate password strength
        $hasLetter = preg_match('/[a-zA-Z]/', $newPassword);
        $hasNumber = preg_match('/[0-9]/', $newPassword);

        if (!$hasLetter || !$hasNumber) {
            $error = 'La password deve contenere almeno una lettera e un numero.';
        } else {
            // Change expired password
            $result = PasswordRecoveryHelper::changeExpiredPassword($username, $oldPassword, $newPassword);

            if ($result['success']) {
                $success = $result['message'];
                $passwordChanged = true;
            } else {
                $error = $result['message'];
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
    <title>Cambia Password - Flussu Server</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 14px;
        }

        .success {
            background-color: #efe;
            color: #2d6a2d;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
            font-size: 14px;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .success-icon {
            text-align: center;
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            padding-left: 10px;
        }

        .password-requirements ul {
            margin-top: 5px;
            padding-left: 20px;
        }

        .password-requirements li {
            margin: 3px 0;
        }

        .divider {
            margin: 15px 0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Flussu</h1>
            <p>Password Scaduta</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($passwordChanged): ?>
            <div class="success-icon">✓</div>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
            </div>

            <a href="/flussu/login.php" class="btn">Vai al Login</a>

        <?php else: ?>
            <div class="warning-box">
                <strong>Password Scaduta</strong><br>
                Per motivi di sicurezza, la tua password è scaduta e deve essere cambiata prima di poter accedere al sistema.
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username o Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        <?php echo empty($username) ? 'autofocus' : ''; ?>
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($username); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="old_password">Vecchia Password</label>
                    <input
                        type="password"
                        id="old_password"
                        name="old_password"
                        required
                        <?php echo !empty($username) ? 'autofocus' : ''; ?>
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group">
                    <label for="new_password">Nuova Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <div class="password-requirements">
                        La nuova password deve:
                        <ul>
                            <li>Contenere almeno 8 caratteri</li>
                            <li>Includere almeno una lettera</li>
                            <li>Includere almeno un numero</li>
                            <li>Essere diversa dalla vecchia password</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Conferma Nuova Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                </div>

                <button type="submit" class="btn">Cambia Password</button>
            </form>
        <?php endif; ?>

        <div class="divider">───────</div>

        <div style="text-align: center; font-size: 12px; color: #999;">
            &copy; <?php echo date('Y'); ?> Flussu Server - Mille Isole SRL
        </div>
    </div>
</body>
</html>
