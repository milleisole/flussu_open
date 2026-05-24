# Upload endpoint — limits & response contract

This document is the source of truth for client integrations
(U-Prompt, partner apps) that POST files to `/flucli/upload.SRV.php`.
Validate against these limits **before** uploading to avoid wasteful
network round-trips and to surface clear errors to the end user.

## Endpoint

```
POST /flucli/upload.SRV.php
Content-Type: multipart/form-data
```

| Form field | Required | Notes |
|---|---|---|
| `file`     | yes | The binary file. |
| `sid`      | recommended | DocumentSpace session id. If present (also accepted via `?sid=` query or `flussu_sid` cookie), the file is ingested into the session and made available to the AI; if absent, the file is only saved to `Uploads/temp/` for later processing. |

## Allowed extensions

```
pdf  docx  txt  md  log  xlsx  ods  csv
jpg  jpeg  png  gif  webp
```

Files outside this list are rejected with HTTP 200 + `status:"error"`
(generic) **except** for the structured rejections listed below.

## Hard limits

| Limit | Value | Enforced by |
|---|---|---|
| Maximum upload size (application cap) | **20 MB** | `webroot/flucli/upload.SRV.php` (`$maxSize`) |
| Maximum sheets per XLSX / ODS | **30** | `DocumentSpace::MAX_SPREADSHEET_SHEETS` |
| Maximum rows per sheet | **5000** | `DocumentSpace::MAX_SPREADSHEET_ROWS` |
| Maximum raw text per text file (`txt`, `md`, `log`, `csv`) | **500 KB** | `DocumentSpace::MAX_TEXT_BYTES` |
| Maximum PDF pages | **50** | `DocumentSpace::MAX_PDF_PAGES` |
| AI-context budget across all documents in a session | **50 KB** | `DocumentSpace::MAX_CONTEXT_CHARS` |

Server-level PHP limits (`upload_max_filesize`, `post_max_size`) may be
stricter than the 20 MB application cap; in that case the response
includes a `limits` block showing the effective values.

Files that exceed the per-sheet row cap are accepted: the first
`MAX_SPREADSHEET_ROWS` rows are extracted and the sheet's `truncated`
flag is set to `true`. Files that exceed the sheet-count cap are
accepted: the first `MAX_SPREADSHEET_SHEETS` sheets are extracted and
the rest are silently dropped (consider this when planning workbook
structure).

## HTTP response contract

All responses are JSON with `Content-Type: application/json`.

### 200 OK — success

```json
{
  "status": "ok",
  "path":   "/Uploads/temp/dsp_xxxxx_filename.xlsx",
  "name":   "filename.xlsx",
  "size":   123456,
  "docspace": {
    "id":      "doc_xxxxx",
    "name":    "filename.xlsx",
    "type":    "spreadsheet",
    "success": true,
    "error":   ""
  }
}
```

The `docspace` field is present only when a SID was supplied.
`docspace.success: true` with a populated `extractionError` field means
the file is in the session but text extraction failed gracefully (e.g.
corrupted DOCX) — the LLM will still be told the attachment exists.

### 200 — generic error (file kept in temp, status:"error")

Returned when the upload is well-formed but downstream processing
failed in a way that doesn't justify a 4xx. Body shape varies per
error path; always parse the `status` field as discriminator.

```json
{
  "status":  "error",
  "message": "File saved to temp but DocumentSpace ingestion failed: ...",
  "path":    "/Uploads/temp/dsp_xxxxx_filename.xlsx",
  "name":    "filename.xlsx",
  "size":    123456,
  "docspace": { "success": false, "error": "..." }
}
```

Other 200-error cases: missing `file` field, file too large, generic
"File type not allowed" for unrecognized extensions, PHP-level upload
errors (size limits hit at the web-server layer).

### 422 — structured rejection

Returned for client-input errors that the caller can fix and retry.
The `reason` field is a stable string identifier; the `message` field
is human-readable English.

```json
{
  "status":  "error",
  "reason":  "<stable-identifier>",
  "message": "<human-readable English>"
}
```

Stable `reason` values:

| `reason` | Meaning | Caller action |
|---|---|---|
| `format_not_supported_use_xlsx` | The file extension is `.xls` (Excel 97-2003 binary). The server does not parse the legacy binary format. | Re-save the workbook as `.xlsx` and upload again. |
| `encrypted_workbook` | The XLSX or ODS is password-protected (or wrapped in an OLE Compound File container). There is no text to extract. | Remove the password protection in Excel / LibreOffice (`File → Info → Protect Workbook → Encrypt with Password` → clear password) and upload again. |

### 500 — fatal extraction failure (last-line defense)

If a PHP fatal escapes every `try/catch` (e.g. an extension crash),
the endpoint installs a `register_shutdown_function` that emits:

```json
{
  "status":  "error",
  "message": "Extraction failed (fatal): <PHP error message>",
  "fatal":   true
}
```

This should be vanishingly rare with the OpenSpout streaming reader
for spreadsheets, but the PDF/DOCX paths still use DOM-style readers
that could in theory exhaust the 512M memory budget.

## Client validation recommendation

Before POSTing, validate locally:

1. File size ≤ 20 MB
2. Extension is in the allowed list **and** not `.xls`
3. If the file is an XLSX/ODS, optionally count sheets and rows so the
   user knows in advance which content will be truncated

This gives the end user immediate feedback without a network round-trip,
and reserves the server-side checks as defense in depth.
