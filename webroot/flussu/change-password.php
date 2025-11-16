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

require_once 'inc/includebase.php';

// Ottieni username da URL o session
$username = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '';
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

            <form id="changePasswordForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        class="form-control"
                        placeholder="Inserisci username"
                        value="<?php echo $username; ?>"
                        required
                        autocomplete="username"
                    />
                </div>

                <div class="form-group">
                    <label for="currentPassword" class="form-label">Password Corrente</label>
                    <input
                        type="password"
                        id="currentPassword"
                        class="form-control"
                        placeholder="Inserisci password corrente"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <div class="form-group">
                    <label for="newPassword" class="form-label">Nuova Password</label>
                    <input
                        type="password"
                        id="newPassword"
                        class="form-control"
                        placeholder="Inserisci nuova password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Conferma Nuova Password</label>
                    <input
                        type="password"
                        id="confirmPassword"
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

                <div id="message" class="alert" style="display: none;"></div>

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

    <script src="js/flussu-password-api.js"></script>
    <script>
        const passwordAPI = new FlussuPasswordAPI();
        const passwordUI = new FlussuPasswordUI();

        const messageDiv = document.getElementById('message');
        const newPasswordInput = document.getElementById('newPassword');

        // Validazione password in tempo reale
        newPasswordInput.addEventListener('input', () => {
            const password = newPasswordInput.value;

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

        // Gestione form cambio password
        document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = newPasswordInput.value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');

            // Nascondi messaggi precedenti
            passwordUI.hideMessage(messageDiv);

            // Validazione base
            if (!username || !currentPassword) {
                passwordUI.showError(messageDiv, 'Inserisci username e password corrente');
                return;
            }

            // Validazione password
            const validation = passwordUI.validatePassword(newPassword);
            if (!validation.valid) {
                passwordUI.showError(messageDiv, validation.message);
                return;
            }

            // Verifica corrispondenza password
            if (newPassword !== confirmPassword) {
                passwordUI.showError(messageDiv, 'Le nuove password non corrispondono');
                return;
            }

            // Verifica che la nuova password sia diversa dalla corrente
            if (currentPassword === newPassword) {
                passwordUI.showError(messageDiv, 'La nuova password deve essere diversa da quella corrente');
                return;
            }

            try {
                // Disabilita pulsante durante l'elaborazione
                passwordUI.disableButton(submitBtn, 'Cambio in corso...');

                const result = await passwordAPI.forcePasswordChange(
                    username,
                    currentPassword,
                    newPassword
                );

                if (result.result === "OK") {
                    // Mostra messaggio di successo
                    passwordUI.showSuccess(
                        messageDiv,
                        'Password cambiata con successo! Reindirizzamento al login...'
                    );

                    // Reindirizza al login dopo 2 secondi
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    passwordUI.showError(
                        messageDiv,
                        result.message || 'Errore durante il cambio password. Verifica che la password corrente sia corretta.'
                    );
                    passwordUI.enableButton(submitBtn);
                }
            } catch (error) {
                console.error('Change password error:', error);
                passwordUI.showError(
                    messageDiv,
                    'Si è verificato un errore. Riprova più tardi.'
                );
                passwordUI.enableButton(submitBtn);
            }
        });
    </script>
</body>
</html>
