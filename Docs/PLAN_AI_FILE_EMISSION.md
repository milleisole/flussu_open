# AI File Emission — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an LLM emit a typed file payload (`flussu-file` fenced block); Flussu intercepts it and surfaces a new `DOC$N` element with `display_info.type:"document"` inside the API `elms` response. Flussu does **not** render the file — it just plumbs the content string to the caller.

**Architecture:** Capability is opt-in via a 4th positional arg `$allowFile` on `Environment::sendToAi`. Parsing is always-on but **emission** only happens when capability is on (zero-change invariant when off). DOC elements travel via a new per-turn pending-elements buffer on `Session`, merged into `$frmElms` by `Engine` right after `getExecElements()`.

**Tech Stack:** PHP 8.1+, no PHPUnit — tests are CLI scripts under `src/Flussu/Test/` invoked via `php`. Spec: `Docs/DESIGN_AI_FILE_EMISSION.md`.

**User convention:** The user (`aldo@milleisole.com`) handles git commits manually. Each task ends with a recommended commit message + suggested `git add` paths; the worker does **not** auto-commit unless the user signals otherwise.

---

## File Structure

| File | Role | Status |
|------|------|--------|
| `src/Flussu/Helpers/FlussuFileBlock.php` | Single source of truth for the capability prompt + parser/validator for the `flussu-file` fenced block. Pure functions, side-effect-free. | NEW |
| `src/Flussu/Flussuserver/Session.php` | Add `pushPendingElm()` / `flushPendingElms()` + private `$_pendingElms` buffer + private `$_pendingElmsCounter`. | MODIFY |
| `src/Flussu/Flussuserver/Environment.php` (line 216) | Add 4th positional arg `$allowFile = false` to `sendToAi`; forward as 4th element of `_addToResArray`. | MODIFY |
| `src/Flussu/Flussuserver/Executor.php` (case `"sendToAi"`, ~line 296-428) | Read `$innerParams[3]` as `$allowFile`; if true prepend capability prompt; after reply, run parser; on validated payload assign stripped reply to var + push DOC element to Session; on capability-OFF + spurious block, log only. | MODIFY |
| `src/Flussu/Api/V40/Engine.php` (~line 376 and switch at ~line 397) | After `getExecElements()` merge `$wSess->flushPendingElms()` into `$frmElms`. Add `case "DOC":` to the element-decoration switch — passthrough, no HTML sanitisation of `content`. | MODIFY |
| `src/Flussu/Test/test_flussu_file_block.php` | CLI unit tests for the parser/validator. | NEW |
| `src/Flussu/Test/test_session_pending_elms.php` | CLI unit tests for the buffer. | NEW |
| `src/Flussu/Test/test_doc_emission_e2e.php` | CLI end-to-end test using a fake AI provider, asserts the final `elms` structure. | NEW |
| `Docs/DESIGN_AI_FILE_EMISSION.md` | Spec — already exists; reference only. | — |
| Doc updates: workflow command ref + API v4.0 ref | Locate or create. See Task 10. | TBD |

---

## Task 1 — `FlussuFileBlock` helper (capability prompt + parser)

**Files:**
- Create: `src/Flussu/Helpers/FlussuFileBlock.php`

The helper is intentionally tiny: one public const (the capability prompt), one public static method that takes a raw LLM reply and returns a structured result. Pure — no I/O, no session access, no logging. The caller logs.

- [ ] **Step 1.1 — Create the file skeleton with the capability prompt constant**

Create `src/Flussu/Helpers/FlussuFileBlock.php`:

