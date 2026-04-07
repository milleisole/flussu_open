# Document Space — Spazio Documentale per Sessione

**Versione:** 4.6
**Data:** 2026-04-04
**Autore:** Claude Code / Aldus

---

## Panoramica

Il Document Space è un sistema di gestione documenti per sessione che permette agli utenti di allegare file (PDF, DOCX, XLSX, CSV, immagini, testo) durante una conversazione nel chatbox. I documenti vengono processati automaticamente — il testo viene estratto, le immagini codificate in base64, i fogli di calcolo convertiti in CSV — e il contenuto risultante viene iniettato come contesto nei prompt inviati ai provider AI.

Ogni sessione ha il proprio spazio documentale, che viene cancellato automaticamente al termine della sessione.

---

## Architettura

### Flusso di esecuzione

```
[Client Chatbox]
    │
    ├─ 1. Upload file ──► upload.SRV.php ──► /Uploads/temp/dsp_*
    │
    └─ 2. Submit (qualsiasi step) ──► Engine.php
                                          │
                                          ├─ 3. Scansiona /Uploads/temp/dsp_*
                                          │       → DocumentSpace::addDocument()
                                          │       → Processa file per tipo
                                          │       → Salva in /Uploads/docspace/{SID}/
                                          │       → Cancella file da temp
                                          │
                                          └─ 4. Executor: sendToAi
                                                  │
                                                  ├─ DocumentSpace::getContextForAi()
                                                  │   → Costruisce contesto testuale
                                                  │
                                                  └─ Prompt arricchito ──► AI Provider
```

**Nota:** L'Engine raccoglie automaticamente tutti i file `dsp_*` dalla cartella temp ad ogni chiamata. Non è necessario che il client passi i path dei file — basta che l'upload avvenga prima del submit.

### Storage su disco

```
/Uploads/docspace/{SID}/
    sid_date               — timestamp Unix dell'ultimo utilizzo della sessione
    manifest.json          — indice dei documenti nello spazio
    {doc-id}.json          — contenuto processato (testo/Markdown/CSV)
    {doc-id}.b64           — contenuto base64 (immagini)
    {doc-id}.orig.{ext}    — copia originale immagine (per uso multimodale)
    generated/             — file generati dall'AI e dal workflow
        gen_xxxxx.png      — immagine generata da DALL-E / Stability AI
        report_xxx.pdf     — documento generato dal workflow
```

Il file `sid_date` viene aggiornato ad ogni interazione con il DocumentSpace (creazione, aggiunta documento, generazione contesto AI, aggiunta file generato). Contiene un timestamp Unix (`time()`) che rappresenta l'ultima data d'uso della sessione.

La sottocartella `generated/` contiene tutti i file prodotti dall'AI (immagini generate con DALL-E, Stability AI, ecc.) e dal workflow (PDF generati, export, ecc.). Questi file sono accessibili alle applicazioni client tramite l'endpoint `docspace.SRV.php`.

---

## Tipi di file supportati

| Estensione | Tipo | Processore | Output |
|---|---|---|---|
| `pdf` | PDF | `smalot/pdfparser` | Testo puro con separatori pagina (max 50 pagine) |
| `docx` | Word | `phpoffice/phpword` | Markdown con heading, tabelle, liste, grassetto/corsivo |
| `txt`, `md`, `log` | Testo | `file_get_contents` | Testo così com'è |
| `xlsx`, `ods` | Spreadsheet | `phpoffice/phpspreadsheet` | JSON con struttura fogli + dati CSV (max 1000 righe/foglio) |
| `csv` | CSV | `file_get_contents` | CSV diretto |
| `jpg`, `jpeg`, `png`, `gif`, `webp` | Immagine | `base64_encode` | Base64 + copia originale |

### Conversione DOCX → Markdown

Il processore DOCX usa `PhpWord\IOFactory::load()` e converte gli elementi:

