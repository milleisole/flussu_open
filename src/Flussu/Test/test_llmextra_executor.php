<?php
/* --------------------------------------------------------------------*
 * Flussu v4.6 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Executor-level smoke test for the $LLMextra path.
 *
 * Bypasses Session/Handler/DB entirely: feeds a FakeSession (that
 * implements only the Session methods Executor touches) into
 * Executor::outputProcess() with a synthetic "sendToAi" command, then
 * verifies that $LLMEXTRA_OUT and $INFO land in the session exactly the
 * way Engine.php expects to read them.
 *
 * Usage:
 *   php src/Flussu/Test/test_llmextra_executor.php
 *
 * Exit code: 0 on pass, 1 on fail.
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

// DocumentSpace reads $_SERVER['DOCUMENT_ROOT'] to resolve Uploads/docspace/.
// Point it at webroot so the path resolves to <project>/Uploads/docspace/.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../webroot');

use Flussu\Config;
use Flussu\Flussuserver\Executor;

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

// ---------- Console helpers --------------------------------------------------
function out($label, $msg, $color = '34') { echo "\033[{$color}m[{$label}]\033[0m {$msg}\n"; }
function ok($m)   { out('OK',   $m, '32'); }
function fail($m) { out('FAIL', $m, '31'); }
function info($m) { out('INFO', $m, '36'); }
function sect($t) { echo "\n\033[35m=== {$t} ===\033[0m\n"; }

$fails = 0;
$assert = function (bool $cond, string $msg) use (&$fails) {
    if ($cond) ok($msg); else { fail($msg); $fails++; }
};

// ---------- FakeSession ------------------------------------------------------
/**
 * Implements only the slice of Session that Executor::sendToAi touches.
 * No DB, no Handler, no filesystem persistence of vars.
 */
class FakeSession {
    public array $vars = [];
    public array $logs = [];
    private string $id;
    public bool $error = false;

    public function __construct(string $sid) { $this->id = $sid; }

    public function getId(): string { return $this->id; }
    public function recLog(string $m): void { $this->logs[] = $m; }
    public function statusCallExt(bool $v): void { /* no-op */ }
    public function statusError(bool $v): void { $this->error = $this->error || $v; }

    public function assignVars(string $name, $value): bool {
        if (strlen($name) >= 1 && $name[0] !== '$') $name = '$' . $name;
        $this->vars[$name] = $value;
        return true;
    }
    public function getVarValue(string $name) {
        return $this->vars[$name] ?? null;
    }
    public function removeVars(string $name): bool {
        unset($this->vars[$name]);
        return true;
    }
}

// ---------- Build inputs -----------------------------------------------------
sect('Setup');
$sid = 'test-llmextra-' . bin2hex(random_bytes(4));
$sess = new FakeSession($sid);

$llmExtra = [
    'model'       => 'claude-haiku-4-5',
    'system'      => 'Sei un router che traduce la richiesta in una chiamata a un tool Gmail. Rispondi SOLO con un tool_use.',
    'temperature' => 0,
    'max_tokens'  => 512,
    'tools' => [[
        'name'        => 'search_messages',
        'description' => 'Cerca messaggi Gmail con Gmail query syntax.',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'q'           => ['type' => 'string'],
                'max_results' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'required'   => ['q'],
        ],
    ]],
    'tool_choice' => ['type' => 'any'],
];

// Session var must be a JSON string (that's how the TRM pass-through delivers it).
$sess->assignVars('$LLMextra', json_encode($llmExtra, JSON_UNESCAPED_UNICODE));
ok('FakeSession created with sid=' . $sid);
ok('$LLMextra pre-seeded as JSON string (' . strlen($sess->getVarValue('$LLMextra')) . ' bytes)');

// Synthetic evalRet mimicking what Environment::sendToAi queues: [provider, prompt, varResponseName]
// Provider=4 (Claude) is the normal $setAgent route; LLMextra.model must override it.
$evalRet = [[
    'sendToAi' => [ 4, "leggi l'ultima email non letta", '$risposta' ]
]];

// ---------- Invoke Executor --------------------------------------------------
sect('Invoke Executor::outputProcess (sendToAi + LLMextra)');
$exec = new Executor();

