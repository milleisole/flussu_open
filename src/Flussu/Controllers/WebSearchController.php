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
 * CLASS-NAME:      Web Search controller
 * CREATED DATE:    02.10.2025 - Aldus - Flussu v4.5
 * VERSION REL.:    4.5.3.20251002
 * UPDATES DATE:    02.10.2025 - Added Chrome support for better results
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Symfony\Component\Panther\Client;

class WebSearchController {
    private $params = [];
    private $debug = false;
    private $timeout = 10;
    private $multiEngine = true;
    private $useChrome = false; // Opzione per usare Chrome invece di cURL
    private $engines = [];
    private $multiCurl;
    private $curlHandles = [];
    private $engineResults = [];
    
    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->initializeEngines();
        $this->useChrome = true;
    }
    
    /**
     * Inizializza la configurazione dei motori di ricerca
     */
    private function initializeEngines() {
        $this->engines = [
            'duckduckgo' => [
                'enabled' => true,
                'weight' => 1.0,
                'useChrome' => false,
                'parser' => 'parseDuckDuckGoResults'
            ],
            'brave' => [
                'enabled' => true,
                'weight' => 0.95,
                'useChrome' => false,
                'parser' => 'parseBraveResults'
            ],
            'searx' => [
                'enabled' => true,
                'weight' => 0.8,
                'useChrome' => false,
                'instances' => [
                    'https://searx.be/search',
                ],
                'parser' => 'parseSearXResults'
            ],
            'startpage' => [
                'enabled' => true,
                'weight' => 0.9,
                'useChrome' => true, // Startpage richiede Chrome
                'parser' => 'parseStartpageResults'
            ],
            'presearch' => [
                'enabled' => true,
                'weight' => 0.9,
                'useChrome' => true, // Startpage richiede Chrome
                'parser' => 'parseStartpageResults'
            ]
        ];
    }
    
    /**
     * Imposta la query di ricerca
     */
    public function setQuery($query) {
        $this->params['q'] = $query;
        return $this;
    }
    
    /**
     * Imposta localizzazione
     */
    public function setLocation($gl = 'us', $hl = 'en') {
        $regionMap = [
            'it' => 'it-it',
            'us' => 'us-en', 
            'uk' => 'uk-en',
            'de' => 'de-de',
            'fr' => 'fr-fr',
            'es' => 'es-es',
            'br' => 'br-pt'
        ];
        
        $this->params['kl'] = $regionMap[$gl] ?? 'wt-wt';
        $this->params['gl'] = $gl;
        $this->params['hl'] = $hl;
        return $this;
    }
    
    /**
     * Imposta paginazione
     */
    public function setPagination($start = 0, $num = 10) {
        $this->params['start'] = $start;
        $this->params['num'] = $num;
        return $this;
    }
    
    /**
     * Imposta safe search
     */
    public function setSafeSearch($level = 'moderate') {
        $this->params['kp'] = $level === false ? '-2' : ($level === 'strict' ? '1' : '-1');
        return $this;
    }
    
    /**
     * Abilita/disabilita ricerca multi-motore
     */
    public function setMultiEngine($enabled = true) {
        $this->multiEngine = $enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita l'uso di Chrome per certi motori
     */
    public function setUseChrome($enabled = true) {
        $this->useChrome = $enabled;
        return $this;
    }
    
    /**
     * Esegue la ricerca
     */
    public function search() {
        if (!isset($this->params['q'])) {
            return [
                'error' => true,
                'message' => 'Query non impostata'
            ];
        }
        
        try {
            if ($this->multiEngine) {
                return $this->searchMultiEngine();
            } else {
                $html = $this->fetchDuckDuckGoResults();
                return $this->parseResultsToJson($html);
            }
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ricerca multi-motore migliorata
     */
    private function searchMultiEngine() {
        $this->multiCurl = curl_multi_init();
        $this->curlHandles = [];
        $this->engineResults = [];
        
        // Raccogli risultati da tutti i motori
        $allResults = [];
        
        foreach ($this->engines as $engineName => $config) {
            if (!$config['enabled']) continue;
            
            try {
                if ($this->debug) {
                    echo "[$engineName] Iniziando ricerca...\n";
                }
                
                if ($config['useChrome'] && $this->useChrome) {
                    // Usa Chrome per questo motore
                    $result = $this->fetchWithChrome($engineName);
                } else {
                    // Usa cURL
                    $result = $this->fetchWithCurl($engineName);
                }
                
                if (!empty($result)) {
                    $parser = $config['parser'];
                    if (method_exists($this, $parser)) {
                        $parsed = $this->$parser($result, $engineName);
                        if (!empty($parsed)) {
                            $allResults[$engineName] = $parsed;
                            if ($this->debug) {
                                echo "[$engineName] Trovati " . count($parsed) . " risultati\n";
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                if ($this->debug) {
                    echo "[$engineName] Errore: " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Se non ci sono risultati da nessun motore, prova con Chrome
        if (empty($allResults) && !$this->useChrome) {
            if ($this->debug) {
                echo "Nessun risultato con cURL, riprovo con Chrome...\n";
            }
            $this->setUseChrome(true);
            return $this->searchMultiEngine();
        }
        
        // Cleanup cURL se usato
        if ($this->multiCurl) {
            $this->cleanupCurl();
        }
        
        // Unisci e deduplica
        return $this->mergeAndDeduplicateResults($allResults);
    }
    
    /**
     * Fetch risultati usando Chrome/Panther
     */
    private function fetchWithChrome($engineName) {
        $projectRoot = realpath(__DIR__);
        $driverPath = $projectRoot . '/../../../drivers/chromedriver';
        
        if (!file_exists($driverPath)) {
            throw new \Exception("Chrome driver non trovato: $driverPath");
        }
        
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $chromeArguments = [
            '--user-agent=' . $userAgent,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage'
        ];
        
        $client = Client::createChromeClient($driverPath, $chromeArguments);
        
        try {
            $url = $this->buildSearchUrl($engineName);
            if ($this->debug) {
                echo "[$engineName] Chrome URL: $url\n";
            }
            
            $crawler = $client->request('GET', $url);
            
            // Aspetta che i risultati siano caricati
            switch ($engineName) {
                case 'startpage':
                    $client->waitForVisibility('.w-gl__result', 5);
                    break;
                case 'brave':
                    $client->waitForVisibility('#results', 5);
                    break;
                default:
                    $client->waitForVisibility('body', 3);
            }
            
            $html = $client->getPageSource();
            return $html;
            
        } finally {
            $client->quit();
        }
    }
    
    /**
     * Fetch risultati usando cURL
     */
    private function fetchWithCurl($engineName) {
        $url = $this->buildSearchUrl($engineName);
        $headers = $this->getHeaders($engineName);
        
        if ($this->debug) {
            echo "[$engineName] cURL URL: $url\n";
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($this->debug) {
            echo "[$engineName] HTTP Code: $httpCode\n";
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP Error: $httpCode");
        }
        
        return $response;
    }
    
    /**
     * Costruisce URL di ricerca per ogni motore
     */
    private function buildSearchUrl($engineName) {
        $query = $this->params['q'];
        
        switch ($engineName) {
            case 'duckduckgo':
                return 'https://html.duckduckgo.com/html/?' . http_build_query([
                    'q' => $query,
                    'kl' => $this->params['kl'] ?? 'it-it'
                ]);
                
            case 'brave':
                return 'https://search.brave.com/search?' . http_build_query([
                    'q' => $query,
                    'source' => 'web'
                ]);
                
            case 'searx':
                $instances = $this->engines['searx']['instances'];
                $instance = $instances[array_rand($instances)];
                return $instance . '?' . http_build_query([
                    'q' => $query,
                    'format' => 'json',
                    'language' => $this->params['hl'] ?? 'it-IT',
                    'pageno' => 1,
                    'categories' => 'general'
                ]);
                
            case 'startpage':
                return 'https://www.startpage.com/do/dsearch?' . http_build_query([
                    'query' => $query,
                    'cat' => 'web',
                    'language' => $this->params['hl'] ?? 'italiano'
                ]);
                
            case 'presearch':
                return 'https://https://presearch.com/search?' . http_build_query([
                    'q' => $query,
                    'language' => $this->params['hl'] ?? 'italiano'
                ]);
                
            default:
                return '';
        }
    }
    
    /**
     * Ottiene headers per ogni motore
     */
    private function getHeaders($engineName) {
        $baseHeaders = [
            'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        
        switch ($engineName) {
            case 'searx':
                return array_merge($baseHeaders, ['Accept: application/json']);
            case 'brave':
            case "presearch":
            case 'startpage':
                return array_merge($baseHeaders, ['Accept: text/html,application/xhtml+xml']);
            default:
                return $baseHeaders;
        }
    }
    
    /**
     * Parser per risultati Brave
     */
    private function parseBraveResults($html, $engineName = 'brave') {
        $results = [];
        
        // Se è JSON (da API futura)
        if ($this->isJson($html)) {
            $data = json_decode($html, true);
            if (isset($data['web']['results'])) {
                foreach ($data['web']['results'] as $item) {
                    $results[] = [
                        'titolo' => $item['title'] ?? '',
                        'link' => $item['url'] ?? '',
                        'descrizione' => $item['description'] ?? '',
                        'displayed_link' => parse_url($item['url'], PHP_URL_HOST) ?? '',
                        'engine' => $engineName
                    ];
                }
            }
        } else {
            // Parse HTML
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Selettori per Brave
            $resultNodes = $xpath->query('//div[@class="snippet fdb"]');
            
            foreach ($resultNodes as $node) {
                $result = [];
                
                // Titolo e link
                $titleNode = $xpath->query('.//a[@class="result-header"]', $node)->item(0);
                if ($titleNode) {
                    $result['titolo'] = trim($titleNode->textContent);
                    $result['link'] = $titleNode->getAttribute('href');
                }
                
                // Descrizione
                $descNode = $xpath->query('.//p[@class="snippet-description"]', $node)->item(0);
                if ($descNode) {
                    $result['descrizione'] = trim($descNode->textContent);
                }
                
                // Display URL
                $urlNode = $xpath->query('.//cite', $node)->item(0);
                if ($urlNode) {
                    $result['displayed_link'] = trim($urlNode->textContent);
                }
                
                if (!empty($result['titolo']) && !empty($result['link'])) {
                    $result['engine'] = $engineName;
                    $results[] = $result;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Parser per risultati Startpage
     */
    private function parseStartpageResults($html, $engineName = 'startpage') {
        $results = [];
        
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Selettori per Startpage
        $resultNodes = $xpath->query('//div[@class="w-gl__result"]');
        
        foreach ($resultNodes as $node) {
            $result = [];
            
            // Titolo e link
            $titleNode = $xpath->query('.//a[@class="w-gl__result-title"]', $node)->item(0);
            if ($titleNode) {
                $result['titolo'] = trim($titleNode->textContent);
                $result['link'] = $titleNode->getAttribute('href');
            }
            
            // Descrizione
            $descNode = $xpath->query('.//p[@class="w-gl__description"]', $node)->item(0);
            if ($descNode) {
                $result['descrizione'] = trim($descNode->textContent);
            }
            
            // Display URL
            $urlNode = $xpath->query('.//span[@class="w-gl__result-url"]', $node)->item(0);
            if ($urlNode) {
                $result['displayed_link'] = trim($urlNode->textContent);
            }
            
            if (!empty($result['titolo']) && !empty($result['link'])) {
                $result['engine'] = $engineName;
                $results[] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Verifica se una stringa è JSON valido
     */
    private function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    
    /**
     * Parse risultati DuckDuckGo (metodo esistente)
     */
    private function parseDuckDuckGoResults($html, $engineName = 'duckduckgo') {
        $results = [];
        
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        $resultNodes = $xpath->query('//div[contains(@class, "result results_links results_links_deep web-result")]');
        
        foreach ($resultNodes as $node) {
            $result = [];
            
            $titleNode = $xpath->query('.//h2[@class="result__title"]/a[@class="result__a"]', $node)->item(0);
            if ($titleNode) {
                $result['titolo'] = trim($titleNode->textContent);
                $href = $titleNode->getAttribute('href');
                if (preg_match('/uddg=([^&]+)/', $href, $matches)) {
                    $result['link'] = urldecode($matches[1]);
                } else {
                    $result['link'] = $href;
                }
            }
            
            $snippetNode = $xpath->query('.//a[@class="result__snippet"]', $node)->item(0);
            if ($snippetNode) {
                $result['descrizione'] = trim(strip_tags($snippetNode->textContent));
            }
            
            $urlNode = $xpath->query('.//a[@class="result__url"]', $node)->item(0);
            if ($urlNode) {
                $result['displayed_link'] = trim($urlNode->textContent);
            }
            
            if (!empty($result['titolo']) && !empty($result['link'])) {
                $result['engine'] = $engineName;
                $results[] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Parse risultati SearX (metodo esistente)
     */
    private function parseSearXResults($json, $engineName = 'searx') {
        $results = [];
        
        if (!$this->isJson($json)) {
            return $results;
        }
        
        $data = json_decode($json, true);
        
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $item) {
                if (!empty($item['url']) && !empty($item['title'])) {
                    $results[] = [
                        'titolo' => $item['title'],
                        'link' => $item['url'],
                        'descrizione' => $item['content'] ?? '',
                        'displayed_link' => parse_url($item['url'], PHP_URL_HOST) ?? '',
                        'engine' => $engineName
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Unisce e deduplica risultati (metodo esistente migliorato)
     */
    private function mergeAndDeduplicateResults($allResults) {
        $merged = [];
        $seenUrls = [];
        
        // Raccogli tutti i risultati con deduplicazione
        foreach ($allResults as $engineName => $results) {
            foreach ($results as $result) {
                $normalizedUrl = $this->normalizeUrl($result['link']);
                $urlHash = md5($normalizedUrl);
                
                if (!isset($seenUrls[$urlHash])) {
                    $seenUrls[$urlHash] = true;
                    $result['found_by'] = [$engineName];
                    $result['score'] = $this->calculateScore($result, $engineName);
                    $merged[$urlHash] = $result;
                } else {
                    // Aggiungi motore alla lista
                    $merged[$urlHash]['found_by'][] = $engineName;
                    // Ricalcola score
                    $merged[$urlHash]['score'] = $this->calculateScore($merged[$urlHash], $engineName);
                    // Mantieni la descrizione più lunga
                    if (strlen($result['descrizione']) > strlen($merged[$urlHash]['descrizione'])) {
                        $merged[$urlHash]['descrizione'] = $result['descrizione'];
                    }
                }
            }
        }
        
        // Ordina per score
        usort($merged, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Prepara risultato finale
        $organicResults = [];
        $position = 1;
        $limit = $this->params['num'] ?? 10;
        
        foreach ($merged as $result) {
            if ($position > $limit) break;
            
            $organicResults[] = [
                'pos' => $position,
                'link' => $result['link'],
                'title' => $result['titolo'],
                'titolo' => $result['titolo'],
                'description' => $result['descrizione'],
                'descrizione' => $result['descrizione'],
                'displayed_link' => $result['displayed_link'],
                'language' => null,
                'update_date' => null,
                'engines' => implode(',', $result['found_by']),
                'score' => $result['score']
            ];
            $position++;
        }
        
        return [
            'search_metadata' => [
                'status' => 'Success',
                'created_at' => date('Y-m-d H:i:s'),
                'source' => $this->multiEngine ? 'Multi-Engine' : 'DuckDuckGo',
                'query' => $this->params['q'],
                'region' => $this->params['kl'] ?? 'it-it',
                'engines_used' => array_keys($allResults),
                'total_engines' => count($allResults),
                'use_chrome' => $this->useChrome
            ],
            'search_parameters' => $this->params,
            'organic_results' => $organicResults,
            'search_information' => [
                'total_results' => count($organicResults),
                'search_time' => null,
                'query_displayed' => $this->params['q'],
                'deduplication_stats' => [
                    'total_before' => array_sum(array_map('count', $allResults)),
                    'total_after' => count($organicResults),
                    'duplicates_removed' => array_sum(array_map('count', $allResults)) - count($organicResults)
                ]
            ]
        ];
    }
    
    /**
     * Calcola score per ordinamento
     */
    private function calculateScore($result, $engineName) {
        $score = 0;
        
        // Score base per motore
        $engineWeight = $this->engines[$engineName]['weight'] ?? 0.5;
        $score += $engineWeight * 100;
        
        // Bonus per numero di motori
        $score += count($result['found_by']) * 50;
        
        // Bonus per completezza
        if (!empty($result['descrizione'])) {
            $score += 10;
        }
        if (strlen($result['descrizione']) > 100) {
            $score += 5;
        }
        
        return $score;
    }
    
    /**
     * Normalizza URL per confronto
     */
    private function normalizeUrl($url) {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://#', '', $url);
        $url = preg_replace('#^www\.#', '', $url);
        $url = rtrim($url, '/');
        return $url;
    }
    
    /**
     * Cleanup cURL
     */
    private function cleanupCurl() {
        if ($this->multiCurl) {
            foreach ($this->curlHandles as $ch) {
                curl_multi_remove_handle($this->multiCurl, $ch);
                curl_close($ch);
            }
            curl_multi_close($this->multiCurl);
        }
        $this->curlHandles = [];
        $this->engineResults = [];
    }
    
    // ... mantieni tutti gli altri metodi esistenti per compatibilità ...
    
    /**
     * Recupera i risultati da DuckDuckGo
     */
    private function fetchDuckDuckGoResults() {
        $queryParams = [
            'q' => $this->params['q'],
            'kl' => $this->params['kl'] ?? 'it-it'
        ];
        
        if (isset($this->params['s']) && $this->params['s'] > 0) {
            $queryParams['s'] = $this->params['s'];
        }
        
        $url = 'https://html.duckduckgo.com/html/?' . http_build_query($queryParams);
        
        if ($this->debug) {
            echo "URL: $url\n";
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP Error: $httpCode");
        }
        
        return $response;
    }
    
    /**
     * Parse risultati in JSON
     */
    private function parseResultsToJson($html) {
        // ... mantieni il metodo esistente ...
    }
    
    /**
     * Metodo per ottenere solo i risultati in formato semplificato
     */
    public function getSimpleResults() {
        // ... mantieni il metodo esistente ...
    }
    
    /**
     * Imposta debug mode
     */
    public function setDebug($debug = true) {
        $this->debug = $debug;
        return $this;
    }
}