- `Title` → `# heading` (livello corrispondente, da `#` a `######`)
- `TextRun` / `Text` → testo con `**grassetto**` e `*corsivo*`
- `Table` → tabella Markdown (`| col1 | col2 |` con riga separatore)
- `ListItem` → `- elemento lista` (con indentazione per sotto-liste)
- `Image` → `[immagine incorporata]` (placeholder)

### Conversione XLSX → JSON + CSV

Il processore spreadsheet produce:

```json
{
  "sheets": [
    {
      "name": "Sheet1",
      "rows": 150,
      "cols": 8,
      "truncated": false,
      "csv": "col1,col2,col3\nval1,val2,val3\n..."
    }
  ]
}
```

---

## Classe `DocumentSpace`

**File:** `src/Flussu/Documents/DocumentSpace.php`
**Namespace:** `Flussu\Documents`

### Metodi pubblici

| Metodo | Descrizione |
|---|---|
| `__construct(string $sessId)` | Crea lo spazio per la sessione. Crea la directory se non esiste. |
| `addDocument(string $filePath, string $originalName): array` | Aggiunge un documento. Ritorna `['id', 'name', 'type', 'success', 'error']`. |
| `removeDocument(string $docId): bool` | Rimuove un documento dallo spazio. |
| `getDocumentList(): array` | Ritorna l'array dal manifest. |
| `getContextForAi(int $maxChars = 50000): string` | Costruisce la stringa di contesto per il prompt AI. |
| `getImagePaths(): array` | Ritorna i path delle immagini originali (per provider multimodali). |
| `hasDocuments(): bool` | Verifica se ci sono documenti nello spazio. |
| `getSpaceSize(): int` | Dimensione totale in byte dello spazio su disco. |
| `getGeneratedDir(): string` | Ritorna il path della sottocartella `generated/`, creandola se necessario. |
| `addGenerated(string $data, string $filename, string $mimeType): array` | Salva un file generato. Ritorna `['filename', 'path', 'url', 'size', 'mimeType']`. |
| `getGeneratedFiles(): array` | Lista tutti i file nella sottocartella `generated/`. |
| `hasGeneratedFiles(): bool` | Verifica se ci sono file generati. |
| `getGeneratedFilePath(string $filename): ?string` | Ritorna il path assoluto di un file generato, o `null`. Protetto da path traversal. |
| `cleanup(string $sessId): void` | **Statico.** Cancella l'intero spazio (incluso `generated/`). |
| `cleanupOrphaned(int $maxAgeHours = 4): int` | **Statico.** Cancella spazi orfani basandosi sul file `sid_date`. Default 4 ore. |

### Limiti configurati (costanti)

| Costante | Valore | Descrizione |
|---|---|---|
| `MAX_PDF_PAGES` | 50 | Massimo pagine PDF processate |
| `MAX_SPREADSHEET_ROWS` | 1000 | Massimo righe per foglio |
| `MAX_TEXT_BYTES` | 500 KB | Massima dimensione testo per file |
| `MAX_CONTEXT_CHARS` | 50000 | Massimo caratteri nel contesto AI |

### Concorrenza

Il file `manifest.json` è protetto da `flock()` (LOCK_SH per lettura, LOCK_EX per scrittura) per gestire accessi concorrenti dalla stessa sessione.

---

## Iniezione contesto AI

Quando il workflow chiama `sendToAi()`, l'Executor verifica se la sessione ha documenti nel DocumentSpace. Se sì, il contesto viene preposto al prompt con questo formato:

```
--- DOCUMENTI ALLEGATI ---
[Documento: report.pdf (PDF, 45KB)]
--- Pagina 1 ---
Contenuto della prima pagina...
--- Pagina 2 ---
Contenuto della seconda pagina...
[Fine Documento: report.pdf]

[Documento: dati.xlsx (XLSX, 12KB)]
{"sheets":[{"name":"Sheet1","rows":50,"cols":5,"csv":"..."}]}
[Fine Documento: dati.xlsx]

[Documento: foto.jpg (Immagine, 200KB)]
[Immagine disponibile per analisi visiva]
[Fine Documento: foto.jpg]
--- FINE DOCUMENTI ALLEGATI ---

<messaggio dell'utente>
```

