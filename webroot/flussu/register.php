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
 *      Registrazione Nuovo Utente
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
    <title>Flussu - Registrazione</title>
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box" style="max-width: 600px;">
            <div class="login-logo">
                <svg width="120" height="40" viewBox="0 0 120 40">
                    <text x="10" y="30" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#188d4d">FLUSSU</text>
                </svg>
            </div>

            <h1 class="login-title">Registrazione</h1>
            <p class="text-muted" style="margin-bottom: 30px; text-align: center;">
                Crea il tuo account Flussu
            </p>

            <form id="registerForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username *</label>
                    <input
                        type="text"
                        id="username"
                        class="form-control"
                        placeholder="Scegli un username"
                        required
                        autocomplete="username"
                        minlength="3"
                    />
                    <small class="text-muted">Minimo 3 caratteri, solo lettere, numeri e underscore</small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input
                        type="email"
                        id="email"
                        class="form-control"
                        placeholder="indirizzo@email.com"
                        required
                        autocomplete="email"
                    />
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Nome</label>
                        <input
                            type="text"
                            id="name"
                            class="form-control"
                            placeholder="Nome"
                            autocomplete="given-name"
                        />
                    </div>

                    <div class="form-group">
                        <label for="surname" class="form-label">Cognome</label>
                        <input
                            type="text"
                            id="surname"
                            class="form-control"
                            placeholder="Cognome"
                            autocomplete="family-name"
                        />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input
                        type="password"
                        id="password"
                        class="form-control"
                        placeholder="Crea una password sicura"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Conferma Password *</label>
                    <input
                        type="password"
                        id="confirmPassword"
                        class="form-control"
                        placeholder="Conferma la password"
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
                    Registrati
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 14px;">
                    Hai già un account? <a href="login.php" style="color: #188d4d; text-decoration: none; font-weight: 600;">Accedi</a>
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

    <script src="js/flussu-password-api.js"></script>
    <script>
        const passwordAPI = new FlussuPasswordAPI();
        const passwordUI = new FlussuPasswordUI();

        const messageDiv = document.getElementById('message');
        const passwordInput = document.getElementById('password');
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');

        // Validazione username in tempo reale
        usernameInput.addEventListener('input', () => {
            const username = usernameInput.value;
            // Rimuovi caratteri non validi
            usernameInput.value = username.replace(/[^a-zA-Z0-9_]/g, '');
        });

        // Validazione password in tempo reale
        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;

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

        // Gestione form registrazione
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = usernameInput.value.trim();
            const email = emailInput.value.trim();
            const name = document.getElementById('name').value.trim();
            const surname = document.getElementById('surname').value.trim();
            const password = passwordInput.value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');

            // Nascondi messaggi precedenti
            passwordUI.hideMessage(messageDiv);

            // Validazione username
            if (username.length < 3) {
                passwordUI.showError(messageDiv, 'Lo username deve essere di almeno 3 caratteri');
                return;
            }

            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                passwordUI.showError(messageDiv, 'Lo username può contenere solo lettere, numeri e underscore');
                return;
            }

            // Validazione email
            if (!passwordUI.validateEmail(email)) {
                passwordUI.showError(messageDiv, 'Inserisci un indirizzo email valido');
                return;
            }

            // Validazione password
            const validation = passwordUI.validatePassword(password);
            if (!validation.valid) {
                passwordUI.showError(messageDiv, validation.message);
                return;
            }

            // Verifica corrispondenza password
            if (password !== confirmPassword) {
                passwordUI.showError(messageDiv, 'Le password non corrispondono');
                return;
            }

            try {
                // Disabilita pulsante durante l'elaborazione
                passwordUI.disableButton(submitBtn, 'Registrazione in corso...');

                // Prima verifica se l'email esiste già
                const emailCheck = await passwordAPI.checkEmailExists(email);
                if (emailCheck.result === "OK" && emailCheck.exists) {
                    passwordUI.showError(messageDiv, 'Questa email è già registrata');
                    passwordUI.enableButton(submitBtn);
                    return;
                }

                // Registra utente
                const result = await passwordAPI.registerUser(
                    username,
                    password,
                    email,
                    name,
                    surname
                );

                if (result.result === "OK") {
                    // Mostra messaggio di successo
                    passwordUI.showSuccess(
                        messageDiv,
                        'Registrazione completata con successo! Reindirizzamento al login...'
                    );

                    // Reindirizza al login dopo 2 secondi
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    passwordUI.showError(
                        messageDiv,
                        result.message || 'Errore durante la registrazione. Lo username potrebbe essere già in uso.'
                    );
                    passwordUI.enableButton(submitBtn);
                }
            } catch (error) {
                console.error('Registration error:', error);
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