```php
<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Capability prompt + parser for the `flussu-file` fenced block.
 *
 * See Docs/DESIGN_AI_FILE_EMISSION.md for the full contract.
 * -------------------------------------------------------*/
namespace Flussu\Helpers;

final class FlussuFileBlock {

    /** Hard cap on the decoded `content` string (bytes). Over cap → reject. */
    public const MAX_CONTENT_BYTES = 262144; // 256 KB, decision §12.2

    /** Allowed values for the optional `format` field. */
    public const ALLOWED_FORMATS = ['md', 'json', 'html'];

    /** Default format when the LLM omits the field. */
    public const DEFAULT_FORMAT = 'md';

    /**
     * Capability prompt prepended to $sendText when `allowFile=true`.
     * English by design (§12.1) — independent of conversation language.
     * One source of truth: any tuning happens HERE.
     */
    public const CAPABILITY_PROMPT = <<<TXT
# File-emission capability
You may, **only when the user explicitly asks for a file** (e.g. "make me a markdown of…", "give me a pdf with…", "send me a json containing…"), close your reply with EXACTLY ONE fenced block in this format and nothing after it:

```flussu-file
{"format":"md","filename":"report.md","content":"<file body as a string>"}
```

Rules:
- `format` must be one of: md, json, html. Default md if omitted.
- `content` is the file body as a string (also for json/html — emit the raw text).
- `filename` is optional and only a suggestion.
- Any prose BEFORE the block is your normal message to the user. Do NOT describe the file there.
- Emit at most ONE block per reply. If unsure whether the user wants a file, do NOT emit the block — just reply in prose.
TXT;

    /**
     * Inspect a raw LLM reply and return a structured result.
     *
     * Return shape:
     *   [
     *     'found'    => bool,          // a `flussu-file` fence was present
     *     'valid'    => bool,          // payload parsed AND validated
     *     'reason'   => string|null,   // when !valid: short tag; null when valid or !found
     *     'stripped' => string,        // reply with the (first) block removed; equals input when !found
     *     'trailing' => bool,          // true when non-whitespace text follows the first fence (warn-log fodder)
     *     'extras'   => int,           // extra blocks found beyond the first (warn-log fodder)
     *     'payload'  => array|null,    // validated payload: ['format'=>..,'content'=>..,'filename'=>?]
     *   ]
     *
     * NOTE: `payload['filename']` is caller-suggested and unsanitised. Apply
     * basename() + null-byte strip before any filesystem use.
     */
    public static function parse(string $reply): array {
        $out = [
            'found' => false, 'valid' => false, 'reason' => null,
            'stripped' => $reply, 'trailing' => false, 'extras' => 0, 'payload' => null,
        ];

        if (!preg_match_all('~```flussu-file\s*\R(.*?)\R```~s', $reply, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        $out['found']  = true;
        $out['extras'] = count($m[0]) - 1;

        // First match drives behaviour; extras are reported, not applied.
        [$firstFenceText, $firstFenceOffset] = $m[0][0];
        $firstFenceLen = strlen($firstFenceText);
        $innerJson     = $m[1][0][0];

        $out['stripped'] = substr($reply, 0, $firstFenceOffset)
                         . substr($reply, $firstFenceOffset + $firstFenceLen);
        $out['trailing'] = (trim(substr($reply, $firstFenceOffset + $firstFenceLen)) !== '');

        $decoded = json_decode($innerJson, true);
        if (!is_array($decoded)) {
            $out['reason'] = 'json_decode_failed';
            return $out;
        }
        if (!isset($decoded['content']) || !is_string($decoded['content'])) {
            $out['reason'] = 'missing_or_nonstring_content';
            return $out;
        }
        if (strlen($decoded['content']) > self::MAX_CONTENT_BYTES) {
            $out['reason'] = 'oversize';
            return $out;
        }
        if (!mb_check_encoding($decoded['content'], 'UTF-8')) {
            $out['reason'] = 'invalid_utf8';
            return $out;
        }
        $format = $decoded['format'] ?? self::DEFAULT_FORMAT;
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $out['reason'] = 'unknown_format';
            return $out;
        }

        $payload = ['format' => $format, 'content' => $decoded['content']];
        if (isset($decoded['filename']) && is_string($decoded['filename'])) {
            $payload['filename'] = $decoded['filename'];
        }

        $out['valid']   = true;
        $out['payload'] = $payload;
        return $out;
    }
}
```

