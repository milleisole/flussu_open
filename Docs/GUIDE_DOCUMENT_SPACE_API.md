# Guida Integrazione Document Space — API per Sviluppatori

**Versione:** 4.6
**Data:** 2026-04-04

---

## Introduzione

Questa guida spiega come un'applicazione client può inviare documenti a una sessione Flussu, in modo che il contenuto dei documenti venga automaticamente incluso come contesto nelle successive chiamate AI del workflow.

Il processo si compone di due passaggi:

1. **Upload del file** — il file viene caricato sul server tramite un endpoint dedicato
2. **Invio del riferimento** — il path del file caricato viene inviato insieme al prossimo step del workflow

I documenti vengono processati automaticamente dal server (estrazione testo, conversione in formato strutturato) e il loro contenuto viene iniettato nei prompt AI per tutta la durata della sessione.

---

## Prerequisiti

- Un **Workflow ID** (`WID`) di un workflow attivo su Flussu
- Un **Session ID** (`SID`) ottenuto dalla prima chiamata al workflow (oppure una sessione già attiva)
- Il **base URL** del server Flussu (es. `https://mioserver.flussu.com`)

---

## Tipi di file supportati

| Estensione | Tipo | Come viene processato |
|---|---|---|
| `pdf` | Documento PDF | Estrazione testo pagina per pagina (max 50 pagine) |
| `docx` | Microsoft Word | Conversione in Markdown (heading, tabelle, liste, grassetto) |
| `txt`, `md`, `log` | Testo puro | Letto così com'è |
| `xlsx`, `ods` | Foglio di calcolo | Struttura fogli in JSON + dati celle in CSV (max 1000 righe/foglio) |
| `csv` | CSV | Letto così com'è |
| `jpg`, `jpeg`, `png`, `gif`, `webp` | Immagine | Codifica base64 + copia originale per analisi visiva |

**Dimensione massima:** 20 MB per file.

---

## Passo 1 — Upload del file

### Endpoint

```
POST {BASE_URL}/flucli/upload.SRV.php
```

### Request

- **Content-Type:** `multipart/form-data`
- **Campo file:** `file`

### Esempio con cURL

```bash
curl -X POST \
  https://mioserver.flussu.com/flucli/upload.SRV.php \
  -F "file=@/percorso/al/documento.pdf"
```

### Esempio con JavaScript (fetch)

```javascript
const formData = new FormData();
formData.append('file', fileObject);  // fileObject da <input type="file">

const response = await fetch('https://mioserver.flussu.com/flucli/upload.SRV.php', {
  method: 'POST',
  body: formData
});

const result = await response.json();
```

### Esempio con Python (requests)

```python
import requests

with open('documento.pdf', 'rb') as f:
    response = requests.post(
        'https://mioserver.flussu.com/flucli/upload.SRV.php',
        files={'file': ('documento.pdf', f, 'application/pdf')}
    )

result = response.json()
```

### Risposta — Successo

```json
{
  "status": "ok",
  "path": "/var/www/flussu/Uploads/temp/dsp_668a1b2c3d4e5_documento.pdf",
  "name": "documento.pdf",
  "size": 245760
}
```

Il campo `path` contiene il **percorso assoluto sul server** del file caricato. Questo valore deve essere conservato per il passo successivo.

### Risposta — Errore

```json
{
  "status": "error",
  "message": "File type not allowed: exe"
}
```

Possibili errori:
- `Only POST allowed` — metodo HTTP non valido
- `No file uploaded` — campo `file` mancante nel form
- `Upload error: N` — errore PHP nell'upload (codice errore N)
- `File too large (max 20MB)` — file supera il limite
- `File type not allowed: xxx` — estensione non supportata
- `Failed to save file` — errore nel salvataggio su disco

---

## Passo 2 — Invio del riferimento al workflow

Dopo aver caricato uno o più file, i loro `path` devono essere inviati al workflow Engine come parte dei parametri `TRM` nello step successivo.

### Endpoint

```
POST {BASE_URL}/flussueng
Content-Type: application/x-www-form-urlencoded
```

