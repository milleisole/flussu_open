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
 * UPDATES DATE:     17.09.2025
 * --------------------------------------------------------------------*/

require_once 'inc/includebase.php';
use Flussu\Persons\User;
use Flussu\General;

$error = '';
$success = '';
$showExpiredPasswordForm = false;
$userId = '';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: /flussu/dashboard.php', true, 303);
    die();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Inserisci username/email e password.';
        } else {
            $user = new User();

            if ($user->authenticate($username, $password)) {
                // Check if password is expired
                if ($user->mustChangePassword()) {
                    $showExpiredPasswordForm = true;
                    $userId = $username;
                    $error = 'La tua password è scaduta. Devi cambiarla prima di continuare.';
                } else {
                    // Login successful
                    $_SESSION['user_id'] = $user->getId();
                    $_SESSION['logged_in'] = true;

                    General::log("User " . $user->getId() . " logged in successfully");

                    // Redirect to dashboard or home
                    header('Location: /flussu/dashboard.php', true, 303);
                    die();
                }
            } else {
                $error = 'Credenziali non valide.';
                General::log("Failed login attempt for: " . $username);
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
    <title>Login - Flussu Server</title>
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

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
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
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
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
            color: #3c3;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
            font-size: 14px;
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

        .info-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Flussu <?php echo $v.".".$m; ?></h1>
            <p>Admin Login</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($showExpiredPasswordForm): ?>
            <div class="info-box">
                La tua password è scaduta. Per motivi di sicurezza, devi cambiarla.
            </div>
            <form method="GET" action="change-expired-password.php">
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($userId); ?>">
                <button type="submit" class="btn">Cambia Password</button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="username">Username/Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn">Login</button>
            </form>

            <div class="links">
                <a href="forgot-password.php">Password dimenticata?</a>
            </div>
        <?php endif; ?>

        <div class="divider">───────</div>

        <div style="text-align: center; font-size: 12px; color: #999;">
            <?php
                $V=$v.".".$m.".".$r;
                $hostname = gethostname();
                $srv=$_ENV["server"];
            ?>
            <p>
                Flussu <?php echo $V; ?> - DB: <?php echo $dbv; ?>
                <br><?php echo $srv; ?> on <?php echo $hostname; ?>
            </p>
            <p>&copy; <?php echo date("Y"); ?> Mille Isole SRL</p>
        </div>
    </div>
</body>
</html>

