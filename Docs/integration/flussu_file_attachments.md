# Invio di file allegati a Flussu

Guida per chi sviluppa un **sistema esterno** (client web, app mobile o
backend) che deve inoltrare a un'istanza Flussu un prompt utente accompagnato
da uno o più file allegati: PDF, documenti Office, fogli di calcolo,
testo strutturato, immagini.

> **Regola d'oro.** Il sender invia il file **così com'è**, in forma binaria.
> Non estrarre testo, non convertire PDF, non encodare immagini in base64,
> non inserire il contenuto del file dentro il `user_message`. Flussu si
> occupa lui di estrarre il testo e/o passare le immagini all'LLM nel
> formato che il provider AI richiede.

---

## 1. Flusso a due step

```
┌─────────────────────┐   (1) POST multipart       ┌─────────────────────────┐
│  Sistema esterno    │ ──────────────────────────▶ │ /flucli/upload.SRV.php  │
│  (sender)           │   file binario + SID        │                         │
│                     │ ◀──────────────────────────  │  salva, ritorna `path`  │
│                     │   { status, path, name }    └─────────────────────────┘
│                     │
│                     │   (2) POST step             ┌─────────────────────────┐
│                     │ ──────────────────────────▶ │  /api.php  (APP=CHAT)   │
│                     │   prompt +                  │                         │
│                     │   TRM.$docspace_files=[..]  │  estrae, costruisce     │
│                     │ ◀──────────────────────────  │  contesto, invia a LLM │
│                     │   reply LLM                 └─────────────────────────┘
└─────────────────────┘
```

Step 1 (`/flucli/upload.SRV.php`) — upload binario, ritorna un `path`
opaco. Step 2 (endpoint workflow standard) — invio del prompt referenziando
quei `path` nel campo `TRM.$docspace_files`.

> Non c'è (e non serve) un endpoint che faccia entrambe le cose in una
> chiamata sola: i due step sono distinti per design.

---

## 2. STEP 1 — upload del file binario

### Request

```
POST <BASE_URL>/flucli/upload.SRV.php
Content-Type: multipart/form-data
```

| Campo  | Tipo   | Obbligatorio          | Note                                          |
|--------|--------|-----------------------|-----------------------------------------------|
| `file` | binary | sì                    | Contenuto binario, **non** base64             |
| `sid`  | string | fortemente consigliato | Session ID corrente del workflow (vedi §4)   |

Endpoint pubblico, CORS aperto, accetta `POST` e `OPTIONS` (preflight).

### Vincoli

- **Max 20 MB per file.** Oltre → 400 con
  `{"status":"error","message":"File too large (max 20MB)"}`.
- **Estensioni accettate**:
  `pdf, docx, txt, md, log, xlsx, ods, csv, jpg, jpeg, png, gif, webp`.
  Altre estensioni vengono rifiutate (`File type not allowed: <ext>`).
- Il filename viene sanitizzato server-side (caratteri fuori da
  `[a-zA-Z0-9_\-\.]` sostituiti con `_`). Trasmetti pure il nome originale
  pulito.

### Response (success)

```json
{
  "status": "ok",
  "path":   "/abs/path/to/Uploads/temp/dsp_60a5f2e86a2b3_documento.pdf",
  "name":   "documento.pdf",
  "size":   123456,
  "docspace": {
    "success": true,
    "id":      "doc_60a5f2e86a2b3",
    "type":    "pdf"
  }
}
```

- `path` è il riferimento da usare nello step 2. Trattalo come **opaco**:
  è un path interno al filesystem del server Flussu, non un URL pubblico
  da risolvere lato sender.
- Se hai mandato il `sid`, Flussu processa subito il file nel DocumentSpace
  della sessione (estrazione testo / preparazione immagine) e te lo segnala
  in `docspace`. In quel caso il file in `temp/` è stato già spostato, ma
  `path` resta utilizzabile nello step 2 come identificatore: il server
  riconosce comunque la coppia file↔sessione.
- Se **non** hai mandato il `sid`, il file resta in `Uploads/temp/` come
  orfano e va referenziato esplicitamente nel passo successivo via
  `$docspace_files` (vedi §3). Senza referenza esplicita, viene cancellato
  da un cleanup periodico dopo qualche ora. Manda **sempre** il `sid`
  all'upload quando lo conosci — è la pratica corretta.

### Response (error)

```json
{ "status": "error", "message": "<descrizione>" }
```

Casistica:
- `Only POST allowed` — metodo HTTP errato
- `No file uploaded` — campo `file` mancante
- `File exceeds server upload_max_filesize (code 1)` — PHP-FPM ha rifiutato
  l'upload prima ancora di arrivare al codice. La response include un
  campo `limits` con i valori effettivi del server:
  ```json
  {
    "status":  "error",
    "message": "File exceeds server upload_max_filesize (code 1)",
    "limits":  {
      "upload_max_filesize": "25M",
      "post_max_size":       "30M",
      "app_max_mb":          20
    }
  }
  ```
  Il sender può confrontare la dimensione del file con
  `upload_max_filesize` e mostrare un errore comprensibile all'utente.