### Parametri

| Parametro | Tipo | Descrizione |
|---|---|---|
| `WID` | string | Workflow ID |
| `SID` | string | Session ID (ottenuto dalla prima chiamata) |
| `BID` | string | Block ID corrente (dalla risposta precedente) |
| `LNG` | string | Lingua (es. `IT`, `EN`) |
| `APP` | string | Identificativo applicazione (es. `CHAT`, `MYAPP`) |
| `TRM` | string (JSON) | Parametri dello step, **incluso `$docspace_files`** |

### Il campo `$docspace_files`

All'interno del JSON `TRM`, includere la chiave `$docspace_files` con un array dei path ottenuti dal Passo 1:

```json
{
  "$docspace_files": [
    "/var/www/flussu/Uploads/temp/dsp_668a1b2c3d4e5_documento.pdf",
    "/var/www/flussu/Uploads/temp/dsp_668a1b2c3d4e6_tabella.xlsx"
  ],
  "$campo1": "valore1"
}
```

### Esempio completo con cURL

```bash
# Passo 1: Upload
UPLOAD_RESULT=$(curl -s -X POST \
  https://mioserver.flussu.com/flucli/upload.SRV.php \
  -F "file=@contratto.pdf")

FILE_PATH=$(echo $UPLOAD_RESULT | jq -r '.path')

# Passo 2: Invio al workflow con il documento allegato
curl -X POST https://mioserver.flussu.com/flussueng \
  -d "WID=W1234567890AB" \
  -d "SID=a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  -d "BID=1234-5678-9abc-def0" \
  -d "LNG=IT" \
  -d "APP=MYAPP" \
  --data-urlencode "TRM={\"\$docspace_files\":[\"$FILE_PATH\"]}"
```

### Esempio completo con JavaScript

```javascript
const BASE_URL = 'https://mioserver.flussu.com';

// Passo 1: Upload del file
async function uploadFile(file) {
  const formData = new FormData();
  formData.append('file', file);
  const res = await fetch(`${BASE_URL}/flucli/upload.SRV.php`, {
    method: 'POST',
    body: formData
  });
  return await res.json();
}

// Passo 2: Invio step con documento
async function sendStepWithDocuments(wid, sid, bid, filePaths, otherTerms = {}) {
  const terms = { ...otherTerms };
  if (filePaths.length > 0) {
    terms['$docspace_files'] = filePaths;
  }

  const payload = new URLSearchParams({
    WID: wid,
    SID: sid,
    BID: bid,
    LNG: 'IT',
    APP: 'MYAPP',
    TRM: JSON.stringify(terms)
  });

  const res = await fetch(`${BASE_URL}/flussueng`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: payload.toString()
  });
  return await res.json();
}

// Utilizzo
async function main() {
  const fileInput = document.getElementById('file-input');
  const file = fileInput.files[0];

  // Upload
  const uploadResult = await uploadFile(file);
  if (uploadResult.status !== 'ok') {
    console.error('Upload fallito:', uploadResult.message);
    return;
  }

  // Invio al workflow
  const result = await sendStepWithDocuments(
    'W1234567890AB',                // WID
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',  // SID
    '1234-5678-9abc-def0',          // BID
    [uploadResult.path],            // file paths
    { '$campo1': 'valore utente' }  // altri parametri
  );

  console.log('Risposta workflow:', result);
}
```

### Esempio completo con Python

