# API V40 - Riferimento Completo

**Versione**: 4.5.1
**Ultimo aggiornamento**: 2025-11-15
**Namespace**: `Flussu\Api\V40`

---

## Indice

1. [Panoramica Architetturale](#panoramica-architetturale)
2. [Engine - Esecuzione Workflow](#engine---esecuzione-workflow)
3. [Flow - Gestione Workflow](#flow---gestione-workflow)
4. [Sess - Gestione Sessioni](#sess---gestione-sessioni)
5. [Conn - Connessioni Remote OTP](#conn---connessioni-remote-otp)
6. [Stat - Statistiche](#stat---statistiche)
7. [Esempi Completi di Integrazione](#esempi-completi-di-integrazione)

---

## Panoramica Architetturale

Le API V40 sono il livello di interfaccia HTTP per Flussu Server. Tutte le classi sono progettate per:

- **Gestire richieste HTTP** con CORS abilitato
- **Processare dati JSON** in input e output
- **Coordinare** con il motore workflow (`Flussuserver/`)
- **Autenticare** utenti quando necessario

### Flusso di Routing

```
HTTP Request → /webroot/api.php
    ↓
    Routing basato su endpoint
    ↓
    ┌─────────────────────────────────┐
    │  API V40 Classes                │
    │  ├─ Engine (workflow execution) │
    │  ├─ Flow (workflow management)  │
    │  ├─ Sess (session management)   │
    │  ├─ Conn (remote OTP)           │
    │  └─ Stat (statistics)           │
    └─────────────────────────────────┘
    ↓
    Flussuserver Core Engine
```

### Headers CORS Standard

Tutte le API rispondono con questi headers:

```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: *
Access-Control-Allow-Headers: *
Access-Control-Max-Age: 200
Access-Control-Expose-Headers: Content-Security-Policy, Location
Content-Type: application/json; charset=UTF-8
```

---

## Engine - Esecuzione Workflow

**File**: `/src/Flussu/Api/V40/Engine.php`
**Responsabilità**: Esecuzione dei workflow e gestione del ciclo di vita delle sessioni

### Endpoint Principale

**URL**: `/flussueng` (via `/webroot/api.php`)
**Metodo**: `POST` o `GET`
**Classe**: `Flussu\Api\V40\Engine`
**Metodo**: `exec(Request $Req, $file_rawdata=null)`

### Parametri

| Parametro | Tipo | Obbligatorio | Descrizione |
|-----------|------|--------------|-------------|
| `WID` | string/numeric | Sì* | Workflow ID. Può essere numerico o stringa. Se numerico viene convertito internamente |
| `SID` | string | No | Session ID. Se vuoto, viene creata una nuova sessione |
| `CMD` | string | No | Comando da eseguire (es: 'info', 'set') |
| `TRM` | string/JSON | No | Parametri aggiuntivi in formato JSON |
| `BID` | string | No | Block ID da eseguire. Se non fornito, usa il blocco corrente |
| `LNG` | string | No | Lingua (IT, EN, FR, ES, DE). Default: IT |
| `APP` | string | No | Identificatore applicazione richiedente |
| `SET` | JSON | No | Impostazioni aggiuntive in formato JSON |
| `file_rawdata` | binary | No | File da caricare (multipart/form-data) |

*\*WID obbligatorio tranne per CMD=info*

### Comandi Speciali

#### 1. CMD=info - Ottieni informazioni workflow

**Descrizione**: Recupera metadati del workflow senza eseguirlo

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "workflow_123",
    "CMD": "info"
  }'
```

**Risposta**:
```json
{
  "tit": "Nome Workflow",
  "langs": "IT,EN,FR",
  "def_lang": "IT"
}
```

#### 2. CMD=set - Imposta variabili di sessione

**Descrizione**: Imposta variabili nella sessione corrente

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "workflow_123",
    "SID": "550e8400-e29b-41d4-a716-446655440000",
    "CMD": "set",
    "SET": "{\"variabile1\":\"valore1\",\"variabile2\":\"valore2\"}"
  }'
```

### Esecuzione Standard (Avvio Nuovo Workflow)

**Esempio 1: Avvio workflow senza parametri**

```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "workflow_123",
    "LNG": "IT"
  }'
```

**Risposta**:
```json
{
  "sid": "550e8400-e29b-41d4-a716-446655440000",
  "bid": "block_001",
  "elms": {
    "L$0": ["Benvenuto nel workflow!", {"display_info": {"type": "text"}}],
    "ITT$1": ["Inserisci il tuo nome", {"display_info": {"type": "input"}}, "[val]:"],
    "ITB$2": ["Continua", {"display_info": {"type": "button"}}, "submit"]
  }
}
```

**Esempio 2: Avvio con parametri iniziali**

```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "workflow_123",
    "LNG": "EN",
    "TRM": "{\"$user_email\":\"user@example.com\",\"$source\":\"web\"}"
  }'
```

### Continuazione Workflow (con SID)

**Esempio 3: Invio dati e continuazione**

```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "workflow_123",
    "SID": "550e8400-e29b-41d4-a716-446655440000",
    "BID": "block_002",
    "TRM": "{\"$nome\":\"Mario\",\"$cognome\":\"Rossi\"}"
  }'
```

**Risposta (blocco successivo)**:
```json
{
  "sid": "550e8400-e29b-41d4-a716-446655440000",
  "bid": "block_003",
  "elms": {
    "L$0": ["Ciao Mario Rossi!", {"display_info": {"type": "text"}}],
    "M$1": ["https://flussu.example.com/uploads/report.pdf", {"display_info": {"type": "file"}}]
  }
}
```

### Upload File

**Esempio 4: Upload file durante workflow**

```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -F "WID=workflow_123" \
  -F "SID=550e8400-e29b-41d4-a716-446655440000" \
  -F "BID=block_upload" \
  -F "file_rawdata=@/path/to/document.pdf"
```

**Risposta**:
```json
{
  "sid": "550e8400-e29b-41d4-a716-446655440000",
  "bid": "block_005",
  "elms": {
    "L$0": ["File caricato con successo!", {"display_info": {"type": "text"}}],
    "M$1": ["https://flussu.example.com/uploads/flussus_01/document.pdf", {"display_info": {"type": "file"}}]
  }
}
```

Il file viene salvato e l'URL è disponibile nella variabile di sessione `[nomevar]_uri`.

### Cambio Lingua Runtime

**Esempio 5: Cambio lingua durante l'esecuzione**

```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "SID": "550e8400-e29b-41d4-a716-446655440000",
    "LNG": "EN"
  }'
```

La lingua viene cambiata e il workflow continua nella nuova lingua.

### Tipi di Elementi (elms)

Gli elementi ritornati in `elms` hanno questa struttura:

```
"TIPO$ID": [contenuto, css/metadata, valore_opzionale]
```

**Tipi comuni**:

- **L** - Label/Testo
- **ITT** - Input Text
- **ITM** - Input Multiline
- **ITS** - Input Select
- **ITB** - Input Button
- **M** - Media (immagine, video, file, QR code)

**Metadata display_info.type**:
- `text` - Testo semplice
- `input` - Campo input
- `button` - Pulsante
- `image` - Immagine
- `video` - Video
- `file` - File generico
- `qrcode` - Codice QR

### Gestione Errori

**Errore: Sessione scaduta**
```json
{
  "error": "This session has expired - E89"
}
```

**Errore: Workflow inattivo**
```json
{
  "error": "This workflow is inactive - E99"
}
```

**Errore: Workflow in errore**
```json
{
  "error": "Workflow load on error - E00"
}
```

---

## Flow - Gestione Workflow

**File**: `/src/Flussu/Api/V40/Flow.php`
**Responsabilità**: CRUD workflow, blocchi, progetti, gestione utenti e traduzioni

### Endpoint

**URL**: Varia in base al comando (gestito da routing)
**Metodo**: `POST` o `GET`
**Autenticazione**: Richiesta per la maggior parte dei comandi

### Parametri Comuni

| Parametro | Descrizione |
|-----------|-------------|
| `C` | Comando da eseguire (vedi lista sotto) |
| `L` | Lingua (LNG) |
| `UUID` | Block UUID |
| `WFAUID` | Workflow AUID |
| `WID` | Workflow ID |
| `CWID` | Coded Workflow ID |
| `ON_UUID` | UUID di riferimento (per operazioni su exit/element) |
| `N` (NAM) | Nome workflow/progetto |
| `DBG` | Debug mode (restituisce dati debug) |

### Comandi Disponibili

#### 1. C=UUID - Genera UUID

**Descrizione**: Genera uno o più UUID v4

**Parametri**:
- `QTY`: Quantità di UUID da generare (1-50)

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=UUID&QTY=5"
```

**Risposta**:
```json
{
  "uuid": [
    "550e8400-e29b-41d4-a716-446655440000",
    "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
    "6ba7b811-9dad-11d1-80b4-00c04fd430c8",
    "6ba7b812-9dad-11d1-80b4-00c04fd430c8",
    "6ba7b813-9dad-11d1-80b4-00c04fd430c8"
  ]
}
```

#### 2. C=L - Lista Workflow Utente

**Descrizione**: Recupera tutti i workflow dell'utente autenticato

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=L" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
[
  {
    "id": 123,
    "name": "Workflow Registrazione",
    "wid": "[w_abc123]",
    "active": 1,
    "created": "2025-01-15 10:30:00"
  },
  {
    "id": 124,
    "name": "Workflow Pagamenti",
    "wid": "[w_def456]",
    "active": 1,
    "created": "2025-02-01 14:20:00"
  }
]
```

#### 3. C=C - Crea Nuovo Workflow

**Descrizione**: Crea un nuovo workflow

**Parametri**:
- `N`: Nome del workflow
- Body (POST): JSON con dettagli workflow

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=C&N=MioWorkflow" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Workflow Test",
    "description": "Workflow di esempio",
    "supp_langs": "IT,EN"
  }'
```

**Risposta**:
```json
{
  "result": "OK",
  "wid": "[w_xyz789]",
  "id": 125
}
```

#### 4. C=G - Get Workflow/Blocco

**Descrizione**: Recupera workflow completo o singolo blocco

**Variante A: Get Workflow Completo**

**Parametri**:
- `WID` o `WFAUID`: Identificatore workflow
- `L`: Lingua (opzionale)

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=G&WID=[w_abc123]&L=IT" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "workflow": [
    {
      "id": 123,
      "name": "Workflow Registrazione",
      "wid": "[w_abc123]",
      "description": "Gestisce registrazione utenti",
      "supp_langs": "IT,EN,FR",
      "def_lang": "IT",
      "active": 1,
      "app_id": 0
    }
  ],
  "blocks": [
    {
      "uuid": "block-uuid-001",
      "name": "Benvenuto",
      "type": "message",
      "elements": [...]
    },
    {
      "uuid": "block-uuid-002",
      "name": "Richiesta Dati",
      "type": "input",
      "elements": [...]
    }
  ]
}
```

**Variante B: Get Singolo Blocco**

**Parametri**:
- `UUID`: Block UUID

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=G&UUID=block-uuid-001&L=IT" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "uuid": "block-uuid-001",
  "name": "Benvenuto",
  "elements": [
    {
      "type": "label",
      "value": "Benvenuto nel sistema!",
      "css": {...}
    }
  ],
  "exits": [
    {
      "name": "next",
      "target_uuid": "block-uuid-002"
    }
  ]
}
```

#### 5. C=S - Get Workflow (Solo Esecuzione)

**Descrizione**: Come C=G ma restituisce solo dati necessari all'esecuzione (senza metadati editing)

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=S&WID=[w_abc123]&L=EN"
```

#### 6. C=E - Get Workflow (Modalità Editing)

**Descrizione**: Recupera workflow con tutti i metadati per editing

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=E&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 7. C=U - Update Workflow/Blocco

**Descrizione**: Aggiorna workflow o singolo blocco

**Variante A: Update Workflow**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=U&WID=[w_abc123]" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Workflow Registrazione Aggiornato",
    "description": "Nuova descrizione",
    "active": 1
  }'
```

**Risposta**:
```json
{
  "result": "OK",
  "message": "Workflow updated successfully"
}
```

**Variante B: Update Blocco**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=U&UUID=block-uuid-001" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Benvenuto Aggiornato",
    "elements": [...]
  }'
```

#### 8. C=D - Delete Workflow/Blocco/Element

**Descrizione**: Elimina workflow, blocco o elemento

**Variante A: Delete Workflow**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=D&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Variante B: Delete Blocco**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=D&UUID=block-uuid-001" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Variante C: Delete Exit da Blocco**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=D&ON_UUID=block-uuid-001&EXIT=exit_name" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Variante D: Delete Element da Blocco**

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=D&ON_UUID=block-uuid-001&ELEMENT=element_id" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 9. C=P - Duplica Blocco (Preview)

**Descrizione**: Duplica un blocco senza salvarlo nel database

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=P&UUID=block-uuid-001" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "newUuid": "block-uuid-new-001",
  "block": {...}
}
```

#### 10. C=PR - Duplica Blocco (Registro)

**Descrizione**: Duplica un blocco e lo salva nel database

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=PR&UUID=block-uuid-001" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 11. C=IN - Import Workflow (Aggiungi)

**Descrizione**: Importa blocchi in un workflow esistente (aggiunge senza sostituire)

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=IN&WID=[w_abc123]" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '"BASE64_ENCODED_WORKFLOW_DATA"'
```

#### 12. C=IS - Import Workflow (Sostituisci)

**Descrizione**: Importa workflow sostituendo completamente l'esistente

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=IS&WID=[w_abc123]" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '"BASE64_ENCODED_WORKFLOW_DATA"'
```

#### 13. C=DEPLOY - Deploy Workflow

**Descrizione**: Esporta workflow verso altro server Flussu

**Parametri**:
- `TO`: URL server destinazione
- `WID`: Workflow da deployare

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=DEPLOY&WID=[w_abc123]&TO=https://target-server.com/flussu" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 14. C=RD - Receive Deploy

**Descrizione**: Riceve workflow da deploy esterno

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/flow?C=RD&WID=[w_abc123]" \
  -H "Content-Type: application/json" \
  -d '{...workflow_data...}'
```

#### 15. C=PDU - Project/Data/User Management

**Descrizione**: Gestione progetti, utenti e dati (comando multi-funzione)

**Parametri**:
- `PDUC`: Sub-comando (0-9)
- `DT`: Tipo dato (PL, UL, BL, BK, SG)
- Altri parametri specifici per sub-comando

**Sub-comandi (PDUC)**:

**0 - Get Data**

**DT=PL - Lista Progetti**
```bash
curl "https://flussu.example.com/flow?C=PDU&PDUC=0&DT=PL" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**DT=UL - Lista Utenti Progetto**
```bash
curl "https://flussu.example.com/flow?C=PDU&PDUC=0&DT=UL&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**DT=BL - Lista Backup**
```bash
curl "https://flussu.example.com/flow?C=PDU&PDUC=0&DT=BL&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**DT=BK - Get Backup Specifico**
```bash
curl "https://flussu.example.com/flow?C=PDU&PDUC=0&DT=BK&WID=[w_abc123]&BID=backup_id" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**1 - Rinomina Progetto**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=1&PI=project_id&NM=NuovoNome" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**2 - Crea Progetto**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=2&NM=NomeProgetto&DS=Descrizione&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**4 - Sposta Workflow in Progetto**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=4&MTP=target_project_id&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**5 - Cambia Proprietario Workflow**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=5&UE=nuovo@proprietario.com&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**6 - Aggiungi Utente a Progetto**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=6&UE=utente@email.com&PI=project_id" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**7 - Rimuovi Utente da Progetto**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=7&UD=username&PI=project_id" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**8 - Traduci Etichette/Aggiungi Lingue**
```bash
curl -X POST "https://flussu.example.com/flow?C=PDU&PDUC=8&TL={\"lf\":\"IT\",\"lt\":\"EN\"}&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "translate_id": "uuid-traduzione",
  "message": "Ongoing."
}
```

**9 - Stato Traduzione**
```bash
curl "https://flussu.example.com/flow?C=PDU&PDUC=9&ELABID=uuid-traduzione" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "Total": 150,
  "Done": 120,
  "State": 30
}
```

#### 16. C=APP - Gestione Applicazioni

**Descrizione**: CRUD per applicazioni mobili/web

**Sub-comandi (APC)**:

**0 - Lista App**
```bash
curl "https://flussu.example.com/flow?C=APP&APC=0"
```

**1 - Get App Info**
```bash
curl "https://flussu.example.com/flow?C=APP&APC=1&COD=app_code"
```

**2 - Crea App**
```bash
curl -X POST "https://flussu.example.com/flow?C=APP&APC=2&WID=[w_abc123]&COD=app_code" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My App",
    "description": "App description",
    "logo": "logo_url"
  }'
```

**3 - Update App**
```bash
curl -X POST "https://flussu.example.com/flow?C=APP&APC=3&COD=app_code" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated App Name"
  }'
```

**4 - Delete App**
```bash
curl -X POST "https://flussu.example.com/flow?C=APP&APC=4&COD=app_code" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**5 - Genera Codice App**
```bash
curl "https://flussu.example.com/flow?C=APP&APC=5&WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "status": "new key",
  "SRV": "flussu.example.com",
  "WID": "[w_abc123]",
  "APP": "ABC123XYZ"
}
```

**9 - Get Public App Info**
```bash
curl "https://flussu.example.com/flow?C=APP&APC=9&COD=app_code"
```

#### 17. C=US - User Flussus

**Descrizione**: Lista workflow associati all'utente

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=US" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 18. C=UWL - User Workflow List

**Descrizione**: Lista workflow di proprietà dell'utente (con progetti)

**Esempio**:
```bash
curl "https://flussu.example.com/flow?C=UWL" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
[
  {
    "project_id": 1,
    "project_name": "Progetto A",
    "workflows": [
      {
        "id": 123,
        "name": "Workflow 1",
        "wid": "[w_abc123]"
      }
    ]
  },
  {
    "project_id": 2,
    "project_name": "Progetto B",
    "workflows": [...]
  }
]
```

---

## Sess - Gestione Sessioni

**File**: `/src/Flussu/Api/V40/Sess.php`
**Responsabilità**: Recupero storico sessioni e log esecuzioni

### Endpoint

**URL**: `/sess` (gestito da routing)
**Metodo**: `GET`
**Autenticazione**: Richiesta

### Funzionalità

#### 1. Lista Sessioni Utente

**Descrizione**: Recupera tutte le sessioni di workflow dell'utente

**Parametri**:
- `WID` (opzionale): Filtra per workflow specifico

**Esempio A: Tutte le sessioni**
```bash
curl "https://flussu.example.com/sess" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
[
  {
    "sess_id": "550e8400-e29b-41d4-a716-446655440000",
    "wid": "[w_abc123]",
    "sess_start": "2025-11-15 10:30:00",
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "status": "completed",
    "block_id": "block-final"
  },
  {
    "sess_id": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
    "wid": "[w_def456]",
    "sess_start": "2025-11-15 14:20:00",
    "ip": "192.168.1.101",
    "user_agent": "curl/7.68.0",
    "status": "active",
    "block_id": "block-003"
  }
]
```

**Esempio B: Sessioni workflow specifico**
```bash
curl "https://flussu.example.com/sess?WID=[w_abc123]" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 2. Storico Sessione

**Descrizione**: Recupera lo storico completo di una sessione specifica

**Parametri**:
- `SID`: Session ID

**Esempio**:
```bash
curl "https://flussu.example.com/sess?SID=550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
[
  {
    "timestamp": "2025-11-15 10:30:00",
    "block_id": "block-001",
    "block_name": "Benvenuto",
    "action": "start",
    "duration_ms": 150
  },
  {
    "timestamp": "2025-11-15 10:30:05",
    "block_id": "block-002",
    "block_name": "Richiesta Dati",
    "action": "input",
    "input_data": {"$nome": "Mario", "$cognome": "Rossi"},
    "duration_ms": 2300
  },
  {
    "timestamp": "2025-11-15 10:30:10",
    "block_id": "block-003",
    "block_name": "Conferma",
    "action": "display",
    "duration_ms": 180
  }
]
```

---

## Conn - Connessioni Remote OTP

**File**: `/src/Flussu/Api/V40/Conn.php`
**Responsabilità**: Esecuzione comandi sicura tramite OTP (One-Time Password)

### Architettura a 2 Step

```
Step 1: Richiesta OTP
  Client → Server: userid + password + command
  Server → Client: OTP

Step 2: Esecuzione Comando
  Client → Server: OTP + data
  Server: Verifica OTP, esegue comando
  Server → Client: risultato
```

### Step 1: Richiesta OTP

**Endpoint**: `/conn`
**Parametri**:
- `C=G` (Get OTP)
- Body: JSON con credenziali

**Esempio**:
```bash
curl -X POST "https://flussu.example.com/conn?C=G" \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "user123",
    "password": "user_token_abc",
    "command": "chkEmail"
  }'
```

**Risposta**:
```json
{
  "result": "OK",
  "key": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
}
```

### Step 2: Esecuzione Comando

**Parametri**:
- `C=E` (Execute)
- `K=OTP` (ottenuto da Step 1)
- Body: JSON con dati per il comando

**Comandi Disponibili**:

#### 1. chkEmail - Verifica Email Esistente

**Esempio**:
```bash
# Step 1: Ottieni OTP
OTP=$(curl -s -X POST "https://flussu.example.com/conn?C=G" \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "user123",
    "password": "token_abc",
    "command": "chkEmail"
  }' | jq -r '.key')

# Step 2: Esegui comando
curl -X POST "https://flussu.example.com/conn?C=E&K=$OTP" \
  -H "Content-Type: application/json" \
  -d '{
    "userEmail": "test@example.com"
  }'
```

**Risposta**:
```json
{
  "result": true
}
```
(true = email esiste, false = email non esiste)

#### 2. regUser - Registra Nuovo Utente

**Esempio**:
```bash
# Step 1: Ottieni OTP
OTP=$(curl -s -X POST "https://flussu.example.com/conn?C=G" \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "admin_user",
    "password": "admin_token",
    "command": "regUser"
  }' | jq -r '.key')

# Step 2: Registra utente
curl -X POST "https://flussu.example.com/conn?C=E&K=$OTP" \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "newuser123",
    "basePass": "hashed_password",
    "userEmail": "newuser@example.com",
    "name": "Mario",
    "surname": "Rossi"
  }'
```

**Risposta**:
```json
{
  "result": "OK"
}
```

#### 3. chPassUser - Cambia Password Utente

**Esempio**:
```bash
# Step 1: Ottieni OTP
OTP=$(curl -s -X POST "https://flussu.example.com/conn?C=G" \
  -H "Content-Type: application/json" \
  -d '{
    "userid": "admin_user",
    "password": "admin_token",
    "command": "chPassUser"
  }' | jq -r '.key')

# Step 2: Cambia password
curl -X POST "https://flussu.example.com/conn?C=E&K=$OTP" \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "user123",
    "basePass": "new_hashed_password"
  }'
```

**Risposta**:
```json
{
  "result": "OK"
}
```

### Note di Sicurezza

- **OTP usa-e-getta**: Ogni OTP può essere usato UNA SOLA VOLTA
- **Autenticazione token**: Richiede userid + token valido
- **Scadenza OTP**: Gli OTP non utilizzati scadono (tempo configurabile)
- **Comando pre-autorizzato**: Il comando viene dichiarato al momento della richiesta OTP

---

## Stat - Statistiche

**File**: `/src/Flussu/Api/V40/Stat.php`
**Responsabilità**: Estrazione statistiche esecuzioni workflow

### Endpoint Principale

**URL**: `/stat`
**Metodo**: `GET`
**Autenticazione**: Richiesta

### Parametri

| Parametro | Descrizione |
|-----------|-------------|
| `WID` | Workflow ID |
| `CTY` | Chart Type (1, 2, 3) |
| `IVL` | Interval (giorni) |
| `LNG` | Lingua |

### Tipi di Statistiche (CTY)

#### 1. CTY=1 - Dati Totali per Giorno

**Descrizione**: Conta totale esecuzioni per giorno

**Esempio**:
```bash
curl "https://flussu.example.com/stat?WID=[w_abc123]&CTY=1&IVL=30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "2025-11-01": 45,
  "2025-11-02": 67,
  "2025-11-03": 52,
  "2025-11-04": 78,
  "2025-11-05": 91,
  ...
}
```

#### 2. CTY=2 - Obiettivi Raggiunti

**Descrizione**: Statistiche obiettivi configurati nel workflow

**Esempio**:
```bash
curl "https://flussu.example.com/stat?WID=[w_abc123]&CTY=2&IVL=365" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "labels": ["Conversioni", "Abbandoni", "Completati"],
  "values": [
    ["2025-11-01", 12, "[45,23,22]"],
    ["2025-11-02", 15, "[50,18,32]"],
    ...
  ]
}
```

#### 3. CTY=3 - Web vs Chat

**Descrizione**: Confronto esecuzioni da interfaccia web vs chatbot

**Esempio**:
```bash
curl "https://flussu.example.com/stat?WID=[w_abc123]&CTY=3&IVL=90" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Risposta**:
```json
{
  "labels": ["web", "chat"],
  "web": {
    "2025-11-01": 30,
    "2025-11-02": 45,
    ...
  },
  "chat": {
    "2025-11-01": 15,
    "2025-11-02": 22,
    ...
  }
}
```

### Export Excel (Endpoint Esterno)

**URL**: `/stat/export` (con auth key)
**Metodo**: `GET`
**Output**: File XLSX

**Parametri**:
- `auk`: Auth Key temporanea
- `WID`: Workflow ID

**Esempio**:
```bash
curl "https://flussu.example.com/stat/export?WID=[w_abc123]&auk=temporal_auth_key_here" \
  --output statistics.xlsx
```

**Contenuto Excel**:
```
| Data       | Web | Chat | Total | Conversioni | Abbandoni | Completati |
|------------|-----|------|-------|-------------|-----------|------------|
| 2025-11-01 | 30  | 15   | 45    | 12          | 10        | 23         |
| 2025-11-02 | 45  | 22   | 67    | 15          | 12        | 40         |
```

---

## Esempi Completi di Integrazione

### Esempio 1: Workflow Completo - Registrazione Utente

**Scenario**: Workflow con raccolta dati, validazione email, registrazione

**Step 1: Avvio Workflow**
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "wf_registration",
    "LNG": "IT"
  }'
```

**Risposta**:
```json
{
  "sid": "sess-001",
  "bid": "block-welcome",
  "elms": {
    "L$0": ["Benvenuto! Crea il tuo account", {}],
    "ITT$1": ["Email", {}, ""],
    "ITT$2": ["Nome", {}, ""],
    "ITT$3": ["Cognome", {}, ""],
    "ITB$4": ["Continua", {}, "next"]
  }
}
```

**Step 2: Invio Dati Utente**
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "wf_registration",
    "SID": "sess-001",
    "BID": "block-welcome",
    "TRM": "{\"$email\":\"mario@example.com\",\"$nome\":\"Mario\",\"$cognome\":\"Rossi\"}"
  }'
