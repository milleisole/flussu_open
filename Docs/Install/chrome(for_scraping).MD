# SCRAPING
per poter fare scraping è necessario installare sia 
   composer require symfony/panther:^2.2 -W
sia
   composer require --dev dbrekelmans/bdi && vendor/bin/bdi detect drivers
Sarà creata una cartella "drivers" nella root del progetto, che conterrà i driver di chrome
---
## Creare le cartelle per la scrittura temporanea di chrome
sudo mkdir -p /tua/dir/progetto/.cache
sudo mkdir -p /tua/dir/progetto/.local
sudo chown -R www-data:www-data /tua/dir/progetto/.cache /tua/dir/progetto/.local