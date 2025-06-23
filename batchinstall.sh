#!/bin/bash
chmod -R 775 Uploads
chmod -R 775 Logs
chmod -R 775 Log_sys
chmod -R 775 webroot
composer install
cd bin
chmod +x add2cron.sh
./add2cron.sh
