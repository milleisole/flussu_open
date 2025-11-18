<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Reset Password Form - Pure PHP Backend Implementation
 * VERSION REL.: 4.5.20251118
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../vendor/autoload.php';

use Flussu\Persons\PasswordRecoveryHelper;
use Flussu\General;

require_once 'inc/includebase.php';
$error = '';
$success = '';
$tokenValid = false;
$passwordReset = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// Validate token on page load
if (!empty($token)) {
    $validation = PasswordRecoveryHelper::validateToken($token);
    $tokenValid = $validation['valid'];

    if (!$tokenValid) {
        $error = $validation['message'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Compila tutti i campi.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Le password non coincidono.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'La password deve contenere almeno 8 caratteri.';
    } else {
        // Validate password strength
        $hasLetter = preg_match('/[a-zA-Z]/', $newPassword);
        $hasNumber = preg_match('/[0-9]/', $newPassword);

        if (!$hasLetter || !$hasNumber) {
            $error = 'La password deve contenere almeno una lettera e un numero.';
        } else {
            // Reset password
            $result = PasswordRecoveryHelper::resetPassword($token, $newPassword);

            if ($result['success']) {
                $success = $result['message'];
                $passwordReset = true;
            } else {
                $error = $result['message'];
                $tokenValid = false; // Token might have been used or expired
            }
        }
    }
}

// No token provided
if (empty($token)) {
    $error = 'Link di recupero non valido. Richiedi un nuovo link.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimposta Password - Flussu Server</title>
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

        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

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

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            color: #1565c0;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
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

        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
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
            <p>Reimposta Password</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($passwordReset): ?>
            <div class="success-icon">✓</div>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
            </div>

            <div class="info-box">
                La tua password è stata reimpostata con successo. Ora puoi effettuare il login con la nuova password.
            </div>

            <a href="/flussu/login.php" class="btn">Vai al Login</a>

        <?php elseif ($tokenValid): ?>
            <div class="warning-box">
                <strong>Attenzione:</strong> Questo link è valido per 1 ora e può essere utilizzato una sola volta.
            </div>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label for="new_password">Nuova Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        autofocus
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <div class="password-requirements">
                        La password deve:
                        <ul>
                            <li>Contenere almeno 8 caratteri</li>
                            <li>Includere almeno una lettera</li>
                            <li>Includere almeno un numero</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Conferma Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                </div>

                <button type="submit" class="btn">Reimposta Password</button>
            </form>

        <?php else: ?>
            <div class="warning-box">
                <strong>Link non valido o scaduto</strong><br>
                Il link di recupero password che hai utilizzato non è valido o è scaduto.
            </div>

            <div class="links">
                <a href="forgot-password.php">Richiedi un nuovo link</a>
                <br><br>
                <a href="/flussu/login.php">← Torna al Login</a>
            </div>
        <?php endif; ?>

        <div class="divider">───────</div>

        <div style="text-align: center; font-size: 12px; color: #999;">
            &copy; <?php echo date('Y'); ?> Flussu Server - Mille Isole SRL
        </div>
    </div>
</body>
</html>
