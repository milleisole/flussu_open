# Sistema di Autenticazione Utente - Flussu v4.5

Questo documento descrive il sistema di autenticazione basato su sessioni PHP implementato in Flussu v4.5.

## Panoramica

Il sistema di autenticazione gestisce gli utenti tramite:
- **AuthManager**: Classe centrale per la gestione dell'autenticazione e delle sessioni
- **User**: Classe esistente estesa con metodi statici per facilitare l'autenticazione
- **Sessioni PHP**: Storage delle informazioni utente autenticato

## Componenti Principali

### AuthManager (`src/Flussu/Persons/AuthManager.php`)

Classe che gestisce tutte le operazioni di autenticazione:
- Login con username/password
- Login con token
- Logout
- Verifica stato autenticazione
- Recupero dati utente dalla sessione

### User (`src/Flussu/Persons/User.php`)

Classe esistente con nuovi metodi statici helper che delegano ad AuthManager.

## Esempi di Utilizzo

### 1. Login Utente

```php
<?php
use Flussu\Persons\User;
use Flussu\Persons\AuthManager;

// Metodo 1: Tramite User (consigliato)
if (User::login($username, $password)) {
    echo "Login effettuato con successo!";
} else {
    echo "Credenziali non valide";
}

// Metodo 2: Tramite AuthManager (equivalente)
if (AuthManager::login($username, $password)) {
    echo "Login effettuato con successo!";
} else {
    echo "Credenziali non valide";
}
```

### 2. Login con Token

```php
<?php
use Flussu\Persons\User;

if (User::loginWithToken($userId, $token)) {
    echo "Autenticato con token";
} else {
    echo "Token non valido";
}
```

### 3. Verificare se l'Utente è Autenticato

```php
<?php
use Flussu\Persons\User;

if (User::isUserAuthenticated()) {
    echo "Utente autenticato";
} else {
    echo "Utente non autenticato";
}
```

### 4. Ottenere l'Utente Corrente

```php
<?php
use Flussu\Persons\User;

// Ottenere l'oggetto User completo
$currentUser = User::getCurrentUser();
if ($currentUser !== null) {
    echo "Benvenuto " . $currentUser->getName();
    echo "Email: " . $currentUser->getEmail();
}

// Ottenere solo l'ID utente
$userId = User::getCurrentUserId();
if ($userId > 0) {
    echo "User ID: " . $userId;
}
```

### 5. Richiedere Autenticazione Obbligatoria

```php
<?php
use Flussu\Persons\User;

// Termina l'esecuzione se l'utente non è autenticato
User::requireAuthentication();

// Il codice seguente viene eseguito solo se l'utente è autenticato
echo "Benvenuto nell'area riservata!";
```

Con messaggio personalizzato:

```php
<?php
User::requireAuthentication("Devi effettuare il login per accedere a questa pagina");
```

### 6. Logout

```php
<?php
use Flussu\Persons\User;

User::logout();
echo "Logout effettuato";
```

### 7. Ottenere Solo i Dati dalla Sessione (senza ricarica da DB)

```php
<?php
use Flussu\Persons\AuthManager;

$userData = AuthManager::getUserData();
if ($userData !== null) {
    echo "Nome: " . $userData['name'];
    echo "Email: " . $userData['email'];
}
```

## Integrazione nei Controller

### Esempio: Controller con Autenticazione Richiesta

```php
<?php
namespace Flussu\Controllers;

use Flussu\Persons\User;
use Flussu\Flussuserver\Request;

class MySecureController
{
    public function secureAction(Request $request)
    {
        // Richiedi autenticazione
        User::requireAuthentication();

        // Ottieni utente corrente
        $user = User::getCurrentUser();

        // Esegui operazioni riservate
        return [
            'user' => $user->getName(),
            'message' => 'Benvenuto nell\'area riservata'
        ];
    }
}
```

### Esempio: Controller con Login

