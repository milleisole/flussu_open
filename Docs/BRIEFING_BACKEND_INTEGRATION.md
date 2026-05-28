# Briefing — integrare la nuova feature "AI File Emission" di Flussu

> Copia questo file e incollalo come prompt nella sessione Claude Code del backend (il sistema che consuma le risposte API di Flussu).

## Cosa è cambiato

Il server Flussu (workflow engine) ora può restituire, dentro la risposta API standard `{sid, bid, elms}`, un nuovo tipo di elemento che rappresenta **un documento da produrre lato client**.

Flussu NON genera il file. Si limita a inoltrarti il *contenuto* prodotto dall'AI (come stringa) insieme a metadati (formato, filename suggerito). **La conversione in file vero (scrittura su disco, render md→pdf, salvataggio, invio, ecc.) è responsabilità tua**.

La capability è **attiva di default per ogni chiamata** `sendToAi` (il workflow author può silenziarla per singolo nodo con `allowFile=false`, ma il default è ON). Questo significa che, su payload reali, ti aspetti di poter ricevere un elemento `DOC$N` ogni volta che l'utente chiede esplicitamente un file. Quando l'utente NON chiede un file, la risposta API è identica a prima — il codice qui sotto è puramente additivo, non modifica il flusso esistente.

## Come riconoscere un documento nella risposta

Risposta API standard di Flussu:

    {
      "sid": "abcd-1234-...",
      "bid": "blocco-corrente-...",
      "elms": {
        "L$1":   ["Ecco i punti chiave:", { "display_info": { "type": "unk" } }],
        "DOC$1": [
          "- punto 1\n- punto 2\n- punto 3\n",
          { "display_info": {
              "type":     "document",
              "format":   "md",
              "filename": "punti-chiave.md"
          } }
        ]
      }
    }

**Algoritmo di detection** — itera su `elms`:

1. Se la chiave matcha `/^DOC\$\d+$/` **E** `elms[key][1].display_info.type === "document"` → è un documento da generare.
2. `elms[key][0]` è il **corpo del file come stringa** (UTF-8, max 256 KB). Non è base64, non è una URL, non è HTML da renderizzare.
3. `elms[key][1].display_info.format` ∈ `{"md", "json", "html"}`. Default `"md"` se assente.
4. `elms[key][1].display_info.filename` è **opzionale** e **caller-suggested** — vedi sicurezza sotto.

**Non confondere con il tipo "file"** che già esiste: `display_info.type:"file"` porta una **URL** a un asset statico ospitato da Flussu, mentre `type:"document"` porta il **contenuto inline**. Sono due cose distinte.

**Prosa nello stesso turno.** Se l'AI ha scritto un messaggio prima del blocco, Flussu lo emette come elemento `L$` normale **nello stesso turno**. Mostra `L$` nella chat come faresti normalmente; **non** mostrare il contenuto del `DOC$` nella chat (è il file da scaricare/salvare, non testo conversazionale).

## Cosa fare quando lo trovi

Pseudocodice del flusso minimo:

    for (key, value) in elms:
        if key matches /^DOC\$\d+$/ and value[1].display_info.type == "document":
            content  = value[0]                                  # stringa, UTF-8
            format   = value[1].display_info.format ?? "md"
            filename = sanitize(value[1].display_info.filename ?? default_name_for(format))

            match format:
                "md":   save_as_markdown_file(filename, content)   # oppure: render md→pdf, render md→docx, ...
                "json": save_as_json_file(filename, content)       # opzionale: json_decode + valida prima di salvare
                "html": save_as_html_file(filename, content)       # opzionale: render html→pdf

**Cosa significa "save"** dipende dal vostro caso d'uso:

- scrivere su disco e restituire una URL di download al client UI
- mandare via email come allegato
- caricare su un object store (S3, Drive) e tornare il link
- renderizzare in PDF e poi salvare

Sono decisioni di prodotto, non di protocollo.

## Sicurezza — filename

Il campo `filename` arriva da un LLM, è **non sanitizzato** e va trattato come input non fidato:

- applicare `basename()` (o equivalente) per scartare componenti di path (`../`, percorsi assoluti)
- rimuovere null byte
- white-list dell'estensione contro `format` (`.md` per md, `.json` per json, `.html` per html)
- imporre una lunghezza massima ragionevole (es. 100 caratteri)
- fallback su un default safe (`document.md` ecc.) se il filename suggerito è inutilizzabile

