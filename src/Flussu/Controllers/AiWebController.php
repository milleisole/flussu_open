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
 * CLASS-NAME:       Flussu AI Web Research Controller
 * CREATED DATE:     26.04.2026 - Aldus - Flussu v5.0
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * Orchestrates web research dispatched from AiChatController when the
 * model emits the {"FLUSSU_WEB":{...}} marker. Mirrors AiMediaController
 * for image generation: native-browser providers (CHATGPT, CLAUDE, GEMINI,
 * GROK, QWEN, ZAI_GLM) can use their own grounding tool when configured;
 * everyone else (and providers that fail their native call) goes through
 * a hybrid local pipeline:
 *   WebSearchController.search()  -> top-N organic results
 *   WebScraperController.getPage* -> optional deep fetch of top hits
 *   $this->_aiClient->Chat()       -> synthesis with results as context
 * Result is a markdown reply with a citation footer.
 * -------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Contracts\IAiProvider;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Api\Ai\FlussuGeminAi;
use Flussu\Api\Ai\FlussuClaudeAi;
use Flussu\Api\Ai\FlussuDeepSeekAi;
use Flussu\Api\Ai\FlussuHuggingFaceAi;
use Flussu\Api\Ai\FlussuZeroOneAi;
use Flussu\Api\Ai\FlussuKimiAi;
use Flussu\Api\Ai\FlussuQwenAi;
use Flussu\Api\Ai\FlussuStabilityAi;
use Flussu\Api\Ai\FlussuMistralAi;
use Flussu\Api\Ai\FlussuZaiGlmAi;
use Flussu\Api\Ai\FlussuTogetherAi;

class AiWebController
{
    private IAiProvider $_aiClient;
    private Platform $_platform;
    private string $_model;
    private string $_chatModel;

    private bool   $_preferNative;
    private string $_defaultLang;
    private string $_defaultGeo;
    private int    $_topResults;
    private int    $_deepFetch;
    private string $_deepFetchFormat;
    private int    $_deepFetchMaxChars;
    private int    $_perRequestTimeout;

    public function __construct(Platform $platform = Platform::CHATGPT, $model = "", $chat_model = "")
    {
        $this->_platform  = $platform;
        $this->_model     = (string)$model;
        $this->_chatModel = (string)$chat_model;

        switch ($platform) {
            case Platform::CHATGPT:     $this->_aiClient = new FlussuOpenAi($model, $chat_model); break;
            case Platform::GROK:        $this->_aiClient = new FlussuGrokAi($model);              break;
            case Platform::GEMINI:      $this->_aiClient = new FlussuGeminAi($model);             break;
            case Platform::CLAUDE:      $this->_aiClient = new FlussuClaudeAi($model);            break;
            case Platform::DEEPSEEK:    $this->_aiClient = new FlussuDeepSeekAi($model);          break;
            case Platform::HUGGINGFACE: $this->_aiClient = new FlussuHuggingFaceAi($model);       break;
            case Platform::ZEROONE:     $this->_aiClient = new FlussuZeroOneAi($model, $chat_model);  break;
            case Platform::KIMI:        $this->_aiClient = new FlussuKimiAi($model, $chat_model);     break;
            case Platform::QWEN:        $this->_aiClient = new FlussuQwenAi($model, $chat_model);     break;
            case Platform::STABILITY:   $this->_aiClient = new FlussuStabilityAi($model);             break;
            case Platform::MISTRAL:     $this->_aiClient = new FlussuMistralAi($model, $chat_model);  break;
            case Platform::ZAI_GLM:     $this->_aiClient = new FlussuZaiGlmAi($model, $chat_model);   break;
            case Platform::TOGETHER:    $this->_aiClient = new FlussuTogetherAi($model, $chat_model); break;
            default:                    $this->_aiClient = new FlussuOpenAi($model, $chat_model);     break;
        }

        $this->_preferNative      = (bool)   config('services.web_research.prefer_native_provider_tool', true);
        $this->_defaultLang       = (string) config('services.web_research.default_lang', 'it');
        $this->_defaultGeo        = (string) config('services.web_research.default_geo', 'it');
        $this->_topResults        = (int)    config('services.web_research.top_results', 5);
        $this->_deepFetch         = (int)    config('services.web_research.deep_fetch', 1);
        $this->_deepFetchFormat   = (string) config('services.web_research.deep_fetch_format', 'markdown');
        $this->_deepFetchMaxChars = (int)    config('services.web_research.deep_fetch_max_chars', 12000);
        $this->_perRequestTimeout = (int)    config('services.web_research.per_request_timeout_sec', 12);
    }

