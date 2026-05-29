<?php
/* --------------------------------------------------------------------*
 * CLI test for Flussu\Helpers\FlussuFileBlock::parse
 * Run from project root:
 *   php src/Flussu/Test/test_flussu_file_block.php
 * Exit code: 0 = all passed, 1 = one or more failures.
 * -------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Helpers\FlussuFileBlock;

$RED = "\033[31m"; $GREEN = "\033[32m"; $RESET = "\033[0m";
$pass = 0; $fail = 0;

function assertEq($label, $expected, $actual) {
    global $pass, $fail, $RED, $GREEN, $RESET;
    $ok = ($expected === $actual);
    if ($ok) { $pass++; echo "{$GREEN}PASS{$RESET} {$label}\n"; }
    else     { $fail++; echo "{$RED}FAIL{$RESET} {$label}\n  expected: " . var_export($expected,true) . "\n  actual:   " . var_export($actual,true) . "\n"; }
}

// ---- 1. No block → unchanged ------------------------------------------------
$r = FlussuFileBlock::parse("just a plain reply, nothing fenced.");
assertEq("no-block: found=false",    false, $r['found']);
assertEq("no-block: valid=false",    false, $r['valid']);
assertEq("no-block: trailing=false", false, $r['trailing']);
assertEq("no-block: stripped==in",   "just a plain reply, nothing fenced.", $r['stripped']);

// ---- 2. Happy path: minimal valid block -------------------------------------
$reply = "Here's your file.\n\n```flussu-file\n{\"format\":\"md\",\"filename\":\"x.md\",\"content\":\"# Hi\"}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("happy: found",      true, $r['found']);
assertEq("happy: valid",      true, $r['valid']);
assertEq("happy: trailing",   false, $r['trailing']);
assertEq("happy: format",     "md",         $r['payload']['format']);
assertEq("happy: content",    "# Hi",       $r['payload']['content']);
assertEq("happy: filename",   "x.md",       $r['payload']['filename']);
assertEq("happy: extras=0",   0, $r['extras']);
assertEq("happy: stripped trims block", "Here's your file.\n\n", $r['stripped']);

// ---- 2b. Trailing prose after the block → trailing=true ---------------------
$reply = "before\n```flussu-file\n{\"content\":\"x\"}\n```\nuh-oh I kept talking";
$r = FlussuFileBlock::parse($reply);
assertEq("trailing: valid",    true, $r['valid']);
assertEq("trailing: trailing", true, $r['trailing']);
assertEq("trailing: content",  "x", $r['payload']['content']);

// ---- 3. Default format when omitted ----------------------------------------
$reply = "```flussu-file\n{\"content\":\"hello\"}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("default-fmt: valid",  true, $r['valid']);
assertEq("default-fmt: md",     "md", $r['payload']['format']);

// ---- 4. Malformed JSON → found, invalid, reason -----------------------------
$reply = "```flussu-file\n{not json}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("bad-json: found",  true,  $r['found']);
assertEq("bad-json: valid",  false, $r['valid']);
assertEq("bad-json: reason", "json_decode_failed", $r['reason']);

// ---- 5. Missing content -----------------------------------------------------
$reply = "```flussu-file\n{\"format\":\"md\"}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("no-content: valid",  false, $r['valid']);
assertEq("no-content: reason", "missing_or_nonstring_content", $r['reason']);

// ---- 6. Unknown format ------------------------------------------------------
$reply = "```flussu-file\n{\"format\":\"docx\",\"content\":\"x\"}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("bad-fmt: valid",  false, $r['valid']);
assertEq("bad-fmt: reason", "unknown_format", $r['reason']);

// ---- 7. Oversize ------------------------------------------------------------
$big = str_repeat("a", FlussuFileBlock::MAX_CONTENT_BYTES + 1);
$reply = "```flussu-file\n" . json_encode(["content" => $big]) . "\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("oversize: valid",  false, $r['valid']);
assertEq("oversize: reason", "oversize", $r['reason']);

// ---- 8. Two blocks → keep first, extras=1 -----------------------------------
$reply = "```flussu-file\n{\"content\":\"first\"}\n```\nmiddle\n```flussu-file\n{\"content\":\"second\"}\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("two-blocks: valid",   true, $r['valid']);
assertEq("two-blocks: content", "first", $r['payload']['content']);
assertEq("two-blocks: extras",  1, $r['extras']);

// ---- 9. CRLF tolerance ------------------------------------------------------
$reply = "prose\r\n```flussu-file\r\n{\"content\":\"x\"}\r\n```";
$r = FlussuFileBlock::parse($reply);
assertEq("crlf: valid", true, $r['valid']);
assertEq("crlf: content", "x", $r['payload']['content']);

// ---- 10. Same-line fence + JSON (Claude style, observed in production) ------
// Some models emit the entire block on one line instead of following the
// fence-on-own-line layout from the capability prompt. The parser must
// tolerate it (no \R required between fence and JSON).
$reply = "Ecco il file.\n\n```flussu-file {\"format\":\"md\",\"filename\":\"d.md\",\"content\":\"# Hi\"}```";
$r = FlussuFileBlock::parse($reply);
assertEq("same-line: valid",    true,    $r['valid']);
assertEq("same-line: content",  "# Hi",  $r['payload']['content']);
assertEq("same-line: filename", "d.md",  $r['payload']['filename']);
assertEq("same-line: trailing", false,   $r['trailing']);

// ---- 11. Mixed: fence then JSON on next line, closing fence on same line ----
$reply = "```flussu-file\n{\"content\":\"x\"}```";
$r = FlussuFileBlock::parse($reply);
assertEq("mixed: valid", true, $r['valid']);
assertEq("mixed: content", "x", $r['payload']['content']);

// NOTE: the parser also rejects non-UTF-8 `content` via mb_check_encoding,
// but in PHP 8+ json_decode catches malformed UTF-8 first and returns null,
// so this branch is effectively defense-in-depth. Not unit-tested here.

// ---- Summary ----------------------------------------------------------------
echo "\n--- {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
