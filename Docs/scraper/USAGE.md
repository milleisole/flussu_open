# Uso di Flussu Scraper nei Workflow

## Comando principale: `doScrape`

```php
$wofoEnv->doScrape($url, $retVarName);
```

Esegue lo scraping di una pagina web e salva il risultato in variabili del workflow.

### Parametri

| Parametro | Tipo | Descrizione |
|-----------|------|-------------|
| `$url` | string | URL della pagina da analizzare |
| `$retVarName` | string | Nome base per le variabili risultato |

### Variabili risultato

| Variabile | Contenuto |
|-----------|-----------|
| `$retVarName` | JSON completo con tutti i dati estratti |
| `$retVarName_title` | Titolo della pagina |
| `$retVarName_description` | Descrizione (meta description o OG) |
| `$retVarName_content` | Testo leggibile pulito (estratto con Readability) |
| `$retVarName_author` | Autore (se trovato) |
| `$retVarName_url` | URL finale dopo eventuali redirect |
| `$retVarName_status` | Codice HTTP (es. "200") |
| `$retVarName_error` | Messaggio di errore (stringa vuota se successo) |

### Esempi

#### Scraping base

```php
$wofoEnv->doScrape('https://aldo.prinzi.it/', 'pagina');

$titolo      = $wofoEnv->getData('pagina_title');
$testo       = $wofoEnv->getData('pagina_content');
$descrizione = $wofoEnv->getData('pagina_description');
```

#### Con URL da variabile e controllo errori

```php
$wofoEnv->doScrape($url_da_analizzare, 'analisi');

if ($wofoEnv->getData('analisi_error') != '') {
    $wofoEnv->alert('Errore: ' . $wofoEnv->getData('analisi_error'));
    $wofoEnv->setExit(1);
    return;
}

$titolo = $wofoEnv->getData('analisi_title');
$testo  = $wofoEnv->getData('analisi_content');
```

#### Scraping + analisi AI

```php
$wofoEnv->doScrape($url_target, 'pagina');

$contenuto = $wofoEnv->getData('pagina_content');
$titolo    = $wofoEnv->getData('pagina_title');

if ($contenuto != '') {
    $wofoEnv->initAiAgent('Sei un esperto di analisi del contenuto web. Rispondi in italiano.');
    $wofoEnv->sendToAi(
        'Analizza il seguente testo estratto dalla pagina "' . $titolo . '": ' . $contenuto,
        'analisi_ai',
        4
    );
}
```

---

## Metodi diretti (da Environment)

Per usi piu' semplici, sono disponibili anche metodi diretti che ritornano il risultato:

### `getHtml($url)`
Ritorna l'HTML completo della pagina.

```php
$html = $wofoEnv->getHtml('https://example.com/');
```

### `getText($url)`
Ritorna il testo pulito della pagina (estratto con Readability).

```php
$testo = $wofoEnv->getText('https://example.com/');
```

### `getMarkdown($url)`
Ritorna il contenuto della pagina in formato Markdown.

```php
$markdown = $wofoEnv->getMarkdown('https://example.com/');
```

### `getPageJson($url)`
Ritorna il JSON completo con tutti i dati estratti.

```php
$json = $wofoEnv->getPageJson('https://example.com/');
$data = json_decode($json, true);
```

---

## Struttura JSON di risposta

```json
{
  "url": "https://example.com/",
  "status": 200,
  "title": "Example Domain",
  "description": "This domain is for use in illustrative examples...",
  "content": "Testo pulito leggibile estratto con Readability...",
  "author": "Nome Autore",
  "headings": [
    {"level": 1, "text": "Titolo principale"},
    {"level": 2, "text": "Sezione 1"}
  ],
  "links": [
    {"text": "Home", "href": "https://example.com/", "external": false},
    {"text": "Contatti", "href": "https://other.com/", "external": true}
  ],
  "images": [
    {"src": "https://example.com/logo.png", "alt": "Logo", "title": ""}
  ],
  "metadata": {
    "description": "...",
    "og:title": "...",
    "og:image": "...",
    "canonical": "https://example.com/"
  },
  "scraped_at": "2026-03-28T12:00:00Z",
  "elapsed_ms": 1823,
  "method": "playwright"
}
```

## Note

- Il servizio gira solo su localhost -- non e' esposto verso l'esterno
- Il tempo di risposta tipico e' 2-8 secondi per pagina
- Pagine con protezioni anti-bot (Cloudflare, CAPTCHA) potrebbero non essere accessibili
- Il testo (`_content`) e' gia' pulito da HTML -- pronto per elaborazioni AI
- Se il microservizio e' spento, il sistema usa automaticamente il fallback (file_get_contents)
- Per verificare che il servizio sia attivo: `systemctl status flussu-scraper`
