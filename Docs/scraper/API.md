# Flussu Scraper - API Reference

Il microservizio espone due endpoint su `http://127.0.0.1:{PORT}` (default porta 3100).

---

## `GET /health`

Health check per monitoraggio e verifica.

### Risposta

```json
{
  "status": "ok",
  "service": "flussu-scraper",
  "uptime": 3600.5,
  "timestamp": "2026-03-28T12:00:00.000Z"
}
```

### Esempio

```bash
curl http://127.0.0.1:3100/health
```

---

## `POST /scrape`

Esegue lo scraping di una pagina web.

### Body (JSON)

| Campo | Tipo | Obbligatorio | Default | Descrizione |
|-------|------|:---:|---------|-------------|
| `url` | string | si | — | URL della pagina da analizzare |
| `timeout` | integer | no | 30000 | Timeout navigazione in ms |
| `extraWait` | integer | no | 2000 | Attesa extra per JS dinamico in ms |

### Risposta successo

```json
{
  "url": "https://example.com/",
  "status": 200,
  "title": "Example Domain",
  "description": "Meta description della pagina",
  "content": "Testo pulito leggibile estratto con Readability...",
  "author": "Nome Autore",
  "headings": [
    {"level": 1, "text": "Titolo principale"},
    {"level": 2, "text": "Sezione"}
  ],
  "links": [
    {"text": "Link text", "href": "https://example.com/page", "external": false}
  ],
  "images": [
    {"src": "https://example.com/img.png", "alt": "Alt text", "title": ""}
  ],
  "metadata": {
    "description": "...",
    "og:title": "...",
    "og:description": "...",
    "og:image": "...",
    "canonical": "https://example.com/"
  },
  "scraped_at": "2026-03-28T12:00:00.000Z",
  "elapsed_ms": 1823,
  "method": "playwright"
}
```

### Risposta errore (pagina non raggiungibile)

```json
{
  "url": "https://unreachable.example.com/",
  "status": 0,
  "title": "",
  "description": "",
  "content": "",
  "author": "",
  "headings": [],
  "links": [],
  "images": [],
  "metadata": {},
  "error": "net::ERR_NAME_NOT_RESOLVED",
  "scraped_at": "2026-03-28T12:00:00.000Z",
  "elapsed_ms": 2105,
  "method": "playwright"
}
```

### Risposta errore (URL non valido)

HTTP 400:
```json
{
  "error": "Invalid URL",
  "url": "not-a-url"
}
```

### Esempi

```bash
# Scraping base
curl -X POST http://127.0.0.1:3100/scrape \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://aldo.prinzi.it/"}'

# Con timeout personalizzato
curl -X POST http://127.0.0.1:3100/scrape \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com/","timeout":60000,"extraWait":5000}'
```

---

## Codici HTTP

| Codice | Significato |
|--------|-------------|
| 200 | Scraping completato (controllare il campo `error` per errori di navigazione) |
| 400 | URL non valido |
| 500 | Errore interno del microservizio |

## Campi risposta

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `url` | string | URL finale (dopo eventuali redirect) |
| `status` | integer | Codice HTTP della pagina (0 se errore di rete) |
| `title` | string | Titolo della pagina |
| `description` | string | Meta description o OpenGraph description |
| `content` | string | Testo leggibile pulito (estratto con Readability) |
| `author` | string | Autore della pagina (se trovato) |
| `headings` | array | Intestazioni H1-H6 con livello e testo |
| `links` | array | Link trovati nella pagina (href, text, external) |
| `images` | array | Immagini trovate (src, alt, title) |
| `metadata` | object | Meta tags (OpenGraph, Twitter Cards, canonical, ecc.) |
| `error` | string | Messaggio di errore (assente se successo) |
| `scraped_at` | string | Timestamp ISO 8601 dello scraping |
| `elapsed_ms` | integer | Tempo di esecuzione in millisecondi |
| `method` | string | Sempre `"playwright"` |
