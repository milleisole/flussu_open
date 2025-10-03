<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5- Mille Isole SRL - Released under Apache License 2.0
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
 * TBD- UNFINISHED
 * 
 * CLASS-NAME:       Web Page Scraper controller - v1.0
 * CREATED DATE:     01.10.2025 - Aldus - Flussu v4.5.1
 * VERSION REL.:     4.5.1 -def- 20251003
 * UPDATE DATE:      03.10:2025 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * -------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Symfony\Component\Panther\Client;

class WebScraperController 
{
    private $url;
    private $html;
    private $dom;
    
    public function __construct($url = null) {
        $this->url = $url;
        $this->dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Sopprimi warning per HTML malformato
    }
    
    /**
     * Recupera l'HTML completo della pagina
     */
    public function getPageHtml($url = null) {
        if ($url) {
            $this->url = $url;
        }
        
        if (!$this->url) {
            throw new \Exception("URL non fornito");
        }
        
        // Opzioni per il contesto stream
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                ],
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        
        $context = stream_context_create($options);
        $this->html = @file_get_contents($this->url, false, $context);
        
        // Gestisci contenuto gzippato
        if ($this->html && substr($this->html, 0, 2) === "\x1f\x8b") {
            $this->html = gzdecode($this->html);
        }
        
        if (!$this->html) {
            throw new \Exception("Impossibile recuperare il contenuto dalla URL: " . $this->url);
        }
        