- `File was only partially uploaded (code 3)` — connessione interrotta
- `File too large (max 20MB)` — cap applicativo (vedi `app_max_mb`)
- `File type not allowed: <ext>` — estensione non in whitelist
- `Failed to save file` — errore di scrittura su disco

> **Nota sui due livelli di limite.** Esistono due cap distinti: il cap
> applicativo di Flussu (20 MB, hardcoded in `upload.SRV.php`) e i limiti
> di PHP (`upload_max_filesize`, `post_max_size`) dell'istanza che ospita
> Flussu. Il limite operativo reale è il **minimo dei due**. L'istanza
> Flussu di riferimento alza i limiti di PHP a 25M / 30M via
> `webroot/.user.ini`. Su altre installazioni il limite PHP potrebbe
> essere più basso — leggi sempre i valori che il server riporta nel
> campo `limits` della response di errore.

---

## 3. STEP 2 — invio del prompt con riferimento ai file

Usa il normale endpoint di esecuzione del workflow (lo stesso che già usi
per i messaggi di chat senza allegati). Nel JSON body, dentro la sezione
**terms** (`TRM`), aggiungi il campo speciale **`$docspace_files`**: un
array con i `path` ottenuti dallo step 1.

### Esempio di body

```json
{
  "WID": "<workflow_id>",
  "SID": "<session_id>",
  "APP": "CHAT",
  "TRM": {
    "user_message": "Riassumi questo contratto e dimmi le clausole a rischio.",
    "$docspace_files": [
      "/abs/path/to/Uploads/temp/dsp_60a5f2e86a2b3_contratto.pdf",
      "/abs/path/to/Uploads/temp/dsp_60a5f2e86a5d1_allegato.png"
    ]
  }
}
```

Dettagli:

- `$docspace_files` è accettato sia come array JSON sia come stringa JSON
  serializzata (il server prova `json_decode` se riceve una stringa).
- **Non inserire il contenuto dei file nel `user_message`.** Il sender non
  legge i file: si limita a passarne i `path`.
- Subito dopo l'ingestion, il server rimuove `$docspace_files` dai terms
  così non finisce nel prompt visto dall'LLM.
- Non esiste un fallback automatico per upload anonimi. Se hai caricato un
  file via §2 **senza** `sid`, devi referenziarne il `path` esplicitamente
  in `$docspace_files` in questo step, altrimenti il file resta orfano in
  `Uploads/temp/` e viene cancellato dal cleanup periodico. La pratica
  corretta è **sempre** mandare il `sid` all'upload (così il file finisce
  subito nel DocumentSpace della sessione) e poi citare comunque il `path`
  in `$docspace_files` per esplicitezza nello step di chat.

### Cosa fa Flussu con i file (informativa, non agire)

Il sender **non deve** replicare nulla di questa logica. È riportata solo
per consapevolezza:

1. Ogni file viene spostato in `Uploads/docspace/<SID>/` (storage
   per-sessione) e registrato nel `manifest.json` della sessione.
2. Estrazione automatica del contenuto a seconda del MIME:

   | Tipo                  | Trattamento server-side                       |
   |-----------------------|-----------------------------------------------|
   | PDF                   | testo estratto (max ~50 pagine)              |
   | DOCX                  | testo + tabelle estratti                     |
   | XLSX / ODS            | parsing tabellare → JSON (max ~1000 righe)   |
   | CSV / TXT / MD / LOG  | letto come testo (eventualmente troncato)    |
   | JPG/JPEG/PNG/GIF/WEBP | preparato per vision (base64 / parts a seconda del provider) |

3. Prima della chiamata all'LLM, Flussu costruisce un blocco
   `--- DOCUMENTI ALLEGATI --- ... --- FINE DOCUMENTI ALLEGATI ---`
   contenente i testi estratti e lo prepende al `user_message`. Per i file
   immagine, vengono passati al provider AI nel formato vision nativo
   (data URI per OpenAI/Claude, `parts` per Gemini, ecc.) — il dispatch è
   fatto dai controller in `src/Flussu/Api/Ai/*`.
4. I file restano nel DocumentSpace della sessione per tutta la sua vita:
   **non ri-uploadarli a ogni turno**.

---

## 4. SID — identità di sessione

Il `SID` è generato da Flussu al primo step del workflow ed è il
collante fra upload, prompt e contesto persistente. Per un sender
server-to-server:

1. Apri la sessione con un primo step del workflow (senza file) e leggi
   il `SID` dalla response JSON di Flussu.
2. Conservalo (es. in una mappa user→SID lato sender) e usalo in **tutti**
   gli upload (`/flucli/upload.SRV.php` con campo `sid`) e in **tutti** gli
   step successivi della stessa conversazione.
3. Lo stesso SID viene anche emesso come cookie `flussu_sid` dal server:
   in chiamate browser-side è automatico, in chiamate server-side devi
   propagarlo a mano.

Senza SID coerente fra upload e step di chat, i file finiscono nel
fallback `temp/*` e si rischiano collisioni in scenari concorrenti.

