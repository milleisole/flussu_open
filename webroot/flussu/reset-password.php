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
 *      Reset Password - Imposta nuova password con token
 *
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once 'inc/includebase.php';

// Ottieni token da URL
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flussu - Reset Password</title>
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

            <h1 class="login-title">Reset Password</h1>
            <p class="text-muted" style="margin-bottom: 30px; text-align: center;">
                Inserisci la tua nuova password.
            </p>

            <div id="tokenVerificationMessage" class="alert alert-info" style="margin-bottom: 20px;">
                Verifica del token in corso...
            </div>

            <form id="resetPasswordForm" style="display: none;">
                <input type="hidden" id="token" value="<?php echo $token; ?>">

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
                    <label for="confirmPassword" class="form-label">Conferma Password</label>
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
                    Imposta Nuova Password
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

        const token = document.getElementById('token').value;
        const tokenVerificationDiv = document.getElementById('tokenVerificationMessage');
        const resetForm = document.getElementById('resetPasswordForm');
        const messageDiv = document.getElementById('message');
        const newPasswordInput = document.getElementById('newPassword');

        // Verifica token al caricamento della pagina
        (async () => {
            if (!token) {
                passwordUI.showError(tokenVerificationDiv,
                    'Token mancante. Utilizza il link ricevuto via email.');
                return;
            }

            try {
                const result = await passwordAPI.verifyResetToken(token);

                if (result.result === "OK" && result.valid) {
                    // Token valido, mostra form
                    tokenVerificationDiv.style.display = 'none';
                    resetForm.style.display = 'block';
                } else {
                    // Token non valido o scaduto
                    passwordUI.showError(tokenVerificationDiv,
                        'Il link per il reset della password non è valido o è scaduto. ' +
                        'Richiedi un nuovo reset password.');

                    // Mostra link per nuova richiesta
                    setTimeout(() => {
                        window.location.href = 'forgot-password.php';
                    }, 5000);
                }
            } catch (error) {
                console.error('Token verification error:', error);
                passwordUI.showError(tokenVerificationDiv,
                    'Errore durante la verifica del token. Riprova più tardi.');
            }
        })();

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

        // Gestione form reset password
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const newPassword = newPasswordInput.value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');

            // Nascondi messaggi precedenti
            passwordUI.hideMessage(messageDiv);

            // Validazione password
            const validation = passwordUI.validatePassword(newPassword);
            if (!validation.valid) {
                passwordUI.showError(messageDiv, validation.message);
                return;
            }

            // Verifica corrispondenza password
            if (newPassword !== confirmPassword) {
                passwordUI.showError(messageDiv, 'Le password non corrispondono');
                return;
            }

            try {
                // Disabilita pulsante durante l'elaborazione
                passwordUI.disableButton(submitBtn, 'Reset in corso...');

                const result = await passwordAPI.resetPassword(token, newPassword);

                if (result.result === "OK") {
                    // Mostra messaggio di successo
                    passwordUI.showSuccess(
                        messageDiv,
                        'Password reimpostata con successo! Reindirizzamento al login...'
                    );

                    // Reindirizza al login dopo 2 secondi
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    passwordUI.showError(
                        messageDiv,
                        result.message || 'Errore durante il reset della password'
                    );
                    passwordUI.enableButton(submitBtn);
                }
            } catch (error) {
                console.error('Reset password error:', error);
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