Non passare mai `filename` grezzo a `fopen`, percorsi filesystem, `Content-Disposition`, ecc.

## Robustezza

- Il `content` è garantito UTF-8 valido e ≤ 256 KB lato Flussu — ma trattalo come dato esterno: niente eval/exec, niente render diretto di `html` in un iframe senza sanitization se vuoi proteggere il client.
- Se il `format` arriva con un valore inatteso (non in `{md, json, html}`) **non deve mai succedere** (Flussu valida prima di emettere), ma per robustness gestiscilo come `md` o rifiutalo loggando.
- Se la chiave matcha `DOC$N` ma `display_info.type` non è `"document"` → ignora silenziosamente, è un caso futuro non ancora previsto.

## Backward compatibility

- Le risposte vecchie (senza `DOC$`) non cambiano forma. Tutto il codice esistente continua a funzionare.
- Clienti che NON conoscono `document` possono ignorare l'elemento (la prosa nella chat continua a esserci come `L$`).
- Non rimuovere o rinominare il riconoscimento di `display_info.type:"file"` (tipo distinto, semantica diversa).

## Log diagnostici lato Flussu

Sul server Flussu i log relativi a questa feature usano la chiave `[DOC_FILE_v1]`. Quando devi debuggare un'integrazione, chiedi al sysadmin Flussu di greppare la chiave nei log giornalieri (`Logs/log_DD-Mon-YYYY.log`). Distingue:

- `emit` — Flussu ha emesso un `DOC$` (vedi `fmt`, `bytes`, `extras`, `trailing`)
- `parse_fail` — l'AI ha emesso un blocco ma malformato; risposta degradata a testo
- `unsolicited_block` — l'AI ha emesso un blocco quando il workflow non aveva opt-in attivo; risposta passata invariata

## Documentazione canonica lato Flussu

Per dettagli sul contratto, esempi più ampi e razionale architetturale, riferimento autoritativo:

- `Docs/REFERENCE_AI_FILE_EMISSION.md` (reference d'uso)
- `Docs/DESIGN_AI_FILE_EMISSION.md` (decisioni di design + vincoli)
- `src/Flussu/Helpers/FlussuFileBlock.php` (parser + capability prompt che vede l'LLM)

## Compito concreto per te (Claude Code backend)

1. **Esplora il codebase** e identifica il/i punto/i dove oggi viene parsata la risposta `{sid, bid, elms}` di Flussu. Cerca pattern come `elms`, `L$`, `display_info`, `display_info.type`, `getElms`, `parseFlussuResponse`, o nomi simili al vostro stile.
2. **Aggiungi il branch `type === "document"`** nello switch/decoder degli elementi `elms`, prima del fallback generico.
3. **Implementa la routine di salvataggio** (vedi pseudocodice sopra) in funzione del vostro caso d'uso (filesystem, S3, email, render, ...). Se non è chiaro quale caso d'uso applicare, **chiedi prima di scrivere**.
4. **Sanitizza il `filename`** secondo le regole sopra.
5. **Scrivi un test** che dia in pasto al decoder questo payload e verifichi che il file venga creato/inoltrato correttamente:

        {
          "sid": "test-sid",
          "bid": "test-bid",
          "elms": {
            "L$1":   ["Ecco il file richiesto.", { "display_info": { "type": "unk" } }],
            "DOC$1": [
              "# Titolo\n\nrigo 1\nrigo 2\n",
              { "display_info": { "type": "document", "format": "md", "filename": "note.md" } }
            ]
          }
        }

   Atteso: `L$1` mostrato in chat come label; un file `note.md` con il contenuto markdown creato/inoltrato.
6. **Verifica backward compatibility**: rispondere a una payload SENZA `DOC$` deve restare identico al comportamento attuale, byte-per-byte.
7. **NON aggiungere** parsing del marker `flussu-file` lato caller: Flussu fa già lo strip; non vedrai mai il marker.

Riporta cosa hai trovato nel codebase, dove proponi di inserire il branch, e quale strategia di "save" è più sensata per il vostro stack prima di scrivere codice.