```

**Risposta** (il workflow internamente verifica email tramite Conn API):
```json
{
  "sid": "sess-001",
  "bid": "block-password",
  "elms": {
    "L$0": ["Email disponibile! Scegli una password", {}],
    "ITT$1": ["Password", {"type": "password"}, ""],
    "ITB$2": ["Registrati", {}, "register"]
  }
}
```

**Step 3: Invio Password e Registrazione**
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "SID": "sess-001",
    "TRM": "{\"$password\":\"SecurePass123!\"}"
  }'
```

**Risposta**:
```json
{
  "sid": "sess-001",
  "bid": "block-success",
  "elms": {
    "L$0": ["✓ Registrazione completata!", {}],
    "L$1": ["Controlla la tua email per confermare l'account", {}]
  }
}
```

### Esempio 2: Upload Documento e Elaborazione

**Scenario**: Upload PDF, estrazione testo OCR, analisi AI

**Step 1: Richiesta Upload**
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "wf_doc_analysis",
    "LNG": "EN"
  }'
```

**Risposta**:
```json
{
  "sid": "sess-doc-001",
  "bid": "block-upload",
  "elms": {
    "L$0": ["Upload your document for analysis", {}],
    "ITF$1": ["Select PDF file", {"accept": ".pdf"}, ""],
    "ITB$2": ["Upload", {}, "upload"]
  }
}
```

**Step 2: Upload File**
```bash
curl -X POST "https://flussu.example.com/flussueng" \
  -F "SID=sess-doc-001" \
  -F "BID=block-upload" \
  -F "file_rawdata=@/path/to/invoice.pdf"
