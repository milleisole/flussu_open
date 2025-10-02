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

    /**
     * Estrae il contenuto pulito da una pagina web e lo restituisce come JSON
     * contenente solo testo, link e date trovate
     * 
     * @param string $address L'URL della pagina da analizzare
     * @return string JSON con struttura {texts: [], links: [], dates: []}
     */
    public function getPageContentJSON(string $address): string 
    {
        // Recupera l'HTML della pagina
        $html = $this->getPageHtml($address);
        
        // Se c'è stato un errore, restituiscilo
        if (strpos($html, '"ERROR:"') !== false) {
            return $html;
        }
        
        // Crea un DOMDocument per parsare l'HTML
        $dom = new \DOMDocument();
        
        // Sopprimi gli warning per HTML malformato
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Rimuovi tutti gli script
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }
        
        // Rimuovi tutti gli style
        $styles = $dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }
        
        // Rimuovi tutti gli attributi class e style
        $xpath = new \DOMXPath($dom);
        $nodesWithClass = $xpath->query('//*[@class]');
        foreach ($nodesWithClass as $node) {
            $node->removeAttribute('class');
        }
        
        $nodesWithStyle = $xpath->query('//*[@style]');
        foreach ($nodesWithStyle as $node) {
            $node->removeAttribute('style');
        }
        
        // Ottieni solo il contenuto del body
        $body = $dom->getElementsByTagName('body')->item(0);
        
        if (!$body) {
            // Se non c'è body, prova a lavorare con tutto il documento
            $body = $dom->documentElement;
        }
        
        // Array per raccogliere gli elementi in sequenza
        $sequentialElements = [];
        $dates = [];
        
        // Buffer per accumulare testi prima del merge
        $textBuffer = [];
        
        // Funzione ricorsiva per processare i nodi in ordine
        $processNode = function($node) use (&$processNode, &$sequentialElements, &$textBuffer, &$dates, $address) {
            // Salta nodi che non ci interessano
            if (in_array($node->nodeName, ['img', 'svg', 'canvas', 'video', 'audio', 'iframe', 'picture', 'source'])) {
                return;
            }
            
            // Se è un link
            if ($node->nodeName === 'a') {
                // Prima salva qualsiasi testo bufferizzato
                if (!empty($textBuffer)) {
                    $mergedTexts = $this->mergeConsecutiveTexts($textBuffer);
                    foreach ($mergedTexts as $text) {
                        $sequentialElements[] = ['text' => $text];
                    }
                    $textBuffer = [];
                }
                
                $href = $node->getAttribute('href');
                $linkText = trim($node->textContent);
                
                if (!empty($href) && !empty($linkText)) {
                    // Normalizza URL relativi
                    if (!empty($href) && strpos($href, 'http') !== 0 && strpos($href, '#') !== 0 && strpos($href, 'javascript:') !== 0) {
                        $parsedUrl = parse_url($address);
                        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                        
                        if (strpos($href, '/') === 0) {
                            $href = $baseUrl . $href;
                        } else {
                            $href = $baseUrl . '/' . $href;
                        }
                    }
                    
                    // Aggiungi il link come elemento separato
                    if (strpos($href, 'http') === 0) {
                        $sequentialElements[] = [
                            'text' => $linkText,
                            'url' => $href
                        ];
                    }
                }
                
                // Cerca date nel testo del link
                $this->extractDates($linkText, $dates);
            }
            // Se è un nodo di testo
            elseif ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->textContent);
                if (!empty($text) && strlen($text) > 1) {
                    // Pulisci il testo da spazi multipli
                    $text = preg_replace('/\s+/', ' ', $text);
                    
                    // Cerca date nel testo
                    $this->extractDates($text, $dates);
                    
                    // Aggiungi al buffer per il merge successivo
                    $textBuffer[] = $text;
                }
            }
            // Per altri elementi, processa ricorsivamente i figli
            elseif ($node->hasChildNodes()) {
                // Se è un elemento di blocco (p, div, h1-h6, li, etc.), flasha il buffer prima
                if (in_array($node->nodeName, ['p', 'div', 'section', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'pre', 'td', 'th'])) {
                    // Salva il buffer corrente se non vuoto
                    if (!empty($textBuffer)) {
                        $mergedTexts = $this->mergeConsecutiveTexts($textBuffer);
                        foreach ($mergedTexts as $text) {
                            $sequentialElements[] = ['text' => $text];
                        }
                        $textBuffer = [];
                    }
                }
                
                // Processa i figli
                foreach ($node->childNodes as $child) {
                    $processNode($child);
                }
                
                // Dopo aver processato un elemento di blocco, flasha di nuovo il buffer
                if (in_array($node->nodeName, ['p', 'div', 'section', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'pre', 'td', 'th'])) {
                    if (!empty($textBuffer)) {
                        $mergedTexts = $this->mergeConsecutiveTexts($textBuffer);
                        foreach ($mergedTexts as $text) {
                            $sequentialElements[] = ['text' => $text];
                        }
                        $textBuffer = [];
                    }
                }
            }
        };
        
        // Processa il body
        if ($body) {
            $processNode($body);
        }
        
        // Processa qualsiasi testo rimasto nel buffer
        if (!empty($textBuffer)) {
            $mergedTexts = $this->mergeConsecutiveTexts($textBuffer);
            foreach ($mergedTexts as $text) {
                $sequentialElements[] = ['text' => $text];
            }
        }
        
        // Pulisci elementi duplicati consecutivi
        $cleanedElements = [];
        $lastElement = null;
        
        foreach ($sequentialElements as $element) {
            // Salta se è identico all'elemento precedente
            if ($lastElement && 
                $lastElement['text'] === $element['text'] && 
                (!isset($element['url']) || (isset($lastElement['url']) && $lastElement['url'] === $element['url']))) {
                continue;
            }
            
            // Salta testi troppo corti che non sono titoli o link
            if (!isset($element['url']) && 
                strlen($element['text']) < 10 && 
                !$this->isAllUppercase($element['text']) && 
                !$this->containsPrice($element['text'])) {
                continue;
            }
            
            $cleanedElements[] = $element;
            $lastElement = $element;
        }
        
        // Rimuovi date duplicate
        $dates = array_unique($dates);
        
        // Costruisci il risultato finale
        $finalResult = [
            'url' => $address,
            'timestamp' => date('Y-m-d H:i:s'),
            'content' => $cleanedElements,
            'dates_found' => array_values($dates),
            'stats' => [
                'total_elements' => count($cleanedElements),
                'text_blocks' => count(array_filter($cleanedElements, function($el) { 
                    return !isset($el['url']); 
                })),
                'links' => count(array_filter($cleanedElements, function($el) { 
                    return isset($el['url']); 
                })),
                'dates' => count($dates)
            ]
        ];
        
        return json_encode($finalResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    /**
     * Estrae date dal testo usando vari pattern
     * 
     * @param string $text Il testo da analizzare
     * @param array &$dates Array dove aggiungere le date trovate
     */
    private function extractDates(string $text, array &$dates): void 
    {
        // Pattern per date comuni
        $patterns = [
            // Formato DD/MM/YYYY o DD-MM-YYYY
            '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/',
            // Formato YYYY-MM-DD
            '/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/',
            // Formato testuale: January 15, 2025 o 15 January 2025
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December),?\s+\d{4}\b/i',
            // Formato italiano: 15 gennaio 2025
            '/\b\d{1,2}\s+(?:gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre),?\s+\d{4}\b/i',
            // Solo anno se è un anno recente/plausibile
            '/\b(19[5-9]\d|20[0-9]\d)\b/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $dates[] = $match;
                }
            }
        }
    }

    /**
     * Unisce testi consecutivi che probabilmente appartengono allo stesso contesto
     * con logica migliorata per gestire titoli, prezzi e continuazioni
     * 
     * @param array $texts Array di testi da unire
     * @return array Array di testi uniti intelligentemente
     */
    private function mergeConsecutiveTexts(array $texts): array 
    {
        if (empty($texts)) {
            return [];
        }
        
        $merged = [];
        $currentBlock = '';
        
        foreach ($texts as $index => $text) {
            $text = trim($text);
            
            // Salta testi vuoti o troppo corti
            if (strlen($text) < 2) {
                continue;
            }
            
            // Controlla se il testo corrente è un elemento standalone
            $isStandalone = $this->isStandaloneText($text);
            
            // Se abbiamo un blocco corrente
            if (!empty($currentBlock)) {
                // Se è standalone (titolo, prezzo, etc.), salva il blocco corrente e inizia nuovo
                if ($isStandalone) {
                    $merged[] = trim($currentBlock);
                    $currentBlock = $text;
                    
                    // Se è un titolo o elemento con punteggiatura finale, salvalo subito
                    if ($this->isAllUppercase($text) || preg_match('/[.!?]\s*$/', $text)) {
                        $merged[] = trim($currentBlock);
                        $currentBlock = '';
                    }
                } else {
                    // Non è standalone, verifica se deve continuare il blocco
                    $shouldContinue = $this->shouldContinueBlock($currentBlock, $text);
                    
                    if ($shouldContinue) {
                        // Continua il blocco corrente
                        $currentBlock .= ' ' . $text;
                        
                        // Se il blocco diventa troppo lungo, salvalo
                        if (strlen($currentBlock) > 800) {
                            $merged[] = trim($currentBlock);
                            $currentBlock = '';
                        }
                    } else {
                        // Non continua: salva il blocco corrente e iniziane uno nuovo
                        $merged[] = trim($currentBlock);
                        $currentBlock = $text;
                    }
                }
            } else {
                // Inizia un nuovo blocco
                $currentBlock = $text;
                
                // Se è standalone con punteggiatura finale, salvalo subito
                if ($isStandalone && preg_match('/[.!?]\s*$/', $text)) {
                    $merged[] = trim($currentBlock);
                    $currentBlock = '';
                }
            }
        }
        
        // Aggiungi l'ultimo blocco se non vuoto
        if (!empty($currentBlock)) {
            $merged[] = trim($currentBlock);
        }
        
        // Filtra blocchi troppo corti (ma mantieni titoli e elementi con prezzi)
        $filtered = array_filter($merged, function($text) {
            // Mantieni sempre testi con prezzi o che sono tutti maiuscoli (titoli)
            if ($this->containsPrice($text) || $this->isAllUppercase($text)) {
                return true;
            }
            // Per altri testi, richiedi lunghezza minima
            return strlen($text) > 10;
        });
        
        return array_values($filtered);
    }

    /**
     * Determina se un testo dovrebbe essere considerato standalone
     * 
     * @param string $text Il testo da analizzare
     * @return bool True se il testo è standalone
     */
    private function isStandaloneText(string $text): bool 
    {
        // Testo tutto maiuscolo (probabilmente un titolo/header)
        if ($this->isAllUppercase($text) && strlen($text) > 3) {
            return true;
        }
        
        // Contiene un prezzo
        if ($this->containsPrice($text)) {
            return true;
        }
        
        // È un numero singolo o una data
        if (preg_match('/^\d+$/', $text) || 
            preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', $text)) {
            return true;
        }
        
        // Menu items comuni o parole chiave di navigazione
        $menuKeywords = [
            'home', 'login', 'logout', 'menu', 'cerca', 'search',
            'contatti', 'contact', 'about', 'info', 'privacy',
            'cookie', 'close', 'chiudi', 'accetta', 'accept'
        ];
        
        $lowerText = strtolower($text);
        foreach ($menuKeywords as $keyword) {
            if ($lowerText === $keyword || strpos($lowerText, $keyword) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determina se il testo corrente dovrebbe continuare il blocco precedente
     * 
     * @param string $currentBlock Il blocco di testo corrente
     * @param string $newText Il nuovo testo da valutare
     * @return bool True se il nuovo testo dovrebbe continuare il blocco
     */
    private function shouldContinueBlock(string $currentBlock, string $newText): bool 
    {
        // REGOLA PRINCIPALE: Se inizia con minuscola, SEMPRE continua
        // (a meno che il blocco precedente non sia concluso definitivamente)
        if (preg_match('/^[a-zàèéìòù]/ui', $newText)) {
            // Eccezione: se il blocco termina con punteggiatura MOLTO definitiva
            if (preg_match('/[.!?]\s*$/', $currentBlock)) {
                // Ma anche qui, se inizia con congiunzione minuscola comune, continua comunque
                if (preg_match('/^(e |o |ma |però |quindi |inoltre |anche )/i', $newText)) {
                    return true;
                }
                return false;
            }
            // Altrimenti, se inizia con minuscola, SEMPRE continua
            return true;
        }
        
        // Se il nuovo testo è tutto maiuscolo, è un titolo -> nuovo blocco
        if ($this->isAllUppercase($newText)) {
            return false;
        }
        
        // Se il blocco corrente NON termina con punteggiatura definitiva
        // e il nuovo testo NON è un chiaro inizio di frase...
        if (!preg_match('/[.!?;]\s*$/', $currentBlock)) {
            // Pattern tipici di inizio nuovo paragrafo/sezione
            $newParagraphPatterns = [
                '/^(Il |La |Gli |Le |I |Lo |Un |Una |Uno |Degli |Delle |Dei )/i',  // Articoli italiani
                '/^(The |A |An |These |Those |This |That )/i',                      // Articoli inglesi  
                '/^(Questo |Questa |Questi |Queste |Quello |Quella )/i',            // Dimostrativi
                '/^(Per |Con |Su |Tra |Fra |Dal |Nel |Sul )/i',                     // Preposizioni
                '/^\d+[\.\)]\s/',                                                   // Numerazione (1. o 1) )
                '/^[A-Z][a-z]+:/',                                                  // Label: testo
                '/^(Nota:|Importante:|Attenzione:|N\.B\.|PS:|P\.S\.)/i',          // Annotazioni
            ];
            
            foreach ($newParagraphPatterns as $pattern) {
                if (preg_match($pattern, $newText)) {
                    return false;
                }
            }
            
            // Se non matcha nessun pattern di nuovo paragrafo, probabilmente continua
            return true;
        }
        
        return false;
    }

    /**
     * Verifica se il testo contiene un prezzo
     * 
     * @param string $text Il testo da verificare
     * @return bool True se contiene un prezzo
     */
    private function containsPrice(string $text): bool 
    {
        // Pattern per riconoscere prezzi in varie valute
        $pricePatterns = [
            '/[€$£¥₹₽¢]\s*\d+([.,]\d{1,2})?/',  // Simbolo valuta prima del numero
            '/\d+([.,]\d{1,2})?\s*[€$£¥₹₽¢]/',  // Simbolo valuta dopo il numero
            '/\b\d+([.,]\d{1,2})?\s*(eur|euro|usd|dollar|gbp|pound|yen|inr|rupee)/i',  // Valuta scritta
            '/\bEUR\s*\d+([.,]\d{1,2})?/i',      // EUR seguito da numero
            '/\bUSD\s*\d+([.,]\d{1,2})?/i',      // USD seguito da numero
            '/\b\d+([.,]\d{1,2})?\s*€/',         // Numero seguito da euro
        ];
        
        foreach ($pricePatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica se il testo è tutto in maiuscolo (escludendo numeri e punteggiatura)
     * 
     * @param string $text Il testo da verificare
     * @return bool True se il testo è tutto maiuscolo
     */
    private function isAllUppercase(string $text): bool 
    {
        // Rimuovi numeri, spazi e punteggiatura per il controllo
        $lettersOnly = preg_replace('/[^a-zA-ZàèéìòùÀÈÉÌÒÙáíóúÁÍÓÚâêîôûÂÊÎÔÛäëïöüÄËÏÖÜ]/u', '', $text);
        
        // Se non ci sono lettere, non è "tutto maiuscolo"
        if (empty($lettersOnly)) {
            return false;
        }
        
        // Se ha meno di 2 lettere, non considerarlo un titolo
        if (mb_strlen($lettersOnly) < 2) {
            return false;
        }
        
        // Controlla se tutte le lettere sono maiuscole
        return mb_strtoupper($lettersOnly, 'UTF-8') === $lettersOnly;
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