    /**
     * Web research is always available because the local hybrid pipeline doesn't
     * depend on the provider's own browsing capability. canBrowse() exists only
     * for symmetry with AiMediaController::canGenerate().
     */
    public function canBrowse(): bool
    {
        return true;
    }

    /**
     * Main entry. Dispatches the parsed FLUSSU_WEB payload either to the
     * provider's native browsing tool (if available and preferred), or to the
     * local hybrid pipeline.
     *
     * @param string $sessId   Workflow session id.
     * @param array  $payload  Parsed FLUSSU_WEB body. Recognised keys:
     *                         - action:   "search" (default) | "fetch" | "research"
     *                         - query:    search query (required for search/research)
     *                         - url:      single URL (required for fetch)
     *                         - seeds:    array of additional URLs to fetch on top of search
     *                         - lang:     "it" / "en" / ...   (defaults to config)
     *                         - geo:      country hint        (defaults to config)
     *                         - top:      max search results to use (1..20, defaults to config)
     *                         - deep:     how many top results to scrape (0..5, defaults to config)
     * @param string $userText Original user prompt (passed to the synthesis call).
     * @return array [string $status, string $markdownReply, ?array $tokenUsage]
     */
    public function dispatch(string $sessId, array $payload, string $userText): array
    {
        $action = strtolower((string)($payload['action'] ?? 'search'));
        $query  = isset($payload['query']) ? trim((string)$payload['query']) : '';
        $url    = isset($payload['url'])   ? trim((string)$payload['url'])   : '';
        $seeds  = (isset($payload['seeds']) && is_array($payload['seeds']))
            ? array_values(array_filter(array_map('strval', $payload['seeds'])))
            : [];
        $lang = isset($payload['lang']) ? (string)$payload['lang'] : $this->_defaultLang;
        $geo  = isset($payload['geo'])  ? (string)$payload['geo']  : $this->_defaultGeo;
        $top  = isset($payload['top'])  ? max(1, min(20, (int)$payload['top']))  : $this->_topResults;
        $deep = isset($payload['deep']) ? max(0, min(5,  (int)$payload['deep'])) : $this->_deepFetch;

        // 1. Native provider tool branch (mirrors how image-capable providers
        // handle FLUSSU_IMG natively without going through TOGETHER).
        if ($this->_preferNative
            && method_exists($this->_aiClient, 'canBrowseWeb')
            && $this->_aiClient->canBrowseWeb()
            && $action !== 'fetch') { // explicit URL fetch always uses local scraper for determinism
            try {
                General::addRowLog("Web research: native path on " . $this->_platform->name . " (action=$action)");
                $native = $this->_aiClient->chat_WebPreview($userText, $sessId, 1024, 0.3);
                if (is_array($native) && isset($native[1]) && trim((string)$native[1]) !== '') {
                    $reply = (string)$native[1];
                    $usage = isset($native[2]) && is_array($native[2]) ? $native[2] : null;
                    return ["Ok", $reply, $usage];
                }
                General::addRowLog("Web research: native returned empty, falling back to hybrid");
            } catch (\Throwable $e) {
                General::addRowLog("Web research: native failed (" . $e->getMessage() . "), falling back to hybrid");
            }
        }

        // 2. Hybrid local branch
        try {
            $sources = [];

            if ($action === 'fetch') {
                if ($url === '') {
                    return ["Error", "_(Web research: action=fetch ma URL mancante.)_", null];
                }
                $body = $this->_scrape($url);
                if ($body === '') {
                    return ["Error", "_(" . self::humanizeWebError("no content fetched from " . $url) . ")_", null];
                }
                $sources[] = [
                    'title'   => parse_url($url, PHP_URL_HOST) ?: $url,
                    'url'     => $url,
                    'snippet' => '',
                    'body'    => $body,
                ];
            } else {
                if ($query === '' && empty($seeds)) {
                    return ["Error", "_(Web research: query e seeds entrambi vuoti.)_", null];
                }
                if ($query !== '') {
                    $sources = $this->_search($query, $lang, $geo, $top);
                    if (empty($sources)) {
                        General::addRowLog("Web research: no organic_results for query \"$query\"");
                        return ["Error", "_(" . self::humanizeWebError("no result") . ")_", null];
                    }
                    if ($deep > 0) {
                        $fetchCount = min($deep, count($sources));
                        for ($i = 0; $i < $fetchCount; $i++) {
                            if (!empty($sources[$i]['url'])) {
                                $body = $this->_scrape($sources[$i]['url']);
                                if ($body !== '') {
                                    $sources[$i]['body'] = $body;
                                }
                            }
                        }
                    }
                }
                foreach ($seeds as $seedUrl) {
                    $body = $this->_scrape($seedUrl);
                    if ($body !== '') {
                        $sources[] = [
                            'title'   => parse_url($seedUrl, PHP_URL_HOST) ?: $seedUrl,
                            'url'     => $seedUrl,
                            'snippet' => '',
                            'body'    => $body,
                        ];
                    }
                }
            }

            if (empty($sources)) {
                return ["Error", "_(" . self::humanizeWebError("no usable source") . ")_", null];
            }

            // Synthesis call to the chat provider.
            $context = $this->_buildContext($sources, $lang);
            $augmented = $context . "\n\n---\n\n## "
                . ($this->_isItalian($lang) ? "Domanda dell'utente" : "User question")
                . "\n" . $userText;

            $synth = $this->_aiClient->Chat([], $augmented, 'user');
            if (!is_array($synth) || !isset($synth[1]) || trim((string)$synth[1]) === '') {
                return ["Error", "_(" . self::humanizeWebError("synthesis empty") . ")_", null];
            }
            $reply = (string)$synth[1];
            $tokenUsage = (isset($synth[2]) && is_array($synth[2])) ? $synth[2] : null;

            $citations = $this->_buildCitationFooter($sources);
            if ($citations !== '') {
                $reply = rtrim($reply) . "\n\n" . $citations;
            }

            $tokenUsage = $this->_refineWebTokenUsage($tokenUsage, $sources);
            General::addRowLog("Web research: hybrid OK on " . $this->_platform->name
                . " (sources=" . count($sources) . ", deep=" . $this->_countWithBody($sources) . ")");

            return ["Ok", $reply, $tokenUsage];

        } catch (\Throwable $e) {
            General::addRowLog("Web research: hybrid exception: " . $e->getMessage());
            return ["Error", "_(" . self::humanizeWebError($e->getMessage()) . ")_", null];
        }
    }