- [ ] **Step 1.2 — Verify syntax**

Run: `php -l src/Flussu/Helpers/FlussuFileBlock.php`
Expected: `No syntax errors detected in src/Flussu/Helpers/FlussuFileBlock.php`

- [ ] **Step 1.3 — Commit checkpoint** *(user-driven)*

Suggested stage + message:
```bash
git add src/Flussu/Helpers/FlussuFileBlock.php
# user runs the commit:
# git commit -m "v5 - FlussuFileBlock helper: capability prompt + parser"
```

---

## Task 2 — TDD `FlussuFileBlock` parser

**Files:**
- Create: `src/Flussu/Test/test_flussu_file_block.php`

Tests are CLI assertions following the existing pattern (`test_qwen_marker.php`, `test_llmextra.php`): a series of cases, `verdict()`-style print + counters, exit code 0 on full pass, 1 on any failure.

- [ ] **Step 2.1 — Write the test script BEFORE running the helper**

Create `src/Flussu/Test/test_flussu_file_block.php`:

```php
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

// NOTE: the parser also rejects non-UTF-8 `content` via mb_check_encoding,
// but in PHP 8+ json_decode catches malformed UTF-8 first and returns null,
// so this branch is effectively defense-in-depth. Not unit-tested here.

// ---- Summary ----------------------------------------------------------------
echo "\n--- {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2.2 — Run the tests, confirm all pass**

Run: `php src/Flussu/Test/test_flussu_file_block.php`
Expected: `--- 31 passed, 0 failed ---` exit code 0.

If failures appear, fix the parser (Task 1 helper), re-run. The parser is small enough that each failing assertion points to one specific branch.

- [ ] **Step 2.3 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Test/test_flussu_file_block.php
# git commit -m "v5 - tests for FlussuFileBlock parser"
```

---

## Task 3 — Pending-elements buffer on `Session`

**Files:**
- Modify: `src/Flussu/Flussuserver/Session.php` (insert near other private fields around line 84-100, public methods near `assignVars` ~line 825)

The buffer is per-turn, in-memory, **not serialised**. Counter resets on each flush so callers don't have to track state.

- [ ] **Step 3.1 — Add private fields**

Find the block of private fields starting at line 84 (`private $_history=[]`) and add immediately after `$_arVars`:

```php
    // v5.x - AI File Emission: side-channel buffer for elements emitted
    // outside the current workflow block (DOC$, future tool-use outputs).
    // Per-turn, in-memory only — never persisted.
    private $_pendingElms = [];
    private $_pendingElmsCounter = 0;
```

- [ ] **Step 3.2 — Add public methods**

Insert immediately AFTER `assignVars()` closes (find the closing `}` of `assignVars` around line ~900; the placement is conventional — adjacent to other element/var APIs):

```php
    /**
     * v5.x - Push an element emitted outside the current workflow block.
     * `$typePrefix` is the element-type tag (e.g. "DOC"); the resulting key
     * is "DOC$1", "DOC$2", … with the counter scoped to the current turn.
     * `$payload` follows the elms convention: [content, metadata] (or
     * [content, metadata, value]).
     */
    public function pushPendingElm(string $typePrefix, array $payload): void {
        $this->_pendingElmsCounter++;
        $key = $typePrefix . '$' . $this->_pendingElmsCounter;
        $this->_pendingElms[$key] = $payload;
    }

    /**
     * v5.x - Return AND clear all pending elements. Called by Engine after
     * building $frmElms. Returns an associative array suitable for
     * array_merge into $frmElms.
     */
    public function flushPendingElms(): array {
        $out = $this->_pendingElms;
        $this->_pendingElms = [];
        $this->_pendingElmsCounter = 0;
        return $out;
    }
```

