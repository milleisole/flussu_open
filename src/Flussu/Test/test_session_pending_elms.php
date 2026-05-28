<?php
/* --------------------------------------------------------------------*
 * CLI test for Session::pushPendingElm / flushPendingElms.
 * Run: php src/Flussu/Test/test_session_pending_elms.php
 * -------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Flussuserver\Session;

$RED = "\033[31m"; $GREEN = "\033[32m"; $RESET = "\033[0m";
$pass = 0; $fail = 0;

function assertEq($label, $expected, $actual) {
    global $pass, $fail, $RED, $GREEN, $RESET;
    $ok = ($expected === $actual);
    if ($ok) { $pass++; echo "{$GREEN}PASS{$RESET} {$label}\n"; }
    else     { $fail++; echo "{$RED}FAIL{$RESET} {$label}\n  expected: " . var_export($expected,true) . "\n  actual:   " . var_export($actual,true) . "\n"; }
}

// Bypass the constructor — Session normally bootstraps DB + workflow,
// neither of which is needed to exercise the buffer.
$sess = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();

// 1. Initially empty
assertEq("empty-start", [], $sess->flushPendingElms());

// 2. Push one, key generated as DOC$1
$sess->pushPendingElm("DOC", ["hello", ["display_info" => ["type" => "document"]]]);
$flushed = $sess->flushPendingElms();
assertEq("one-push: count", 1, count($flushed));
assertEq("one-push: key",   true, array_key_exists("DOC\$1", $flushed));
assertEq("one-push: payload[0]", "hello", $flushed["DOC\$1"][0]);

// 3. After flush, counter resets → next push is DOC$1 again
$sess->pushPendingElm("DOC", ["world", []]);
$flushed = $sess->flushPendingElms();
assertEq("after-flush: key reset", true, array_key_exists("DOC\$1", $flushed));

// 4. Multiple pushes in one turn → DOC$1, DOC$2
$sess->pushPendingElm("DOC", ["a", []]);
$sess->pushPendingElm("DOC", ["b", []]);
$flushed = $sess->flushPendingElms();
assertEq("multi: 2 elements",   2, count($flushed));
assertEq("multi: DOC\$1 first", "a", $flushed["DOC\$1"][0]);
assertEq("multi: DOC\$2 second","b", $flushed["DOC\$2"][0]);

// 5. Flush is idempotent — second flush returns []
$sess->pushPendingElm("DOC", ["x", []]);
$sess->flushPendingElms();
assertEq("flush idempotent", [], $sess->flushPendingElms());

echo "\n--- {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
