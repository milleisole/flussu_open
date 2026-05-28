<?php
/* --------------------------------------------------------------------*
 * End-to-end test for AI File Emission.
 * Simulates the Executor.sendToAi branch: parse a fake LLM reply,
 * push DOC element, then mimic Engine's flush + merge step.
 * Run: php src/Flussu/Test/test_doc_emission_e2e.php
 * -------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Flussuserver\Session;
use Flussu\Helpers\FlussuFileBlock;

$RED = "\033[31m"; $GREEN = "\033[32m"; $RESET = "\033[0m";
$pass = 0; $fail = 0;

function assertEq($label, $expected, $actual) {
    global $pass, $fail, $RED, $GREEN, $RESET;
    $ok = ($expected === $actual);
    if ($ok) { $pass++; echo "{$GREEN}PASS{$RESET} {$label}\n"; }
    else     { $fail++; echo "{$RED}FAIL{$RESET} {$label}\n  expected: " . var_export($expected,true) . "\n  actual:   " . var_export($actual,true) . "\n"; }
}

function assertTrue($label, $cond) { assertEq($label, true, (bool)$cond); }

$sess = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();

// --- Scenario A: capability ON + valid block ---------------------------------
$replyA = "Ecco il file richiesto.\n\n```flussu-file\n"
        . json_encode(["format" => "md", "filename" => "note.md", "content" => "# Note\n\nciao"])
        . "\n```";

// Simulate Executor branch
$fb = FlussuFileBlock::parse($replyA);
assertTrue("A: found", $fb['found']);
assertTrue("A: valid", $fb['valid']);
$varValue = $fb['stripped'];
$sess->pushPendingElm("DOC", [
    $fb['payload']['content'],
    ['display_info' => [
        'type' => 'document',
        'format' => $fb['payload']['format'],
        'filename' => $fb['payload']['filename'],
    ]],
]);

// Simulate Engine merge step
$frmElms = ["L\$1" => [$varValue, []]];
foreach ($sess->flushPendingElms() as $k => $v) { $frmElms[$k] = $v; }

assertTrue("A: L\$1 present", isset($frmElms["L\$1"]));
assertEq("A: L\$1 content prose only", "Ecco il file richiesto.\n\n", $frmElms["L\$1"][0]);
assertTrue("A: DOC\$1 present", isset($frmElms["DOC\$1"]));
assertEq("A: DOC\$1 content", "# Note\n\nciao", $frmElms["DOC\$1"][0]);
assertEq("A: DOC\$1 type", "document", $frmElms["DOC\$1"][1]['display_info']['type']);
assertEq("A: DOC\$1 format", "md", $frmElms["DOC\$1"][1]['display_info']['format']);
assertEq("A: DOC\$1 filename", "note.md", $frmElms["DOC\$1"][1]['display_info']['filename']);

// --- Scenario B: capability OFF + spurious block → no DOC, reply unchanged --
$replyB = "stuff\n```flussu-file\n{\"content\":\"x\"}\n```";
$fb = FlussuFileBlock::parse($replyB);
assertTrue("B: found", $fb['found']);
// In Executor with allowFile=false we skip the push and keep the original reply.
$varValue = $replyB;  // intentionally unchanged
$frmElms = ["L\$1" => [$varValue, []]];
foreach ($sess->flushPendingElms() as $k => $v) { $frmElms[$k] = $v; }
assertTrue("B: no DOC element", !isset($frmElms["DOC\$1"]));
assertEq("B: reply passes through verbatim", $replyB, $frmElms["L\$1"][0]);

// --- Scenario C: capability ON + malformed block → degrade to text -----------
$replyC = "look:\n```flussu-file\n{broken json}\n```";
$fb = FlussuFileBlock::parse($replyC);
assertTrue("C: found", $fb['found']);
assertTrue("C: invalid", !$fb['valid']);
// Executor branch: do not strip, keep original reply, no push
$varValue = $replyC;
$frmElms = ["L\$1" => [$varValue, []]];
foreach ($sess->flushPendingElms() as $k => $v) { $frmElms[$k] = $v; }
assertTrue("C: no DOC element", !isset($frmElms["DOC\$1"]));
assertEq("C: reply intact", $replyC, $frmElms["L\$1"][0]);

// --- Scenario D: capability ON + valid block + plain prompt (no block) -------
$replyD = "Just a plain answer.";
$fb = FlussuFileBlock::parse($replyD);
assertTrue("D: not found", !$fb['found']);
$varValue = $replyD;
$frmElms = ["L\$1" => [$varValue, []]];
foreach ($sess->flushPendingElms() as $k => $v) { $frmElms[$k] = $v; }
assertTrue("D: no DOC", !isset($frmElms["DOC\$1"]));
assertEq("D: reply intact", $replyD, $frmElms["L\$1"][0]);

echo "\n--- {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
