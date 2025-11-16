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
 *      Password dimenticata - Richiesta reset password
 *
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once 'inc/includebase.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flussu - Password Dimenticata</title>
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

            <h1 class="login-title">Password Dimenticata</h1>
            <p class="text-muted" style="margin-bottom: 30px; text-align: center;">
                Inserisci il tuo username o email per ricevere le istruzioni per il reset della password.
            </p>

            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="emailOrUsername" class="form-label">Username o Email</label>
                    <input
                        type="text"
                        id="emailOrUsername"
                        class="form-control"
                        placeholder="Inserisci username o email"
                        required
                        autocomplete="username"
                    />
                </div>

                <div id="message" class="alert" style="display: none;"></div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Richiedi Reset Password
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

        // Gestione form richiesta reset password
        document.getElementById('forgotPasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailOrUsername = document.getElementById('emailOrUsername').value.trim();
            const messageDiv = document.getElementById('message');
            const submitBtn = e.target.querySelector('button[type="submit"]');

            // Nascondi messaggi precedenti
            passwordUI.hideMessage(messageDiv);

            // Validazione base
            if (!emailOrUsername) {
                passwordUI.showError(messageDiv, 'Inserisci username o email');
                return;
            }

            try {
                // Disabilita pulsante durante l'elaborazione
                passwordUI.disableButton(submitBtn, 'Invio in corso...');

                const result = await passwordAPI.requestPasswordReset(emailOrUsername);

                if (result.result === "OK") {
                    // Mostra messaggio di successo
                    passwordUI.showSuccess(
                        messageDiv,
                        'Se l\'account esiste, riceverai un\'email con le istruzioni per il reset della password. ' +
                        'Controlla la tua casella di posta (e la cartella spam).'
                    );

                    // Reset form
                    e.target.reset();

                    // Reindirizza al login dopo 5 secondi
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 5000);
                } else {
                    // Anche in caso di errore, per sicurezza mostriamo lo stesso messaggio
                    // (non riveliamo se l'utente esiste o no)
                    passwordUI.showSuccess(
                        messageDiv,
                        'Se l\'account esiste, riceverai un\'email con le istruzioni per il reset della password. ' +
                        'Controlla la tua casella di posta (e la cartella spam).'
                    );

                    // Reset form
                    e.target.reset();

                    // Reindirizza al login dopo 5 secondi
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 5000);
                }
            } catch (error) {
                console.error('Forgot password error:', error);
                passwordUI.showError(
                    messageDiv,
                    'Si è verificato un errore. Riprova più tardi.'
                );
            } finally {
                passwordUI.enableButton(submitBtn);
            }
        });
    </script>
</body>
</html>