    private function _search(string $query, string $lang, string $geo, int $top): array
    {
        try {
            $controller = new WebSearchController(false);
            $raw = $controller->setQuery($query)->setLocation($geo, $lang)->search();
        } catch (\Throwable $e) {
            General::addRowLog("Web research: search exception: " . $e->getMessage());
            return [];
        }
        if (!is_array($raw) || !empty($raw['error']) || empty($raw['organic_results'])) {
            return [];
        }
        $out = [];
        foreach (array_slice($raw['organic_results'], 0, $top) as $r) {
            $title = (string)($r['title'] ?? $r['titolo'] ?? '');
            $link  = (string)($r['link'] ?? '');
            if ($link === '') continue;
            $out[] = [
                'title'   => $title,
                'url'     => $link,
                'snippet' => (string)($r['description'] ?? $r['descrizione'] ?? ''),
            ];
        }
        return $out;
    }

    private function _scrape(string $url): string
    {
        $content = '';
        try {
            $scraper = new WebScraperController();
            switch ($this->_deepFetchFormat) {
                case 'json':
                    $json = $scraper->getPageContentJson($url);
                    $content = is_array($json)
                        ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        : (string)$json;
                    break;
                case 'html':
                    $content = (string)$scraper->getPageHtml($url);
                    break;
                case 'markdown':
                case 'text':
                default:
                    // WebScraperController doesn't expose markdown/text directly.
                    // getPageContentBody returns cleaned HTML (scripts/styles/ads stripped),
                    // which is compact enough to inject as synthesis context.
                    $content = (string)$scraper->getPageContentBody($url);
                    break;
            }
        } catch (\Throwable $e) {
            General::addRowLog("Web research: scrape exception for $url: " . $e->getMessage());
            return '';
        }
        if ($content === '') return '';
        if (strlen($content) > $this->_deepFetchMaxChars) {
            $content = substr($content, 0, $this->_deepFetchMaxChars) . "\n\n…[contenuto troncato]";
        }
        return $content;
    }