```

**Risposta** (workflow esegue OCR + AI analysis):
```json
{
  "sid": "sess-doc-001",
  "bid": "block-results",
  "elms": {
    "L$0": ["✓ Document processed successfully", {}],
    "L$1": ["Document Type: Invoice", {}],
    "L$2": ["Total Amount: €1,234.56", {}],
    "L$3": ["Date: 2025-11-15", {}],
    "M$4": ["https://flussu.example.com/uploads/flussus_01/invoice_processed.pdf", {"display_info": {"type": "file"}}]
  }
}
```

### Esempio 3: Client JavaScript Completo

```javascript
class FlussuClient {
  constructor(baseUrl, workflowId) {
    this.baseUrl = baseUrl;
    this.workflowId = workflowId;
    this.sessionId = null;
  }

  async start(language = 'IT', params = {}) {
    const data = {
      WID: this.workflowId,
      LNG: language,
      TRM: JSON.stringify(params)
    };

    const response = await fetch(`${this.baseUrl}/flussueng`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data)
    });

    const result = await response.json();
    this.sessionId = result.sid;
    return result;
  }

  async sendData(blockId, data) {
    const payload = {
      SID: this.sessionId,
      BID: blockId,
      TRM: JSON.stringify(data)
    };

    const response = await fetch(`${this.baseUrl}/flussueng`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    return await response.json();
  }

  async uploadFile(blockId, file) {
    const formData = new FormData();
    formData.append('SID', this.sessionId);
    formData.append('BID', blockId);
    formData.append('file_rawdata', file);

    const response = await fetch(`${this.baseUrl}/flussueng`, {
      method: 'POST',
      body: formData
    });

    return await response.json();
  }

  async changeLanguage(newLanguage) {
    const payload = {
      SID: this.sessionId,
      LNG: newLanguage
    };

    const response = await fetch(`${this.baseUrl}/flussueng`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    return await response.json();
  }

  async getInfo() {
    const payload = {
      WID: this.workflowId,
      CMD: 'info'
    };

    const response = await fetch(`${this.baseUrl}/flussueng`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    return await response.json();
  }
}

// Utilizzo
const client = new FlussuClient('https://flussu.example.com', 'my_workflow');

// Avvio workflow
const startResult = await client.start('EN', {
  $source: 'web',
  $referrer: document.referrer
});

console.log('Started:', startResult);
// Mostra elementi UI
renderElements(startResult.elms);

// Quando l'utente invia dati
const nextResult = await client.sendData(startResult.bid, {
  $name: 'John',
  $email: 'john@example.com'
});

renderElements(nextResult.elms);
```

### Esempio 4: Client PHP con Gestione Completa

```php
<?php

class FlussuApiClient {
    private $baseUrl;
    private $workflowId;
    private $sessionId;

    public function __construct($baseUrl, $workflowId) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->workflowId = $workflowId;
    }

    public function startWorkflow($language = 'IT', $params = []) {
        $data = [
            'WID' => $this->workflowId,
            'LNG' => $language
        ];

        if (!empty($params)) {
            $data['TRM'] = json_encode($params);
        }

        $result = $this->post('/flussueng', $data);
        $this->sessionId = $result['sid'] ?? null;

        return $result;
    }

    public function sendData($blockId, $data) {
        $payload = [
            'SID' => $this->sessionId,
            'BID' => $blockId,
            'TRM' => json_encode($data)
        ];

        return $this->post('/flussueng', $payload);
    }

    public function uploadFile($blockId, $filePath) {
        $ch = curl_init($this->baseUrl . '/flussueng');

        $postData = [
            'SID' => $this->sessionId,
            'BID' => $blockId,
            'file_rawdata' => new CURLFile($filePath)
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getWorkflowInfo() {
        $data = [
            'WID' => $this->workflowId,
            'CMD' => 'info'
        ];

        return $this->post('/flussueng', $data);
    }

    public function changeLanguage($newLanguage) {
        $data = [
            'SID' => $this->sessionId,
            'LNG' => $newLanguage
        ];

        return $this->post('/flussueng', $data);
    }

    private function post($endpoint, $data) {
        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }

        return json_decode($response, true);
    }
}

// Esempio d'uso
$client = new FlussuApiClient('https://flussu.example.com', 'registration_wf');

// Ottieni info workflow
$info = $client->getWorkflowInfo();
echo "Workflow: {$info['tit']}\n";
echo "Lingue supportate: {$info['langs']}\n";

// Avvia workflow
$result = $client->startWorkflow('IT', [
    '$source' => 'php_client',
    '$version' => '1.0'
]);

echo "Session ID: {$result['sid']}\n";
echo "Current Block: {$result['bid']}\n";

// Processa elementi
foreach ($result['elms'] as $key => $element) {
    echo "Element $key: {$element[0]}\n";
}

// Invia dati
$nextResult = $client->sendData($result['bid'], [
    '$nome' => 'Mario',
    '$cognome' => 'Rossi',
    '$email' => 'mario.rossi@example.com'
]);

// Upload file
if (file_exists('document.pdf')) {
    $uploadResult = $client->uploadFile($nextResult['bid'], 'document.pdf');
    echo "File uploaded: " . $uploadResult['elms']['M$1'][0] . "\n";
}
?>
```

---

## Note Finali

### Autenticazione

Molte API richiedono autenticazione utente. Metodi supportati:
- **Bearer Token**: Header `Authorization: Bearer YOUR_TOKEN`
- **Session Cookie**: Cookie di sessione PHP
- **API Key**: Header `X-API-Key` (per alcuni endpoint)

### Rate Limiting

Attualmente non implementato, ma consigliato per deployment in produzione.

### Versioning

Le API sono nella versione 4.0 (`V40`). Per garantire compatibilità futura, il namespace include la versione.

### CORS

Tutte le API hanno CORS completamente aperto (`*`). Per ambienti di produzione, configurare restrizioni appropriate.

### Error Handling

Errori comuni restituiti:
- `ERR:0` - Errore generico
- `ERR:1` - Parametri mancanti
- `ERR:5` - Dati POST mancanti
- `ERR:6` - Dati blocco mancanti
- `ERR:7` - UUID/WID mancante
- `ERR:800A` - Errore interno generico
- `E00` - Errore caricamento workflow
- `E89` - Sessione scaduta
- `E99` - Workflow inattivo

---

**Fine Documentazione API V40**

Per ulteriori informazioni, consultare:
- `CLAUDE.md` - Guida completa al codebase
- `WEBHOOK_INTEGRATION.md` - Integrazione webhooks
- `/webroot/client/api/php_sample.php` - Esempi pratici
- `/openapi.yaml` - Specifica OpenAPI
