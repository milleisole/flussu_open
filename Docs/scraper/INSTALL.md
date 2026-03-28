# Installazione Flussu Scraper

## Prerequisiti

- **Node.js >= 18** — `node -v` per verificare
- **npm** — incluso con Node.js
- Accesso root/sudo per il servizio systemd

## Installazione automatica

```bash
cd services/flussu-scraper
chmod +x install.sh
sudo ./install.sh
```

Lo script:
1. Verifica Node.js >= 18
2. Installa le dipendenze npm
3. Installa Chromium via Playwright
4. Legge la porta dal file `.env` (`scraper_port`)
5. Crea e avvia il servizio systemd

## Installazione manuale

### 1. Dipendenze npm

```bash
cd services/flussu-scraper
npm install --production
```

### 2. Playwright Chromium

```bash
npx playwright install chromium
npx playwright install-deps chromium
```

### 3. Configurazione

#### Porta (`.env`)

Aggiungere nel file `.env` nella root di Flussu:

```env
# Flussu Scraper microservice port (default: 3100)
scraper_port=3100
```

#### Opzioni avanzate (`config/.services.json`)

Aggiungere nella sezione `"services"`:

```json
"scraper": {
  "host": "127.0.0.1",
  "timeout": 30,
  "enabled": true
}
```

- `host` — Indirizzo del microservizio (default: `127.0.0.1`)
- `timeout` — Timeout in secondi per lo scraping (default: `30`)
- `enabled` — `true` per abilitare, `false` per disabilitare (fallback a metodo semplice)

### 4. Servizio systemd

```bash
# Copia il template (adatta i path se necessario)
sudo cp flussu-scraper.service /etc/systemd/system/

# Modifica WorkingDirectory e porta se necessario
sudo systemctl daemon-reload
sudo systemctl enable flussu-scraper
sudo systemctl start flussu-scraper
```

## Verifica

```bash
# Stato del servizio
systemctl status flussu-scraper

# Logs in tempo reale
journalctl -u flussu-scraper -f

# Health check
curl http://127.0.0.1:3100/health

# Test scraping
curl -X POST http://127.0.0.1:3100/scrape \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com/"}'
```

## Troubleshooting

### Il servizio non parte

```bash
# Controlla i log
journalctl -u flussu-scraper --no-pager -n 50

# Verifica che la porta non sia occupata
ss -tlnp | grep 3100

# Prova manualmente
cd services/flussu-scraper
PORT=3100 node server.js
```

### Chromium non si installa

Su sistemi minimali, potrebbero mancare le dipendenze di sistema:

```bash
# Ubuntu/Debian
npx playwright install-deps chromium

# oppure manualmente
sudo apt-get install -y libnss3 libatk-bridge2.0-0 libdrm2 libxkbcommon0 libgbm1
```

### Il PHP non raggiunge il microservizio

1. Verifica che il servizio sia attivo: `systemctl status flussu-scraper`
2. Verifica la porta nel `.env`: `grep scraper_port .env`
3. Verifica la config: controlla `config/.services.json` → `services.scraper.enabled = true`
4. Test diretto: `curl http://127.0.0.1:3100/health`
