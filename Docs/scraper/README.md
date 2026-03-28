# Flussu Scraper

Microservizio di web scraping per Flussu, ispirato a [Tavily](https://tavily.com/).

## Architettura

Flussu Scraper e' un servizio Node.js locale che:
1. Riceve richieste HTTP da Flussu (PHP)
2. Renderizza le pagine web con **Playwright** (Chromium headless)
3. Estrae il contenuto leggibile con **@mozilla/readability**
4. Restituisce un JSON strutturato con titolo, testo pulito, link, immagini, metadata

```
Workflow Flussu
    |
    v
Environment.php / Executor.php
    |
    v  HTTP POST (localhost:3100)
    |
Flussu Scraper (Node.js)
    |-- Playwright (Chromium headless)
    |-- @mozilla/readability
    |-- linkedom
    |
    v
JSON strutturato --> variabili workflow
```

## Requisiti

- **Node.js** >= 18
- **Chromium** (installato automaticamente da Playwright)
- ~512 MB RAM per il processo Chromium

## Quick Start

```bash
cd services/flussu-scraper
./install.sh
```

Vedi [INSTALL.md](INSTALL.md) per la guida completa.

## File del microservizio

| File | Descrizione |
|------|-------------|
| `server.js` | Entry point Fastify, endpoint HTTP |
| `scraper.js` | Logica di scraping (Playwright + Readability) |
| `browser-pool.js` | Gestione browser Chromium persistente |
| `package.json` | Dipendenze Node.js |
| `install.sh` | Script di installazione automatica |
| `flussu-scraper.service` | Template servizio systemd |