- [ ] **Step 3.3 — Verify syntax**

Run: `php -l src/Flussu/Flussuserver/Session.php`
Expected: `No syntax errors detected in src/Flussu/Flussuserver/Session.php`

- [ ] **Step 3.4 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Flussuserver/Session.php
# git commit -m "v5 - Session: pending-elements buffer for side-channel emission"
```

---

## Task 4 — TDD the buffer

**Files:**
- Create: `src/Flussu/Test/test_session_pending_elms.php`

We can't easily instantiate a full `Session` (needs DB, workflow context). The buffer methods are pure-state, so we use a minimal subclass that bypasses the constructor via `ReflectionClass::newInstanceWithoutConstructor`.

- [ ] **Step 4.1 — Write the test**

Create `src/Flussu/Test/test_session_pending_elms.php`:

```php
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
```

- [ ] **Step 4.2 — Run the test, confirm pass**

Run: `php src/Flussu/Test/test_session_pending_elms.php`
Expected: `--- 9 passed, 0 failed ---` exit code 0.

- [ ] **Step 4.3 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Test/test_session_pending_elms.php
# git commit -m "v5 - tests for Session pending-elements buffer"
```

---

## Task 5 — Extend `Environment::sendToAi` signature

**Files:**
- Modify: `src/Flussu/Flussuserver/Environment.php` line 216

- [ ] **Step 5.1 — Read context**

Read `src/Flussu/Flussuserver/Environment.php:213-220` to confirm the surrounding lines haven't drifted from the spec mapping.

- [ ] **Step 5.2 — Edit the signature**

Replace line 216:
```php
    public function sendToAi            ($sendText, $varResponseName,$provider=0)  {$this->_addToResArray("sendToAi", array($provider,$sendText, $varResponseName));}
```
with:
```php
    public function sendToAi            ($sendText, $varResponseName,$provider=0,$allowFile=false)  {$this->_addToResArray("sendToAi", array($provider,$sendText, $varResponseName, $allowFile));}
```

The 4th element appended to the params array is the `$allowFile` flag — Executor reads `$innerParams[3]`.

- [ ] **Step 5.3 — Verify syntax**

Run: `php -l src/Flussu/Flussuserver/Environment.php`
Expected: `No syntax errors detected …`

- [ ] **Step 5.4 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Flussuserver/Environment.php
# git commit -m "v5 - Environment.sendToAi: opt-in allowFile flag"
```

---

## Task 6 — Wire capability + interception in `Executor`

**Files:**
- Modify: `src/Flussu/Flussuserver/Executor.php` case `"sendToAi"` (lines 296-428)

The changes are localised: read the new param, prepend the capability prompt before the DocSpace context, parse the AI reply after it returns, branch on capability + parse result.

- [ ] **Step 6.1 — Add the `use` statement near the top of Executor.php**

Find the existing `use` block (search for `use Flussu\Controllers\AiChatController;`) and add:

```php
use Flussu\Helpers\FlussuFileBlock;
```

- [ ] **Step 6.2 — Read the param at the top of the case**

In the `case "sendToAi":` block, immediately after `$Sess->recLog("AI provider: ".$innerParams[0]);` (~line 299), add:

```php
                        $allowFile = (bool)($innerParams[3] ?? false);
```

- [ ] **Step 6.3 — Prepend capability prompt when opt-in is on**

Find the assignment `$sendText = $innerParams[1];` (~line 302) and replace the line plus the immediately following `$docSpace` block so the final order is `[capability] + [docSpace] + [user sendText]`:

```php
                        // V4.6 - Document Space: inject document context into prompt
                        $sendText = $innerParams[1];
                        $docSpace = new \Flussu\Documents\DocumentSpace($Sess->getId());
                        if ($docSpace->hasDocuments()) {
                            $docContext = $docSpace->getContextForAi();
                            $sendText = $docContext . "\n\n" . $sendText;
                            $Sess->recLog("DocSpace: injected " . strlen($docContext) . " chars of document context");
                        }
                        // v5.x - AI File Emission capability (opt-in via 4th sendToAi param).
                        // Prepended LAST so the capability rules are the first thing the model reads.
                        if ($allowFile) {
                            $sendText = FlussuFileBlock::CAPABILITY_PROMPT . "\n\n" . $sendText;
                            $Sess->recLog("AI File Emission: capability ON");
                        }
