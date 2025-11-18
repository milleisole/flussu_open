# Sistema di Autenticazione e Recupero Password - Flussu Server

## Panoramica

Sistema completo di autenticazione e gestione password implementato in PHP puro (backend-only, no JavaScript), seguendo le best practices di sicurezza.

## Funzionalità Implementate

### 1. Login (`login.php`)
- Autenticazione con username o email
- Supporto per password normali e password scadute
- Validazione backend completa
- Session management sicuro
- Redirect automatico per password scadute

### 2. Recupero Password (`forgot-password.php`)
- Richiesta token via email o username
- Protezione contro user enumeration (risposta uguale per utenti esistenti/non esistenti)
- Rate limiting basato su database
- Token con scadenza di 1 ora

### 3. Reset Password con Token (`reset-password.php`)
- Validazione token con hash SHA-256
- Requisiti password strong:
  - Minimo 8 caratteri
  - Almeno una lettera
  - Almeno un numero
- Token monouso (invalida automaticamente dopo l'uso)
- Verifica scadenza token

### 4. Cambio Password Scaduta (`change-expired-password.php`)
- Verifica password corrente
- Requisiti per nuova password
- Impedisce riutilizzo della stessa password
- Aggiornamento automatico data scadenza

## Struttura File

```
webroot/auth/
├── login.php                      # Form di login
├── forgot-password.php            # Richiesta recupero password
├── reset-password.php             # Reset password con token
├── change-expired-password.php    # Cambio password scaduta
└── README.md                      # Questa documentazione

src/Flussu/
├── Persons/
│   ├── User.php                   # Classe utente (esistente, migliorata)
│   └── PasswordRecoveryHelper.php # Helper per recupero password (nuovo)
└── Beans/
    ├── User.php                   # Bean database utenti (esistente)
    └── PasswordRecovery.php       # Bean database token recovery (nuovo)

Docs/Install/migrations/
└── 001_add_password_recovery.sql  # Migrazione database
```

## Installazione

### 1. Eseguire la Migrazione Database

```bash
mysql -u your_user -p your_database < Docs/Install/migrations/001_add_password_recovery.sql
```

Questo crea la tabella `t81_pwd_recovery` con:
- Token hashed (SHA-256)
- Scadenza automatica (1 ora)
- Tracking IP e User-Agent
- Flag monouso

### 2. Configurare Email

Assicurarsi che il file `config/services.json` contenga la configurazione email:

```json
{
  "email": {
    "default": "smtp_provider",
    "smtp_provider": {
      "smtp_host": "smtp.example.com",
      "smtp_port": 587,
      "smtp_auth": 1,
      "smtp_user": "your@email.com",
      "smtp_pass": "encrypted_password",
      "smtp_encrypt": "STARTTLS"
    }
  }
}
```

### 3. Verificare Autoload

Il sistema usa Composer autoload. Assicurarsi che sia aggiornato:

```bash
composer dump-autoload
```

## Utilizzo

### Flusso Login Normale

1. Utente visita `/auth/login.php`
2. Inserisce username/email e password
3. Sistema valida credenziali
4. Se password scaduta → redirect a `change-expired-password.php`
5. Se credenziali valide → crea sessione e redirect

### Flusso Recupero Password

1. Utente visita `/auth/forgot-password.php`
2. Inserisce username o email
3. Sistema:
   - Genera token casuale (64 caratteri hex)
   - Salva hash SHA-256 del token nel database
   - Invia email con link contenente token in chiaro
4. Utente clicca link ricevuto via email
5. Visita `/auth/reset-password.php?token=...`
6. Inserisce nuova password
7. Sistema:
   - Valida token (hash, scadenza, già usato)
   - Aggiorna password
   - Marca token come usato

### Flusso Password Scaduta

1. Utente prova a fare login con password scaduta
2. Sistema redirect a `/auth/change-expired-password.php`
3. Utente inserisce:
   - Username/email
   - Vecchia password
   - Nuova password (2x per conferma)
4. Sistema:
   - Verifica vecchia password
   - Valida nuova password
   - Aggiorna password con data scadenza futura
5. Redirect a login

## Sicurezza

### Implementazioni di Sicurezza

1. **Password Hashing**
   - Algoritmo personalizzato basato su user ID
   - Salt unico per ogni utente
   - Non usa bcrypt standard ma sistema proprietario

2. **Token Recovery**
   - Token casuali crittograficamente sicuri (random_bytes)
   - Hash SHA-256 prima del salvataggio
   - Scadenza obbligatoria (1 ora)
   - Monouso (flag `c81_used`)

3. **Protezione User Enumeration**
   - Risposta identica per utenti esistenti/non esistenti
   - Logging interno separato

4. **Rate Limiting**
   - Cleanup automatico token scaduti
   - Un solo token valido per utente alla volta

5. **Session Security**
   - Session PHP standard
   - Timeout configurabile
   - Validazione lato server

6. **Input Validation**
   - Validazione lunghezza password (min 8)
   - Richiesta lettere + numeri
   - Sanitizzazione output HTML
   - Prepared statements per database

### Logging

Tutte le operazioni critiche vengono loggatevia `General::log()`:
- Tentativi di login falliti
- Richieste recupero password
- Reset password riusciti
- Cambi password

## Personalizzazione

### Modificare Requisiti Password

In `PasswordRecoveryHelper.php` e nei file PHP:

```php
// Cambiare lunghezza minima
if (strlen($newPassword) < 12) { // era 8
    // ...
}

// Aggiungere requisiti
$hasSpecialChar = preg_match('/[!@#$%^&*]/', $newPassword);
if (!$hasSpecialChar) {
    $error = 'Richiesto almeno un carattere speciale';
}
```

### Modificare Scadenza Token

In `PasswordRecoveryHelper.php`:

```php
// Cambiare da 1 ora a 24 ore
$recoveryBean->setc81_expires(date('Y-m-d H:i:s', strtotime('+24 hours')));
```

### Personalizzare Email

In `PasswordRecoveryHelper.php`, metodo `sendRecoveryEmail()`:
- Modificare template HTML
- Cambiare subject
- Aggiungere logo/branding

## Test

### Test Manuale

1. **Test Login**:
   ```
   URL: http://your-domain/auth/login.php
   - Test con credenziali valide
   - Test con credenziali invalide
   - Test con password scaduta
   ```

2. **Test Recupero Password**:
   ```
   URL: http://your-domain/auth/forgot-password.php
   - Test con username valido
   - Test con email valida
   - Test con utente inesistente (deve comunque mostrare successo)
   - Verificare ricezione email
   - Verificare link funzionante
   ```

3. **Test Reset con Token**:
   ```
   - Usare link ricevuto via email
   - Test con token valido
   - Test con token già usato
   - Test con token scaduto
   - Test con token inesistente
   ```

4. **Test Password Scaduta**:
   ```
   - Creare utente con password scaduta:
     UPDATE t80_user SET c80_pwd_chng='2020-01-01' WHERE c80_username='testuser';
   - Provare login → deve redirect a change-expired-password.php
   - Completare cambio password
   ```

### Creazione Utente di Test

```php
// Usare User::registerNew()
require_once 'vendor/autoload.php';
use Flussu\Persons\User;

$user = new User();
$user->registerNew(
    'testuser',           // username
    'TestPass123',        // password
    'test@example.com',   // email
    'Test',               // name
    'User'                // surname
);
```

## Troubleshooting

### Email non ricevute

1. Verificare configurazione SMTP in `config/services.json`
2. Controllare log: `General::log()` scrive in logs
3. Verificare firewall/porta SMTP
4. Testare credenziali SMTP manualmente

### Token sempre invalidi

1. Verificare timezone server/database
2. Controllare che la migrazione sia stata eseguita
3. Verificare che `random_bytes()` funzioni (PHP 7+)

### Password non accettate

1. Verificare requisiti minimi (8 caratteri, lettera+numero)
2. Controllare che la funzione `_genPwd()` funzioni correttamente
3. Verificare che il bean User sia aggiornato

### Sessione non persistente

1. Verificare che `session_start()` sia chiamato
2. Controllare permessi cartella sessioni PHP
3. Verificare configurazione `session.save_path`

## Compatibilità

- **PHP**: >= 7.4 (per `random_bytes()`)
- **Database**: MySQL/MariaDB >= 5.7
- **Browser**: Tutti i browser moderni (no JavaScript richiesto)
- **PHPMailer**: Incluso via Composer

## Licenza

Apache License 2.0 - Mille Isole SRL

## Changelog

### Version 4.5.20251118
- Implementazione completa sistema autenticazione
- Aggiunta gestione recupero password
- Aggiunta gestione password scadute
- Creazione tabella `t81_pwd_recovery`
- Implementazione `PasswordRecoveryHelper`
- Creazione form PHP pure (no JavaScript)
- Documentazione completa

## Supporto

Per problemi o domande:
- GitHub Issues: [Repository URL]
- Email: support@milleisole.net
