#!/bin/bash
# --------------------------------------------------------------------
# Flussu Scraper - Installation Script
# --------------------------------------------------------------------
# Installs the Flussu Scraper microservice:
# - Checks Node.js >= 18
# - Installs npm dependencies
# - Installs Playwright Chromium browser
# - Creates and enables systemd service
# --------------------------------------------------------------------

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FLUSSU_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
SERVICE_NAME="flussu-scraper"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== Flussu Scraper - Installation ===${NC}"
echo ""

# 1. Check Node.js
echo -e "${YELLOW}[1/5] Checking Node.js...${NC}"
if ! command -v node &> /dev/null; then
    echo -e "${RED}ERROR: Node.js not found. Please install Node.js >= 18${NC}"
    echo "  Ubuntu/Debian: curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash - && sudo apt-get install -y nodejs"
    exit 1
fi

NODE_VERSION=$(node -v | sed 's/v//' | cut -d. -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    echo -e "${RED}ERROR: Node.js >= 18 required. Current version: $(node -v)${NC}"
    exit 1
fi
echo -e "  Node.js $(node -v) ${GREEN}OK${NC}"

# 2. Install npm dependencies
echo -e "${YELLOW}[2/5] Installing npm dependencies...${NC}"
cd "$SCRIPT_DIR"
npm install --production
echo -e "  ${GREEN}OK${NC}"

# 3. Install Playwright Chromium
echo -e "${YELLOW}[3/5] Installing Playwright Chromium browser...${NC}"
npx playwright install chromium
npx playwright install-deps chromium 2>/dev/null || true
echo -e "  ${GREEN}OK${NC}"

# 4. Read port from .env
echo -e "${YELLOW}[4/5] Reading configuration...${NC}"
SCRAPER_PORT=3100
if [ -f "$FLUSSU_ROOT/.env" ]; then
    ENV_PORT=$(grep -E "^scraper_port=" "$FLUSSU_ROOT/.env" | cut -d= -f2 | tr -d '"' | tr -d ' ')
    if [ -n "$ENV_PORT" ]; then
        SCRAPER_PORT="$ENV_PORT"
    fi
fi
echo -e "  Port: ${SCRAPER_PORT}"

# Detect www-data user or fallback
SERVICE_USER="www-data"
if ! id "$SERVICE_USER" &>/dev/null; then
    SERVICE_USER="$(whoami)"
fi
echo -e "  User: ${SERVICE_USER}"

# 5. Create systemd service
echo -e "${YELLOW}[5/5] Creating systemd service...${NC}"

cat > /tmp/${SERVICE_NAME}.service << EOF
[Unit]
Description=Flussu Scraper Microservice (Playwright + Readability)
After=network.target

[Service]
Type=simple
User=${SERVICE_USER}
WorkingDirectory=${SCRIPT_DIR}
ExecStart=$(which node) server.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production
Environment=PORT=${SCRAPER_PORT}
MemoryMax=512M

[Install]
WantedBy=multi-user.target
EOF

if [ "$(id -u)" -eq 0 ]; then
    cp /tmp/${SERVICE_NAME}.service "$SERVICE_FILE"
    systemctl daemon-reload
    systemctl enable ${SERVICE_NAME}
    systemctl start ${SERVICE_NAME}
    echo -e "  ${GREEN}Service installed and started${NC}"
    echo ""
    echo -e "${GREEN}=== Installation complete ===${NC}"
    echo ""
    echo "  Status:  systemctl status ${SERVICE_NAME}"
    echo "  Logs:    journalctl -u ${SERVICE_NAME} -f"
    echo "  Test:    curl http://127.0.0.1:${SCRAPER_PORT}/health"
else
    echo -e "  ${YELLOW}Not running as root. Service file saved to: /tmp/${SERVICE_NAME}.service${NC}"
    echo "  To install the service manually:"
    echo "    sudo cp /tmp/${SERVICE_NAME}.service ${SERVICE_FILE}"
    echo "    sudo systemctl daemon-reload"
    echo "    sudo systemctl enable ${SERVICE_NAME}"
    echo "    sudo systemctl start ${SERVICE_NAME}"
    echo ""
    echo -e "${GREEN}=== Installation complete (manual service setup needed) ===${NC}"
fi

echo ""
echo "  Test: curl -X POST http://127.0.0.1:${SCRAPER_PORT}/scrape \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d '{\"url\":\"https://example.com/\"}'"