```

- [ ] **Step 6.4 — Parse the reply and route to var + buffer**

Find the post-reply assignment block currently at ~lines 411-427:

```php
                        if ($reslt[0]!="Ok"){
                            $Sess->recLog("AI response: ".json_encode($reslt[1]));
                            $Sess->statusError(true);
                            //$reslt[1]="[ERROR]";
                            //break;
                        }
                        $Sess->assignVars($innerParams[2],$reslt[1]);
```

Replace the `$Sess->assignVars($innerParams[2],$reslt[1]);` line with the parse + branch:

```php
                        // v5.x - AI File Emission: intercept the reply.
                        // Parsing runs ALWAYS (cheap), but emission only when capability=ON
                        // so we preserve the zero-change invariant when off (Docs/DESIGN_AI_FILE_EMISSION.md §11).
                        $replyForVar = $reslt[1];
                        if (is_string($reslt[1])) {
                            $fb = FlussuFileBlock::parse($reslt[1]);
                            if ($fb['found']) {
                                if ($allowFile && $fb['valid']) {
                                    $replyForVar = $fb['stripped'];
                                    $docElm = [
                                        $fb['payload']['content'],
                                        ['display_info' => array_filter([
                                            'type'     => 'document',
                                            'format'   => $fb['payload']['format'],
                                            'filename' => $fb['payload']['filename'] ?? null,
                                        ], fn($v) => $v !== null)],
                                    ];
                                    $Sess->pushPendingElm('DOC', $docElm);
                                    \Flussu\General::Log_nocaller('[DOC_FILE_v1] emit sess=' . $Sess->getId()
                                        . ' fmt=' . $fb['payload']['format']
                                        . ' bytes=' . strlen($fb['payload']['content'])
                                        . ' extras=' . $fb['extras']
                                        . ' trailing=' . ($fb['trailing'] ? 'yes' : 'no'), true);
                                } elseif ($allowFile && !$fb['valid']) {
                                    \Flussu\General::Log_nocaller('[DOC_FILE_v1] parse_fail sess=' . $Sess->getId()
                                        . ' reason=' . $fb['reason'], true);
                                } else {
                                    // capability OFF but the model emitted anyway — log and pass through unchanged
                                    \Flussu\General::Log_nocaller('[DOC_FILE_v1] unsolicited_block sess=' . $Sess->getId()
                                        . ' len=' . strlen($reslt[1]), true);
                                }
                            }
                        }
                        $Sess->assignVars($innerParams[2], $replyForVar);
```

- [ ] **Step 6.5 — Verify syntax**

Run: `php -l src/Flussu/Flussuserver/Executor.php`
Expected: `No syntax errors detected …`

- [ ] **Step 6.6 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Flussuserver/Executor.php
# git commit -m "v5 - Executor.sendToAi: capability prompt + flussu-file interception"
```

---

## Task 7 — Wire `Engine` to merge pending elements + decorate `DOC`

**Files:**
- Modify: `src/Flussu/Api/V40/Engine.php` (~line 376 merge; ~line 397-470 switch)

- [ ] **Step 7.1 — Merge pending elements right after `getExecElements()`**

Find the existing line at ~376:
```php
            $frmElms=$wwork->getExecElements($restart,$restRows);
```