try {
    $t0 = microtime(true);
    $exec->outputProcess($sess, null, $evalRet, null, null, null);
    $dt = (microtime(true) - $t0) * 1000;
    ok(sprintf('outputProcess returned in %.0f ms', $dt));
} catch (\Throwable $e) {
    fail('outputProcess threw: ' . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ---------- Assertions -------------------------------------------------------
sect('Session state after call');

// 1) Input $LLMextra must have been consumed (removed).
$assert($sess->getVarValue('$LLMextra') === null, '$LLMextra input var was consumed');

// 2) $LLMEXTRA_OUT must be present and a valid JSON of the normalized object.
$rawOut = $sess->getVarValue('$LLMEXTRA_OUT');
$assert(!empty($rawOut), '$LLMEXTRA_OUT is set');
$decoded = is_string($rawOut) ? json_decode($rawOut, true) : (is_array($rawOut) ? $rawOut : null);
$assert(is_array($decoded), '$LLMEXTRA_OUT decodes to an array');

if (is_array($decoded)) {
    echo "llmExtra output:\n" . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    $assert(isset($decoded['type']),        'llmExtra.type is set');
    $assert(isset($decoded['model']),       'llmExtra.model is set');
    $assert(isset($decoded['stop_reason']), 'llmExtra.stop_reason is set');
    $assert(isset($decoded['usage']['input_tokens'], $decoded['usage']['output_tokens']), 'llmExtra.usage is set');
    $assert(($decoded['type'] ?? '') === 'tool_use', "type == 'tool_use' (got '" . ($decoded['type'] ?? '') . "')");
    $assert(($decoded['stop_reason'] ?? '') === 'tool_use', "stop_reason == 'tool_use'");
    $assert(($decoded['tool_use']['name'] ?? '') === 'search_messages',
        "tool_use.name == 'search_messages' (got '" . ($decoded['tool_use']['name'] ?? '') . "')");
    $assert(isset($decoded['tool_use']['input']), 'tool_use.input is set');
}

// 3) $INFO must be the token-usage JSON with MDL/CTI/CTO.
$rawInfo = $sess->getVarValue('$INFO');
$assert(!empty($rawInfo), '$INFO is set');
$infoDec = is_string($rawInfo) ? json_decode($rawInfo, true) : null;
$assert(is_array($infoDec) && isset($infoDec['MDL'], $infoDec['CTI'], $infoDec['CTO']),
    '$INFO has MDL/CTI/CTO');
if (is_array($infoDec)) {
    echo '$INFO: ' . json_encode($infoDec) . "\n";
    $assert(($infoDec['CTI'] ?? 0) > 0, '$INFO.CTI > 0');
    $assert(($infoDec['CTO'] ?? 0) > 0, '$INFO.CTO > 0');
}

// 4) Response variable ($risposta) should be empty on tool_use.
$risposta = $sess->getVarValue('$risposta');
$assert($risposta === '' || $risposta === null, '$risposta is empty on tool_use (got ' . var_export($risposta, true) . ')');

// 5) No error status.
$assert($sess->error === false, 'Session not marked as error');

// ---------- Simulate Engine response promotion ------------------------------
sect('Simulate Engine response construction');
$res = ['sid' => $sid, 'bid' => 'fake-bid', 'elms' => []];
$info = $sess->getVarValue('$INFO');
if (!empty($info)) { $res['info'] = $info; }
$llmOut = $sess->getVarValue('$LLMEXTRA_OUT');
if (!empty($llmOut)) {
    $d = is_string($llmOut) ? json_decode($llmOut, true) : $llmOut;
    $res['LLMextra'] = is_array($d) ? $d : $llmOut;
}

echo "Final response (what Engine would return):\n";
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$assert(isset($res['LLMextra']) && is_array($res['LLMextra']), "Response contains top-level 'LLMextra' object");
$assert(isset($res['info']), "Response contains 'info' field");

// ---------- Verdict ----------------------------------------------------------
sect('Result');
if ($fails === 0) {
    ok('ALL CHECKS PASSED — Executor + Engine LLMextra plumbing is correct');
    exit(0);
}
fail($fails . ' check(s) failed');
exit(1);