---

## 5. Canale metadata (opzionale)

Indipendente dall'invio file. Flussu supporta uno scambio di metadata
client↔server invisibile all'LLM.

**Da te → Flussu**, in testa al `user_message`:

```xml
<client_special_request>{"request_id":"abc-123","request":"chat summary title"}</client_special_request>
Riassumi questo contratto…
```

Il server estrae il tag, lo **rimuove** dal prompt prima dell'LLM, e usa il
`request_id` per correlare la risposta.

**Da Flussu → te**, in coda alla risposta:

```xml
<server_special_response>{"request_id":"abc-123","chat summary title":"Analisi contratto","filename":"riassunto_contratto.png"}</server_special_response>
```

- `chat summary title` è valorizzato se hai richiesto un titolo di chat.
- `filename` è valorizzato quando il workflow ha prodotto un file (es. una
  immagine generata dall'AI): l'URL del file è già inline nel testo della
  risposta come Markdown `![alt](url)`, il `filename` ti serve solo per
  dare al download un nome significativo.
- Prima di mostrare il testo all'utente, **togli** il tag con una regex
  tipo `<server_special_response>.*?</server_special_response>`.
- Il tag sta **fuori** dal blocco `{MD}...{/MD}` per risposte plain, e
  **dentro** il campo `TEXT` se la risposta è un `FLUSSU_CMD`.

---

## 6. Esempio end-to-end (pseudo-curl)

```bash
# Pre-requisito: SID già ottenuto dal primo step della sessione

# (1) upload del file
curl -X POST "$BASE_URL/flucli/upload.SRV.php" \
     -F "file=@./contratto.pdf" \
     -F "sid=$SID"
# → { "status":"ok",
#     "path":"/.../Uploads/temp/dsp_xxx_contratto.pdf",
#     "name":"contratto.pdf", "size":123456, "docspace":{...} }

# (2) invio del prompt referenziando il file
curl -X POST "$BASE_URL/api.php" \
     -H "Content-Type: application/json" \
     -d '{
       "WID": "'"$WID"'",
       "SID": "'"$SID"'",
       "APP": "CHAT",
       "TRM": {
         "user_message": "Estrai le clausole rilevanti e riassumile.",
         "$docspace_files": ["/.../Uploads/temp/dsp_xxx_contratto.pdf"]
       }
     }'
# → risposta dell'LLM contestualizzata sul contenuto del PDF
```

---

## 7. Checklist di integrazione

- [ ] Aprire/recuperare il `SID` della sessione **prima** di caricare file.
- [ ] Per ogni file da allegare: `POST /flucli/upload.SRV.php` come
      `multipart/form-data` con `file` (binario) e `sid`.
- [ ] Conservare il `path` restituito.
- [ ] Validare lato sender estensione e dimensione **prima** dell'upload
      (vedi §2 vincoli) per evitare round-trip inutili.
- [ ] Nello step di chat, raccogliere tutti i `path` dei file da
      considerare e metterli in `TRM.$docspace_files` come array JSON.
- [ ] **Non** inserire il contenuto dei file nel `user_message`.
- [ ] **Non** estrarre testo da PDF/DOCX/ecc. lato sender.
- [ ] **Non** convertire le immagini in base64 lato sender.
- [ ] Non ri-uploadare lo stesso file nei turni successivi della stessa
      sessione: una volta in DocumentSpace, resta disponibile.
- [ ] Parsare e nascondere `<server_special_response>` dal testo mostrato.
- [ ] (Opzionale) Inviare `<client_special_request>` con `request_id` per
      correlazione o per chiedere un titolo di chat.

---

## 8. FAQ

**D. Devo io estrarre il testo dal PDF prima di inviarlo?**
R. No. Manda il PDF binario. Flussu lo estrae da solo prima dell'LLM.

**D. Le immagini vanno in base64 nel prompt?**
R. No. Manda l'immagine binaria. Flussu la converte nel formato vision del
provider AI in uso (data URI inline per OpenAI/Claude, parts per Gemini).

**D. Posso allegare più file insieme?**
R. Sì. Fai una chiamata di upload per file, poi metti tutti i `path` in
`$docspace_files` nello stesso step di chat.

**D. Devo ricaricare i file a ogni turno?**
R. No. Una volta caricati per una sessione, restano disponibili al modello
per tutta la vita della sessione. Caricali solo quando l'utente ne aggiunge
di nuovi.

**D. Posso passare un URL invece di caricare il binario?**
R. No, non c'è un endpoint "ingest by URL". Scarica tu il file e caricalo
binario via §2.

**D. Cosa succede se il file supera 20 MB o ha un'estensione non in lista?**
R. L'upload viene rifiutato con `status: "error"`. Mostra all'utente il
`message` ricevuto e non procedere allo step di chat per quel file.

**D. C'è autenticazione sull'endpoint di upload?**
R. L'endpoint accetta upload anonimi (CORS aperto), il binding alla
sessione avviene via `SID`. Se serve auth più stretta, va coordinata con
il team Flussu (reverse proxy / token) — non è competenza del sender.
