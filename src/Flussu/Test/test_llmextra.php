<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Smoke test for the $LLMextra tool-use path (FlussuClaudeAi::chatExtra).
 *
 * Usage:
 *   php src/Flussu/Test/test_llmextra.php                    # default: Gmail tool_use
 *   php src/Flussu/Test/test_llmextra.php --mode=text        # no tools, pure text reply
 *   php src/Flussu/Test/test_llmextra.php --model=claude-haiku-4-5
 *   php src/Flussu/Test/test_llmextra.php --prompt="leggi ultime 3 email non lette"
 *
 * Exit code: 0 on pass, 1 on fail.
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Config;
use Flussu\Api\Ai\FlussuClaudeAi;

// Register the global config() helper (normally defined in webroot/api.php).
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

// ---------- CLI args ---------------------------------------------------------
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) {
        $args[$m[1]] = $m[2];
    } elseif (preg_match('/^--(.+)$/', $a, $m)) {
        $args[$m[1]] = true;
    }
}
$mode   = $args['mode']   ?? 'tool_use';
$model  = $args['model']  ?? 'claude-haiku-4-5';
$prompt = $args['prompt'] ?? "leggi l'ultima email non letta";

// ---------- Console helpers --------------------------------------------------
function out($label, $msg, $color = '34') { echo "\033[{$color}m[{$label}]\033[0m {$msg}\n"; }
function ok($m)   { out('OK',   $m, '32'); }
function fail($m) { out('FAIL', $m, '31'); }
function info($m) { out('INFO', $m, '36'); }
function sect($t) { echo "\n\033[35m=== {$t} ===\033[0m\n"; }

// ---------- Build LLMextra payload ------------------------------------------
function buildExtra(string $mode, string $model): array {
    $base = [
        'model'       => $model,
        'system'      => 'Sei un router che traduce la richiesta utente in una chiamata a un tool Gmail. Rispondi SOLO con un tool_use, mai con testo.',
        'temperature' => 0,
        'max_tokens'  => 512,
    ];
    if ($mode === 'text') {
        // No tools: expect stop_reason=end_turn and a text block
        unset($base['system']);
        $base['max_tokens'] = 64;
        return $base;
    }
    $base['tools'] = [
        [
            'name'        => 'search_messages',
            'description' => 'Cerca messaggi Gmail con Gmail query syntax (es. is:unread, from:x@y.com).',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'q'           => ['type' => 'string', 'description' => 'Gmail query string.'],
                    'max_results' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ],
                'required'   => ['q'],
            ],
        ],
    ];
    $base['tool_choice'] = ['type' => 'any'];
    return $base;
}

// ---------- Bootstrap ---------------------------------------------------------
sect('Test $LLMextra on FlussuClaudeAi::chatExtra()');
info("mode={$mode}  model={$model}");
info("prompt=\"{$prompt}\"");

try {
    $cfg = Config::init();
} catch (\Throwable $e) {
    fail("Config::init failed: " . $e->getMessage());
    exit(1);
}

$key = $cfg->get('services.ai_provider.ant_claude.auth_key');
if (empty($key) || strpos($key, 'insert-your-api-key') !== false) {
    fail('Claude auth_key is missing or placeholder in .services.json');
    exit(1);
}
ok('Claude auth_key present (' . substr($key, 0, 4) . '…' . substr($key, -4) . ')');

// ---------- Instantiate & call ----------------------------------------------
try {
    $provider = new FlussuClaudeAi($model, $model);
} catch (\Throwable $e) {
    fail('Provider construction failed: ' . $e->getMessage());
    exit(1);
}
if (!method_exists($provider, 'chatExtra')) {
    fail('FlussuClaudeAi::chatExtra() is missing — check your branch.');
    exit(1);
}
ok('Provider instantiated');

$extra = buildExtra($mode, $model);
info('LLMextra payload:');
echo json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$t0 = microtime(true);
$result = $provider->chatExtra($prompt, $extra);
$dt = (microtime(true) - $t0) * 1000;
info(sprintf('chatExtra returned in %.0f ms', $dt));

// ---------- Shape assertions -------------------------------------------------
sect('Return tuple');
if (!is_array($result) || count($result) !== 3) {
    fail('Expected a 3-element array [textReply, tokenUsage, llmExtra], got: ' . var_export($result, true));
    exit(1);
}
[$textReply, $tokenUsage, $llmExtra] = $result;
ok('Tuple shape OK');

sect('llmExtra normalized object');
echo json_encode($llmExtra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$fails = 0;
$check = function (bool $cond, string $msg) use (&$fails) {
    if ($cond) ok($msg); else { fail($msg); $fails++; }
};

if (($llmExtra['type'] ?? '') === 'error') {
    fail('Provider returned error: ' . ($llmExtra['error'] ?? '(no message)'));
    exit(1);
}

$check(isset($llmExtra['type']),        'llmExtra.type is set');
$check(isset($llmExtra['model']),       'llmExtra.model is set');
$check(isset($llmExtra['stop_reason']), 'llmExtra.stop_reason is set');
$check(isset($llmExtra['usage']['input_tokens']),  'llmExtra.usage.input_tokens is set');
$check(isset($llmExtra['usage']['output_tokens']), 'llmExtra.usage.output_tokens is set');

if ($mode === 'tool_use') {
    $check(($llmExtra['type'] ?? '') === 'tool_use', "type == 'tool_use' (got '" . ($llmExtra['type'] ?? '') . "')");
    $check(($llmExtra['stop_reason'] ?? '') === 'tool_use', "stop_reason == 'tool_use' (got '" . ($llmExtra['stop_reason'] ?? '') . "')");
    $check(isset($llmExtra['tool_use']['name']), 'tool_use.name is set');
    $check(isset($llmExtra['tool_use']['input']), 'tool_use.input is set');
    $check(($llmExtra['tool_use']['name'] ?? '') === 'search_messages',
        "tool_use.name == 'search_messages' (got '" . ($llmExtra['tool_use']['name'] ?? '') . "')");
    $check($textReply === '', 'textReply is empty on tool_use');
} else {
    $check(($llmExtra['type'] ?? '') === 'text', "type == 'text' (got '" . ($llmExtra['type'] ?? '') . "')");
    $check(!empty($llmExtra['text']), 'llmExtra.text is non-empty');
    $check($textReply !== '', 'textReply is non-empty');
}

sect('Token usage (for $INFO)');
echo json_encode($tokenUsage, JSON_PRETTY_PRINT) . "\n";
$check(is_array($tokenUsage) && isset($tokenUsage['model'], $tokenUsage['input'], $tokenUsage['output']),
    'tokenUsage has model/input/output');
$check(($tokenUsage['input'] ?? 0) > 0,  'tokenUsage.input > 0');
$check(($tokenUsage['output'] ?? 0) > 0, 'tokenUsage.output > 0');

sect('Result');
if ($fails === 0) {
    ok("ALL CHECKS PASSED — \$LLMextra path is wired correctly");
    exit(0);
}
fail($fails . ' check(s) failed');
exit(1);
