<?php
/* --------------------------------------------------------------------*
 * Test for FLUSSU_WEB / Web Research integration.
 *
 * Subcommands:
 *   php test_ai_web_research.php parser    -- extractFlussuWebPayload edge cases (no network)
 *   php test_ai_web_research.php local     -- hybrid path with DEEPSEEK (no native browse)
 *   php test_ai_web_research.php native    -- CHATGPT chat_WebPreview Responses API
 *   php test_ai_web_research.php fetch     -- action=fetch on a specific URL with CLAUDE
 *   php test_ai_web_research.php research  -- search + deep fetch with QWEN
 *   php test_ai_web_research.php all       -- run parser + local (cheapest) sequentially
 *
 * The parser case is offline. The other cases require valid API keys for the
 * relevant provider plus internet access for the search/scraping step.
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Config;
use Flussu\Controllers\AiChatController;
use Flussu\Controllers\AiWebController;
use Flussu\Controllers\Platform;

// Register the global config() helper (normally defined in webroot/api.php).
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

class TC {
    public static function ok($m)   { echo "\033[32m[OK]\033[0m   $m\n"; }
    public static function bad($m)  { echo "\033[31m[FAIL]\033[0m $m\n"; }
    public static function info($m) { echo "\033[34m[INFO]\033[0m $m\n"; }
    public static function head($m) { echo "\n\033[36m=== $m ===\033[0m\n"; }
}

function assert_true($cond, string $label): bool {
    if ($cond) { TC::ok($label); return true; }
    TC::bad($label);
    return false;
}

function run_parser_tests(): void {
    TC::head('Parser: extractFlussuWebPayload');
    $ctrl = new AiChatController(Platform::CHATGPT);

    $cases = [
        'string short-form' => [
            'input'  => '{"FLUSSU_WEB":"meteo Milano"}',
            'expect' => ['action' => 'search', 'query' => 'meteo Milano'],
        ],
        'string URL short-form' => [
            'input'  => '{"FLUSSU_WEB":"https://flussu.com"}',
            'expect' => ['action' => 'fetch', 'url' => 'https://flussu.com'],
        ],
        'object search' => [
            'input'  => '{"FLUSSU_WEB":{"action":"search","query":"flussu","lang":"it","top":5}}',
            'expect' => ['action' => 'search', 'query' => 'flussu', 'lang' => 'it', 'top' => 5],
        ],
        'object fetch' => [
            'input'  => 'prefix text {"FLUSSU_WEB":{"action":"fetch","url":"https://example.com/x"}} suffix',
            'expect' => ['action' => 'fetch', 'url' => 'https://example.com/x'],
        ],
        'object research with nested seeds array' => [
            'input'  => '{"FLUSSU_WEB":{"action":"research","query":"AI","seeds":["https://a.com","https://b.com"],"deep":2}}',
            'expect' => ['action' => 'research', 'query' => 'AI', 'seeds' => ['https://a.com','https://b.com'], 'deep' => 2],
        ],
        'inside markdown fences' => [
            'input'  => "Here you go:\n```json\n{\"FLUSSU_WEB\":{\"action\":\"search\",\"query\":\"x\"}}\n```",
            'expect' => ['action' => 'search', 'query' => 'x'],
        ],
        'malformed JSON' => [
            'input'  => '{"FLUSSU_WEB":{"action":"search", "query":}}',
            'expect' => null,
        ],
        'no marker' => [
            'input'  => 'just regular text with no marker',
            'expect' => null,
        ],
        'mixed FLUSSU_IMG and FLUSSU_WEB (web wins extract since img extracted separately)' => [
            'input'  => '{"FLUSSU_IMG":"a cat"} also {"FLUSSU_WEB":{"action":"search","query":"y"}}',
            'expect' => ['action' => 'search', 'query' => 'y'],
        ],
        'empty string value' => [
            'input'  => '{"FLUSSU_WEB":""}',
            'expect' => null,
        ],
    ];

    $pass = 0; $fail = 0;
    foreach ($cases as $name => $c) {
        $got = $ctrl->extractFlussuWebPayload($c['input']);
        $expect = $c['expect'];
        if ($expect === null) {
            $isOk = (empty($got));
            $msg = "$name → " . ($isOk ? "[] (as expected)" : "got: " . json_encode($got));
        } else {
            $isOk = true;
            foreach ($expect as $k => $v) {
                if (!isset($got[$k]) || $got[$k] !== $v) { $isOk = false; break; }
            }
            $msg = "$name → " . json_encode($got);
        }
        $isOk ? TC::ok($msg) : TC::bad($msg);
        $isOk ? $pass++ : $fail++;
    }
    TC::info("Parser: $pass passed, $fail failed");
}

function run_local_test(): void {
    TC::head('Hybrid local path (DEEPSEEK)');
    try {
        $sessId = "test-" . uniqid();
        $userText = "cerca su internet le ultime news su Flussu";
        $payload = ['action' => 'search', 'query' => 'flussu open source workflow', 'lang' => 'it', 'top' => 3, 'deep' => 1];
        $ctrl = new AiWebController(Platform::DEEPSEEK);
        TC::info("Dispatching: " . json_encode($payload));
        $res = $ctrl->dispatch($sessId, $payload, $userText);
        TC::info("Status: " . ($res[0] ?? '?'));
        TC::info("Reply (first 500 chars): " . substr((string)($res[1] ?? ''), 0, 500));
        TC::info("Token usage: " . json_encode($res[2] ?? null));

        assert_true(($res[0] ?? '') === 'Ok', 'status is Ok');
        assert_true(strlen((string)($res[1] ?? '')) > 50, 'reply non-trivial');
        assert_true(strpos((string)($res[1] ?? ''), 'http') !== false, 'reply contains a URL');
        assert_true(strpos((string)($res[1] ?? ''), 'Fonti') !== false, 'reply contains citation footer');
        assert_true(is_array($res[2] ?? null), 'token usage present');
    } catch (\Throwable $e) {
        TC::bad("local: " . $e->getMessage());
    }
}

function run_native_test(): void {
    TC::head('Native path (CHATGPT chat_WebPreview)');
    try {
        $sessId = "test-" . uniqid();
        $userText = "Quali sono le ultime news principali di oggi sul mondo della tecnologia? Rispondi in italiano in 3 punti.";
        $payload = ['action' => 'search', 'query' => 'tech news today', 'lang' => 'it', 'top' => 5];
        $ctrl = new AiWebController(Platform::CHATGPT);
        TC::info("Dispatching: " . json_encode($payload));
        $res = $ctrl->dispatch($sessId, $payload, $userText);
        TC::info("Status: " . ($res[0] ?? '?'));
        TC::info("Reply (first 500 chars): " . substr((string)($res[1] ?? ''), 0, 500));
        TC::info("Token usage: " . json_encode($res[2] ?? null));
        assert_true(($res[0] ?? '') === 'Ok', 'status is Ok');
        assert_true(strlen((string)($res[1] ?? '')) > 50, 'reply non-trivial');
    } catch (\Throwable $e) {
        TC::bad("native: " . $e->getMessage());
    }
}

function run_fetch_test(): void {
    TC::head('Fetch action (CLAUDE on https://flussu.com)');
    try {
        $sessId = "test-" . uniqid();
        $userText = "Riassumi in 3 punti cosa offre questo sito.";
        $payload = ['action' => 'fetch', 'url' => 'https://flussu.com', 'intent' => 'summary', 'lang' => 'it'];
        $ctrl = new AiWebController(Platform::CLAUDE);
        $res = $ctrl->dispatch($sessId, $payload, $userText);
        TC::info("Status: " . ($res[0] ?? '?'));
        TC::info("Reply (first 500 chars): " . substr((string)($res[1] ?? ''), 0, 500));
        assert_true(($res[0] ?? '') === 'Ok', 'status is Ok');
        assert_true(strlen((string)($res[1] ?? '')) > 50, 'reply non-trivial');
    } catch (\Throwable $e) {
        TC::bad("fetch: " . $e->getMessage());
    }
}

function run_research_test(): void {
    TC::head('Research action (QWEN, search + deep=2)');
    try {
        $sessId = "test-" . uniqid();
        $userText = "Cosa è Flussu? Spiegami in 5 punti citando le fonti.";
        $payload = ['action' => 'research', 'query' => 'flussu open source workflow', 'lang' => 'it', 'top' => 5, 'deep' => 2];
        $ctrl = new AiWebController(Platform::QWEN);
        $res = $ctrl->dispatch($sessId, $payload, $userText);
        TC::info("Status: " . ($res[0] ?? '?'));
        TC::info("Reply (first 500 chars): " . substr((string)($res[1] ?? ''), 0, 500));
        assert_true(($res[0] ?? '') === 'Ok', 'status is Ok');
        assert_true(strlen((string)($res[1] ?? '')) > 100, 'reply non-trivial');
    } catch (\Throwable $e) {
        TC::bad("research: " . $e->getMessage());
    }
}

// -------- main --------
$mode = $argv[1] ?? 'parser';
switch ($mode) {
    case 'parser':   run_parser_tests();   break;
    case 'local':    run_local_test();     break;
    case 'native':   run_native_test();    break;
    case 'fetch':    run_fetch_test();     break;
    case 'research': run_research_test();  break;
    case 'all':      run_parser_tests(); run_local_test(); break;
    default:
        echo "Unknown mode: $mode\n";
        echo "Usage: php test_ai_web_research.php [parser|local|native|fetch|research|all]\n";
        exit(1);
}
echo "\n";
