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
    private $baseUrl = '';
    
    /**
     * Recupera l'HTML di una pagina web
     */
    public function getPageHtml(string $address): ?string
    {
        $projectRoot = realpath(__DIR__);
        $driverPath = $projectRoot . '/../../../drivers/chromedriver';
        
        if (!file_exists($driverPath)) {
            return json_encode(["error" => "Chrome driver not found at: " . $driverPath]);
        }
        
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36 Flussu/4.5';
        $chromeArguments = [
            '--user-agent=' . $userAgent,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage'
        ];
        
        try {
            $client = Client::createChromeClient($driverPath, $chromeArguments);
            $crawler = $client->request('GET', $address);
            $client->waitForVisibility('body', 8);
            $html = $client->getPageSource();
            $client->quit();
            return $html;
        } catch (\Throwable $e) {
            return json_encode(["error" => $e->getMessage()]);
        }
    }
    
    /**
     * METODO PRINCIPALE: Estrae contenuti dalla pagina
     */
    public function getPageContentJSON(string $address): string 
    {
        $this->baseUrl = parse_url($address, PHP_URL_SCHEME) . '://' . parse_url($address, PHP_URL_HOST);
        
        $html = $this->getPageHtml($address);
        if (strpos($html, '"error"') !== false) {
            return $html;
        }
        
        // Parse DOM
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Pulisci HTML base
        $this->cleanDocument($dom);
        
        $xpath = new \DOMXPath($dom);
        
        // Prepara output base
        $output = [
            'url' => $address,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // PRIMA: Verifica se è una pagina di articolo singolo ed estrai il contenuto principale
        $mainContent = $this->extractMainArticleContent($xpath);
        if (!empty($mainContent)) {
            $output['article'] = $mainContent;
        }
        
        // POI: Estrai i link e altri contenuti
        $content = [];
        
        // PATTERN 1: Articoli strutturati
        $structuredContent = $this->extractStructuredContent($xpath);
        if (!empty($structuredContent) && count($structuredContent) >= 3) {
            $content = $structuredContent;
        }
        
        // PATTERN 2: Heading con link
        if (empty($content)) {
            $headingContent = $this->extractHeadingLinks($xpath);
            if (!empty($headingContent) && count($headingContent) >= 3) {
                $content = $headingContent;
            }
        }
        
        // PATTERN 3: FALLBACK - Estrai TUTTI i link e testi significativi
        if (empty($content) || count($content) < 3) {
            $content = $this->extractAllContent($xpath);
        }
        
        // Rimuovi duplicati
        $content = $this->removeDuplicates($content);
        
        // Aggiungi contenuti correlati all'output
        if (!empty($content)) {
            $output['content'] = $content;
        }
        
        // Statistiche
        $output['stats'] = [
            'has_main_article' => !empty($mainContent),
            'total_elements' => count($content),
            'with_url' => count(array_filter($content, fn($e) => isset($e['url']))),
            'with_date' => count(array_filter($content, fn($e) => isset($e['date']))),
        ];
        
        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * PATTERN 1: Estrae contenuti strutturati (article, post, entry, etc.)
     */
    private function extractStructuredContent(\DOMXPath $xpath): array 
    {
        $content = [];
        
        // Cerca elementi comuni per articoli
        $articleSelectors = [
            '//article',
            '//li[.//h1 or .//h2 or .//h3 or .//h4]',
            '//*[contains(@class, "post") or contains(@class, "article") or contains(@class, "entry") or contains(@class, "item")]',
            '//*[@data-testid]'  // Alcuni siti moderni usano data-testid
        ];
        
        foreach ($articleSelectors as $selector) {
            $elements = $xpath->query($selector);
            
            if ($elements->length >= 3) {  // Se troviamo almeno 3 elementi simili
                foreach ($elements as $element) {
                    $item = $this->extractFromElement($element, $xpath);
                    if (!empty($item['text'])) {
                        $content[] = $item;
                    }
                }
                
                // Se abbiamo trovato contenuti validi, usali
                if (count($content) >= 3) {
                    break;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * PATTERN 2: Estrae heading con link
     */
    private function extractHeadingLinks(\DOMXPath $xpath): array 
    {
        $content = [];
        
        $headings = $xpath->query('//h1/a | //h2/a | //h3/a | //h4/a | //h5/a');
        
        foreach ($headings as $link) {
            $text = $this->cleanText($link->textContent);
            
            if (strlen($text) > 15 && !$this->isNavigation($text)) {
                $item = [
                    'text' => $text,
                    'url' => $this->normalizeUrl($link->getAttribute('href'))
                ];
                
                // Cerca data vicina
                $parent = $link->parentNode->parentNode;
                if ($parent) {
                    $date = $this->findDateInContext($parent->textContent);
                    if ($date) {
                        $item['date'] = $date;
                    }
                }
                
                $content[] = $item;
            }
        }
        
        return $content;
    }
    
    /**
     * PATTERN 3: FALLBACK - Estrae TUTTO il contenuto significativo
     */
    private function extractAllContent(\DOMXPath $xpath): array 
    {
        $content = [];
        $processedTexts = [];
        
        // Estrai TUTTI i link con testo
        $allLinks = $xpath->query('//a[@href]');
        
        foreach ($allLinks as $link) {
            $text = $this->cleanText($link->textContent);
            $href = $link->getAttribute('href');
            
            // Filtra solo contenuti significativi
            if (strlen($text) < 20 || 
                $this->isNavigation($text) || 
                strpos($href, 'javascript:') === 0 || 
                $href === '#' ||
                in_array($text, $processedTexts)) {
                continue;
            }
            
            $item = [
                'text' => $text,
                'url' => $this->normalizeUrl($href)
            ];
            
            // Cerca data nel contesto immediato
            $parent = $link->parentNode;
            if ($parent) {
                $contextText = $this->cleanText($parent->textContent);
                $date = $this->findDateInContext($contextText);
                if ($date) {
                    $item['date'] = $date;
                }
            }
            
            $content[] = $item;
            $processedTexts[] = $text;
        }
        
        // Se abbiamo pochi link, estrai anche testi senza link
        if (count($content) < 10) {
            // Estrai heading senza link
            $headings = $xpath->query('//h1 | //h2 | //h3 | //h4');
            foreach ($headings as $heading) {
                // Salta se contiene già un link (già processato)
                $links = $xpath->query('.//a', $heading);
                if ($links->length > 0) {
                    continue;
                }
                
                $text = $this->cleanText($heading->textContent);
                if (strlen($text) > 20 && !in_array($text, $processedTexts)) {
                    $item = ['text' => $text];
                    
                    // Cerca data vicina
                    $parent = $heading->parentNode;
                    if ($parent) {
                        $date = $this->findDateInContext($parent->textContent);
                        if ($date) {
                            $item['date'] = $date;
                        }
                    }
                    
                    $content[] = $item;
                    $processedTexts[] = $text;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Estrae contenuto da un elemento DOM
     */
    private function extractFromElement(\DOMNode $element, \DOMXPath $xpath): array 
    {
        $item = [];
        
        // Cerca il testo principale (heading o link più lungo)
        $headings = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//h5', $element);
        $mainText = '';
        $mainUrl = '';
        
        if ($headings->length > 0) {
            $mainText = $this->cleanText($headings->item(0)->textContent);
            
            // Se il heading contiene un link
            $link = $xpath->query('.//a', $headings->item(0))->item(0);
            if ($link) {
                $mainUrl = $this->normalizeUrl($link->getAttribute('href'));
            }
        }
        
        // Se non ha trovato heading, cerca il link più lungo
        if (empty($mainText)) {
            $links = $xpath->query('.//a[@href]', $element);
            foreach ($links as $link) {
                $text = $this->cleanText($link->textContent);
                if (strlen($text) > strlen($mainText)) {
                    $mainText = $text;
                    $mainUrl = $this->normalizeUrl($link->getAttribute('href'));
                }
            }
        }
        
        if (!empty($mainText)) {
            $item['text'] = $mainText;
            
            if (!empty($mainUrl)) {
                $item['url'] = $mainUrl;
            }
            
            // Cerca data
            $elementText = $this->cleanText($element->textContent);
            $date = $this->findDateInContext($elementText);
            if ($date) {
                $item['date'] = $date;
            }
        }
        
        return $item;
    }
    
    /**
     * Cerca una data nel testo
     */
    private function findDateInContext(string $text): ?string 
    {
        // Pattern per date comuni
        $patterns = [
            // Relative (3 days ago, 2 ore fa)
            '/(\d+\s+(hours?|days?|weeks?|months?|years?)\s+ago)/i',
            '/(\d+\s+(ore|giorni|settimane|mesi|anni)\s+fa)/i',
            
            // Date esplicite
            '/(\d{1,2}\s+(gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\s+\d{4})/i',
            '/((Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4})/i',
            '/(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/',
            
            // Solo mese e giorno
            '/((Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2})/i',
            '/(oggi|ieri|today|yesterday)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Verifica se un testo è navigazione
     */
    private function isNavigation(string $text): bool 
    {
        $text = strtolower(trim($text));
        
        $navWords = [
            'home', 'about', 'contact', 'privacy', 'cookie', 'terms',
            'login', 'sign in', 'register', 'menu', 'search',
            'next', 'previous', 'prev', 'older', 'newer',
            'copyright', '©', 'rights reserved',
            'chi siamo', 'contatti', 'accedi', 'registrati'
        ];
        
        foreach ($navWords as $word) {
            if (strpos($text, $word) !== false) {
                return true;
            }
        }
        
        // Se è molto corto o solo numeri
        if (strlen($text) < 10 || is_numeric($text)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Estrae il contenuto principale se è una pagina di articolo singolo
     */
    private function extractMainArticleContent(\DOMXPath $xpath): ?array 
    {
        $article = [];
        
        // 1. Cerca il titolo principale (h1 o h2 con classe post-title o simili)
        $titleSelectors = [
            '//h1[contains(@class, "post-title") or contains(@class, "entry-title") or contains(@class, "article-title")]',
            '//h1[@class="wp-block-post-title"]',
            '//h1[parent::*[contains(@class, "post") or contains(@class, "article") or contains(@class, "entry")]]',
            '//h1',  // Fallback generico
            '//h2[contains(@class, "post-title") or contains(@class, "entry-title")]'
        ];
        
        foreach ($titleSelectors as $selector) {
            $titles = $xpath->query($selector);
            if ($titles->length > 0) {
                $article['title'] = $this->cleanText($titles->item(0)->textContent);
                break;
            }
        }
        
        // 2. Cerca il contenuto dell'articolo
        $contentSelectors = [
            '//div[contains(@class, "entry-content") or contains(@class, "post-content") or contains(@class, "article-content")]',
            '//div[@class="wp-block-post-content"]',
            '//article//div[contains(@class, "content")]',
            '//main//div[contains(@class, "content")]'
        ];
        
        $contentText = '';
        foreach ($contentSelectors as $selector) {
            $contents = $xpath->query($selector);
            if ($contents->length > 0) {
                // Estrai tutto il testo, rimuovendo tag HTML ma mantenendo struttura
                $contentNode = $contents->item(0);
                $contentText = $this->extractTextFromNode($contentNode, $xpath);
                break;
            }
        }
        
        if (!empty($contentText)) {
            $article['content'] = $contentText;
            
            // Estrai anche un excerpt (primi 500 caratteri)
            $article['excerpt'] = substr($contentText, 0, 500);
            if (strlen($contentText) > 500) {
                $article['excerpt'] .= '...';
            }
        }
        
        // 3. Cerca la data di pubblicazione
        $dateSelectors = [
            '//time[@datetime]',
            '//div[contains(@class, "post-date") or contains(@class, "entry-date") or contains(@class, "published")]',
            '//span[contains(@class, "date") or contains(@class, "time")]',
            '//*[contains(@class, "post-meta")]//time'
        ];
        
        foreach ($dateSelectors as $selector) {
            $dates = $xpath->query($selector);
            if ($dates->length > 0) {
                $dateNode = $dates->item(0);
                if ($dateNode->hasAttribute('datetime')) {
                    $article['datetime'] = $dateNode->getAttribute('datetime');
                    $article['date'] = $this->cleanText($dateNode->textContent);
                } else {
                    $dateText = $this->cleanText($dateNode->textContent);
                    if ($this->findDateInContext($dateText)) {
                        $article['date'] = $dateText;
                    }
                }
                break;
            }
        }
        
        // 4. Cerca l'autore
        $authorSelectors = [
            '//div[contains(@class, "post-author")]//a',
            '//span[contains(@class, "author")]//a',
            '//a[contains(@class, "author")]',
            '//*[contains(@class, "by-author")]//a'
        ];
        
        foreach ($authorSelectors as $selector) {
            $authors = $xpath->query($selector);
            if ($authors->length > 0) {
                $article['author'] = $this->cleanText($authors->item(0)->textContent);
                break;
            }
        }
        
        // 5. Cerca categorie/tag
        $categorySelectors = [
            '//div[contains(@class, "post-terms") or contains(@class, "post-categories")]//a',
            '//div[contains(@class, "taxonomy")]//a',
            '//a[@rel="category tag" or @rel="category" or @rel="tag"]'
        ];
        
        $categories = [];
        foreach ($categorySelectors as $selector) {
            $catNodes = $xpath->query($selector);
            foreach ($catNodes as $catNode) {
                $catText = $this->cleanText($catNode->textContent);
                if (!empty($catText) && strlen($catText) < 50) {
                    $categories[] = $catText;
                }
            }
            if (!empty($categories)) {
                $article['categories'] = array_unique($categories);
                break;
            }
        }
        
        // Se abbiamo trovato almeno un titolo o contenuto, è una pagina articolo
        if (!empty($article['title']) || !empty($article['content'])) {
            return $article;
        }
        
        return null;
    }
    
    /**
     * Estrae testo da un nodo preservando paragrafi e struttura
     */
    private function extractTextFromNode(\DOMNode $node, \DOMXPath $xpath): string 
    {
        $text = '';
        $paragraphs = [];
        
        // Prima prova ad estrarre paragrafi strutturati
        $paras = $xpath->query('.//p | .//h2 | .//h3 | .//h4 | .//h5 | .//blockquote | .//li', $node);
        
        if ($paras->length > 0) {
            foreach ($paras as $para) {
                $paraText = $this->cleanText($para->textContent);
                
                // Salta paragrafi vuoti o molto corti
                if (strlen($paraText) > 10) {
                    // Aggiungi separatore per heading
                    if (preg_match('/^h[2-5]$/', $para->tagName)) {
                        $paragraphs[] = "\n\n### " . $paraText . "\n";
                    }
                    // Aggiungi quote per blockquote
                    elseif ($para->tagName === 'blockquote') {
                        $paragraphs[] = "> " . $paraText;
                    }
                    // Aggiungi bullet per liste
                    elseif ($para->tagName === 'li') {
                        $paragraphs[] = "• " . $paraText;
                    }
                    // Paragrafo normale
                    else {
                        $paragraphs[] = $paraText;
                    }
                }
            }
            
            $text = implode("\n\n", $paragraphs);
        } else {
            // Fallback: prendi tutto il testo
            $text = $this->cleanText($node->textContent);
        }
        
        // Rimuovi spazi multipli ma preserva newline
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\n\n+/', "\n\n", $text);
        
        return trim($text);
    }
    
    /**
     * Rimuove duplicati
     */
    private function removeDuplicates(array $content): array 
    {
        $seen = [];
        $unique = [];
        
        foreach ($content as $item) {
            $key = $item['text'] ?? '';
            
            // Se ha URL, usa quello come chiave aggiuntiva
            if (isset($item['url'])) {
                $key .= '|' . $item['url'];
            }
            
            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }
        
        return $unique;
    }
    
    /**
     * Pulisce il documento
     */
    private function cleanDocument(\DOMDocument $dom): void 
    {
        $xpath = new \DOMXPath($dom);
        
        // Rimuovi elementi non necessari
        $toRemove = $xpath->query('//script | //style | //noscript | //iframe | //svg | //nav | //footer');
        foreach ($toRemove as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }
    }
    
    /**
     * Pulisce il testo
     */
    private function cleanText(string $text): string 
    {
        // Decodifica entità HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Rimuovi spazi multipli e caratteri speciali
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = preg_replace('/[\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Normalizza URL
     */
    private function normalizeUrl(string $href): string 
    {
        $href = trim($href);
        
        if (empty($href) || $href === '#') {
            return '';
        }
        
        if (strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0) {
            return '';
        }
        
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }
        
        if (strpos($href, '//') === 0) {
            return 'https:' . $href;
        }
        
        if (strpos($href, '/') === 0) {
            return $this->baseUrl . $href;
        }
        
        return $this->baseUrl . '/' . $href;
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