        $this->dom->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        return $this->html;
    }
    
    /**
     * Restituisce solo il contenuto del body senza script e CSS
     */
    public function getPageContentBody($url = null) {
        if ($url || !$this->html) {
            $this->getPageHtml($url);
        }
        
        // Crea un nuovo documento per il body pulito
        $cleanDom = new \DOMDocument();
        $cleanDom->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Rimuovi tutti gli script
        $scripts = $cleanDom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }
        
        // Rimuovi tutti gli style
        $styles = $cleanDom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }
        
        // Rimuovi tutti i link CSS
        $links = $cleanDom->getElementsByTagName('link');
        $toRemove = [];
        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'stylesheet') {
                $toRemove[] = $link;
            }
        }
        foreach ($toRemove as $node) {
            $node->parentNode->removeChild($node);
        }
        
        // Rimuovi attributi style inline
        $xpath = new \DOMXPath($cleanDom);
        $nodesWithStyle = $xpath->query('//*[@style]');
        foreach ($nodesWithStyle as $node) {
            $node->removeAttribute('style');
        }
        
        // Estrai solo il contenuto del body
        $body = $cleanDom->getElementsByTagName('body')->item(0);
        
        if (!$body) {
            return '';
        }
        
        // Salva solo il contenuto interno del body
        $bodyContent = '';
        foreach ($body->childNodes as $child) {
            $bodyContent .= $cleanDom->saveHTML($child);
        }
        
        return $bodyContent;
    }
    
    /**
     * Restituisce un JSON con contenuto testuale e links
     */
    public function getPageContentJson($url = null) {
        if ($url || !$this->html) {
            $this->getPageHtml($url);
        }
        
        $result = [
            'url' => $this->url,
            'title' => $this->extractTitle(),
            'content' => $this->extractContent(),
            'links' => $this->extractLinks(),
            'metadata' => $this->extractMetadata()
        ];
        
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Estrae il titolo della pagina
     */
    private function extractTitle() {
        $titleNode = $this->dom->getElementsByTagName('title')->item(0);
        if ($titleNode) {
            return trim($titleNode->textContent);
        }
        
        // Prova con h1
        $h1Node = $this->dom->getElementsByTagName('h1')->item(0);
        if ($h1Node) {
            return trim($h1Node->textContent);
        }
        
        return '';
    }
    
    /**
     * Estrae il contenuto principale della pagina
     */
    private function extractContent() {
        $xpath = new \DOMXPath($this->dom);
        $content = [];
        
        // Rimuovi script e style dal DOM temporaneo
        $this->removeUnwantedElements();
        
        // Cerca contenitori principali comuni
        $mainSelectors = [
            '//main',
            '//article',
            '//*[@role="main"]',
            '//*[@id="main"]',
            '//*[@id="content"]',
            '//*[@class="content"]',
            '//div[contains(@class, "article")]',
            '//div[contains(@class, "post")]',
            '//div[contains(@class, "entry")]',
            '//div[contains(@class, "body")]'
        ];
        
        $mainContent = null;
        foreach ($mainSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $mainContent = $nodes->item(0);
                break;
            }
        }
        
        // Se non troviamo un contenitore principale, usa il body
        if (!$mainContent) {
            $mainContent = $this->dom->getElementsByTagName('body')->item(0);
        }
        
        if ($mainContent) {
            // Estrai testo completo usando il metodo semplice
            $fullText = $this->extractText($mainContent);
            if (!empty($fullText)) {
                $content['text'] = $fullText;
            }
            
            // Estrai paragrafi strutturati
            $paragraphs = $this->extractParagraphs($mainContent);
            if (!empty($paragraphs)) {
                $content['paragraphs'] = $paragraphs;
            }
            
            // Estrai intestazioni
            $headings = $this->extractHeadings($mainContent);
            if (!empty($headings)) {
                $content['headings'] = $headings;
            }
            
            // Estrai liste
            $lists = $this->extractLists($mainContent);
            if (!empty($lists)) {
                $content['lists'] = $lists;
            }
            
            // Estrai tabelle
            $tables = $this->extractTables($mainContent);
            if (!empty($tables)) {
                $content['tables'] = $tables;
            }
        }
        
        return $content;
    }
    
    /**
     * Estrae tutto il testo rilevante da un nodo DOM
     * Metodo semplice e diretto basato su paragrafi e altri elementi testuali
     */
    private function extractText($node) {
        $text = '';
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        // Importa il nodo nel nuovo documento
        $imported = $dom->importNode($node, true);
        $dom->appendChild($imported);
        
        // Prima rimuovi elementi non desiderati
        $xpath = new \DOMXPath($dom);
        $elementsToRemove = $xpath->query('//script | //style | //noscript | //iframe');
        foreach ($elementsToRemove as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }
        
        // Estrai testo dai paragrafi
        foreach ($dom->getElementsByTagName('p') as $p) {
            $t = $this->cleanText($p->textContent);
            if ($t !== '' && strlen($t) > 10) {
                $text .= $t . "\n\n";
            }
        }
        
        // Estrai anche da div con contenuto testuale (senza altri div dentro)
        $divs = $xpath->query('//div[not(.//div) and not(.//p) and string-length(normalize-space(text())) > 50]');
        foreach ($divs as $div) {
            $t = $this->cleanText($div->textContent);
            if ($t !== '' && strlen($t) > 50 && !str_contains($text, $t)) {
                $text .= $t . "\n\n";
            }
        }
        
        // Estrai da blockquote
        foreach ($dom->getElementsByTagName('blockquote') as $quote) {
            $t = $this->cleanText($quote->textContent);
            if ($t !== '' && !str_contains($text, $t)) {
                $text .= $t . "\n\n";
            }
        }
        
        // Estrai da elementi article e section se non abbiamo abbastanza testo
        if (strlen($text) < 100) {
            $articles = $xpath->query('//article | //section');
            foreach ($articles as $article) {
                $t = $this->cleanText($article->textContent);
                if ($t !== '' && strlen($t) > 100 && !str_contains($text, $t)) {
                    $text .= $t . "\n\n";
                }
            }
        }
        
        // Pulisci il testo finale
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        
        libxml_clear_errors();
        return trim($text);
    }
    
    /**
     * Estrae i paragrafi dal contenuto
     */
    private function extractParagraphs($node) {
        $paragraphs = [];
        $xpath = new \DOMXPath($this->dom);
        
        // Cerca tutti i paragrafi e div con testo
        $textNodes = $xpath->query('.//p | .//div[not(.//div) and not(.//p)]', $node);
        
        foreach ($textNodes as $textNode) {
            $text = $this->cleanText($textNode->textContent);
            // Filtra paragrafi troppo corti o vuoti
            if (strlen($text) > 20) {
                $paragraphs[] = $text;
            }
        }
        
        // Se non ci sono paragrafi, prova a estrarre il testo direttamente
        if (empty($paragraphs)) {
            $text = $this->cleanText($node->textContent);
            if (strlen($text) > 50) {
                // Dividi per linee vuote
                $lines = preg_split('/\n\s*\n/', $text);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strlen($line) > 20) {
                        $paragraphs[] = $line;
                    }
                }
            }
        }
        
        return array_unique($paragraphs);
    }
    
    /**
     * Estrae le intestazioni
     */
    private function extractHeadings($node) {
        $headings = [];
        $xpath = new \DOMXPath($this->dom);
        
        for ($i = 1; $i <= 6; $i++) {
            $headers = $xpath->query(".//h{$i}", $node);
            foreach ($headers as $header) {
                $text = $this->cleanText($header->textContent);
                if (!empty($text)) {
                    $headings[] = [
                        'level' => $i,
                        'text' => $text
                    ];
                }
            }
        }
        
        return $headings;
    }
    
    /**
     * Estrae le liste
     */
    private function extractLists($node) {
        $lists = [];
        $xpath = new \DOMXPath($this->dom);
        
        // Liste non ordinate
        $uls = $xpath->query('.//ul', $node);
        foreach ($uls as $ul) {
            $items = [];
            $lis = $xpath->query('./li', $ul);
            foreach ($lis as $li) {
                $text = $this->cleanText($li->textContent);
                if (!empty($text)) {
                    $items[] = $text;
                }
            }
            if (!empty($items)) {
                $lists[] = [
                    'type' => 'unordered',
                    'items' => $items
                ];
            }
        }
        
        // Liste ordinate
        $ols = $xpath->query('.//ol', $node);
        foreach ($ols as $ol) {
            $items = [];
            $lis = $xpath->query('./li', $ol);
            foreach ($lis as $li) {
                $text = $this->cleanText($li->textContent);
                if (!empty($text)) {
                    $items[] = $text;
                }
            }
            if (!empty($items)) {
                $lists[] = [
                    'type' => 'ordered',
                    'items' => $items
                ];
            }
        }
        
        return $lists;
    }
    
    /**
     * Estrae le tabelle
     */
    private function extractTables($node) {
        $tables = [];
        $xpath = new \DOMXPath($this->dom);
        
        $tableNodes = $xpath->query('.//table', $node);
        foreach ($tableNodes as $table) {
            $tableData = [];
            
            // Estrai header
            $headers = [];
            $ths = $xpath->query('.//th', $table);
            foreach ($ths as $th) {
                $headers[] = $this->cleanText($th->textContent);
            }
            if (!empty($headers)) {
                $tableData['headers'] = $headers;
            }
            
            // Estrai righe
            $rows = [];
            $trs = $xpath->query('.//tr', $table);
            foreach ($trs as $tr) {
                $row = [];
                $tds = $xpath->query('./td', $tr);
                foreach ($tds as $td) {
                    $row[] = $this->cleanText($td->textContent);
                }
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
            if (!empty($rows)) {
                $tableData['rows'] = $rows;
            }
            
            if (!empty($tableData)) {
                $tables[] = $tableData;
            }
        }
        
        return $tables;
    }
    
    /**
     * Estrae tutti i links dalla pagina
     */
    private function extractLinks() {
        $links = [];
        $xpath = new \DOMXPath($this->dom);
        
        // Trova tutti i link
        $linkNodes = $xpath->query('//a[@href]');
        
        foreach ($linkNodes as $link) {
            $href = $link->getAttribute('href');
            $text = $this->cleanText($link->textContent);
            
            // Salta link vuoti o solo con immagini
            if (empty($text) || empty($href)) {
                continue;
            }
            
            // Normalizza URL relative
            $href = $this->normalizeUrl($href);
            
            $linkData = [
                'url' => $href,
                'text' => $text
            ];
            
            // Cerca una data associata
            $date = $this->findAssociatedDate($link);
            if ($date) {
                $linkData['date'] = $date;
            }
            
            // Estrai altri attributi utili
            if ($link->hasAttribute('title')) {
                $linkData['title'] = $link->getAttribute('title');
            }
            
            if ($link->hasAttribute('rel')) {
                $linkData['rel'] = $link->getAttribute('rel');
            }
            
            // Categorizza il link
            $linkData['type'] = $this->categorizeLink($href, $text);
            
            $links[] = $linkData;
        }
        
        // Rimuovi duplicati basandoti sull'URL
        $uniqueLinks = [];
        $seenUrls = [];
        foreach ($links as $link) {
            if (!in_array($link['url'], $seenUrls)) {
                $uniqueLinks[] = $link;
                $seenUrls[] = $link['url'];
            }
        }
        
        return $uniqueLinks;
    }
    
    /**
     * Estrae metadata dalla pagina
     */
    private function extractMetadata() {
        $metadata = [];
        $xpath = new \DOMXPath($this->dom);
        
        // Meta tags standard
        $metaTags = $xpath->query('//meta[@name or @property]');
        foreach ($metaTags as $meta) {
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            
            if ($name && $content) {
                $metadata[$name] = $content;
            }
        }
        
        // Open Graph
        $ogTags = $xpath->query('//meta[starts-with(@property, "og:")]');
        foreach ($ogTags as $og) {
            $property = str_replace('og:', '', $og->getAttribute('property'));
            $metadata['og'][$property] = $og->getAttribute('content');
        }
        
        // Twitter Cards
        $twitterTags = $xpath->query('//meta[starts-with(@name, "twitter:")]');
        foreach ($twitterTags as $twitter) {
            $name = str_replace('twitter:', '', $twitter->getAttribute('name'));
            $metadata['twitter'][$name] = $twitter->getAttribute('content');
        }
        
        // Data pubblicazione
        $publishDate = $this->findPublishDate();
        if ($publishDate) {
            $metadata['publishDate'] = $publishDate;
        }
        
        // Autore
        $author = $this->findAuthor();
        if ($author) {
            $metadata['author'] = $author;
        }
        
        return $metadata;
    }
    
    /**
     * Cerca di trovare la data di pubblicazione
     */
    private function findPublishDate() {
        $xpath = new \DOMXPath($this->dom);
        
        // Cerca in meta tags
        $dateSelectors = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="publish_date"]/@content',
            '//meta[@name="date"]/@content',
            '//time[@datetime]/@datetime',
            '//*[@class="date"]',
            '//*[@class="published"]',
            '//*[contains(@class, "date")]',
            '//*[contains(@class, "time")]'
        ];
        
        foreach ($dateSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $dateStr = $nodes->item(0)->nodeValue ?: $nodes->item(0)->textContent;
                $date = $this->parseDate($dateStr);
                if ($date) {
                    return $date;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Cerca di trovare l'autore
     */
    private function findAuthor() {
        $xpath = new \DOMXPath($this->dom);
        
        $authorSelectors = [
            '//meta[@name="author"]/@content',
            '//meta[@property="article:author"]/@content',
            '//*[@class="author"]',
            '//*[@class="by-author"]',
            '//*[contains(@class, "author")]',
            '//span[@itemprop="author"]'
        ];
        
        foreach ($authorSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $author = $nodes->item(0)->nodeValue ?: $nodes->item(0)->textContent;
                return $this->cleanText($author);
            }
        }
        
        return null;
    }
    
    /**
     * Cerca una data associata a un elemento
     */
    private function findAssociatedDate($element) {
        $xpath = new \DOMXPath($this->dom);
        
        // Cerca date nel parent e siblings
        $parent = $element->parentNode;
        if ($parent) {
            // Cerca time elements
            $timeNodes = $xpath->query('.//time[@datetime]', $parent);
            if ($timeNodes->length > 0) {
                return $this->parseDate($timeNodes->item(0)->getAttribute('datetime'));
            }
            
            // Cerca classi con date
            $dateNodes = $xpath->query('.//*[contains(@class, "date") or contains(@class, "time")]', $parent);
            if ($dateNodes->length > 0) {
                $dateStr = $this->cleanText($dateNodes->item(0)->textContent);
                return $this->parseDate($dateStr);
            }
        }
        
        return null;
    }
    
    /**
     * Categorizza un link
     */
    private function categorizeLink($url, $text) {
        $url = strtolower($url);
        $text = strtolower($text);
        
        // Social media
        if (preg_match('/(facebook|twitter|instagram|linkedin|youtube)\.com/', $url)) {
            return 'social';
        }
        
        // Email
        if (strpos($url, 'mailto:') === 0) {
            return 'email';
        }
        
        // Tel
        if (strpos($url, 'tel:') === 0) {
            return 'phone';
        }
        
        // Download
        if (preg_match('/\.(pdf|doc|docx|xls|xlsx|zip|rar)$/i', $url)) {
            return 'download';
        }
        
        // External
        if (strpos($url, 'http') === 0) {
            $currentHost = parse_url($this->url, PHP_URL_HOST);
            $linkHost = parse_url($url, PHP_URL_HOST);
            if ($currentHost !== $linkHost) {
                return 'external';
            }
        }
        
        // Navigation
        if (preg_match('/(home|menu|nav|about|contact|privacy|terms)/', $text)) {
            return 'navigation';
        }
        
        return 'internal';
    }
    
    /**
     * Normalizza URL relative
     */
    private function normalizeUrl($url) {
        // Se è già un URL assoluto, ritornalo
        if (preg_match('/^https?:\/\//', $url) || strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) {
            return $url;
        }
        
        // Se è un anchor, ritornalo
        if (strpos($url, '#') === 0) {
            return $url;
        }
        
        // Parse base URL
        $parts = parse_url($this->url);
        $base = $parts['scheme'] . '://' . $parts['host'];
        
        if (isset($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        
        // URL assoluto dal root
        if (strpos($url, '/') === 0) {
            return $base . $url;
        }
        
        // URL relativo
        $path = isset($parts['path']) ? dirname($parts['path']) : '';
        if ($path === '/') {
            $path = '';
        }
        
        return $base . $path . '/' . $url;
    }
    
    /**
     * Pulisce il testo
     */
    private function cleanText($text) {
        // Rimuovi spazi multipli
        $text = preg_replace('/\s+/', ' ', $text);
        // Rimuovi newline eccessive
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Trim
        $text = trim($text);
        // Decodifica entità HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $text;
    }
    
    /**
     * Parse una data
     */
    private function parseDate($dateStr) {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }
        
        // Prova formati comuni
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'j F Y',
            'F j, Y',
            'Y-m-d\TH:i:sP',
            'c'
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        // Prova con strtotime
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        // Pattern italiano
        if (preg_match('/(\d{1,2})\s+(gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\s+(\d{4})/i', $dateStr, $matches)) {
            $months = [
                'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4,
                'maggio' => 5, 'giugno' => 6, 'luglio' => 7, 'agosto' => 8,
                'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12
            ];
            
            $month = $months[strtolower($matches[2])];
            $date = sprintf('%04d-%02d-%02d', $matches[3], $month, $matches[1]);
            return $date . ' 00:00:00';
        }
        
        return null;
    }
    
    /**
     * Rimuove elementi non desiderati dal DOM
     */
    private function removeUnwantedElements() {
        $xpath = new \DOMXPath($this->dom);
        
        // Lista di elementi da rimuovere
        $toRemove = [
            '//script',
            '//style',
            '//noscript',
            '//iframe',
            '//form',
            '//button',
            '//input',
            '//select',
            '//textarea',
            '//*[@class="advertisement"]',
            '//*[@class="ad"]',
            '//*[contains(@class, "cookie")]',
            '//*[contains(@class, "banner")]',
            '//*[contains(@class, "popup")]',
            '//*[contains(@id, "advertisement")]',
            '//*[contains(@id, "ad-")]'
        ];
        
        foreach ($toRemove as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

}
/*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS | & CLAUDE
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/ 