Add immediately after it:
```php
            // v5.x - AI File Emission: drain Session's side-channel buffer
            // (DOC$ from sendToAi with allowFile=true). Done BEFORE the
            // decoration switch so DOC elements receive the standard pass.
            foreach ($wSess->flushPendingElms() as $sideKey => $sidePayload) {
                $frmElms[$sideKey] = $sidePayload;
            }
```

- [ ] **Step 7.2 — Add `case "DOC":` to the decoration switch**

In the `switch ($key)` block (~line 397-470), add a new case AFTER `case "L":` (so a label sits between cases and DOC is its own thing). Place this just before `case "ITB":` or wherever case order feels natural — after the `"L"`/`"ITM"`/`"ITT"` group is cleanest:

```php
                        case "DOC":
                            // v5.x - AI File Emission: passthrough.
                            // $Elm is the file body (string), $Css already carries
                            // display_info.type=document set by Executor. Skip the
                            // sanitiser — this is a file body, not HTML to render.
                            $frmElms[$Key] = [$Elm, $Css];
                            break;
```

- [ ] **Step 7.3 — Verify syntax**

Run: `php -l src/Flussu/Api/V40/Engine.php`
Expected: `No syntax errors detected …`

- [ ] **Step 7.4 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Api/V40/Engine.php
# git commit -m "v5 - Engine.V40: merge pending elements + DOC passthrough"
```

---

## Task 8 — End-to-end test (Session + parser + Engine-style decoration)

**Files:**
- Create: `src/Flussu/Test/test_doc_emission_e2e.php`

This test exercises the *integration path* without booting the full Engine: simulate the Executor branch by hand, run the buffer through the same merge step Engine performs, then assert the final `elms`-style array. It covers the contract end-to-end and acts as a regression guard.

- [ ] **Step 8.1 — Write the test**

Create `src/Flussu/Test/test_doc_emission_e2e.php`:

```php
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
```

- [ ] **Step 8.2 — Run, confirm pass**

Run: `php src/Flussu/Test/test_doc_emission_e2e.php`
Expected: `--- 19 passed, 0 failed ---` exit code 0.

- [ ] **Step 8.3 — Commit checkpoint** *(user-driven)*

```bash
git add src/Flussu/Test/test_doc_emission_e2e.php
# git commit -m "v5 - E2E test for AI File Emission"
```

---

## Task 9 — Smoke check against an existing workflow

This task is a **manual verification**, not a code change. Goal: prove the zero-change invariant holds — run a workflow that calls `sendToAi` with no `allowFile` flag and confirm the API response is byte-identical to a pre-change baseline (modulo timestamps / session ids).

- [ ] **Step 9.1 — Pick a known-good workflow**

Identify a workflow currently in production that uses `sendToAi`. The chat-style workflow (`286/U-Prompt STAGELAB` visible in `Logs/log_28-May-2026.log`) is a candidate. Confirm with the user which workflow to use as baseline.

- [ ] **Step 9.2 — Capture baseline response BEFORE deploying changes**

This step assumes you keep the implementation on a feature branch. From `main` (or pre-change tip):
```bash
# trigger the workflow via its public URL, capture JSON
curl -s "<workflow public url + initial step>" > /tmp/flussu-baseline.json
```
Save the path; document the exact curl invocation in the PR description.

- [ ] **Step 9.3 — Repeat after deploying changes on the feature branch**

```bash
curl -s "<same url>" > /tmp/flussu-after.json
diff /tmp/flussu-baseline.json /tmp/flussu-after.json
```
Expected diff: only `sid`, `bid`, and any timestamps differ. **No structural change** to `elms`. If `elms` differs, the zero-change invariant has been broken — return to Task 6 step 6.4 and inspect why a non-opt-in call landed in the emission branch.

- [ ] **Step 9.4 — Demo the ON path**

Modify a test workflow node to call `sendToAi` with `allowFile=true`, ask the AI "fammi un markdown con tre punti sulla pizza", confirm the response contains a `DOC$1` element with `format=md`, `content` looks like a markdown list, and no `flussu-file` marker is visible.

- [ ] **Step 9.5 — Commit checkpoint** *(user-driven)*

No code to commit if all green. If the smoke check surfaces a bug, fix it and amend the relevant task's commit (the user decides whether to fold or stack a new commit).

---

## Task 10 — Documentation updates

**Files:**
- Locate or create the two reference docs from spec §10.

- [ ] **Step 10.1 — Hunt for an existing workflow-commands reference**

Run:
```bash
grep -rli --include="*.md" "sendToAi" Docs/
```
Expected: surfaces the file the project actually uses to document `wofoEnv` commands. The user brief named `wofoEnv-commands.md` — if grep finds a different filename, use that. If grep finds nothing, ask the user whether to create `Docs/WOFOENV_COMMANDS.md` (matching the `DESIGN_*` flat convention) and document `sendToAi` there from scratch.

- [ ] **Step 10.2 — Document the new parameter**

In whichever file Task 10.1 locates (or creates), add an entry for the new `allowFile` parameter:

```markdown
### `sendToAi(sendText, varResponseName, provider=0, allowFile=false)`

