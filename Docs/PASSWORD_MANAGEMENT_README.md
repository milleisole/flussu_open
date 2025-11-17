# Sistema di Gestione Password - Flussu

## 📋 Panoramica

Implementazione completa del sistema di gestione password per Flussu, che include:

1. **Cambio Password Obbligatorio**: Per utenti con password scadute o temporanee
2. **Reset Password**: Per utenti che hanno dimenticato la password

## 📁 File Creati/Modificati

### Nuovi File

1. **`src/Flussu/Api/V40/PasswordManager.php`**
   - Classe principale per la gestione password
   - Metodi per reset password e cambio forzato
   - Gestione token temporanei
   - Validazione e scadenza token

2. **`Docs/API_Password_Management.md`**
   - Documentazione completa API
   - Esempi di utilizzo in JavaScript e PHP
   - Guide passo-passo per ogni scenario
   - Note di sicurezza

3. **`Docs/examples/password_management_example.html`**
   - Interfaccia web di test interattiva
   - Form per testare tutte le funzionalità
   - Esempi pratici funzionanti
   - UI moderna e responsive

### File Modificati

1. **`src/Flussu/Api/V40/Conn.php`**
   - Aggiunto import di `PasswordManager`
   - Aggiunti 6 nuovi comandi API:
     - `reqPwdReset` / `reqpwdreset`
     - `verifyResetToken` / `verifyresettoken`
     - `resetPwd` / `resetpwd`
     - `forcePwdChg` / `forcepwdchg`
     - `chkPwdStatus` / `chkpwdstatus`
     - `cleanupTokens` / `cleanuptokens`

## 🚀 Come Usare

### 1. Testare con l'Interfaccia HTML

1. Apri il file `Docs/examples/password_management_example.html` in un browser
2. Configura il server web per servire i file Flussu
3. Prova le varie funzionalità:
   - Verifica status password
   - Cambia password obbligatoria
   - Richiedi reset password
   - Verifica token
   - Reset password con token

### 2. Integrare nelle Applicazioni

#### JavaScript/Frontend

```javascript
// Esempio: Verifica se utente deve cambiare password
const result = await flussuApiCall('chkPwdStatus', { userId: 'testuser' });
if (result.mustChangePassword) {
    // Mostra form cambio password
}
```

#### PHP/Backend

```php
$pwdMgr = new \Flussu\Api\V40\PasswordManager();
$db = new \Flussu\Flussuserver\NC\HandlerNC();
$result = $pwdMgr->requestPasswordReset($db, 'user@example.com');
```

Vedi `Docs/API_Password_Management.md` per esempi completi.

## 🔐 Funzionalità Implementate

### Cambio Password Obbligatorio

✅ Verifica se password deve essere cambiata (`mustChangePassword()`)
✅ Endpoint per controllare status password (`chkPwdStatus`)
✅ Endpoint per cambio password forzato (`forcePwdChg`)
✅ Richiede autenticazione con password corrente
✅ Aggiorna data di scadenza password (+1 anno)

### Reset Password (Password Dimenticata)

✅ Generazione token di reset univoci (UUID v4)
✅ Token temporanei con scadenza (default: 1 ora)
✅ Storage sicuro in database (`t50_otcmd`)
✅ Verifica validità token
✅ Reset password con token (uso singolo)
✅ Pulizia automatica token scaduti
✅ Non richiede autenticazione (usa token temporaneo)

## 📊 Schema Database

Il sistema usa la tabella esistente `t50_otcmd` per memorizzare i token di reset:

```sql
CREATE TABLE `t50_otcmd` (
  `c50_id` int(11) NOT NULL AUTO_INCREMENT,
  `c50_key` varchar(36),           -- Token UUID
  `c50_command` varchar(50) NOT NULL,  -- 'RESET_PASSWORD'
  `c50_uid` int(10) unsigned NOT NULL, -- User ID
  `c50_created` timestamp,         -- Timestamp creazione
  PRIMARY KEY (`c50_id`),
  KEY `ix_Key` (`c50_key`)
)
```

## 🔄 Flussi di Lavoro

### Flusso 1: Cambio Password Obbligatorio

```
1. Login utente
2. Sistema verifica se password scaduta (mustChangePassword)
3. Se scaduta → mostra form cambio password
4. Utente inserisce password corrente + nuova password
5. Sistema valida e aggiorna password
6. Password valida per +1 anno
```

### Flusso 2: Password Dimenticata

```
1. Utente clicca "Password dimenticata"
2. Inserisce email/username
3. Sistema genera token di reset (validità: 1h)
4. Sistema invia email con link contenente token
5. Utente clicca link nell'email
6. Sistema verifica token
7. Utente inserisce nuova password
8. Sistema resetta password e elimina token
```

## ⚙️ Configurazione

### Validità Token

Modifica in `PasswordManager.php`:

```php
const TOKEN_VALIDITY_MINUTES = 60; // Default: 1 ora
```

### URL Reset Password

