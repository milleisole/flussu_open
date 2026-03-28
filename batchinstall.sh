#!/bin/bash
chmod -R 775 Uploads
chmod -R 775 Logs
chmod -R 775 Log_sys
chmod -R 775 webroot
composer install
cd bin
chmod +x add2cron.sh
./add2cron.sh
cd ..
# Install Flussu Scraper microservice
if [ -d "services/flussu-scraper" ]; then
    echo "Installing Flussu Scraper..."
    cd services/flussu-scraper
    chmod +x install.sh
    ./install.sh
    cd ../..
fi
