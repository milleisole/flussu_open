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
    


    public function getPageHtml($url){
        //$url = 'https://www.facebook.com/centralmarketingintelligenceitalia';
        if ($url) {
            $this->url = $url;
        }
        
        if (!$this->url) {
            throw new \Exception("URL non fornito");
        }
        $scriptPath = $_SERVER["DOCUMENT_ROOT"] . "/../src/scripts";
        $url = escapeshellarg($this->url);
        $cmd = "cd " . escapeshellarg($scriptPath) . " && /usr/local/bin/node browser_fetch.js " . $url . " 2>&1";
        $this->html = shell_exec($cmd);
        $this->dom->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return $this->html;
    }


    /**
     * Recupera l'HTML completo della pagina
     */
    public function getPageHtml2($url) {
        if ($url) {
            $this->url = $url;
        }
        
        if (!$this->url) {
            throw new \Exception("URL non fornito");
        }
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Pragma: no-cache',
            'Cache-Control: no-cache'
        ];
        // Opzioni per il contesto stream
        $options = [
            'https' => [
                'method' => 'GET',
                'header' => $headers,
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
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => $headers,
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
     * Restituisce un JSON strutturato top-down con tutto il testo della pagina
     * organizzato per sezioni semantiche (header, nav, main, footer, ecc.)
     *
     * Rimuove completamente JavaScript, CSS e elementi non testuali.
     * Il risultato riflette la struttura reale della pagina.
     */
    public function getPageContentJson($url = null) {
        if ($url || !$this->html) {
            $this->getPageHtml($url);
        }

        $cleanDom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $cleanDom->loadHTML('<?xml encoding="UTF-8">' . $this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Rimuovi tutti gli elementi non testuali
        $this->_stripNonContentElements($cleanDom);

        $body = $cleanDom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return json_encode(['url' => $this->url, 'error' => 'No body found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $result = [
            'url' => $this->url,
            'title' => $this->extractTitle(),
        ];

        // Mappa delle sezioni semantiche trovate
        $semanticSections = $this->_identifySemanticSections($body);

        if (!empty($semanticSections)) {
            $result['body'] = $semanticSections;
        } else {
            // Fallback: estrai tutto il body come una sezione unica
            $result['body'] = ['content' => $this->_extractNodeContent($body)];
        }

        // Aggiungi metadata essenziali
        $meta = $this->extractMetadata();
        if (!empty($meta)) {
            $result['metadata'] = $meta;
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rimuove completamente script, style, e tutti gli elementi non-contenuto dal DOM
     */
    private function _stripNonContentElements(\DOMDocument $dom) {
        $xpath = new \DOMXPath($dom);

        // Elementi da rimuovere completamente
        $selectorsToRemove = [
            '//script', '//style', '//noscript', '//iframe', '//object', '//embed',
            '//link[@rel="stylesheet"]', '//link[@rel="preload"]', '//link[@rel="prefetch"]',
            '//meta', '//svg[not(ancestor::a)]',
            '//form', '//input', '//select', '//textarea', '//button',
            '//comment()',
            // Elementi pubblicitari e overlay comuni
            '//*[contains(@class,"cookie")]', '//*[contains(@class,"Cookie")]',
            '//*[contains(@class,"consent")]', '//*[contains(@class,"Consent")]',
            '//*[contains(@class,"popup")]', '//*[contains(@class,"Popup")]',
            '//*[contains(@class,"modal")]', '//*[contains(@class,"Modal")]',
            '//*[contains(@class,"overlay")]',
            '//*[contains(@class,"advertisement")]', '//*[contains(@class,"ad-container")]',
            '//*[contains(@class,"ads-")]', '//*[contains(@class,"adsbygoogle")]',
            '//*[contains(@id,"cookie")]', '//*[contains(@id,"Cookie")]',
            '//*[contains(@id,"consent")]', '//*[contains(@id,"Consent")]',
            '//*[contains(@id,"advertisement")]', '//*[contains(@id,"ad-container")]',
        ];

        foreach ($selectorsToRemove as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes) {
                // Raccogli prima tutti i nodi, poi rimuovili
                $toRemove = [];
                foreach ($nodes as $node) {
                    $toRemove[] = $node;
                }
                foreach ($toRemove as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }

        // Rimuovi tutti gli attributi style inline e gli attributi on* (event handlers JS)
        $allElements = $xpath->query('//*');
        if ($allElements) {
            foreach ($allElements as $el) {
                $el->removeAttribute('style');
                $el->removeAttribute('onclick');
                $el->removeAttribute('onload');
                $el->removeAttribute('onmouseover');
                $el->removeAttribute('onmouseout');
                $el->removeAttribute('onfocus');
                $el->removeAttribute('onblur');
            }
        }
    }

    /**
     * Identifica le sezioni semantiche della pagina e ne estrae il contenuto
     * Restituisce un array associativo con le sezioni trovate
     */
    private function _identifySemanticSections(\DOMNode $body) {
        $sections = [];
        $xpath = new \DOMXPath($body->ownerDocument);

        // Definizione delle sezioni semantiche da cercare, in ordine top-down
        $sectionMap = [
            'header'  => ['//header', '//*[@role="banner"]', '//*[contains(@class,"header") and not(ancestor::main) and not(ancestor::article)]', '//*[contains(@id,"header")]'],
            'nav'     => ['//nav', '//*[@role="navigation"]', '//*[contains(@class,"nav") and not(contains(@class,"navbar-"))]', '//*[contains(@class,"menu") and not(ancestor::footer)]'],
            'main'    => ['//main', '//*[@role="main"]', '//*[@id="main"]', '//*[@id="content"]', '//*[contains(@class,"main-content")]', '//*[contains(@class,"page-content")]'],
            'article' => ['//article', '//*[contains(@class,"article")]', '//*[contains(@class,"post-content")]', '//*[contains(@class,"entry-content")]'],
            'aside'   => ['//aside', '//*[@role="complementary"]', '//*[contains(@class,"sidebar")]', '//*[contains(@class,"side-bar")]'],
            'footer'  => ['//footer', '//*[@role="contentinfo"]', '//*[contains(@class,"footer")]', '//*[contains(@id,"footer")]'],
        ];

        // Nodi già processati (per evitare duplicati quando un nodo è figlio di un altro già estratto)
        $processedNodes = new \SplObjectStorage();

        foreach ($sectionMap as $sectionName => $selectors) {
            $sectionContent = [];

            foreach ($selectors as $selector) {
                $nodes = $xpath->query($selector, $body);
                if (!$nodes) continue;

                foreach ($nodes as $node) {
                    // Salta nodi già processati o figli di nodi già processati
                    if ($processedNodes->contains($node) || $this->_isDescendantOfProcessed($node, $processedNodes)) {
                        continue;
                    }

                    $content = $this->_extractNodeContent($node);
                    if (!empty($content)) {
                        $processedNodes->attach($node);
                        if (is_array($content) && count($content) === 1 && isset($content[0])) {
                            $sectionContent[] = $content[0];
                        } else {
                            $sectionContent[] = $content;
                        }
                    }
                }
            }

            if (!empty($sectionContent)) {
                $sections[$sectionName] = count($sectionContent) === 1 ? $sectionContent[0] : $sectionContent;
            }
        }

        // Cerca contenuto non ancora catturato (sezioni generiche, div di primo livello, ecc.)
        $uncaptured = $this->_extractUncapturedContent($body, $processedNodes);
        if (!empty($uncaptured)) {
            if (empty($sections['main'])) {
                $sections['main'] = $uncaptured;
            } else {
                $sections['other_content'] = $uncaptured;
            }
        }

        return $sections;
    }

    /**
     * Verifica se un nodo è discendente di un nodo già processato
     */
    private function _isDescendantOfProcessed(\DOMNode $node, \SplObjectStorage $processed) {
        $parent = $node->parentNode;
        while ($parent) {
            if ($processed->contains($parent)) {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /**
     * Estrae contenuto testuale strutturato da un nodo DOM.
     * Percorre ricorsivamente i figli e genera un array strutturato.
     */
    private function _extractNodeContent(\DOMNode $node) {
        $result = [];
        $currentHeading = null;
        $currentBlock = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $this->_cleanTextContent($child->textContent);
                if ($text !== '') {
                    $currentBlock[] = $text;
                }
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($child->nodeName);

            // Salta elementi nascosti
            if ($this->_isHiddenElement($child)) {
                continue;
            }

            // Gestisci headings - creano nuove sezioni
            if (preg_match('/^h([1-6])$/', $tagName, $m)) {
                // Salva il blocco corrente
                if (!empty($currentBlock)) {
                    if ($currentHeading) {
                        $result[] = [$currentHeading => $this->_mergeTextBlocks($currentBlock)];
                    } else {
                        $merged = $this->_mergeTextBlocks($currentBlock);
                        if (is_string($merged)) {
                            $result[] = $merged;
                        } else {
                            $result = array_merge($result, (array)$merged);
                        }
                    }
                    $currentBlock = [];
                }
                $headingText = $this->_cleanTextContent($child->textContent);
                if ($headingText !== '') {
                    $currentHeading = $headingText;
                }
                continue;
            }

            // Gestisci link
            if ($tagName === 'a') {
                $linkText = $this->_cleanTextContent($child->textContent);
                $href = $child->getAttribute('href');
                if ($linkText !== '' && $href !== '' && $href !== '#') {
                    $href = $this->normalizeUrl($href);
                    $currentBlock[] = "[{$linkText}]({$href})";
                } elseif ($linkText !== '') {
                    $currentBlock[] = $linkText;
                }
                continue;
            }

            // Gestisci immagini con alt text
            if ($tagName === 'img') {
                $alt = $child->getAttribute('alt');
                if ($alt !== '') {
                    $currentBlock[] = "[img: " . $this->_cleanTextContent($alt) . "]";
                }
                continue;
            }

            // Gestisci liste
            if ($tagName === 'ul' || $tagName === 'ol') {
                $listItems = $this->_extractListItems($child);
                if (!empty($listItems)) {
                    $currentBlock[] = $listItems;
                }
                continue;
            }

            // Gestisci tabelle
            if ($tagName === 'table') {
                $tableData = $this->_extractTableData($child);
                if (!empty($tableData)) {
                    $currentBlock[] = ['table' => $tableData];
                }
                continue;
            }

            // Gestisci <br> come separatore
            if ($tagName === 'br') {
                continue;
            }

            // Elementi blocco: section, div, p, blockquote, figure, ecc.
            // Ricorsione per estrarre il contenuto
            if (in_array($tagName, ['div', 'section', 'p', 'blockquote', 'figure', 'figcaption', 'details', 'summary', 'dl', 'dd', 'dt', 'address', 'pre', 'code'])) {
                $innerContent = $this->_extractNodeContent($child);
                if (!empty($innerContent)) {
                    // Per <p> e blockquote, il contenuto è testo inline
                    if ($tagName === 'p' || $tagName === 'blockquote') {
                        $flat = $this->_flattenContent($innerContent);
                        if ($flat !== '') {
                            $currentBlock[] = $flat;
                        }
                    } else {
                        // Per div/section, annida il contenuto se è complesso
                        $sectionLabel = $this->_getSectionLabel($child);
                        if ($sectionLabel) {
                            $currentBlock[] = [$sectionLabel => $innerContent];
                        } else {
                            // Merge diretto se il contenuto è semplice
                            if (is_array($innerContent)) {
                                foreach ($innerContent as $item) {
                                    $currentBlock[] = $item;
                                }
                            } else {
                                $currentBlock[] = $innerContent;
                            }
                        }
                    }
                }
                continue;
            }

            // Elementi inline (span, strong, em, b, i, small, mark, ecc.)
            $inlineText = $this->_cleanTextContent($child->textContent);
            if ($inlineText !== '') {
                $currentBlock[] = $inlineText;
            }
        }

        // Salva l'ultimo blocco
        if (!empty($currentBlock)) {
            if ($currentHeading) {
                $result[] = [$currentHeading => $this->_mergeTextBlocks($currentBlock)];
            } else {
                $merged = $this->_mergeTextBlocks($currentBlock);
                if (is_string($merged)) {
                    if ($merged !== '') {
                        $result[] = $merged;
                    }
                } elseif (is_array($merged)) {
                    $result = array_merge($result, $merged);
                }
            }
        }

        // Semplifica: se c'è un solo elemento, restituiscilo direttamente
        if (count($result) === 1) {
            return $result[0];
        }

        return $result;
    }

    /**
     * Estrae gli items da una lista UL/OL
     */
    private function _extractListItems(\DOMNode $listNode) {
        $items = [];
        foreach ($listNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'li') {
                // Controlla se c'è una sottolista
                $subList = null;
                $textParts = [];
                foreach ($child->childNodes as $liChild) {
                    if ($liChild->nodeType === XML_ELEMENT_NODE && in_array(strtolower($liChild->nodeName), ['ul', 'ol'])) {
                        $subList = $this->_extractListItems($liChild);
                    } elseif ($liChild->nodeType === XML_ELEMENT_NODE && strtolower($liChild->nodeName) === 'a') {
                        $linkText = $this->_cleanTextContent($liChild->textContent);
                        $href = $liChild->getAttribute('href');
                        if ($linkText !== '' && $href !== '' && $href !== '#') {
                            $href = $this->normalizeUrl($href);
                            $textParts[] = "[{$linkText}]({$href})";
                        } elseif ($linkText !== '') {
                            $textParts[] = $linkText;
                        }
                    } else {
                        $t = $this->_cleanTextContent($liChild->textContent);
                        if ($t !== '') {
                            $textParts[] = $t;
                        }
                    }
                }
                $itemText = implode(' ', $textParts);
                if ($itemText !== '' || !empty($subList)) {
                    if (!empty($subList)) {
                        $items[] = ['text' => $itemText, 'subitems' => $subList];
                    } else {
                        $items[] = $itemText;
                    }
                }
            }
        }
        return $items;
    }

    /**
     * Estrae dati strutturati da una tabella
     */
    private function _extractTableData(\DOMNode $tableNode) {
        $xpath = new \DOMXPath($tableNode->ownerDocument);
        $data = [];

        // Headers
        $headers = [];
        $thNodes = $xpath->query('.//th', $tableNode);
        if ($thNodes) {
            foreach ($thNodes as $th) {
                $t = $this->_cleanTextContent($th->textContent);
                if ($t !== '') {
                    $headers[] = $t;
                }
            }
        }
        if (!empty($headers)) {
            $data['headers'] = $headers;
        }

        // Rows
        $rows = [];
        $trNodes = $xpath->query('.//tr', $tableNode);
        if ($trNodes) {
            foreach ($trNodes as $tr) {
                $row = [];
                $tdNodes = $xpath->query('./td', $tr);
                if ($tdNodes) {
                    foreach ($tdNodes as $td) {
                        $row[] = $this->_cleanTextContent($td->textContent);
                    }
                }
                if (!empty($row) && implode('', $row) !== '') {
                    if (!empty($headers) && count($row) === count($headers)) {
                        $rows[] = array_combine($headers, $row);
                    } else {
                        $rows[] = $row;
                    }
                }
            }
        }
        if (!empty($rows)) {
            $data['rows'] = $rows;
        }

        return $data;
    }

    /**
     * Estrae contenuto non catturato dalle sezioni semantiche
     */
    private function _extractUncapturedContent(\DOMNode $body, \SplObjectStorage $processed) {
        $uncaptured = [];

        foreach ($body->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $text = $this->_cleanTextContent($child->textContent ?? '');
                if ($text !== '') {
                    $uncaptured[] = $text;
                }
                continue;
            }

            if ($processed->contains($child) || $this->_isDescendantOfProcessed($child, $processed)) {
                continue;
            }

            // Controlla se questo nodo o i suoi figli sono già processati
            $content = $this->_extractNodeContent($child);
            if (!empty($content)) {
                $label = $this->_getSectionLabel($child);
                if ($label) {
                    $uncaptured[] = [$label => $content];
                } else {
                    if (is_array($content)) {
                        foreach ($content as $item) {
                            $uncaptured[] = $item;
                        }
                    } else {
                        $uncaptured[] = $content;
                    }
                }
            }
        }

        // Filtra voci vuote
        $uncaptured = array_filter($uncaptured, function($item) {
            if (is_string($item)) return trim($item) !== '';
            if (is_array($item)) return !empty($item);
            return false;
        });

        return array_values($uncaptured);
    }

    /**
     * Cerca di determinare un'etichetta per una sezione (da id, class, aria-label, heading figlio)
     */
    private function _getSectionLabel(\DOMNode $node) {
        if (!($node instanceof \DOMElement)) {
            return null;
        }

        // aria-label
        $ariaLabel = $node->getAttribute('aria-label');
        if ($ariaLabel !== '') {
            return $this->_cleanTextContent($ariaLabel);
        }

        // aria-labelledby
        $ariaLabelledBy = $node->getAttribute('aria-labelledby');
        if ($ariaLabelledBy !== '') {
            $xpath = new \DOMXPath($node->ownerDocument);
            $labelNode = $xpath->query('//*[@id="' . $ariaLabelledBy . '"]')->item(0);
            if ($labelNode) {
                $label = $this->_cleanTextContent($labelNode->textContent);
                if ($label !== '') return $label;
            }
        }

        // id significativo (escludi id generati tipo "div-123")
        $id = $node->getAttribute('id');
        if ($id !== '' && !preg_match('/^[a-z]{0,3}\d+$/i', $id) && strlen($id) > 2 && strlen($id) < 50) {
            return str_replace(['-', '_'], ' ', $id);
        }

        // class significativa (solo la prima classe significativa)
        $class = $node->getAttribute('class');
        if ($class !== '') {
            $classes = preg_split('/\s+/', $class);
            foreach ($classes as $cls) {
                // Salta classi utility/framework (tailwind, bootstrap, ecc.)
                if (preg_match('/^(col|row|container|flex|grid|d-|p-|m-|px-|py-|mx-|my-|w-|h-|bg-|text-|font-|border-|rounded|shadow|hidden|block|inline|relative|absolute|static|overflow|clearfix|wrapper|inner|outer|left|right|center)/i', $cls)) {
                    continue;
                }
                if (strlen($cls) > 2 && strlen($cls) < 40 && !preg_match('/^\d+$/', $cls)) {
                    return str_replace(['-', '_'], ' ', $cls);
                }
            }
        }

        return null;
    }

    /**
     * Verifica se un elemento è nascosto
     */
    private function _isHiddenElement(\DOMNode $node) {
        if (!($node instanceof \DOMElement)) {
            return false;
        }

        if ($node->getAttribute('hidden') !== '') return true;
        if ($node->getAttribute('aria-hidden') === 'true') return true;

        $class = strtolower($node->getAttribute('class'));
        if (strpos($class, 'hidden') !== false || strpos($class, 'sr-only') !== false || strpos($class, 'visually-hidden') !== false || strpos($class, 'd-none') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Pulisce il testo rimuovendo spazi, newline eccessivi e caratteri non stampabili
     */
    private function _cleanTextContent($text) {
        // Rimuovi caratteri non stampabili (zero-width, soft hyphens, ecc.)
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $text);
        // Normalizza whitespace
        $text = preg_replace('/[\s\t\n\r]+/', ' ', $text);
        $text = trim($text);
        // Decodifica entità HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }

    /**
     * Unisce blocchi di testo in un formato leggibile
     */
    private function _mergeTextBlocks(array $blocks) {
        if (empty($blocks)) return '';

        // Se c'è un solo elemento stringa, restituiscilo
        if (count($blocks) === 1) {
            return $blocks[0];
        }

        // Controlla se ci sono elementi complessi (array)
        $hasComplex = false;
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $hasComplex = true;
                break;
            }
        }

        if (!$hasComplex) {
            // Tutti stringhe: unisci con spazio, evita duplicati adiacenti
            $merged = [];
            $prev = '';
            foreach ($blocks as $block) {
                if (is_string($block) && $block !== '' && $block !== $prev) {
                    $merged[] = $block;
                    $prev = $block;
                }
            }
            return count($merged) === 1 ? $merged[0] : implode(' ', $merged);
        }

        // Mix di stringhe e strutture: restituisci come array
        $result = [];
        $textBuffer = [];
        foreach ($blocks as $block) {
            if (is_string($block) && $block !== '') {
                $textBuffer[] = $block;
            } elseif (is_array($block)) {
                if (!empty($textBuffer)) {
                    $result[] = implode(' ', $textBuffer);
                    $textBuffer = [];
                }
                $result[] = $block;
            }
        }
        if (!empty($textBuffer)) {
            $result[] = implode(' ', $textBuffer);
        }

        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Appiattisce il contenuto annidato in una stringa
     */
    private function _flattenContent($content) {
        if (is_string($content)) return $content;
        if (!is_array($content)) return '';

        $parts = [];
        foreach ($content as $item) {
            if (is_string($item)) {
                $parts[] = $item;
            } elseif (is_array($item)) {
                $flat = $this->_flattenContent($item);
                if ($flat !== '') {
                    $parts[] = $flat;
                }
            }
        }
        return implode(' ', $parts);
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
            '//*[@class="ad-"]',
            '//*[@class="ads-"]',
            '//*[contains(@class, "cookie")]',
            '//*[contains(@class, "banner")]',
            '//*[contains(@class, "popup")]',
            '//*[contains(@id, "advertisement")]',
            '//*[contains(@id, "ad-")]',
            '//*[contains(@id, "ads-")]'
        ];
        
        $debugRes="";
        foreach ($toRemove as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                // DEBUG: Verifica se il nodo contiene link
                if ($node->tagName!="html" && $node->tagName!="body") {
                    $links = $xpath->query('.//a', $node);
                    if ($links->length > 0) {
                        $debugRes .= "ATTENZIONE: Rimosso elemento con {$links->length} link - Classe: " . $node->getAttribute('class') . " - ID: " . $node->getAttribute('id') . "\n";
                    } else {
                        $debugRes .= "ATTENZIONE: Rimosso elemento {$node->tagName} link - Classe: " . $node->getAttribute('class') . " - ID: " . $node->getAttribute('id') . "\n";
                    }
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
                else {
                    $debugRes .= "ATTENZIONE: TENTATIVO di rimozione elemento {$node->tagName} link - Classe: " . $node->getAttribute('class') . " - ID: " . $node->getAttribute('id') . "\n";
                }
            }
        }
        if ($debugRes) {
            $debugRes.="...";
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