Modifica il metodo `generateResetLink()` in `PasswordManager.php` per personalizzare l'URL.

### Invio Email ✅ IMPLEMENTATO

Il sistema ora supporta l'invio email reale tramite PHPMailer:

1. **PHPMailer** è già incluso nelle dipendenze del progetto

2. **Configura SMTP** in `/config/.services.json`:
   ```json
   {
     "services": {
       "email": {
         "default": "smtp_provider",
         "smtp_provider": {
           "smtp_host": "smtp.example.com",
           "smtp_port": 587,
           "smtp_auth": 1,
           "smtp_user": "noreply@example.com",
           "smtp_pass": "your_smtp_password",
           "smtp_encrypt": "STARTTLS",
           "from_email": "noreply@example.com",
           "from_name": "Flussu Password Reset"
         }
       }
     }
   }
   ```

3. **Test configurazione** con lo script di test:
   ```bash
   php webroot/flussu/test-email-config.php
   ```

Per dettagli completi, vedi [EMAIL_CONFIGURATION.md](EMAIL_CONFIGURATION.md)

## 🛡️ Sicurezza

### Implementato

✅ Token univoci UUID v4
✅ Scadenza token temporizzati
✅ Uso singolo per token (eliminazione dopo uso)
✅ Password hash con algoritmo custom
✅ Autenticazione OTP per comandi sensibili
✅ Non rivela se email/utente esiste
✅ Logging delle operazioni

### Raccomandazioni

⚠️ **Aggiornare algoritmo hash password**
- Attualmente usa XOR custom (vedi `User.php:_genPwd()`)
- Si raccomanda bcrypt o Argon2 per maggiore sicurezza

⚠️ **Implementare HTTPS**
- Tutte le chiamate API devono usare HTTPS in produzione

⚠️ **Rate Limiting**
- Limitare richieste reset password per email (es. max 3/ora)
- Prevenire brute force su verifica token

⚠️ **Validazione Password**
- Implementare requisiti complessità password lato server
- Minimo 8 caratteri, maiuscole, minuscole, numeri, caratteri speciali

⚠️ **Email Security**
- Non includere token in risposta HTTP (solo via email)
- Usare template email professionali
- Includere link scadenza e istruzioni chiare

## 🧪 Testing

### Test Manuale con HTML

1. Apri `Docs/examples/password_management_example.html`
2. Crea un utente di test:
   ```php
   $usr = new User();
   $usr->registerNew('testuser', 'testpass', 'test@example.com');
   ```
3. Testa tutti i flussi dall'interfaccia

### Test con cURL

```bash
# 1. Richiedi reset password
curl -X POST "http://localhost/api.php?url=flussuconn&C=G" \
  -H "Content-Type: application/json" \
  -d '{"userid":"anonymous","password":"","command":"reqPwdReset"}'

# 2. Usa il token ricevuto
curl -X POST "http://localhost/api.php?url=flussuconn&C=E&K=otp-uuid" \
  -H "Content-Type: application/json" \
  -d '{"emailOrUsername":"test@example.com"}'
```

## 📞 Manutenzione

### Pulizia Token Scaduti

Configura un cron job per eseguire periodicamente:

```bash
# Ogni ora
0 * * * * curl -X POST "http://domain.com/api.php?url=flussuconn" \
  -d '{"C":"G","userid":"admin","password":"pass","command":"cleanupTokens"}'
```

Oppure esegui manualmente:

```php
$pwdMgr = new PasswordManager();
$db = new HandlerNC();
$pwdMgr->cleanupExpiredTokens($db);
```

## 📝 TODO / Miglioramenti Futuri

- [x] Implementare invio email reale ✅ (Implementato con PHPMailer - vedi Docs/EMAIL_CONFIGURATION.md)
- [ ] Aggiungere rate limiting per reset password
- [ ] Implementare validazione complessità password
- [ ] Aggiornare algoritmo hash (bcrypt/Argon2)
- [ ] Aggiungere logging dettagliato (chi, quando, da dove)
- [ ] Implementare 2FA opzionale
- [ ] Storia password (prevenire riuso password recenti)
- [ ] Dashboard admin per gestione utenti e password
- [ ] Notifica email quando password viene cambiata
- [ ] Blocco account dopo N tentativi falliti

## 📚 Riferimenti

- **Documentazione API**: `Docs/API_Password_Management.md`
- **Esempio HTML**: `Docs/examples/password_management_example.html`
- **Classe PasswordManager**: `src/Flussu/Api/V40/PasswordManager.php`
- **API Conn**: `src/Flussu/Api/V40/Conn.php`

## 🤝 Supporto

Per domande o problemi:
- Consulta la documentazione completa in `Docs/API_Password_Management.md`
- Apri un issue su GitHub
- Contatta il team di sviluppo Flussu

---

**Versione**: 4.5.1
**Data**: 16.11.2025
**Autore**: Claude AI per Mille Isole SRL
**Licenza**: Apache License 2.0
