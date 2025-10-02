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
 * VERSION REL.:    4.5.1.20251002
 * UPDATES DATE:    02.10.2025
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;

class OLDWebSearchController {    private $params = [];
    private $debug = false;
    private $timeout = 10;
    
    public function __construct($debug = false) {
        $this->debug = $debug;
    }
    
    /**
     * Imposta la query di ricerca
     */
    public function setQuery($query) {
        $this->params['q'] = $query;
        return $this;
    }
    
    /**
     * Imposta localizzazione (DuckDuckGo supporta regioni)
     */
    public function setLocation($gl = 'us', $hl = 'en') {
        // DuckDuckGo usa codici regionali tipo: us-en, it-it, wt-wt (nessuna regione)
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
        return $this;
    }
    
    /**
     * Imposta paginazione
     */
    public function setPagination($start = 0, $num = 10) {
        // DuckDuckGo HTML non supporta paginazione diretta
        // ma possiamo limitare i risultati nel parsing
        $this->params['start'] = $start;
        $this->params['num'] = $num;
        return $this;
    }
    
    /**
     * Imposta safe search
     */
    public function setSafeSearch($level = 'moderate') {
        // DuckDuckGo: strict, moderate, off
        $this->params['kp'] = $level === false ? '-2' : ($level === 'strict' ? '1' : '-1');
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
            $html = $this->fetchDuckDuckGoResults();
            return $this->parseResultsToJson($html);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Recupera i risultati da DuckDuckGo
     */
    private function fetchDuckDuckGoResults() {
        $queryParams = [
            'q' => $this->params['q'],
            'kl' => $this->params['kl'] ?? 'it-it'
        ];
        
        // Aggiungi offset se presente
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

    private function parseResultsToJson($html) {
        $results = [
            'search_metadata' => [
                'status' => 'Success',
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'DuckDuckGo',
                'query' => $this->params['q'],
                'region' => $this->params['kl'] ?? 'it-it'
            ],
            'search_parameters' => $this->params,
            'organic_results' => []
        ];
        
        // Parse con DOMDocument
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Trova tutti i risultati
        $resultNodes = $xpath->query('//div[contains(@class, "result results_links results_links_deep web-result")]');
        
        $position = 1;
        $limit = $this->params['num'] ?? 10;
        
        foreach ($resultNodes as $node) {
            if ($position > $limit) break;
            
            $result = [
                'pos' => $position,
                'link' => null,
                'title' => null,
                'description' => null,
                'displayed_link' => null,
                'language' => null,  // Non disponibile in DuckDuckGo HTML
                'update_date' => null  // Non disponibile in DuckDuckGo HTML
            ];
            
            // Estrai titolo e link
            $titleNode = $xpath->query('.//h2[@class="result__title"]/a[@class="result__a"]', $node)->item(0);
            if ($titleNode) {
                $result['titolo'] = trim($titleNode->textContent);
                
                // Estrai URL reale dal redirect di DuckDuckGo
                $href = $titleNode->getAttribute('href');
                if (preg_match('/uddg=([^&]+)/', $href, $matches)) {
                    $result['link'] = urldecode($matches[1]);
                } else {
                    $result['link'] = $href;
                }
            }
            
            // Estrai snippet/descrizione
            $snippetNode = $xpath->query('.//a[@class="result__snippet"]', $node)->item(0);
            if ($snippetNode) {
                // Rimuovi tag HTML e pulisci il testo
                $snippet = strip_tags($snippetNode->textContent);
                $result['descrizione'] = trim($snippet);
            }
            
            // Estrai display URL
            $urlNode = $xpath->query('.//a[@class="result__url"]', $node)->item(0);
            if ($urlNode) {
                $result['displayed_link'] = trim($urlNode->textContent);
            }
            
            // Aggiungi solo se ha almeno titolo e link
            if (!empty($result['titolo']) && !empty($result['link'])) {
                $results['organic_results'][] = $result;
                $position++;
            }
        }
        
        // Aggiungi informazioni aggiuntive
        $results['search_information'] = [
            'total_results' => count($results['organic_results']),
            'search_time' => null,
            'query_displayed' => $this->params['q']
        ];
        
        return $results;
    }
    
    /**
     * Metodo per ottenere solo i risultati in formato semplificato
     */
    public function getSimpleResults() {
        $fullResults = $this->search();
        
        if (isset($fullResults['error'])) {
            return $fullResults;
        }
        
        $simpleResults = [];
        foreach ($fullResults['organic_results'] as $result) {
            $simpleResults[] = [
                'pos' => $result['pos'],
                'link' => $result['link'],
                'description' => $result['description'],
                'language' => $result['language'],
                'update_date' => $result['update_date']
            ];
        }
        
        return $simpleResults;
    }

    /**
     * Metodo alternativo con regex (più veloce ma meno robusto)
     */
    private function parseResultsWithRegex($html) {
        $results = [
            'search_metadata' => [
                'status' => 'Success',
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'DuckDuckGo',
                'query' => $this->params['q']
            ],
            'search_parameters' => $this->params,
            'organic_results' => []
        ];
        
        // Pattern per estrarre risultati
        $pattern = '/<a class="result__a" href="([^"]+)"[^>]*>([^<]+)<\/a>.*?<a class="result__snippet"[^>]*>([^<]+)<\/a>/s';
        
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        $num = $this->params['num'] ?? 10;
        
        foreach (array_slice($matches, 0, $num) as $index => $match) {
            $url = $match[1];
            
            // Estrai URL reale dal redirect DuckDuckGo
            if (strpos($url, '//duckduckgo.com/l/') !== false) {
                parse_str(parse_url($url, PHP_URL_QUERY), $params);
                $url = isset($params['uddg']) ? urldecode($params['uddg']) : $url;
            }
            
            $results['organic_results'][] = [
                'pos' => $index + 1,
                'title' => html_entity_decode(trim($match[2])),
                'link' => $url,
                'snippet' => html_entity_decode(trim($match[3])),
                'displayed_link' => parse_url($url, PHP_URL_HOST)
            ];
        }
        
        return $results;
    }
    
    /**
     * Imposta debug mode
     */
    public function setDebug($debug = true) {
        $this->debug = $debug;
        return $this;
    }
}