…

- **`allowFile`** *(bool, default `false`)* — when `true`, prepends a capability
  prompt to `sendText` instructing the LLM to emit a `flussu-file` fenced block
  if the user explicitly asks for a file. The block is intercepted: the file
  payload is forwarded to the caller as a `DOC$N` element with
  `display_info.type:"document"`, and the visible reply contains only the
  prose preceding the block.

  Off by default — turn on only on nodes where the user is expected to ask
  for documents, to avoid wasting tokens on the capability prompt.

  See `Docs/DESIGN_AI_FILE_EMISSION.md` for the contract.
```

- [ ] **Step 10.3 — Hunt for the API v4.0 reference**

Run:
```bash
grep -rli --include="*.md" "display_info" Docs/
```
Expected: surfaces the file documenting element types. If found, append the new type; if not, ask the user whether to create `Docs/API_V40_REFERENCE.md`.

- [ ] **Step 10.4 — Document the new `document` type**

```markdown
| `display_info.type` value | `content` semantics | Notes |
|---|---|---|
| `document` | Literal file body as a string | Emitted by `sendToAi(..., allowFile=true)` when the LLM returns a `flussu-file` block. The caller is expected to render the string as a file (md, json, or html). `display_info.format` carries the format hint; `display_info.filename` is a suggested name (optional). |

Example response fragment:

```json
"DOC$1": [
  "# Report\n\n…",
  { "display_info": { "type": "document", "format": "md", "filename": "report.md" } }
]
```
```

- [ ] **Step 10.5 — Update `Docs/DESIGN_AI_FILE_EMISSION.md` §10**

Once the actual file locations are confirmed, replace the "neither file exists" caveat in §10 with the resolved paths.

- [ ] **Step 10.6 — Commit checkpoint** *(user-driven)*

```bash
git add Docs/<the files touched>
# git commit -m "v5 - docs: AI File Emission (allowFile param + document element type)"
```

---

## Acceptance — full run-through

After all tasks above:

- [ ] `php src/Flussu/Test/test_flussu_file_block.php` → all green
- [ ] `php src/Flussu/Test/test_session_pending_elms.php` → all green
- [ ] `php src/Flussu/Test/test_doc_emission_e2e.php` → all green
- [ ] Smoke check (Task 9) on a real workflow with `allowFile=false` shows zero behavioural change
- [ ] Smoke check on a workflow with `allowFile=true` produces a `DOC$1` element, no visible marker, prose-only `L$` for the conversational part
- [ ] No new files outside the ones in the table at the top
- [ ] Memory file `feature_ai_document_generation.md` and `Docs/DESIGN_AI_FILE_EMISSION.md` stay in sync — if the implementation needed to deviate from the spec, update both
