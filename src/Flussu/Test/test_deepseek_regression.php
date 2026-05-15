<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * DeepSeek provider regression test
 *
 * Catches the v5.0 bug where FlussuDeepSeekAi called
 * `->withTemperature($t)` — a method that does NOT exist on
 * DeepSeek\DeepSeekClient (which exposes `setTemperature()` instead).
 * The undefined-method exception was silently caught and the chat
 * controller ended up returning just the character "r" (index 1 of
 * the literal string "Error:..." returned by the broken catch path).
 *
 * No API key required: the test introspects the source and runs the
 * catch-path with an injected stub client.
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use DeepSeek\DeepSeekClient;
use Flussu\Config;
use Flussu\Api\Ai\FlussuDeepSeekAi;

// Register the global config() helper (normally defined in webroot/api.php).
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

$RED   = "\033[31m";
$GREEN = "\033[32m";
$CYAN  = "\033[36m";
$RESET = "\033[0m";

$pass = 0;
$fail = 0;

function check(string $label, bool $cond, string $detail = ""): void {
    global $RED, $GREEN, $RESET, $pass, $fail;
    if ($cond) {
        echo "{$GREEN}[PASS]{$RESET} {$label}\n";
        $pass++;
    } else {
        echo "{$RED}[FAIL]{$RESET} {$label}";
        if ($detail !== "") echo "  --  {$detail}";
        echo "\n";
        $fail++;
    }
}

echo "{$CYAN}=== DeepSeek provider regression test ==={$RESET}\n\n";

// ---------------------------------------------------------------------
// Test 1: introspect FlussuDeepSeekAi source for every `->name(` call
//         and verify each one exists on DeepSeek\DeepSeekClient.
// ---------------------------------------------------------------------
$sourcePath = (new ReflectionClass(FlussuDeepSeekAi::class))->getFileName();
$source     = file_get_contents($sourcePath);

// Match every chained method call on $this->_deepseek (same statement,
// possibly multiline, with nested parens).  Strategy: locate each
// "$this->_deepseek" occurrence and walk forward consuming "->name(...)"
// segments with proper paren-balance counting.
$methodsCalled = [];
$offset = 0;
while (($pos = strpos($source, '$this->_deepseek', $offset)) !== false) {
    $i = $pos + strlen('$this->_deepseek');
    while (true) {
        if (substr($source, $i, 2) !== '->') break;
        $j = $i + 2;
        $name = '';
        while ($j < strlen($source) && preg_match('/[A-Za-z0-9_]/', $source[$j])) {
            $name .= $source[$j++];
        }
        if ($name === '' || ($source[$j] ?? '') !== '(') break;
        $methodsCalled[$name] = true;
        // skip balanced parens
        $depth = 1;
        $j++;
        while ($j < strlen($source) && $depth > 0) {
            $ch = $source[$j];
            if ($ch === '(')      $depth++;
            elseif ($ch === ')')  $depth--;
            $j++;
        }
        $i = $j;
    }
    $offset = $pos + 1;
}

check(
    "Detected at least one method chained on \$this->_deepseek",
    count($methodsCalled) > 0,
    "no chained call found - FlussuDeepSeekAi shape may have changed"
);

echo "  Methods detected: " . implode(', ', array_keys($methodsCalled)) . "\n\n";

foreach (array_keys($methodsCalled) as $methodName) {
    check(
        "DeepSeekClient::{$methodName}() exists",
        method_exists(DeepSeekClient::class, $methodName),
        "FlussuDeepSeekAi calls \$this->_deepseek->{$methodName}() but DeepSeek\\DeepSeekClient does not expose it"
    );
}

// ---------------------------------------------------------------------
// Test 2: explicit guard against the historical typo.
// ---------------------------------------------------------------------
check(
    "FlussuDeepSeekAi source does NOT contain the broken `withTemperature(` call",
    strpos($source, 'withTemperature(') === false,
    "regression: withTemperature() does not exist on DeepSeekClient (use setTemperature)"
);

// ---------------------------------------------------------------------
// Test 3: the catch block in _chatContinue must return an ARRAY (not a
// string) so the controller's $result[1] indexing returns the error
// message — not a random character.
// ---------------------------------------------------------------------
$provider = new FlussuDeepSeekAi('deepseek-chat', 'deepseek-chat');

// inject a stub client that always throws on run()
$ref  = new ReflectionClass($provider);
$prop = $ref->getProperty('_deepseek');
$prop->setAccessible(true);
$prop->setValue($provider, new class {
    public function query($s)             { return $this; }
    public function withModel($m)         { return $this; }
    public function setTemperature($t)    { return $this; }
    public function run(): string         { throw new \RuntimeException('forced failure for test'); }
});

$out = $provider->chat([], "ping", "user");
check(
    "_chatContinue returns array (not string) on exception",
    is_array($out),
    "got " . gettype($out) . ": " . (is_string($out) ? substr($out, 0, 60) : '')
);
check(
    "Returned array has expected 3-element shape [history, message, tokens]",
    is_array($out) && count($out) === 3,
    "got " . (is_array($out) ? count($out) . " elements" : "not array")
);
check(
    "Error message at index [1] is a real string longer than 1 char (not just 'r')",
    is_array($out) && isset($out[1]) && is_string($out[1]) && strlen($out[1]) > 1,
    "got: " . var_export($out[1] ?? null, true)
);
check(
    "Error message at index [1] starts with 'Error:' (informative)",
    is_array($out) && isset($out[1]) && is_string($out[1]) && str_starts_with($out[1], 'Error:'),
    "got: " . var_export($out[1] ?? null, true)
);

echo "\n{$CYAN}=== Result ==={$RESET}\n";
echo "Passed: {$GREEN}{$pass}{$RESET}\n";
echo "Failed: " . ($fail > 0 ? $RED : $GREEN) . "{$fail}{$RESET}\n";

exit($fail === 0 ? 0 : 1);