Se il contesto totale supera `MAX_CONTEXT_CHARS` (50000), i documenti vengono troncati e un messaggio indica quanti sono stati omessi.

---

## Funzioni Environment per workflow

**File:** `src/Flussu/Flussuserver/Environment.php`

Due nuove funzioni disponibili nel codice dei workflow:

```php
// Aggiunge un documento allo spazio della sessione
addDocToSpace($filePath, $originalName = null)

// Svuota lo spazio documentale della sessione
clearDocSpace()

// Lista i file generati (salvati come JSON nella variabile indicata)
listGeneratedFiles($varName)
```

### Esempio di utilizzo nel workflow

```php
// Aggiungere un file caricato dall'utente
addDocToSpace($file_filepath, "contratto.pdf");

// Il prossimo sendToAi includerà automaticamente il contesto del documento
sendToAi("Riassumi il documento allegato", '$risposta', 4);

// Generare un'immagine con AI — il file viene salvato automaticamente in generated/
generateImageWithAi("Un gatto su una spiaggia", '$immagine_url', 0);

// Ottenere la lista dei file generati
listGeneratedFiles('$file_list');
// $file_list contiene JSON: [{"filename":"gen_xxx.png","url":"...","size":...}, ...]

// Svuotare lo spazio quando non serve più
clearDocSpace();
```

---

## Lifecycle e cleanup

### Cleanup automatico alla fine sessione

In `Session::__destruct()`, viene chiamato `DocumentSpace::cleanup($sessId)` che cancella ricorsivamente la directory `/Uploads/docspace/{SID}/`.

### Cleanup orfani via Timedcall

In `Timedcall::exec()` (eseguito ogni minuto via cron), viene chiamato `DocumentSpace::cleanupOrphaned(4)` che legge il file `sid_date` in ogni cartella di docspace e cancella quelle dove l'ultimo utilizzo risale a più di 4 ore fa.

Il file `sid_date` viene aggiornato automaticamente ad ogni operazione:
- Creazione del DocumentSpace (`__construct`)
- Aggiunta di un documento (`addDocument`)
- Generazione del contesto AI (`getContextForAi`)

Se il file `sid_date` non esiste (cartelle legacy), viene usato il `mtime` della directory come fallback.

---

## Endpoint upload

**File:** `webroot/flucli/upload.SRV.php`

Endpoint per ricevere file dal client chatbox.

| Parametro | Valore |
|---|---|
| Metodo | `POST` |
| Content-Type | `multipart/form-data` |
| Campo file | `file` |
| Max dimensione | 20 MB |
| Estensioni ammesse | pdf, docx, txt, md, log, xlsx, ods, csv, jpg, jpeg, png, gif, webp |
| Directory destinazione | `/Uploads/temp/` |

### Risposta

**Successo:**
```json
{
  "status": "ok",
  "path": "/absolute/path/to/Uploads/temp/dsp_xxxx_filename.ext",
  "name": "filename.ext",
  "size": 12345
}
```

**Errore:**
```json
{
  "status": "error",
  "message": "Descrizione dell'errore"
}
```

---

## Endpoint Document Space API

**File:** `webroot/flucli/docspace.SRV.php`

Endpoint per interrogare lo spazio documentale di una sessione. Permette alle applicazioni client di listare e scaricare i file generati.

### Lista file generati

```
GET {BASE_URL}/flucli/docspace.SRV.php?action=list&sid={SID}
```

Risposta:
```json
{
  "status": "ok",
  "sid": "a1b2c3d4-...",
  "files": [
    {
      "filename": "gen_668a1b2c.png",
      "url": "https://server/Uploads/docspace/{SID}/generated/gen_668a1b2c.png",
      "size": 245760,
      "mimeType": "image/png",
      "createdAt": "2026-04-04T10:30:00+00:00"
    }
  ]
}
```