```python
import requests
import json

BASE_URL = 'https://mioserver.flussu.com'

def upload_file(file_path):
    """Passo 1: Carica un file sul server."""
    with open(file_path, 'rb') as f:
        filename = file_path.split('/')[-1]
        resp = requests.post(
            f'{BASE_URL}/flucli/upload.SRV.php',
            files={'file': (filename, f)}
        )
    return resp.json()

def send_step_with_documents(wid, sid, bid, file_paths, other_terms=None):
    """Passo 2: Invia uno step al workflow con documenti allegati."""
    terms = other_terms or {}
    if file_paths:
        terms['$docspace_files'] = file_paths

    resp = requests.post(
        f'{BASE_URL}/flussueng',
        data={
            'WID': wid,
            'SID': sid,
            'BID': bid,
            'LNG': 'IT',
            'APP': 'MYAPP',
            'TRM': json.dumps(terms)
        }
    )
    return resp.json()

# Utilizzo
upload_result = upload_file('contratto.pdf')
if upload_result['status'] != 'ok':
    print(f"Errore upload: {upload_result['message']}")
    exit(1)

result = send_step_with_documents(
    wid='W1234567890AB',
    sid='a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    bid='1234-5678-9abc-def0',
    file_paths=[upload_result['path']],
    other_terms={'$campo1': 'valore utente'}
)
print(f"Risposta: {result}")
```

---

## Upload multiplo

Si possono caricare più file nella stessa sessione. Ogni file va caricato separatamente al Passo 1, poi tutti i path vengono inviati insieme al Passo 2:

```javascript
// Upload di più file
const files = fileInput.files;  // FileList
const paths = [];

for (const file of files) {
  const result = await uploadFile(file);
  if (result.status === 'ok') {
    paths.push(result.path);
  }
}

// Invio di tutti i path in un unico step
await sendStepWithDocuments(wid, sid, bid, paths);
```

I documenti si **accumulano** nella sessione: se al primo step invio un PDF e al secondo step invio un XLSX, entrambi saranno disponibili come contesto per le successive chiamate AI.

---

## Cosa succede lato server

Il processing dei documenti avviene in due momenti:

**Al momento dell'upload** (`upload.SRV.php`):
- Il file viene salvato in `/Uploads/temp/` con prefisso `dsp_`
- Se il SID è disponibile (via POST, GET o cookie `flussu_sid`), il file viene processato immediatamente nel DocumentSpace

**Al prossimo step del workflow** (`Engine.php`):
- L'Engine scansiona `/Uploads/temp/` per file con prefisso `dsp_*`
- Ogni file trovato viene processato nel DocumentSpace della sessione corrente
- Il file viene cancellato da temp dopo il processing

Il processamento consiste in:
1. **Creazione dello spazio** — viene creata (se non esiste) la cartella `/Uploads/docspace/{SID}/`
2. **Conversione** — ogni file viene letto e convertito in base al tipo:
   - PDF → testo puro con separatori pagina
   - DOCX → Markdown strutturato
   - XLSX/ODS → JSON con metadati fogli + dati CSV
   - Immagini → base64 + copia originale
   - TXT/CSV → letto direttamente
3. **Aggiornamento manifest** — il file `manifest.json` viene aggiornato con i metadati del documento
4. **Aggiornamento sid_date** — il file `sid_date` viene aggiornato con il timestamp corrente
5. **Iniezione nei prompt AI** — quando il workflow chiama `sendToAi()`, il contenuto di tutti i documenti viene automaticamente preposto al prompt dell'utente

---

## File generati dall'AI

Quando il workflow genera file tramite AI (ad esempio immagini con DALL-E o Stability AI), questi vengono salvati automaticamente nella sottocartella `generated/` dello spazio documentale della sessione. Le applicazioni client possono elencarli e scaricarli tramite l'endpoint `docspace.SRV.php`.

### Listare i file generati

```
GET {BASE_URL}/flucli/docspace.SRV.php?action=list&sid={SID}
```

**Risposta:**

```json
{
  "status": "ok",
  "sid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "files": [
    {
      "filename": "gen_668a1b2c3d4e5-123456789.png",
      "url": "https://mioserver.flussu.com/Uploads/docspace/a1b2c3d4-.../generated/gen_668a1b2c3d4e5-123456789.png",
      "size": 245760,
      "mimeType": "image/png",
      "createdAt": "2026-04-04T10:30:00+00:00"
    },
    {
      "filename": "gen_668a1b2c3d4e6-987654321.png",
      "url": "https://mioserver.flussu.com/Uploads/docspace/a1b2c3d4-.../generated/gen_668a1b2c3d4e6-987654321.png",
      "size": 189440,
      "mimeType": "image/png",
      "createdAt": "2026-04-04T10:31:15+00:00"
    }
  ]
}
```

