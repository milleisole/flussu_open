<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * CLASS-NAME:      Web Scraping controller
 * CREATED DATE:    30.09.2025 - Aldus - Flussu v4.5
 * VERSION REL.:    4.5.1.20250930
 * UPDATES DATE:    30.09:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Symfony\Component\Panther\Client;

/**
 * Provides functionality to send SMS messages using the JoMobile API.
 */
class WebScraperController 
{
    function getPageHtml(string $address): ?string
    {
        $ret="";
        $projectRoot = realpath(__DIR__ );
        $driverPath = $projectRoot . '/../../../drivers/chromedriver';
        
        // VERIFICA SE IL FILE ESISTE
        if (!file_exists($driverPath)) {
            $ret= json_encode(["ERROR:" => "The Chrome driver was not found on: " . $driverPath]);
        } else {
            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36 Flussu/4.5';
            $chromeArguments = [
                '--user-agent='  . $userAgent,
                '--headless',    // Senza interfaccia grafica
                '--disable-gpu', // Spesso necessario in ambienti server
            ];
            $client = Client::createChromeClient($driverPath, $chromeArguments);
            try {
                $crawler = $client->request('GET', $address);
                // Aspetta un elemento specifico
                $client->waitForVisibility('body', 8);
                // Recupera il sorgente HTML completo della pagina, anceh dopo esecuzione JS
                $ret = $client->getPageSource();
            } catch (\Throwable $e) {
                // In caso di errore (es. timeout, URL non valido), restituisci l'errore.
                $ret= json_encode(["ERROR:" => $e->getMessage()]);
            } finally {
                // Chiudere il client per 
                $client->quit();
            }
        }
        return $ret;
    }
}
 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //--------------- 