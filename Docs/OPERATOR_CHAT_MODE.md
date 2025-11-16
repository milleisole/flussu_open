# Modalità Chat Operatore - Documentazione Tecnica

## Indice
1. [Panoramica](#panoramica)
2. [Sistema Statistiche](#sistema-statistiche)
3. [Sistema Notifiche](#sistema-notifiche)
4. [Modalità Operatore](#modalita-operatore)
5. [API Reference](#api-reference)
6. [Esempi di Utilizzo](#esempi-di-utilizzo)

---

## Panoramica

Il sistema Flussu fornisce ora una funzionalità di **chat in tempo reale con operatore** che permette a un operatore umano di intervenire durante una conversazione automatica tra l'utente e il workflow.

### Architettura del Sistema

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Utente    │ ◄─SSE─► │   notify.php │ ◄─DB─► │  t203_      │
│  (Client)   │         │  (EventSrc)  │         │notifications│
└─────────────┘         └──────────────┘         └─────────────┘
       │                                                  ▲
       │                                                  │
       ▼                                                  │
┌─────────────┐         ┌──────────────┐                │
│  flussueng  │────────►│   Workflow   │────────────────┘
│    API      │         │   Engine     │   (addNotify)
└─────────────┘         └──────────────┘
```

---

## Sistema Statistiche

### Funzionamento

Le statistiche sui messaggi scambiati vengono salvate **in differita** quando la sessione si chiude.

#### Tabelle Database

**`t70_stat`** - Tracking dettagliato degli eventi
```sql
CREATE TABLE `t70_stat` (
  `c70_wid` int(10) NOT NULL,           -- Workflow ID
  `c70_sid` varchar(36) NOT NULL,       -- Session ID
  `c70_bid` int(10) NOT NULL,           -- Block ID
  `c70_start` smallint(6) NOT NULL,     -- Flag inizio (0/1)
  `c70_channel` int(2) unsigned NOT NULL, -- Canale comunicazione
  `c70_timestamp` datetime NOT NULL,    -- Data/ora evento
  `c70_data` mediumtext NOT NULL,       -- Dati JSON
  `c70_tag` varchar(2) DEFAULT NULL     -- Tag interno
);
```

**Canali supportati:**
- `0` = Web
- `1` = Telegram
- `2` = WhatsApp
- `3` = Facebook Messenger
- `4` = Android App
- `5` = iOS App
- `10` = Zapier

#### Flusso di Tracciamento

1. Durante l'esecuzione del workflow, i dati vengono raccolti in memoria tramite `Session::recUseStat()`
2. Alla chiusura della sessione, `HandlerSessNC::closeSession()` esegue un INSERT batch in `t70_stat`
3. Le statistiche vengono poi aggregate dalla classe `Statistic.php`

#### Endpoint API

**File:** `/src/Flussu/Api/V40/Stat.php`

```http
POST /api/v4.0/stat
{
  "WID": "workflow_id",
  "CTY": 1,              // 1=totale, 2=obiettivi, 3=web vs chat
  "IVL": 30              // Intervallo giorni
}
```

### Cron Job

Il file `/bin/add2cron.sh` configura un job che esegue ogni minuto:
```bash
*/1 * * * * cd /path/to/flussu && php src/Flussu/Timedcall.php
```

**Nota:** Il cron NON aggiorna direttamente le statistiche, ma gestisce l'esecuzione temporizzata dei workflow.

---

## Sistema Notifiche

### Server-Sent Events (SSE)

Le notifiche vengono inviate in tempo reale tramite **SSE** dall'endpoint `/notify.php`.

#### Endpoint Notifiche

**URL:** `/notify.php?SID={session_id}`

**Protocollo:** `text/event-stream`

**Response Format:**
```json
{
  "uuid-v4-1": {
    "id": "generated-uuid",
    "type": "notification-type",
    "name": "notification-name",
    "value": "notification-value"
  }
}
```

### Tabella Database Notifiche

**`t203_notifications`**
```sql
CREATE TABLE `t203_notifications` (
  `c203_notify_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `c203_recdate` timestamp NOT NULL DEFAULT current_timestamp(),
  `c203_sess_id` varchar(36) NOT NULL,
  `c203_n_type` varchar(3) NOT NULL,    -- Tipo notifica
  `c203_n_name` varchar(45) NOT NULL,   -- Nome/identificatore
  `c203_n_value` text NOT NULL,         -- Valore/payload
  PRIMARY KEY (`c203_notify_id`),
  KEY `ix203_session` (`c203_sess_id`,`c203_recdate`)
);
```

### Tipi di Notifiche Supportati

| Tipo | Codice | Descrizione | Parametri |
|------|--------|-------------|-----------|
| 0 | N | Notifica generica | name, value |
| 1 | A | Alert/Avviso | value |
| 2 | CI | Counter Init | name, value |
| 3 | CV | Counter Value | name, value |
| 4 | AR | Add Row | value |
| 5 | CB | Callback | wid, bid |
| - | **operator-join** | **Operatore entra** | **name=operatorName** |
| - | **chat-msg** | **Messaggio operatore** | **name=operatorName, value=message** |

### Gestione Lato Server

**File:** `/src/Flussu/Flussuserver/NC/HandlerSessNC.php`

```php
public function addNotify($dataType, $dataName, $dataValue, $channel, $wid, $sessid, $bidid) {
    // Inserisce notifica in t203_notifications
    $SQL = "INSERT INTO t203_notifications
            (c203_sess_id, c203_n_type, c203_n_name, c203_n_value)
            VALUES (?, ?, ?, ?)";
    $this->execSql($SQL, [$sessid, $dataType, $dataName, $dataValue]);

    // Traccia anche in t70_stat per statistiche
    // ...
}

public function getNotify($sessId) {
    // Recupera e CANCELLA le notifiche dalla tabella
    $SQL = "SELECT * FROM t203_notifications WHERE c203_sess_id=? ORDER BY c203_recdate DESC";
    // ...
    // DELETE dopo la lettura
    return $notifications;
}
```

**Importante:** Le notifiche vengono **eliminate** dal database dopo la consegna al client.

---

## Modalità Operatore

### Funzionalità Implementate

La modalità operatore permette a un operatore umano di:
1. Entrare in una conversazione attiva
2. Inviare messaggi all'utente in tempo reale
3. Essere visualizzato con nome identificativo

### Client-Side Implementation

**File:** `/webroot/flucli/client_api.dev.js`

#### Variabili Globali

```javascript
let operatorMode = false;          // Stato modalità operatore
let operatorName = null;           // Nome operatore corrente
let eventSource = null;            // Connessione SSE
const NOTIFY_URL = "/notify.php";  // Endpoint notifiche
```

#### Inizializzazione SSE

Quando viene ricevuto un Session ID (SID), il client inizializza automaticamente la connessione SSE:

```javascript
function initNotifications() {
  if (!SID || eventSource) return;

  eventSource = new EventSource(`${NOTIFY_URL}?SID=${SID}`);

  eventSource.onmessage = function(event) {
    const notifications = JSON.parse(event.data);
    Object.values(notifications).forEach(handleNotification);
  };
}
```

#### Gestione Notifica `operator-join`

Quando l'operatore entra nella conversazione:

```javascript
function handleOperatorJoin(name, value) {
  operatorMode = true;
  operatorName = value || name || 'Operatore';

  // Mostra messaggio di sistema
  appendSystemMessage(`${operatorName} si è unito alla conversazione`);

  // Aggiorna UI con indicatore visivo
  updateOperatorModeUI(true);
}
```

**Effetto Visuale:**
- Badge verde "Operatore online" nell'header
- Messaggio di sistema nella chat
- Dot verde pulsante accanto al nome operatore

#### Gestione Notifica `chat-msg`

I messaggi dall'operatore vengono visualizzati come messaggi del bot ma con styling distintivo:

```javascript
function handleOperatorMessage(name, value) {
  const senderName = name || operatorName || 'Operatore';
  appendOperatorMessage(senderName, value);
}
```

**Rendering Messaggio Operatore:**
```javascript
function appendOperatorMessage(operatorName, message) {
  const operatorMsg = `
    <div class="w-full flex flex-col justify-start mb-3">
      <div class="flex items-center gap-2 mb-1">
        <div class="w-2 h-2 rounded-full bg-green-500"></div>
        <span class="text-xs font-semibold">${operatorName}</span>
      </div>
      <div class="bg-gradient-to-r from-blue-50 to-blue-100
                  border-l-4 border-blue-500 rounded-lg px-4 py-3">
        ${message}
      </div>
    </div>
  `;
}
```

### Interfaccia Utente

#### Indicatore Operatore Online

Quando attivo, viene mostrato nell'header:

```html
<div id="operator-indicator" class="px-3 py-1 rounded-full
     bg-green-100 text-green-800">
  <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
  <span>Operatore online</span>
</div>
```

#### Messaggi di Sistema

I messaggi di sistema (join/disconnect) hanno uno stile centrato:

```html
<div class="w-full flex justify-center my-3">
  <div class="px-4 py-2 rounded-full bg-blue-100 text-blue-800">
    Operatore si è unito alla conversazione
  </div>
</div>
```

### Traduzioni

Le traduzioni per "Operatore online" sono disponibili in:

| Lingua | Traduzione |
|--------|-----------|
| Italiano | Operatore online |
| English | Operator online |
| Français | Opérateur en ligne |
| Español | Operador en línea |
| Deutsch | Operator online |
| 中文 | 操作员在线 |

**File:** `/webroot/flucli/langs/{lang}.lng`

---

## API Reference

### Invio Notifica `operator-join`

Per far entrare un operatore nella conversazione:

```php
use Flussu\Flussuserver\NC\HandlerSessNC;

$handler = new HandlerSessNC();
$handler->addNotify(
    'operator-join',           // type
    'Mario Rossi',            // name (nome operatore)
    'Mario Rossi',            // value (opzionale, default = name)
    0,                        // channel (0 = web)
    $workflowId,              // wid
    $sessionId,               // sid
    null                      // bid (opzionale)
);
```

### Invio Messaggio Operatore

Per inviare un messaggio dall'operatore all'utente:

```php
$handler->addNotify(
    'chat-msg',               // type
    'Mario Rossi',            // name (nome operatore che invia)
    'Ciao! Come posso aiutarti?',  // value (messaggio)
    0,                        // channel
    $workflowId,              // wid
    $sessionId,               // sid
    null                      // bid
);
```

### Endpoint per Recuperare Sessioni Attive

Per implementare un endpoint che restituisca le sessioni attive in tempo reale:

```php
// Esempio endpoint: /api/v4.0/active-sessions

public function getActiveSessions($workflowId) {
    $SQL = "SELECT DISTINCT c70_sid as session_id,
                   MAX(c70_timestamp) as last_activity
            FROM t70_stat
            WHERE c70_wid = ?
            AND c70_timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY c70_sid
            ORDER BY last_activity DESC";

    return $this->execSql($SQL, [$workflowId]);
}
```

### Endpoint per Contenuto Sessione

Per ottenere il contenuto di una specifica sessione in tempo reale:

```php
// Esempio endpoint: /api/v4.0/session-content

public function getSessionContent($sessionId) {
    // Recupera lo storico dalla tabella t207_history
    $SQL = "SELECT c207_history, c207_count, c207_timestamp
            FROM t207_history
            WHERE c207_sess_id = ?";

    $result = $this->execSql($SQL, [$sessionId]);

    if ($result && isset($result[0])) {
        return [
            'history' => json_decode($result[0]['c207_history'], true),
            'count' => $result[0]['c207_count'],
            'timestamp' => $result[0]['c207_timestamp']
        ];
    }

    return null;
}
```

---

## Esempi di Utilizzo

### Scenario Completo: Chat con Operatore

#### 1. L'utente inizia una conversazione

```javascript
// Client
startWorkflow('workflow-id-123');
// → Riceve SID
// → initNotifications() viene chiamato automaticamente
```

#### 2. L'operatore vede la sessione attiva

```http
GET /api/v4.0/active-sessions?WID=workflow-id-123

Response:
{
  "sessions": [
    {
      "session_id": "abc123def456",
      "last_activity": "2025-11-16 14:30:00"
    }
  ]
}
```

#### 3. L'operatore legge il contenuto

```http
GET /api/v4.0/session-content?SID=abc123def456

Response:
{
  "history": [...],
  "count": 5,
  "timestamp": "2025-11-16 14:30:00"
}
```

#### 4. L'operatore entra nella chat

```php
$handler->addNotify(
    'operator-join',
    'Marco - Supporto',
    'Marco - Supporto',
    0,
    'workflow-id-123',
    'abc123def456',
    null
);
```

**Risultato sul client:**
- Appare badge "Operatore online"
- Messaggio di sistema: "Marco - Supporto si è unito alla conversazione"

#### 5. L'operatore invia un messaggio

```php
$handler->addNotify(
    'chat-msg',
    'Marco - Supporto',
    'Ciao! Ho visto che hai una domanda sul prodotto. Come posso aiutarti?',
    0,
    'workflow-id-123',
    'abc123def456',
    null
);
```

**Risultato sul client:**
- Messaggio visualizzato con:
  - Dot verde + nome "Marco - Supporto"
  - Sfondo blu sfumato con bordo sinistro blu
  - Testo del messaggio

#### 6. L'utente risponde

L'utente digita normalmente nel chat input. Il messaggio viene inviato al workflow come sempre.

#### 7. Monitoraggio continuo

L'operatore continua a ricevere aggiornamenti in tempo reale:
- Via polling dell'endpoint session-content
- Oppure implementando un SSE lato operatore

---

## Note Tecniche

### Performance

- **SSE** mantiene una connessione persistente per sessione
- Le notifiche vengono **eliminate** dopo la consegna (no accumulo)
- Timeout automatico della connessione SSE gestito dal browser

### Sicurezza

- Il SID deve essere un UUID v4 valido
- Validazione server-side del formato SID
- CORS configurato per l'host corrente

### Compatibilità

- EventSource è supportato in tutti i browser moderni
- Fallback: implementare long polling per browser legacy

### Limitazioni

- Una sessione può avere un solo EventSource attivo
- Le notifiche non consegnate vengono perse se il client è offline
- Lo storico completo della conversazione è in `t207_history`

---

## File Modificati

### Client JavaScript
- `/webroot/flucli/client_api.dev.js`
  - Aggiunto supporto SSE
  - Implementata modalità operatore
  - Gestione notifiche operator-join e chat-msg

### File di Lingua
- `/webroot/flucli/langs/it.lng` - Italiano
- `/webroot/flucli/langs/en.lng` - English
- `/webroot/flucli/langs/fr.lng` - Français
- `/webroot/flucli/langs/es.lng` - Español
- `/webroot/flucli/langs/de.lng` - Deutsch
- `/webroot/flucli/langs/zh.lng` - 中文

Tutti i file lingua ora includono:
```json
"operator_online": "Operatore online"
```

---

## Testing

### Test Manuale

1. Aprire il client chat: `/flucli/client.html?wid=YOUR_WORKFLOW_ID`
2. Inviare un messaggio per creare una sessione
3. Annotare il Session ID dal footer
4. Eseguire via PHP/API:
   ```php
   $handler->addNotify('operator-join', 'Test Operator', 'Test Operator', 0, $wid, $sid, null);
   ```
5. Verificare che appaia "Operatore online"
6. Inviare un messaggio:
   ```php
   $handler->addNotify('chat-msg', 'Test Operator', 'Messaggio di test', 0, $wid, $sid, null);
   ```
7. Verificare che il messaggio appaia con lo stile corretto

### Debug

Aprire la console del browser per vedere i log:
```
Notifications initialized for session: abc123...
Received notification: {type: "operator-join", name: "Test", value: "Test"}
Operator joined: Test
```

---

## Conclusioni

Il sistema di chat con operatore è ora completamente integrato nel client Flussu. Permette una transizione fluida da un'interazione automatica a una conversazione assistita da un operatore umano, mantenendo la continuità dell'esperienza utente.

Per domande o supporto, consultare:
- Documentazione API: `/Docs/API/`
- Codice sorgente: `/src/Flussu/`
- Client: `/webroot/flucli/`

---

**Versione:** 1.0
**Data:** 16 Novembre 2025
**Autore:** Claude (Anthropic)
**Licenza:** Apache License 2.0