Se non ci sono file generati, `files` sarà un array vuoto.

### Scaricare un file generato

```
GET {BASE_URL}/flucli/docspace.SRV.php?action=download&sid={SID}&file={FILENAME}
```

| Parametro | Descrizione |
|---|---|
| `sid` | Session ID |
| `file` | Nome del file (il campo `filename` dalla risposta `list`) |

La risposta è il **contenuto binario del file** con gli header appropriati:
- `Content-Type`: il MIME type del file (es. `image/png`)
- `Content-Length`: dimensione in byte
- `Content-Disposition: inline; filename="gen_xxx.png"`

**Errore 404** se il file non esiste:
```json
{
  "status": "error",
  "message": "File not found"
}
```

### Esempio con cURL

```bash
# Lista file generati
curl -s "https://mioserver.flussu.com/flucli/docspace.SRV.php?action=list&sid=$SID" | jq

# Download di un file specifico
curl -o immagine.png \
  "https://mioserver.flussu.com/flucli/docspace.SRV.php?action=download&sid=$SID&file=gen_668a1b2c3d4e5.png"
```

### Esempio con JavaScript

```javascript
const BASE_URL = 'https://mioserver.flussu.com';

// Lista file generati
async function listGeneratedFiles(sid) {
  const res = await fetch(
    `${BASE_URL}/flucli/docspace.SRV.php?action=list&sid=${encodeURIComponent(sid)}`
  );
  const json = await res.json();
  if (json.status !== 'ok') {
    console.error('Errore:', json.message);
    return [];
  }
  return json.files;
}

// Download file (restituisce un Blob)
async function downloadGeneratedFile(sid, filename) {
  const res = await fetch(
    `${BASE_URL}/flucli/docspace.SRV.php?action=download&sid=${encodeURIComponent(sid)}&file=${encodeURIComponent(filename)}`
  );
  if (!res.ok) throw new Error('File non trovato');
  return await res.blob();
}

// Utilizzo: mostra immagine generata in un <img>
async function showGeneratedImage(sid) {
  const files = await listGeneratedFiles(sid);
  const images = files.filter(f => f.mimeType.startsWith('image/'));

  if (images.length > 0) {
    const blob = await downloadGeneratedFile(sid, images[0].filename);
    const imgUrl = URL.createObjectURL(blob);
    document.getElementById('generated-img').src = imgUrl;
  }
}
```

### Esempio con Python

```python
import requests

BASE_URL = 'https://mioserver.flussu.com'

def list_generated_files(sid):
    """Elenca i file generati nella sessione."""
    resp = requests.get(
        f'{BASE_URL}/flucli/docspace.SRV.php',
        params={'action': 'list', 'sid': sid}
    )
    data = resp.json()
    if data['status'] != 'ok':
        print(f"Errore: {data['message']}")
        return []
    return data['files']

def download_generated_file(sid, filename, save_as=None):
    """Scarica un file generato e lo salva su disco."""
    resp = requests.get(
        f'{BASE_URL}/flucli/docspace.SRV.php',
        params={'action': 'download', 'sid': sid, 'file': filename}
    )
    if resp.status_code != 200:
        print(f"Errore download: {resp.status_code}")
        return None

    local_path = save_as or filename
    with open(local_path, 'wb') as f:
        f.write(resp.content)
    return local_path

# Utilizzo
sid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'
files = list_generated_files(sid)

for f in files:
    print(f"  {f['filename']} ({f['mimeType']}, {f['size']} bytes)")
    download_generated_file(sid, f['filename'], f'./downloads/{f["filename"]}')
```

### Accesso diretto via URL

I file generati sono anche accessibili direttamente tramite il loro URL completo (restituito sia dal campo `url` nella risposta `list`, sia dalla variabile workflow dopo `generateImageWithAi`):

```
https://mioserver.flussu.com/Uploads/docspace/{SID}/generated/{FILENAME}
```

