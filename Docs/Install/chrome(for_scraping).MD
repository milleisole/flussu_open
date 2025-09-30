# SCRAPING
per poter fare scraping è necessario installare sia 
   composer require symfony/panther:^2.2 -W
sia
   composer require --dev dbrekelmans/bdi && vendor/bin/bdi detect drivers
Sarà creata una cartella "drivers" nella root del progetto, che conterrà i driver di chrome