### Download file generato

```
GET {BASE_URL}/flucli/docspace.SRV.php?action=download&sid={SID}&file={FILENAME}
```

Ritorna il contenuto binario del file con gli header `Content-Type`, `Content-Length` e `Content-Disposition` appropriati.

### Sicurezza

- Il parametro `sid` viene validato con regex (solo caratteri UUID)
- Il parametro `file` viene passato tramite `basename()` per prevenire path traversal
- L'endpoint verifica che la directory docspace esista prima di procedere

---

## Modifiche client-side

### `client.dev.js`

- L'upload handler cattura il `path` dalla risposta server e lo salva in `fobj.serverPath`
- Al submit, raccoglie i server path dei file completati e li passa a `submitFormStep(termObj, docspaceFiles)`

### `client_api.dev.js`

- `submitFormStep(termObj, docspaceFiles)` accetta un secondo parametro opzionale
- Se `docspaceFiles` è un array non vuoto, viene aggiunto ai terms come `$docspace_files`
- Il campo viene serializzato nel JSON dei `TRM` e inviato al server

### Integrazione API (Engine.php)

In `Engine::execWorker()`, dopo `fileCheckExtract()`, l'Engine:
1. Scansiona `/Uploads/temp/` per file con prefisso `dsp_*`
2. Per ogni file trovato, chiama `DocumentSpace::addDocument()` con il SID della sessione corrente
3. Cancella il file da temp dopo il processing
4. Supporta anche `$docspace_files` nei terms come meccanismo alternativo (API diretta)

---

## Dipendenze aggiunte

| Pacchetto | Versione | Scopo |
|---|---|---|
| `smalot/pdfparser` | ^2.0 | Estrazione testo da PDF |
| `phpoffice/phpword` | ^1.3 | Lettura DOCX con struttura (heading, tabelle, liste) |

Dipendenze già presenti e riutilizzate:
- `phpoffice/phpspreadsheet` ^2.1 — lettura Excel/ODS
- `ext-zip` — supporto archivi ZIP (usato internamente da PhpWord)

---

## File coinvolti

| File | Tipo modifica |
|---|---|
| `src/Flussu/Documents/DocumentSpace.php` | **Nuovo** — classe principale + gestione generated |
| `webroot/flucli/upload.SRV.php` | **Nuovo** — endpoint upload file |
| `webroot/flucli/docspace.SRV.php` | **Nuovo** — endpoint lista/download file generati |
| `src/Flussu/Test/test_docspace.php` | **Nuovo** |
| `composer.json` | Modificato — aggiunte dipendenze |
| `src/Flussu/Flussuserver/Executor.php` | Modificato — iniezione contesto + nuovi case |
| `src/Flussu/Flussuserver/Environment.php` | Modificato — nuove funzioni workflow |
| `src/Flussu/Flussuserver/Session.php` | Modificato — cleanup nel distruttore |
| `src/Flussu/Api/V40/Engine.php` | Modificato — processing $docspace_files |
| `src/Flussu/Timedcall.php` | Modificato — cleanup orfani |
| `webroot/flucli/client.dev.js` | Modificato — cattura server path, invio file |
| `webroot/flucli/client_api.dev.js` | Modificato — parametro docspaceFiles |

---

## Test

```bash
php src/Flussu/Test/test_docspace.php
```

Il test verifica:
1. Creazione DocumentSpace e aggiunta documenti (TXT, CSV, DOCX)
2. Lista documenti nel manifest
3. `hasDocuments()` ritorna true
4. `getContextForAi()` produce contesto formattato
5. `getSpaceSize()` calcola dimensione su disco
6. `removeDocument()` rimuove un singolo documento
7. `cleanup()` cancella l'intera directory
