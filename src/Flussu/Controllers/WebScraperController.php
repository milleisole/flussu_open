<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Web Page Scraper controller - v2.0
 * CREATED DATE:     01.10.2025 - Aldus - Flussu v4.5.1
 * UPDATE DATE:      28.03.2026 - Aldus - Flussu v5.0
 * DESCRIPTION:      Uses Flussu Scraper microservice (Playwright + Readability)
 *                   with fallback to simple file_get_contents (getPageHtml2).
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * -------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use GuzzleHttp\Client;

class WebScraperController
{
    private $url;
    private $html;
    private $dom;
    private $_scraperData = null;

    public function __construct($url = null) {
        $this->url = $url;
        $this->dom = new \DOMDocument();
        libxml_use_internal_errors(true);
    }

    /**
     * Chiama il microservizio Flussu Scraper.
     * Ritorna l'array decodificato dalla risposta JSON, oppure null in caso di errore.
     */
    private function _callScraperService($url) {
        $host = '127.0.0.1';
        $port = 3100;
        $timeout = 30;
        $enabled = true;

        try {
            $host = config('services.scraper.host') ?: $host;
            $timeout = config('services.scraper.timeout') ?: $timeout;
            $enabled = config('services.scraper.enabled') ?? true;
        } catch (\Throwable $e) {
            // config non disponibile, usa defaults
        }

        // Porta dal .env (priorita') o default
        if (!empty($_ENV['scraper_port'])) {
            $port = (int) $_ENV['scraper_port'];
        }

        if (!$enabled) {
            return null;
        }

        try {
            $client = new Client([
                'base_uri' => "http://{$host}:{$port}",
                'timeout'  => $timeout + 5,
                'connect_timeout' => 3,
            ]);

            $response = $client->post('/scrape', [
                'json' => [
                    'url' => $url,
                    'timeout' => $timeout * 1000,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            if (is_array($data)) {
                return $data;
            }
        } catch (\Throwable $e) {
            General::Log("WebScraperController", "Scraper service error: " . $e->getMessage(), "ERROR");
        }

        return null;
    }

    /**
     * Recupera l'HTML completo della pagina tramite il microservizio Playwright.
     * Fallback: getPageHtml2() se il microservizio non risponde.
     */
    public function getPageHtml($url) {
        if ($url) {
            $this->url = $url;
        }

        if (!$this->url) {
            throw new \Exception("URL non fornito");
        }

        // Prova il microservizio
        $data = $this->_callScraperService($this->url);
        if ($data) {
            $this->_scraperData = $data;
            // Il microservizio non restituisce raw HTML per default,
            // ma abbiamo il content. Per backward compat, usiamo getPageHtml2 per l'HTML raw
            // se serve il DOM completo.
        }

        // Fallback / HTML grezzo: usa il metodo semplice
        $this->getPageHtml2($this->url);
        return $this->html;
    }

    /**
     * Recupera l'HTML completo della pagina (metodo semplice con file_get_contents)
     * NOTA: Questa routine NON va modificata - e' il metodo legacy di fallback.
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
     * Restituisce solo il contenuto del body pulito (testo leggibile da Readability).
     * Fallback: estrazione semplice dal DOM via getPageHtml2.
     */
    public function getPageContentBody($url = null) {
        if ($url) {
            $this->url = $url;
        }

        // Prova il microservizio
        $data = $this->_callScraperService($this->url);
        if ($data && !empty($data['content'])) {
            return $data['content'];
        }

        // Fallback: estrai dal DOM come prima
        if (!$this->html) {
            $this->getPageHtml2($this->url);
        }

        $cleanDom = new \DOMDocument();
        $cleanDom->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Rimuovi script e style
        foreach (['script', 'style'] as $tag) {
            $elements = $cleanDom->getElementsByTagName($tag);
            while ($elements->length > 0) {
                $elements->item(0)->parentNode->removeChild($elements->item(0));
            }
        }

        // Rimuovi link CSS
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

        $body = $cleanDom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        $bodyContent = '';
        foreach ($body->childNodes as $child) {
            $bodyContent .= $cleanDom->saveHTML($child);
        }

        return $bodyContent;
    }

    /**
     * Restituisce un JSON strutturato con il contenuto della pagina.
     * Usa il microservizio Flussu Scraper (Playwright + Readability).
     * Fallback: parsing DOM semplice via getPageHtml2.
     */
    public function getPageContentJson($url = null) {
        if ($url) {
            $this->url = $url;
        }

        // Prova il microservizio
        $data = $this->_callScraperService($this->url);
        if ($data) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Fallback: costruisci un JSON minimale dal DOM
        if (!$this->html) {
            $this->getPageHtml2($this->url);
        }

        $result = [
            'url' => $this->url,
            'status' => 200,
            'title' => $this->_extractTitleFromDom(),
            'description' => '',
            'content' => strip_tags($this->html ?? ''),
            'author' => '',
            'headings' => [],
            'links' => [],
            'images' => [],
            'metadata' => [],
            'scraped_at' => date('c'),
            'elapsed_ms' => 0,
            'method' => 'fallback'
        ];

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Alias per retrocompatibilita' (chiamato da Environment.php e AiChatController.php)
     */
    public function getPageContentJSON($url = null) {
        return $this->getPageContentJson($url);
    }

    /**
     * Restituisce il testo pulito della pagina (estratto da Readability).
     */
    public function getPageText($url = null) {
        if ($url) {
            $this->url = $url;
        }

        $data = $this->_callScraperService($this->url);
        if ($data && isset($data['content'])) {
            return $data['content'];
        }

        // Fallback: estrai testo dal DOM
        if (!$this->html) {
            $this->getPageHtml2($this->url);
        }
        return strip_tags($this->html ?? '');
    }

    /**
     * Restituisce il contenuto della pagina in formato Markdown.
     */
    public function getPageMarkdown($url = null) {
        if ($url) {
            $this->url = $url;
        }

        $data = $this->_callScraperService($this->url);
        if ($data) {
            return $this->_convertToMarkdown($data);
        }

        // Fallback minimale
        if (!$this->html) {
            $this->getPageHtml2($this->url);
        }
        return strip_tags($this->html ?? '');
    }

    /**
     * Converte la risposta del microservizio in Markdown.
     */
    private function _convertToMarkdown($data) {
        $md = '';

        if (!empty($data['title'])) {
            $md .= "# " . $data['title'] . "\n\n";
        }
        if (!empty($data['author'])) {
            $md .= "*" . $data['author'] . "*\n\n";
        }
        if (!empty($data['description'])) {
            $md .= "> " . $data['description'] . "\n\n";
        }
        if (!empty($data['content'])) {
            $md .= $data['content'] . "\n\n";
        }
        if (!empty($data['links']) && is_array($data['links'])) {
            $md .= "---\n\n## Links\n\n";
            foreach ($data['links'] as $link) {
                $text = $link['text'] ?? $link['href'] ?? '';
                $href = $link['href'] ?? '';
                if ($href) {
                    $md .= "- [{$text}]({$href})\n";
                }
            }
        }

        return $md;
    }

    /**
     * Estrae il titolo dal DOM (fallback quando il microservizio non e' disponibile).
     */
    private function _extractTitleFromDom() {
        if (!$this->dom) return '';
        $titleNode = $this->dom->getElementsByTagName('title')->item(0);
        if ($titleNode) {
            return trim($titleNode->textContent);
        }
        $h1Node = $this->dom->getElementsByTagName('h1')->item(0);
        if ($h1Node) {
            return trim($h1Node->textContent);
        }
        return '';
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