```php
<?php
namespace Flussu\Controllers;

use Flussu\Persons\User;
use Flussu\Flussuserver\Request;

class AuthController
{
    public function login(Request $request)
    {
        $username = $request['username'] ?? '';
        $password = $request['password'] ?? '';

        if (User::login($username, $password)) {
            return [
                'success' => true,
                'message' => 'Login effettuato',
                'user' => User::getCurrentUser()->getName()
            ];
        }

        return [
            'success' => false,
            'message' => 'Credenziali non valide'
        ];
    }

    public function logout(Request $request)
    {
        User::logout();
        return [
            'success' => true,
            'message' => 'Logout effettuato'
        ];
    }

    public function checkAuth(Request $request)
    {
        if (User::isUserAuthenticated()) {
            $user = User::getCurrentUser();
            return [
                'authenticated' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUserId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail()
                ]
            ];
        }

        return [
            'authenticated' => false
        ];
    }
}
```

## Modifica al FlussuController

Per integrare il sistema nel controller principale:

```php
<?php
// In src/Flussu/Controllers/FlussuController.php

public function apiCall(Request $request, $apiPage){
    // ... codice esistente ...

    session_start();

    // Verifica se c'è un utente autenticato in sessione
    if (User::isUserAuthenticated()) {
        $theFlussuUser = User::getCurrentUser();
    } else {
        // Fallback al sistema esistente con API key
        $uid = General::getUserFromDateTimedApiKey($authKey);
        if ($uid > 0) {
            $theFlussuUser = new \Flussu\Persons\User();
            $theFlussuUser->load($uid);
        }
    }

    // ... resto del codice ...
}
```

## Funzionalità Avanzate

### Verifica Scadenza Sessione

```php
<?php
use Flussu\Persons\AuthManager;

// Verifica se la sessione è più vecchia di 1 ora (3600 secondi)
if (AuthManager::isSessionOlderThan(3600)) {
    echo "Sessione scaduta, effettua nuovamente il login";
    AuthManager::logout();
}
```

### Aggiornamento Timestamp Autenticazione

```php
<?php
use Flussu\Persons\AuthManager;

// Aggiorna il timestamp per estendere la sessione
AuthManager::refreshAuthTime();
```

### Verifica Permessi Utente

```php
<?php
use Flussu\Persons\AuthManager;

// Verifica se l'utente ha un livello di permessi specifico
if (AuthManager::checkRuleLevel(5)) {
    echo "Hai i permessi per questa operazione";
}
```

## Dati Memorizzati in Sessione

L'AuthManager memorizza nella sessione PHP:

- `flussu_user_id`: ID numerico dell'utente
- `flussu_username`: Username dell'utente
- `flussu_auth_time`: Timestamp dell'autenticazione
- `flussu_authenticated_user`: Array con dati utente:
  - `id`: ID utente
  - `username`: Username
  - `email`: Email
  - `name`: Nome
  - `surname`: Cognome

## Note Importanti

1. **Session Start**: Il sistema richiede che `session_start()` sia chiamato. FlussuController lo fa già alla riga 45.

2. **Sicurezza**: Le password sono gestite tramite il metodo `_genPwd()` esistente nella classe User.

3. **Compatibilità**: Il sistema è completamente compatibile con il codice esistente. I metodi originali di autenticazione continuano a funzionare.

4. **Performance**: `getUser()` ricarica i dati dal database per garantire dati freschi. Usare `getUserData()` per accesso più veloce senza ricarica.

## Migrazione dal Sistema Esistente

Il codice esistente continua a funzionare. Per migrare:

```php
// PRIMA (sistema esistente)
$user = new \Flussu\Persons\User();
if ($user->authenticate($username, $password)) {
    // utente autenticato
}

// DOPO (con sessione)
if (User::login($username, $password)) {
    // utente autenticato E memorizzato in sessione
}

// Verifica autenticazione in altre parti del codice
if (User::isUserAuthenticated()) {
    $user = User::getCurrentUser();
    // usa $user
}
```

## Troubleshooting

### "Session already started"
Il sistema controlla automaticamente se la sessione è già avviata prima di chiamare `session_start()`.

### "User not found in session"
Verifica che il login sia stato effettuato correttamente e che la sessione non sia scaduta.

### Logout automatico
Se `getUser()` non riesce a caricare l'utente dal database (es. utente cancellato), la sessione viene automaticamente pulita.

---

**Versione**: 4.5.20251117
**Data**: 17 Novembre 2025
**Autore**: Mille Isole SRL