Questo URL può essere usato direttamente in tag `<img>`, link di download, o qualsiasi altro contesto che accetti un URL.

---

## Lifecycle dei documenti

| Evento | Cosa succede |
|---|---|
| Upload file | File salvato in `/Uploads/temp/` |
| Invio step con `$docspace_files` | File processato e spostato in `/Uploads/docspace/{SID}/` |
| `generateImageWithAi()` nel workflow | Immagine generata salvata in `/Uploads/docspace/{SID}/generated/` |
| Ogni interazione con DocumentSpace | File `sid_date` aggiornato con timestamp corrente |
| `sendToAi()` nel workflow | Contenuto documenti iniettato nel prompt |
| Client chiama `docspace.SRV.php?action=list` | Riceve lista file generati con URL |
| Client chiama `docspace.SRV.php?action=download` | Riceve il contenuto binario del file |
| Fine sessione (workflow raggiunge end block) | **Tutta** la cartella `/Uploads/docspace/{SID}/` cancellata (incluso `generated/`) |
| Cleanup periodico (ogni minuto) | Cartelle con `sid_date` > 4 ore fa vengono cancellate |

I documenti e i file generati **non sopravvivono alla sessione**. Se serve conservare i file, il workflow deve copiarli in una posizione permanente prima della fine della sessione.

---

## Limiti

| Limite | Valore |
|---|---|
| Dimensione massima per file | 20 MB |
| Pagine PDF processate | max 50 |
| Righe per foglio di calcolo | max 1000 |
| Testo estratto per file | max 500 KB |
| Contesto totale iniettato nel prompt AI | max 50000 caratteri |
| Durata massima inattivita prima del cleanup | 4 ore |

---

## Errori comuni

| Problema | Causa | Soluzione |
|---|---|---|
| `File type not allowed` | Estensione non nell'elenco dei supportati | Verificare i tipi supportati (vedi tabella sopra) |
| `File too large` | File supera 20 MB | Comprimere il file o dividerlo |
| `This session has expired` | Sessione terminata o scaduta | Creare una nuova sessione (inviare senza SID) |
| Documento non incluso nel prompt AI | `$docspace_files` non presente nel TRM | Verificare che il JSON in TRM contenga la chiave `$docspace_files` con i path |
| Path del file non valido | Il path restituito dall'upload non corrisponde | Usare il valore esatto del campo `path` dalla risposta di upload, senza modificarlo |

---

## Diagrammi di sequenza

### Invio documento

```
Client                          upload.SRV.php              Engine
  │                                    │                       │
  │  POST file (multipart)             │                       │
  │───────────────────────────────────►│                       │
  │                                    │ Salva in temp/dsp_*   │
  │  { status:"ok", ... }              │                       │
  │◄───────────────────────────────────│                       │
  │                                                            │
  │  POST flussueng (submit qualsiasi)                         │
  │───────────────────────────────────────────────────────────►│
  │                                    Scansiona temp/dsp_*
  │                                    DocumentSpace::addDocument
  │                                    → Processa file
  │                                    → Salva in docspace/{SID}
  │                                    → Cancella da temp
  │                                    Workflow: sendToAi() con contesto
  │  { sid, bid, elms }                                        │
  │◄───────────────────────────────────────────────────────────│
```

### Ricezione file generati

```
Client                          docspace.SRV.php            Engine
  │                                    │                       │
  │  (Il workflow genera un'immagine con AI)                   │
  │                                    │   generateImageWithAi │
  │                                    │   → Salva in          │
  │                                    │     generated/{SID}/  │
  │                                                            │
  │  GET ?action=list&sid={SID}        │                       │
  │───────────────────────────────────►│                       │
  │  { files: [{filename, url, ...}] } │                       │
  │◄───────────────────────────────────│                       │
  │                                                            │
  │  GET ?action=download&sid=..&file= │                       │
  │───────────────────────────────────►│                       │
  │  [contenuto binario del file]      │                       │
  │◄───────────────────────────────────│                       │
```
