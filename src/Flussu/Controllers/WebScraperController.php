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

    /**
     * Converte HTML in formato Markdown (solo contenuto testuale, no codice)
     * 
     * @param string $address la pagina all'url da convertire
     * @return string Il testo in formato Markdown
     */
    function getPageMarkdown(string $address) {
        $html=$this->getPageHtml($address);
      // Rimuove script e style
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
      // RIMUOVE COMPLETAMENTE I BLOCCHI DI CODICE
        // Rimuove pre/code blocks
        $html = preg_replace('/<pre[^>]*>.*?<\/pre>/is', '', $html);
        $html = preg_replace('/<code[^>]*>.*?<\/code>/is', '', $html);
        // Rimuove anche eventuali blocchi con classe code/highlight/syntax
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*code[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*highlight[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*syntax[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        // Converte entità HTML
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      // === TITOLI ===
        $html = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $html);        // H1 -> #
        $html = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $html);       // H2 -> ##
        $html = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $html);      // H3 -> ###
        $html = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', "\n#### $1\n", $html);     // H4 -> ####
        $html = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', "\n##### $1\n", $html);    // H5 -> #####
        $html = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', "\n###### $1\n", $html);   // H6 -> ######
      // === FORMATTAZIONE TESTO ===
        $html = preg_replace('/<(b|strong)[^>]*>(.*?)<\/(b|strong)>/is', '**$2**', $html);          // Bold/Strong -> **testo**
        $html = preg_replace('/<(i|em)[^>]*>(.*?)<\/(i|em)>/is', '*$2*', $html);                    // Italic/Emphasis -> *testo*
        $html = preg_replace('/<(del|s|strike)[^>]*>(.*?)<\/(del|s|strike)>/is', '~~$2~~', $html);  // Strikethrough -> ~~testo~~
      // === LINK E IMMAGINI ===
        // Link con testo -> [testo](url)
        $html = preg_replace_callback('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', 
            function($matches) {
                $url = $matches[1];
                $text = strip_tags($matches[2]);
                return "[$text]($url)";
            }, $html);
        // Immagini -> ![alt](src)
        $html = preg_replace_callback('/<img[^>]*>/is',
            function($matches) {
                preg_match('/src=["\']([^"\']+)["\']/i', $matches[0], $src);
                preg_match('/alt=["\']([^"\']+)["\']/i', $matches[0], $alt);
                $srcUrl = isset($src[1]) ? $src[1] : '';
                $altText = isset($alt[1]) ? $alt[1] : 'image';
                return "![$altText]($srcUrl)";
            }, $html);
      // === LISTE ===
        // Liste non ordinate
        $html = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is',
            function($matches) {
                $content = $matches[1];
                // Converti ogni <li> in "- elemento"
                $content = preg_replace('/<li[^>]*>(.*?)<\/li>/is', '- $1', $content);
                return "\n" . $content . "\n";
            }, $html);
        // Liste ordinate
        $html = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is',
            function($matches) {
                $content = $matches[1];
                // Conta gli elementi per numerarli
                preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $content, $items);
                $result = "\n";
                foreach ($items[1] as $index => $item) {
                    $num = $index + 1;
                    $result .= "$num. $item\n";
                }
                return $result;
            }, $html);
      // === BLOCKQUOTE ===
        // Blockquote -> > citazione
        $html = preg_replace_callback('/<blockquote[^>]*>(.*?)<\/blockquote>/is',
            function($matches) {
                $lines = explode("\n", trim($matches[1]));
                $result = "";
                foreach ($lines as $line) {
                    if (trim($line) != '') {
                        $result .= "> " . trim($line) . "\n";
                    }
                }
                return "\n" . $result . "\n";
            }, $html);
      // === PARAGRAFI ===
        $html = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "\n$1\n", $html);    // Paragrafi - doppio a capo
      // === BREAK ===
        $html = preg_replace('/<br[^>]*>/i', "\n", $html);                  // Line breaks
      // === DIVISORI ===
        $html = preg_replace('/<hr[^>]*>/i', "\n---\n", $html);             // HR - ---
      // === TABELLE === (versione base)
        $html = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is',
            function($matches) {
                $table = $matches[1];
                $result = "\n";
                // Estrae righe
                preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $table, $rows);
                $isFirstRow = true;
                foreach ($rows[1] as $row) {
                    // Estrae celle (th o td)
                    preg_match_all('/<(th|td)[^>]*>(.*?)<\/(th|td)>/is', $row, $cells);
                    if (!empty($cells[2])) {
                        $result .= "| " . implode(" | ", array_map('trim', $cells[2])) . " |\n";
                        // Dopo la prima riga, aggiunge il separatore
                        if ($isFirstRow) {
                            $result .= "|" . str_repeat(" --- |", count($cells[2])) . "\n";
                            $isFirstRow = false;
                        }
                    }
                }
                return $result . "\n";
            }, $html);
      // === PULIZIA FINALE ===
        // Rimuove tutti i tag HTML rimanenti
        $markdown = strip_tags($html);
        // Pulisce spazi multipli
        $markdown = preg_replace('/[ \t]+/', ' ', $markdown);
        // Pulisce righe vuote multiple
        $markdown = preg_replace('/\n\s*\n\s*\n/', "\n\n", $markdown);
        // Trim finale
        $markdown = trim($markdown);
        return $markdown;
    }

    /**
     * Estrae solo il testo puro dall'HTML (senza formattazione Markdown)
     * 
     * @param string $address la pagina all'url da convertire
     * @return string Il testo puro
     */
    function getPageText(string $address) {
        $html=$this->getPageHtml($address);
        // Rimuove script, style e blocchi di codice
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        $html = preg_replace('/<pre[^>]*>.*?<\/pre>/is', '', $html);
        $html = preg_replace('/<code[^>]*>.*?<\/code>/is', '', $html);
        // Aggiunge spazi dove necessario
        $html = preg_replace('/<br[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);
        $html = preg_replace('/<li[^>]*>/i', "\n• ", $html);
        // Rimuove tutti i tag HTML
        $text = strip_tags($html);
        // Decodifica entità HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Pulisce spazi multipli
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        return trim($text);
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