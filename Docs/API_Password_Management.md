# API Password Management - Documentazione

Questa documentazione descrive l'utilizzo delle API per la gestione delle password in Flussu, inclusi il cambio password obbligatorio e il reset password per password dimenticate.

## Indice

1. [Panoramica](#panoramica)
2. [Flusso di Autenticazione OTP](#flusso-di-autenticazione-otp)
3. [Cambio Password Obbligatorio](#cambio-password-obbligatorio)
4. [Reset Password (Password Dimenticata)](#reset-password-password-dimenticata)
5. [Comandi API Disponibili](#comandi-api-disponibili)
6. [Esempi di Utilizzo](#esempi-di-utilizzo)

---

## Panoramica

Il sistema di gestione password di Flussu supporta due scenari principali:

### 1. Cambio Password Obbligatorio
Quando un utente **DEVE** cambiare la password perché:
- La password è scaduta (oltre 1 anno)
- La password è temporanea (impostata da un amministratore)
- È il primo accesso

### 2. Reset Password per Password Dimenticata
Quando un utente ha dimenticato la password e necessita di reimpostarla tramite un token inviato via email.

---

## Flusso di Autenticazione OTP

Tutte le chiamate API utilizzano un sistema di autenticazione a due passaggi basato su OTP (One-Time Password):

### Passo 1: Richiedere un OTP

**Endpoint:** `/flussuconn?C=G`

**Metodo:** POST

**Body JSON:**
```json
{
  "userid": "username o email",
  "password": "password_utente",
  "command": "nome_comando"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "key": "uuid-otp-generato"
}
```

### Passo 2: Eseguire il Comando con OTP

**Endpoint:** `/flussuconn?C=E&K=uuid-otp-generato`

**Metodo:** POST

**Body JSON:** (dipende dal comando)

---

## Cambio Password Obbligatorio

Quando un utente deve cambiare la password (perché scaduta o temporanea), segui questo flusso:

### 1. Verificare se la Password Deve Essere Cambiata

#### Passo 1: Ottenere OTP
```bash
curl -X POST https://your-domain.com/api.php?url=flussuconn&C=G \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "testuser",
    "password": "current_password",
    "command": "chkPwdStatus"
  }'
```

**Risposta:**
```json
{
  "result": "OK",
  "key": "550e8400-e29b-41d4-a716-446655440000"
}
```

#### Passo 2: Eseguire il Comando
```bash
curl -X POST "https://your-domain.com/api.php?url=flussuconn&C=E&K=550e8400-e29b-41d4-a716-446655440000" \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "testuser"
  }'
```

**Risposta:**
```json
{
  "result": "OK",
  "userId": "testuser",
  "email": "test@example.com",
  "mustChangePassword": true,
  "hasPassword": true,
  "passwordChangeDate": "2024-01-15 10:30:00",
  "message": "Password must be changed"
}
```

### 2. Cambiare la Password (Forzato)

#### Passo 1: Ottenere OTP
```bash
curl -X POST https://your-domain.com/api.php?url=flussuconn&C=G \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "testuser",
    "password": "current_password",
    "command": "forcePwdChg"
  }'
```

#### Passo 2: Eseguire il Cambio Password
```bash
curl -X POST "https://your-domain.com/api.php?url=flussuconn&C=E&K=otp-uuid" \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "testuser",
    "currentPassword": "old_password",
    "newPassword": "new_secure_password123"
  }'
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password has been changed successfully",
  "userId": "testuser"
}
```

---

## Reset Password (Password Dimenticata)

Quando un utente ha dimenticato la password, segui questo flusso:

### 1. Richiedere il Reset Password

**NOTA:** Questo comando NON richiede autenticazione (non serve password corrente)

#### Passo 1: Ottenere OTP per comando pubblico
```bash
curl -X POST https://your-domain.com/api.php?url=flussuconn&C=G \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "anonymous",
    "password": "",
    "command": "reqPwdReset"
  }'
```

#### Passo 2: Richiedere Reset
```bash
curl -X POST "https://your-domain.com/api.php?url=flussuconn&C=E&K=otp-uuid" \
  -H "Content-Type: application/json" \
  -d '{
    "emailOrUsername": "user@example.com"
  }'
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password reset token generated",
  "token": "reset-token-uuid",
  "resetLink": "http://your-domain.com/reset-password?token=reset-token-uuid",
  "email": "user@example.com",
  "expiresInMinutes": 60
}
```

**IMPORTANTE:** In produzione, il `token` e il `resetLink` dovrebbero essere inviati solo via email all'utente, non nella risposta HTTP.

### 2. Verificare il Token di Reset (Opzionale)

Utile per verificare se un token è ancora valido prima di mostrare il form di reset.

#### Passo 1: Ottenere OTP
```bash
curl -X POST https://your-domain.com/api.php?url=flussuconn&C=G \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "anonymous",
    "password": "",
    "command": "verifyResetToken"
  }'
```

#### Passo 2: Verificare Token
```bash
curl -X POST "https://your-domain.com/api.php?url=flussuconn&C=E&K=otp-uuid" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "reset-token-uuid"
  }'
```

**Risposta (Token valido):**
```json
{
  "result": "OK",
  "message": "Token is valid",
  "userId": "testuser",
  "email": "user@example.com",
  "expiresIn": "3456 seconds"
}
```

**Risposta (Token scaduto):**
```json
{
  "result": "ERROR",
  "message": "Token has expired"
}
```

### 3. Impostare Nuova Password con Token

#### Passo 1: Ottenere OTP
```bash
curl -X POST https://your-domain.com/api.php?url=flussuconn&C=G \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "anonymous",
    "password": "",
    "command": "resetPwd"
  }'
```

#### Passo 2: Reset Password
```bash
curl -X POST "https://your-domain.com/api.php?url=flussuconn&C=E&K=otp-uuid" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "reset-token-uuid",
    "newPassword": "new_secure_password123"
  }'
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password has been reset successfully"
}
```

---

## Comandi API Disponibili

### 1. `chkPwdStatus` / `chkpwdstatus`
**Descrizione:** Verifica se un utente deve cambiare la password

**Dati richiesti:**
```json
{
  "userId": "username"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "userId": "testuser",
  "email": "test@example.com",
  "mustChangePassword": true|false,
  "hasPassword": true|false,
  "passwordChangeDate": "2024-01-15 10:30:00",
  "message": "Password must be changed"
}
```

---

### 2. `forcePwdChg` / `forcepwdchg`
**Descrizione:** Cambia la password quando l'utente DEVE cambiarla (password scaduta/temporanea)

**Dati richiesti:**
```json
{
  "userId": "username",
  "currentPassword": "old_password",
  "newPassword": "new_password"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password has been changed successfully",
  "userId": "testuser"
}
```

---

### 3. `reqPwdReset` / `reqpwdreset`
**Descrizione:** Richiede un reset password (genera token)

**Autenticazione:** NON richiesta

**Dati richiesti:**
```json
{
  "emailOrUsername": "user@example.com"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password reset token generated",
  "token": "uuid-token",
  "resetLink": "http://domain.com/reset-password?token=uuid",
  "email": "user@example.com",
  "expiresInMinutes": 60
}
```

---

### 4. `verifyResetToken` / `verifyresettoken`
**Descrizione:** Verifica se un token di reset è valido

**Autenticazione:** NON richiesta

**Dati richiesti:**
```json
{
  "token": "uuid-token"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Token is valid",
  "userId": "testuser",
  "email": "user@example.com",
  "expiresIn": "3456 seconds"
}
```

---

### 5. `resetPwd` / `resetpwd`
**Descrizione:** Imposta una nuova password usando un token di reset

**Autenticazione:** NON richiesta (usa token)

**Dati richiesti:**
```json
{
  "token": "uuid-token",
  "newPassword": "new_password"
}
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Password has been reset successfully"
}
```

---

### 6. `cleanupTokens` / `cleanuptokens`
**Descrizione:** Pulisce i token di reset scaduti (manutenzione)

**Dati richiesti:**
```json
{}
```

**Risposta:**
```json
{
  "result": "OK",
  "message": "Expired tokens cleaned up"
}
```

---

## Esempi di Utilizzo

### Esempio JavaScript (Frontend)

```javascript
// Funzione helper per chiamate API con OTP
async function flussuApiCall(command, data, userId = "anonymous", password = "") {
  // Step 1: Get OTP
  const otpResponse = await fetch('/api.php?url=flussuconn&C=G', {
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
    throw new Error("Failed to get OTP: " + otpData.message);
  }

  // Step 2: Execute command
  const cmdResponse = await fetch(`/api.php?url=flussuconn&C=E&K=${otpData.key}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  return await cmdResponse.json();
}

// 1. Verificare se password deve essere cambiata
async function checkPasswordStatus(username, password) {
  const result = await flussuApiCall(
    'chkPwdStatus',
    { userId: username },
    username,
    password
  );

  if (result.mustChangePassword) {
    console.log("L'utente deve cambiare la password!");
    // Mostra form cambio password
  }

  return result;
}

// 2. Cambiare password (forzato)
async function forcePasswordChange(username, currentPassword, newPassword) {
  const result = await flussuApiCall(
    'forcePwdChg',
    {
      userId: username,
      currentPassword: currentPassword,
      newPassword: newPassword
    },
    username,
    currentPassword
  );

  if (result.result === "OK") {
    console.log("Password cambiata con successo!");
  }

  return result;
}

// 3. Richiedere reset password
async function requestPasswordReset(emailOrUsername) {
  const result = await flussuApiCall(
    'reqPwdReset',
    { emailOrUsername: emailOrUsername }
  );

  if (result.result === "OK") {
    console.log("Token di reset generato:", result.token);
    console.log("Link di reset:", result.resetLink);
    // In produzione, il token viene inviato via email
  }

  return result;
}

// 4. Verificare token di reset
async function verifyResetToken(token) {
  const result = await flussuApiCall(
    'verifyResetToken',
    { token: token }
  );

  return result.result === "OK";
}

// 5. Reset password con token
async function resetPasswordWithToken(token, newPassword) {
  const result = await flussuApiCall(
    'resetPwd',
    {
      token: token,
      newPassword: newPassword
    }
  );

  if (result.result === "OK") {
    console.log("Password resettata con successo!");
  }

  return result;
}

// Esempio di flusso completo per password dimenticata
async function forgotPasswordFlow() {
  // 1. Utente inserisce email
  const email = "user@example.com";

  // 2. Richiedi reset
  const resetRequest = await requestPasswordReset(email);
  console.log("Email inviata con link di reset");

  // 3. Utente clicca su link nell'email con token
  const token = resetRequest.token; // In produzione, questo viene dall'URL

  // 4. Verifica che il token sia valido
  const isValid = await verifyResetToken(token);
  if (!isValid) {
    alert("Token scaduto o non valido");
    return;
  }

  // 5. Utente inserisce nuova password
  const newPassword = "new_secure_password123";

  // 6. Reset password
  await resetPasswordWithToken(token, newPassword);
  console.log("Password resettata! Puoi ora effettuare il login");
}

// Esempio di flusso per cambio password obbligatorio
async function forcedPasswordChangeFlow() {
  const username = "testuser";
  const currentPassword = "old_password";

  // 1. Controlla se password deve essere cambiata
  const status = await checkPasswordStatus(username, currentPassword);

  if (status.mustChangePassword) {
    // 2. Mostra form e cambia password
    const newPassword = "new_secure_password123";
    await forcePasswordChange(username, currentPassword, newPassword);
  }
}
```

---

### Esempio PHP (Backend)

```php
<?php
// Funzione helper per chiamate API interne
function callFlussuCommand($command, $data) {
    $conn = new \Flussu\Api\V40\Conn();
    $db = new \Flussu\Flussuserver\NC\HandlerNC();

    // Simula l'esecuzione diretta del comando
    $jsonData = json_decode(json_encode($data));
    return $conn->execCmd($command, $jsonData);
}

// 1. Verificare status password
$result = callFlussuCommand('chkPwdStatus', [
    'userId' => 'testuser'
]);

if ($result['mustChangePassword']) {
    echo "L'utente deve cambiare la password!\n";
}

// 2. Richiedere reset password
$result = callFlussuCommand('reqPwdReset', [
    'emailOrUsername' => 'user@example.com'
]);

if ($result['result'] === 'OK') {
    $token = $result['token'];
    $resetLink = $result['resetLink'];

    // Invia email all'utente
    // sendEmail($result['email'], "Reset Password", "Clicca qui: $resetLink");

    echo "Token di reset: $token\n";
}

// 3. Reset password con token
$result = callFlussuCommand('resetPwd', [
    'token' => 'uuid-token-from-email',
    'newPassword' => 'new_secure_password123'
]);

if ($result['result'] === 'OK') {
    echo "Password resettata con successo!\n";
}
?>
```

---

## Note di Sicurezza

1. **Token di Reset:**
   - I token hanno una validità di 1 ora (configurabile in `PasswordManager::TOKEN_VALIDITY_MINUTES`)
   - I token vengono eliminati dopo l'uso
   - I token scaduti devono essere puliti periodicamente usando `cleanupTokens`

2. **Invio Email:**
   - In produzione, NON restituire il token nella risposta HTTP
   - Inviare il token SOLO via email all'indirizzo dell'utente
   - Implementare rate limiting per prevenire spam

3. **Password:**
   - Validare la complessità della password lato server
   - Implementare HTTPS per tutte le chiamate API
   - Considerare l'aggiornamento dell'algoritmo di hash password (attualmente usa XOR custom, si raccomanda bcrypt/Argon2)

4. **Autenticazione:**
   - Per comandi sensibili, richiedere sempre autenticazione OTP
   - I comandi di reset password pubblici (`reqPwdReset`, `resetPwd`) non richiedono autenticazione ma usano token temporanei

---

## Configurazione

### Tempo di Validità Token

Per modificare il tempo di validità dei token di reset, modifica la costante in `PasswordManager.php`:

```php
const TOKEN_VALIDITY_MINUTES = 60; // Default: 1 ora
```

### URL di Reset

L'URL generato per il reset password può essere personalizzato modificando il metodo `generateResetLink()` in `PasswordManager.php`.

---

## Manutenzione

### Pulizia Token Scaduti

Si raccomanda di eseguire periodicamente (es. via cron job) il comando `cleanupTokens` per rimuovere i token scaduti dal database:

```bash
# Esempio cron job (ogni ora)
0 * * * * curl -X POST "http://your-domain.com/api.php?url=flussuconn" \
  -H "Content-Type: application/json" \
  -d '{"C":"G","userid":"admin","password":"admin_pass","command":"cleanupTokens"}'
```

---

## Troubleshooting

### Errore "Invalid or expired token"
- Il token di reset è scaduto (oltre 1 ora)
- Il token è già stato utilizzato
- Il token non esiste nel database

### Errore "Current password is incorrect"
- La password corrente fornita non è corretta
- L'utente non esiste

### Errore "Password change is not required"
- L'utente sta cercando di usare `forcePwdChg` ma la password non è scaduta
- Usare il normale cambio password invece

---

## Supporto

Per domande o problemi, consultare:
- [Documentazione Flussu](../README.md)
- [Issue Tracker](https://github.com/milleisole/flussu_open/issues)

