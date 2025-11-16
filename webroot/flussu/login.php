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

// Carica configurazione (con fallback se non disponibile)
$v = "4";
$m = "5";
$r = "1";

try {
    // Prova a caricare vendor/autoload se esiste
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        use Flussu\General;
        use Flussu\Config;

        // Carica .env se esiste
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->safeLoad();
        }

        // Helper config se non esiste
        if (!function_exists('config')) {
            function config(string $key, $default = null)
            {
                try {
                    return Config::init()->get($key, $default);
                } catch (Exception $e) {
                    return $default;
                }
            }
        }

        // Prova a leggere versione da config
        $version = config("flussu.version", "4.5");
        $release = config("flussu.release", "1");
        $FVP = explode(".", $version . "." . $release);
        $v = $FVP[0];
        $m = $FVP[1];
        $r = $FVP[2] ?? "1";
    } elseif (file_exists(__DIR__ . '/../../.env')) {
        // Fallback: leggi direttamente da .env
        $envContent = parse_ini_file(__DIR__ . '/../../.env');
        $v = $envContent['major'] ?? "4";
        $m = $envContent['minor'] ?? "5";
        $r = $envContent['release'] ?? "1";
    }
} catch (Exception $e) {
    // Usa valori di default se qualcosa va storto
    error_log("Login page config error: " . $e->getMessage());
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

            <form id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username o Email</label>
                    <input
                        type="text"
                        id="username"
                        class="form-control"
                        placeholder="Inserisci username o email"
                        required
                        autocomplete="username"
                    />
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        class="form-control"
                        placeholder="Inserisci password"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <div id="loginError" class="alert alert-danger" style="display: none;"></div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Accedi
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 12px;">
                    Flussu User Management System v<?php echo $v.".".$m; ?><br>
                    &copy; <?php echo date("Y"); ?> Mille Isole SRL
                </p>
            </div>
        </div>
    </div>

    <script src="js/flussu-api.js"></script>
    <script>
        const api = new FlussuAPI();

        // Verifica se già autenticato
        if (api.isAuthenticated()) {
            window.location.href = 'dashboard.html';
        }

        // Gestione form login
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('loginError');

            // Nascondi errori precedenti
            errorDiv.style.display = 'none';

            // Validazione base
            if (!username || !password) {
                errorDiv.textContent = 'Inserisci username e password';
                errorDiv.style.display = 'block';
                return;
            }

            try {
                // Disabilita pulsante durante il login
                const submitBtn = e.target.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Accesso in corso...';

                const result = await api.login(username, password);

                if (result.success) {
                    // Redirect alla dashboard
                    window.location.href = 'dashboard.html';
                } else {
                    errorDiv.textContent = result.message || 'Credenziali non valide';
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Accedi';
                }
            } catch (error) {
                console.error('Login error:', error);
                errorDiv.textContent = 'Errore durante il login. Riprova.';
                errorDiv.style.display = 'block';

                const submitBtn = e.target.querySelector('button[type="submit"]');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Accedi';
            }
        });

        // Enter per submit
        document.getElementById('password').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
