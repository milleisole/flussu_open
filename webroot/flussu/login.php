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

    <script src="js/flussu-api.js"></script>
    <script src="js/flussu-password-api.js"></script>
    <script>
        const api = new FlussuAPI();
        const passwordAPI = new FlussuPasswordAPI();

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
                    // Verifica se l'utente deve cambiare password
                    try {
                        const passwordStatus = await passwordAPI.checkPasswordStatus(username);

                        if (passwordStatus.result === "OK" && passwordStatus.mustChangePassword) {
                            // Redirect a change-password.php
                            window.location.href = `change-password.php?username=${encodeURIComponent(username)}`;
                            return;
                        }
                    } catch (pwdError) {
                        console.warn('Could not check password status:', pwdError);
                        // In caso di errore nel controllo password, procedi comunque con il login
                    }

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
