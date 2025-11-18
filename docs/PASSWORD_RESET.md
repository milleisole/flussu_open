# Sistema di Reset Password - Flussu

## Panoramica

Il sistema di reset password è stato implementato in `/webroot/forgot-password.php` e fornisce un'API REST completa per gestire il processo di recupero password.

## Componenti

### 1. Backend API (`/webroot/forgot-password.php`)

L'API espone tre endpoint principali:

#### **POST** `/forgot-password.php?action=request`
Richiede un reset della password per un indirizzo email.

**Request Body:**
```json
{
  "action": "request",
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password reset link has been sent to your email.",
  "debug": {
    "sent": true,
    "debug_link": "http://localhost/forgot-password.php?action=verify&token=abc123...",
    "note": "Email sending not implemented. Use debug_link for testing."
  }
}
```

#### **GET** `/forgot-password.php?action=verify&token=TOKEN`
Verifica la validità di un token di reset.

**Response:**
```json
{
  "success": true,
  "message": "Token is valid",
  "email": "user@example.com",
  "token": "abc123..."
}
```

#### **POST** `/forgot-password.php?action=reset`
Resetta effettivamente la password usando un token valido.

**Request Body:**
```json
{
  "action": "reset",
  "token": "abc123...",
  "password": "newPassword123",
  "confirm_password": "newPassword123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password has been successfully reset. You can now login with your new password."
}
```

### 2. Demo UI (`/webroot/forgot-password-demo.html`)

Una pagina HTML completa con interfaccia utente per testare tutte le funzionalità:
- Form per richiedere il reset
- Form per impostare la nuova password
- Gestione automatica del token dall'URL
- Messaggi di errore e successo
- Design responsive moderno

## Flusso di Utilizzo

1. **Richiesta Reset**
   - L'utente inserisce la sua email
   - Il sistema verifica se l'email esiste nel database
   - Viene generato un token sicuro (64 caratteri esadecimali)
   - Il token viene salvato in `/temp/reset_tokens/` con scadenza 24 ore
   - (In produzione) Viene inviata un'email con il link di reset

2. **Verifica Token**
   - L'utente clicca sul link ricevuto
   - Il sistema verifica che il token sia valido e non scaduto
   - Viene mostrato il form per la nuova password

3. **Reset Password**
   - L'utente inserisce la nuova password (minimo 8 caratteri)
   - Il sistema valida i dati e aggiorna la password nel database
   - Il token usato viene eliminato
   - L'utente può effettuare il login con la nuova password

## Caratteristiche di Sicurezza

- **Token Sicuri**: Generati con `random_bytes(32)` (256 bit)
- **Scadenza Token**: I token scadono dopo 24 ore
- **One-Time Use**: I token vengono eliminati dopo l'uso
- **Password Validation**: Minimo 8 caratteri richiesti
- **Cleanup Automatico**: I token scaduti vengono eliminati automaticamente
- **Protezione Email Enumeration**: Non rivela se un'email esiste o meno

## Configurazione

### Prerequisiti
- PHP 7.4+
- Composer (dipendenze già installate)
- Database MySQL configurato
- File `.env` con credenziali database

### Directory Temporanee
Il sistema crea automaticamente la directory `/temp/reset_tokens/` per memorizzare i token.

**Nota**: In produzione, si consiglia di:
1. Spostare i token in database (creare tabella `password_reset_tokens`)
2. Implementare un sistema di invio email reale
3. Configurare HTTPS per la sicurezza
4. Rimuovere il campo `debug` dalle risposte API

## Integrazione Email

Attualmente l'invio email è uno stub. Per implementarlo:

1. Installare una libreria email (es. PHPMailer, SwiftMailer)
2. Modificare la funzione `sendResetEmail()` in `forgot-password.php`
3. Configurare SMTP o servizio email (SendGrid, Mailgun, AWS SES)

### Esempio con PHPMailer:

```php
function sendResetEmail($email, $token) {
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('noreply@flussu.com', 'Flussu');
    $mail->addAddress($email);

    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/forgot-password.php?action=verify&token=" . $token;

    $mail->Subject = 'Password Reset Request';
    $mail->Body = "Click the following link to reset your password: " . $resetLink;

    return $mail->send();
}
```

## Testing

### Usando cURL

**1. Richiedere reset:**
```bash
curl -X POST http://localhost/forgot-password.php \
  -H "Content-Type: application/json" \
  -d '{"action":"request","email":"test@example.com"}'
```

**2. Verificare token:**
```bash
curl http://localhost/forgot-password.php?action=verify&token=YOUR_TOKEN
```

**3. Reset password:**
```bash
curl -X POST http://localhost/forgot-password.php \
  -H "Content-Type: application/json" \
  -d '{
    "action":"reset",
    "token":"YOUR_TOKEN",
    "password":"newPassword123",
    "confirm_password":"newPassword123"
  }'
```

### Usando la Demo UI

1. Aprire `http://localhost/forgot-password-demo.html`
2. Inserire un'email esistente nel database
3. Copiare il link di debug fornito nella risposta
4. Incollare il link nel browser per aprire il form di reset
5. Inserire la nuova password e confermare

## Codici di Errore

- `400`: Bad Request (dati mancanti o invalidi)
- `405`: Method Not Allowed (metodo HTTP non supportato)
- `500`: Internal Server Error (errore del server)

## File Modificati/Creati

- `/webroot/forgot-password.php` - API backend
- `/webroot/forgot-password-demo.html` - UI di demo
- `/docs/PASSWORD_RESET.md` - Questa documentazione

## Riferimenti Codice

L'implementazione usa:
- `src/Flussu/Persons/User.php:367` - Metodo `changeUserPassword()`
- `src/Flussu/Persons/User.php:123` - Metodo `emailExist()`
- `src/Flussu/Persons/User.php:249` - Metodo `setPassword()`

## TODO per Produzione

- [ ] Implementare invio email reale
- [ ] Spostare token da filesystem a database
- [ ] Aggiungere rate limiting per prevenire abusi
- [ ] Implementare logging degli eventi di sicurezza
- [ ] Configurare HTTPS obbligatorio
- [ ] Rimuovere informazioni di debug dalle risposte
- [ ] Aggiungere supporto per autenticazione a due fattori
- [ ] Implementare notifica email quando la password viene cambiata