    private function _buildContext(array $sources, string $lang): string
    {
        $it = $this->_isItalian($lang);
        $intro = $it
            ? "Hai a disposizione i seguenti risultati di ricerca web. Rispondi alla domanda dell'utente nello stesso linguaggio in cui è formulata, usando solo le informazioni dei risultati. Cita le fonti tramite numero, ad esempio [1] o [2]. Se i dati sono insufficienti, dichiaralo onestamente. NON inventare informazioni non presenti nei risultati."
            : "You have the following web search results available. Answer the user's question in the same language they used, drawing only from these results. Cite sources by number, e.g. [1] or [2]. If the data is insufficient, say so honestly. Do NOT invent information not present in the results.";

        $blocks = [];
        $blocks[] = "## " . ($it ? "Risultati ricerca web" : "Web search results");
        foreach ($sources as $i => $s) {
            $n = $i + 1;
            $line = "[$n] " . ($s['title'] !== '' ? $s['title'] : $s['url']) . " — " . $s['url'];
            if (!empty($s['snippet'])) {
                $line .= "\n  " . $s['snippet'];
            }
            $blocks[] = $line;
        }

        $hasBody = false;
        foreach ($sources as $i => $s) {
            if (empty($s['body'])) continue;
            if (!$hasBody) {
                $blocks[] = "";
                $blocks[] = "## " . ($it ? "Contenuto delle fonti" : "Source contents");
                $hasBody = true;
            }
            $n = $i + 1;
            $blocks[] = "### [$n] " . ($s['title'] !== '' ? $s['title'] : $s['url']);
            $blocks[] = $s['body'];
        }

        return $intro . "\n\n" . implode("\n", $blocks);
    }

    private function _buildCitationFooter(array $sources): string
    {
        $lines = [];
        foreach ($sources as $i => $s) {
            if (empty($s['url'])) continue;
            $n = $i + 1;
            $title = $s['title'] !== '' ? $s['title'] : $s['url'];
            $lines[] = "> [$n] [$title](" . $s['url'] . ")";
        }
        if (empty($lines)) return '';
        return "---\n> **Fonti:**\n" . implode("\n", $lines);
    }

    private function _refineWebTokenUsage(?array $usage, array $sources): ?array
    {
        if (!is_array($usage)) {
            $usage = [
                'model'  => $this->_chatModel !== '' ? $this->_chatModel : ($this->_platform->name . ':web'),
                'input'  => 0,
                'output' => 0,
            ];
        }
        $searchCost = max(500, count($sources) * 500);
        $scrapeChars = 0;
        foreach ($sources as $s) {
            if (!empty($s['body'])) $scrapeChars += strlen((string)$s['body']);
        }
        $scrapeCost = (int)floor($scrapeChars / 4);
        $usage['input'] = (int)($usage['input'] ?? 0) + $searchCost + $scrapeCost;
        return $usage;
    }

    private function _countWithBody(array $sources): int
    {
        $n = 0;
        foreach ($sources as $s) if (!empty($s['body'])) $n++;
        return $n;
    }

    private function _isItalian(string $lang): bool
    {
        $l = strtolower($lang);
        return $l === 'it' || strpos($l, 'it') === 0 || strpos($l, 'italia') !== false;
    }

    public static function humanizeWebError(string $rawError): string
    {
        $low = strtolower($rawError);
        if (strpos($low, 'timeout') !== false || strpos($low, 'timed out') !== false)
            return "Web research timed out. Please try again.";
        if (strpos($low, 'rate') !== false && strpos($low, 'limit') !== false)
            return "Web research rate limit reached. Please try again in a few moments.";
        if (strpos($low, 'unauthorized') !== false || strpos($low, ' 401') !== false
            || strpos($low, ' 403') !== false || strpos($low, 'forbidden') !== false)
            return "Web research authentication failed. Please contact the administrator.";
        if (strpos($low, 'no result') !== false || strpos($low, 'empty') !== false)
            return "No web search results found. Please rephrase the query.";
        if (strpos($low, 'no content') !== false || strpos($low, 'no usable source') !== false)
            return "Could not retrieve any usable content from the web.";
        if (strpos($low, 'connection') !== false || strpos($low, 'dns') !== false || strpos($low, 'resolve') !== false)
            return "Web research connection error. Please try again.";
        if (strpos($low, 'synthesis empty') !== false)
            return "The model could not summarize the web results. Please try again.";
        return "Web research failed. Please try again with a different query.";
    }
}
 /*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS |
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/
