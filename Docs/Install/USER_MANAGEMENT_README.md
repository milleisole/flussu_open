# Flussu User Management System
## Sistema di Gestione Utenti e Permessi

Versione: 4.5.1
Data: 2025-11-16
Copyright © 2025 Mille Isole SRL

---

## Indice

1. [Introduzione](#introduzione)
2. [Architettura del Sistema](#architettura)
3. [Installazione](#installazione)
4. [Configurazione](#configurazione)
5. [Livelli Gerarchici Utenti](#livelli-utenti)
6. [Utilizzo Frontend](#utilizzo-frontend)
7. [API REST](#api-rest)
8. [Workflow di Autenticazione](#workflow-autenticazione)
9. [Troubleshooting](#troubleshooting)

---

## <a name="introduzione"></a>1. Introduzione

Il sistema di gestione utenti Flussu implementa una gerarchia a 4 livelli con gestione completa di permessi, ruoli e workflow condivisi.

### Caratteristiche Principali

- ✅ 4 livelli gerarchici di utenti
- ✅ Gestione permessi granulare su workflow
- ✅ Sistema di progetti condivisi
- ✅ Audit logging completo
- ✅ Gestione sessioni e API keys
- ✅ Sistema di inviti utente
- ✅ Frontend HTML5/JS/CSS3 minimale
- ✅ API REST per integrazioni

---

## <a name="architettura"></a>2. Architettura del Sistema

### Struttura Directory

```
flussu_open/
├── src/Flussu/Users/             # Backend gestione utenti
│   ├── UserManager.php           # CRUD utenti
│   ├── RoleManager.php           # Gestione ruoli e permessi
│   ├── SessionManager.php        # Gestione sessioni
│   ├── InvitationManager.php     # Sistema inviti
│   └── AuditLogger.php           # Logging attività
│
├── src/Flussu/Controllers/
│   └── UserManagementController.php  # API REST Controller
│
├── webroot/flussu/               # Frontend
│   ├── index.html                # Login page
│   ├── dashboard.html            # Dashboard utente
│   ├── users.html                # Gestione utenti (admin)
│   ├── css/flussu-admin.css      # Stili
│   └── js/flussu-api.js          # API Client JavaScript
│
└── Docs/Install/
    ├── database.sql              # Schema database originale
    └── user_management_schema.sql # Schema gestione utenti
```

### Schema Database

Il sistema utilizza le seguenti tabelle:

#### Tabelle Principali

- **t80_user** - Utenti del sistema
- **t90_role** - Definizione ruoli
- **t88_wf_permissions** - Permessi granulari su workflow
- **t92_user_audit** - Audit log delle attività
- **t94_user_sessions** - Sessioni e API keys
- **t96_user_invitations** - Inviti registrazione

#### Tabelle Esistenti (Utilizzate)

- **t10_workflow** - Workflow
- **t83_project** - Progetti
- **t85_prj_wflow** - Relazione progetto-workflow
- **t87_prj_user** - Relazione progetto-utente

---

## <a name="installazione"></a>3. Installazione

### Prerequisiti

- PHP 7.4 o superiore
- MySQL/MariaDB 10.3 o superiore
- Web server (Apache/Nginx)
- Flussu v4.5+ già installato

### Step 1: Backup Database

```bash
mysqldump -u flussu_user -p flussu_db > backup_pre_usermgmt.sql
```

### Step 2: Esecuzione Schema SQL

```bash
mysql -u flussu_user -p flussu_db < Docs/Install/user_management_schema.sql
```

Questo script:
- Popola la tabella `t90_role` con i 4 ruoli predefiniti
- Crea le nuove tabelle per permessi, audit, sessioni e inviti
- Crea viste per semplificare le query
- Aggiorna l'utente admin predefinito (ID=16)

### Step 3: Verifica Installazione

```sql
-- Verifica ruoli
SELECT * FROM t90_role;

-- Verifica utente admin
SELECT * FROM v30_users_with_roles WHERE user_id = 16;

-- Verifica tabelle create
SHOW TABLES LIKE 't%_user_%';
```

### Step 4: Copia File Frontend

I file frontend sono già nella posizione corretta in `/webroot/flussu/`.
Verifica che siano accessibili dal web server.

### Step 5: Configurazione Web Server

#### Apache (.htaccess)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /flussu/

    # API routes
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/(.*)$ ../api/user-management.php?path=$1 [QSA,L]
</IfModule>
```

#### Nginx

```nginx
location /flussu/api/ {
    rewrite ^/flussu/api/(.*)$ /api/user-management.php?path=$1 last;
}

location /flussu/ {
    try_files $uri $uri/ /flussu/index.html;
}
```

---

## <a name="configurazione"></a>4. Configurazione

### Configurazione Utente Admin

L'utente admin predefinito è stato creato con ID=16 durante l'installazione di Flussu.
Il sistema di user management lo aggiorna automaticamente:

```sql
-- L'utente admin ha:
Username: admin
Email: admin@example.com
Password: [vuota - da impostare al primo accesso]
Ruolo: 1 (System Admin)
```

### Primo Accesso

1. Accedi a `http://tuosito.com/flussu/`
2. Usa username: `admin`
3. La password è vuota - premi semplicemente "Accedi"
4. Il sistema ti chiederà di impostare una nuova password

### Creazione Primo Utente Admin Alternativo

Se preferisci creare un nuovo admin:

```sql
INSERT INTO t80_user
(c80_username, c80_email, c80_password, c80_role, c80_name, c80_surname)
VALUES
('mioadmin', 'admin@example.com', '', 1, 'Mio', 'Admin');
```

---

## <a name="livelli-utenti"></a>5. Livelli Gerarchici Utenti

### Role ID 0 - Utente Finale

**Permessi:**
- ✅ Esegue i workflow pubblici
- ❌ Non può creare workflow
- ❌ Non può vedere la gestione backend

**Use Case:** Utenti finali che utilizzano solo i workflow tramite app/chatbot.

### Role ID 1 - Amministratore del Sistema

**Permessi:**
- ✅ Gestisce tutti gli utenti (crea, modifica, disabilita)
- ✅ Accede a tutti i workflow
- ✅ Crea e gestisce workflow condivisi (sub-workflow)
- ✅ Gestisce progetti e permessi
- ✅ Visualizza audit log completo
- ✅ Accesso completo al backend

**Use Case:** Admin di sistema con controllo totale.

### Role ID 2 - Editor di Workflow

**Permessi:**
- ✅ Crea e modifica i propri workflow
- ✅ Può condividere workflow con altri utenti (progetti)
- ✅ Può aggiungere sub-workflow ai propri workflow
- ✅ Può duplicare sub-workflow per modificarli
- ❌ Non può modificare sub-workflow originali
- ❌ Non può gestire utenti

**Use Case:** Utenti che creano workflow per la propria organizzazione.

### Role ID 3 - Visualizzatore/Tester

**Permessi:**
- ✅ Visualizza workflow assegnati
- ✅ Testa workflow in anteprima
- ✅ Può decidere se rendere pubblico un workflow (se autorizzato)
- ❌ Non può modificare workflow
- ❌ Non può creare nuovi workflow

**Use Case:** QA/Tester che validano workflow prima della pubblicazione.

---

## <a name="utilizzo-frontend"></a>6. Utilizzo Frontend

### Login (`index.html`)

![Login](https://via.placeholder.com/600x400?text=Flussu+Login)

1. Accedi con username/email e password
2. Il sistema crea automaticamente una sessione
3. L'API key viene salvata localmente
4. Redirect automatico alla dashboard

### Dashboard (`dashboard.html`)

Mostra:
- Statistiche workflow attivi
- Lista workflow personali
- Attività recente (solo admin)
- Link rapidi

### Gestione Utenti (`users.html`)

**Solo per Amministratori**

Funzionalità:
- ➕ Crea nuovo utente
- ✏️ Modifica utente esistente
- 🚫 Disabilita/Riabilita utente
- 🔑 Reset password
- 📊 Statistiche utenti per ruolo

#### Creazione Utente

1. Click su "Nuovo Utente"
2. Compila form:
   - Username (obbligatorio, univoco)
   - Email (obbligatorio, univoco)
   - Nome e Cognome (opzionali)
   - Ruolo (seleziona da dropdown)
   - Password (opzionale - se vuoto, l'utente deve cambiarla al primo accesso)
3. Click "Salva"

#### Modifica Utente

1. Click sull'icona ✏️ nella riga utente
2. Modifica i campi necessari
3. Click "Salva"

**Nota:** Non è possibile modificare la password da qui. Usa il pulsante "Reset Password".

#### Reset Password

1. Click sul pulsante 🔑
2. Conferma l'operazione
3. Viene generata una password temporanea
4. Copia la password e comunicala all'utente
5. L'utente dovrà cambiarla al prossimo accesso

---

## <a name="api-rest"></a>7. API REST

### Base URL

```
http://tuosito.com/api/flussu
```

### Headers Richiesti

```http
Content-Type: application/json
X-API-Key: [your-api-key]
X-Session-ID: [your-session-id]
```

### Endpoints Principali

#### Autenticazione

**POST** `/auth/login`
```json
{
  "username": "admin",
  "password": "mypassword"
}
```

Response:
```json
{
  "success": true,
  "session_id": "abc123...",
  "api_key": "xyz789...",
  "expires_at": "2025-11-16 18:00:00",
  "user": { ... }
}
```

**POST** `/auth/logout`

**GET** `/auth/me`

#### Utenti

**GET** `/users?includeDeleted=false`

**GET** `/users/{id}`

**POST** `/users`
```json
{
  "username": "newuser",
  "email": "user@example.com",
  "name": "Mario",
  "surname": "Rossi",
  "role": 2,
  "password": "optional"
}
```

**PUT** `/users/{id}`

**PUT** `/users/{id}/status`
```json
{
  "active": true
}
```

**PUT** `/users/{id}/password`
```json
{
  "newPassword": "newpass123",
  "temporary": true
}
```

**GET** `/users/stats`

#### Ruoli

**GET** `/roles`

#### Workflow

**GET** `/workflows/me`

**GET** `/workflows/user/{userId}`

**GET** `/workflows/{workflowId}/permissions`

**POST** `/workflows/{workflowId}/permissions`
```json
{
  "userId": 123,
  "permission": "RWX"
}
```

**DELETE** `/workflows/{workflowId}/permissions/{userId}`

#### Inviti

**POST** `/invitations`
```json
{
  "email": "newuser@example.com",
  "role": 2,
  "expiresInDays": 7
}
```

**GET** `/invitations/validate/{code}`

**POST** `/invitations/accept/{code}`
```json
{
  "username": "newuser",
  "password": "mypassword",
  "name": "Mario",
  "surname": "Rossi"
}
```

**GET** `/invitations/pending`

#### Audit

**GET** `/audit/users/{userId}?limit=100&offset=0`

**GET** `/audit/stats?startDate=2025-01-01&endDate=2025-12-31`

---

## <a name="workflow-autenticazione"></a>8. Workflow di Autenticazione

### Workflow Predefiniti da Creare

Il sistema necessita dei seguenti workflow per funzionare completamente:

#### 1. Workflow Registrazione Utente

**Nome:** `[SYS] User Registration`
**Descrizione:** Workflow per registrazione nuovi utenti via invito
**Owner:** admin (ID=16)

**Funzionalità:**
1. Verifica codice invito
2. Richiede dati utente (username, password, nome, cognome)
3. Valida input
4. Crea account utente
5. Invia email di benvenuto

#### 2. Workflow Login

**Nome:** `[SYS] User Login`
**Descrizione:** Workflow per autenticazione utenti
**Owner:** admin (ID=16)

**Funzionalità:**
1. Richiede username/email e password
2. Valida credenziali
3. Crea sessione
4. Restituisce API key

#### 3. Workflow Cambio Password

**Nome:** `[SYS] Password Change`
**Descrizione:** Workflow per cambio password
**Owner:** admin (ID=16)

**Funzionalità:**
1. Verifica password corrente
2. Richiede nuova password
3. Valida complessità password
4. Aggiorna password
5. Invalida sessioni esistenti

#### 4. Workflow Reset Password

**Nome:** `[SYS] Password Reset`
**Descrizione:** Workflow per recupero password
**Owner:** admin (ID=16)

**Funzionalità:**
1. Richiede email
2. Genera token temporaneo
3. Invia email con link
4. Verifica token
5. Permette impostazione nuova password

---

## <a name="troubleshooting"></a>9. Troubleshooting

### Problema: "Sessione non valida o scaduta"

**Causa:** API key scaduta o session ID non valido

**Soluzione:**
1. Effettua logout
2. Cancella localStorage del browser:
   ```javascript
   localStorage.removeItem('flussu_api_key');
   localStorage.removeItem('flussu_session_id');
   ```
3. Effettua nuovo login

### Problema: "Accesso negato: privilegi di amministratore richiesti"

**Causa:** L'utente non ha ruolo admin (role_id != 1)

**Soluzione:**
Aggiorna il ruolo dell'utente nel database:
```sql
UPDATE t80_user SET c80_role = 1 WHERE c80_id = [user_id];
```

### Problema: Utente non riesce a fare login

**Causa 1:** Password non impostata

**Soluzione:**
```sql
-- Imposta password vuota per forzare cambio
UPDATE t80_user SET c80_password = '', c80_pwd_chng = '1899-12-31'
WHERE c80_id = [user_id];
```

**Causa 2:** Utente disabilitato

**Soluzione:**
```sql
UPDATE t80_user
SET c80_deleted = '1899-12-31 23:59:59', c80_deleted_by = 0
WHERE c80_id = [user_id];
```

### Problema: Frontend non carica

**Causa:** Percorsi errati o file mancanti

**Soluzione:**
1. Verifica che tutti i file esistano:
   ```bash
   ls -la /webroot/flussu/
   ls -la /webroot/flussu/css/
   ls -la /webroot/flussu/js/
   ```

2. Verifica permessi:
   ```bash
   chmod 644 /webroot/flussu/*.html
   chmod 644 /webroot/flussu/css/*.css
   chmod 644 /webroot/flussu/js/*.js
   ```

### Problema: API non risponde

**Causa:** Controller non configurato correttamente

**Soluzione:**
Crea file `/api/user-management.php`:
```php
<?php
require_once '../bootstrap.php';

use Flussu\Controllers\UserManagementController;

$controller = new UserManagementController(true); // debug=true

$path = $_GET['path'] ?? '';
$request = [
    'path' => '/' . $path,
    'method' => $_SERVER['REQUEST_METHOD']
];

// Aggiungi query params
foreach ($_GET as $key => $value) {
    if ($key !== 'path') {
        $request[$key] = $value;
    }
}

$result = $controller->handleRequest($request);
echo json_encode($result);
```

### Pulizia Dati di Test

```sql
-- Rimuovi utenti di test
DELETE FROM t80_user WHERE c80_id > 16;

-- Rimuovi sessioni scadute
DELETE FROM t94_user_sessions WHERE c94_expires_at < NOW();

-- Rimuovi inviti scaduti
UPDATE t96_user_invitations SET c96_status = 2
WHERE c96_status = 0 AND c96_expires_at < NOW();

-- Pulisci audit log vecchi (oltre 90 giorni)
DELETE FROM t92_user_audit
WHERE c92_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## Support

Per supporto tecnico:
- Email: flussu@milleisole.com
- Documentazione: https://docs.flussu.com
- GitHub Issues: https://github.com/milleisole/flussu_open/issues

---

**© 2025 Mille Isole SRL - Tutti i diritti riservati**
