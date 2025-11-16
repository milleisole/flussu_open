/**
 * Flussu Password API Helper
 * Gestione chiamate API per reset e cambio password
 */

class FlussuPasswordAPI {
    constructor() {
        this.apiBase = '/api.php?url=flussuconn';
    }

    /**
     * Funzione helper per chiamate API con sistema OTP
     * @param {string} command - Comando da eseguire
     * @param {object} data - Dati da inviare
     * @param {string} userId - Username per autenticazione (default: "anonymous")
     * @param {string} password - Password per autenticazione (default: "")
     * @returns {Promise<object>} - Risultato dell'API
     */
    async call(command, data, userId = "anonymous", password = "") {
        try {
            // Step 1: Get OTP
            const otpResponse = await fetch(`${this.apiBase}&C=G`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userid: userId,
                    password: password,
                    command: command
                })
            });

            const otpData = await otpResponse.json();
            if (otpData.result !== "OK") {
                throw new Error(otpData.message || "Errore durante l'autenticazione");
            }

            // Step 2: Execute command
            const cmdResponse = await fetch(`${this.apiBase}&C=E&K=${otpData.key}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            return await cmdResponse.json();
        } catch (error) {
            return {
                result: "ERROR",
                message: error.message
            };
        }
    }

    /**
     * Verifica status password utente
     * @param {string} userId - Username
     * @returns {Promise<object>} - {result, mustChangePassword, lastChanged}
     */
    async checkPasswordStatus(userId) {
        return await this.call('chkPwdStatus', { userId });
    }

    /**
     * Cambio password forzato (quando password è scaduta)
     * @param {string} userId - Username
     * @param {string} currentPassword - Password corrente
     * @param {string} newPassword - Nuova password
     * @returns {Promise<object>} - {result, message}
     */
    async forcePasswordChange(userId, currentPassword, newPassword) {
        return await this.call(
            'forcePwdChg',
            {
                userId: userId,
                currentPassword: currentPassword,
                newPassword: newPassword
            },
            userId,
            currentPassword
        );
    }

    /**
     * Richiedi reset password (password dimenticata)
     * @param {string} emailOrUsername - Email o username
     * @returns {Promise<object>} - {result, message, token (solo per test)}
     */
    async requestPasswordReset(emailOrUsername) {
        return await this.call('reqPwdReset', { emailOrUsername });
    }

    /**
     * Verifica validità token di reset
     * @param {string} token - Token di reset
     * @returns {Promise<object>} - {result, valid, message}
     */
    async verifyResetToken(token) {
        return await this.call('verifyResetToken', { token });
    }

    /**
     * Reset password con token
     * @param {string} token - Token di reset
     * @param {string} newPassword - Nuova password
     * @returns {Promise<object>} - {result, message}
     */
    async resetPassword(token, newPassword) {
        return await this.call('resetPwd', {
            token: token,
            newPassword: newPassword
        });
    }

    /**
     * Registra nuovo utente
     * @param {string} username - Username
     * @param {string} password - Password
     * @param {string} email - Email
     * @param {string} name - Nome
     * @param {string} surname - Cognome
     * @returns {Promise<object>} - {result, message}
     */
    async registerUser(username, password, email, name = '', surname = '') {
        return await this.call('regUser', {
            username: username,
            password: password,
            email: email,
            name: name,
            surname: surname
        });
    }

    /**
     * Verifica se email esiste già
     * @param {string} email - Email da verificare
     * @returns {Promise<object>} - {result, exists}
     */
    async checkEmailExists(email) {
        return await this.call('chkEmail', { email });
    }
}

/**
 * UI Helper per messaggi e validazione
 */
class FlussuPasswordUI {
    /**
     * Mostra messaggio di errore
     * @param {HTMLElement|string} element - Elemento o ID elemento
     * @param {string} message - Messaggio
     */
    showError(element, message) {
        const el = typeof element === 'string' ? document.getElementById(element) : element;
        if (el) {
            el.textContent = message;
            el.className = 'alert alert-danger';
            el.style.display = 'block';
        }
    }

    /**
     * Mostra messaggio di successo
     * @param {HTMLElement|string} element - Elemento o ID elemento
     * @param {string} message - Messaggio
     */
    showSuccess(element, message) {
        const el = typeof element === 'string' ? document.getElementById(element) : element;
        if (el) {
            el.textContent = message;
            el.className = 'alert alert-success';
            el.style.display = 'block';
        }
    }

    /**
     * Mostra messaggio informativo
     * @param {HTMLElement|string} element - Elemento o ID elemento
     * @param {string} message - Messaggio
     */
    showInfo(element, message) {
        const el = typeof element === 'string' ? document.getElementById(element) : element;
        if (el) {
            el.textContent = message;
            el.className = 'alert alert-info';
            el.style.display = 'block';
        }
    }

    /**
     * Nascondi messaggio
     * @param {HTMLElement|string} element - Elemento o ID elemento
     */
    hideMessage(element) {
        const el = typeof element === 'string' ? document.getElementById(element) : element;
        if (el) {
            el.style.display = 'none';
        }
    }

    /**
     * Valida password
     * @param {string} password - Password da validare
     * @returns {object} - {valid: boolean, message: string}
     */
    validatePassword(password) {
        if (!password || password.length < 8) {
            return {
                valid: false,
                message: 'La password deve essere di almeno 8 caratteri'
            };
        }

        if (!/[A-Z]/.test(password)) {
            return {
                valid: false,
                message: 'La password deve contenere almeno una lettera maiuscola'
            };
        }

        if (!/[a-z]/.test(password)) {
            return {
                valid: false,
                message: 'La password deve contenere almeno una lettera minuscola'
            };
        }

        if (!/[0-9]/.test(password)) {
            return {
                valid: false,
                message: 'La password deve contenere almeno un numero'
            };
        }

        return { valid: true, message: '' };
    }

    /**
     * Valida email
     * @param {string} email - Email da validare
     * @returns {boolean}
     */
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Disabilita pulsante con loading
     * @param {HTMLElement|string} button - Pulsante o ID pulsante
     * @param {string} loadingText - Testo durante loading
     */
    disableButton(button, loadingText = 'Caricamento...') {
        const btn = typeof button === 'string' ? document.getElementById(button) : button;
        if (btn) {
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = loadingText;
        }
    }

    /**
     * Abilita pulsante
     * @param {HTMLElement|string} button - Pulsante o ID pulsante
     */
    enableButton(button) {
        const btn = typeof button === 'string' ? document.getElementById(button) : button;
        if (btn) {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.textContent = btn.dataset.originalText;
            }
        }
    }
}

// Export per utilizzo globale
if (typeof window !== 'undefined') {
    window.FlussuPasswordAPI = FlussuPasswordAPI;
    window.FlussuPasswordUI = FlussuPasswordUI;
}
