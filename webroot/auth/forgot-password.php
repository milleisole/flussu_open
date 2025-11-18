<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Forgot Password Form - Pure PHP Backend Implementation
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
$emailSent = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userIdentifier = trim($_POST['user_identifier'] ?? '');

    if (empty($userIdentifier)) {
        $error = 'Inserisci il tuo username o indirizzo email.';
    } else {
        // Request password recovery
        $result = PasswordRecoveryHelper::requestPasswordRecovery($userIdentifier);

        if ($result['success']) {
            $success = $result['message'];
            $emailSent = true;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Dimenticata - Flussu Server</title>
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
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
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
            font-size: 14px;
            line-height: 1.6;
        }

        .success-icon {
            text-align: center;
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 15px;
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
            <p>Recupero Password</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($emailSent): ?>
            <div class="success-icon">✓</div>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
            </div>

            <div class="info-box">
                <strong>Controlla la tua email</strong><br>
                Abbiamo inviato le istruzioni per reimpostare la password all'indirizzo email associato al tuo account.
                <br><br>
                <strong>Nota:</strong> Il link sarà valido per 1 ora. Se non ricevi l'email entro qualche minuto, controlla la cartella spam.
            </div>

            <a href="login.php" class="btn">Torna al Login</a>
        <?php else: ?>
            <div class="info-box">
                <strong>Hai dimenticato la password?</strong><br>
                Inserisci il tuo username o indirizzo email e ti invieremo le istruzioni per reimpostarla.
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_identifier">Username o Email</label>
                    <input
                        type="text"
                        id="user_identifier"
                        name="user_identifier"
                        required
                        autofocus
                        placeholder="mario.rossi o mario@example.com"
                        value="<?php echo htmlspecialchars($_POST['user_identifier'] ?? ''); ?>"
                    >
                </div>

                <button type="submit" class="btn">Invia Link di Recupero</button>
            </form>

            <div class="links">
                <a href="login.php">← Torna al Login</a>
            </div>
        <?php endif; ?>

        <div class="divider">───────</div>

        <div style="text-align: center; font-size: 12px; color: #999;">
            &copy; <?php echo date('Y'); ?> Flussu Server - Mille Isole SRL
        </div>
    </div>
</body>
</html>
