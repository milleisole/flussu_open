# DESIGN — AI File Emission (`flussu-file`)

> **Status:** design — not yet implemented.
> **Date:** 2026-05-28.
> **Scope:** add to Flussu the ability to surface, in the API response, a "document to be generated" when the user — while chatting with the AI — explicitly asks for a file (e.g. "fammi un pdf/markdown di…").

## 1. Goal & non-goals

**Goal.** Let an LLM emit a typed document payload inside the normal chat response, and let Flussu forward it to the caller as a new typed element inside `elms`, without the caller ever seeing the in-band marker.

**Non-goals — explicit architectural constraint.** Flussu does **not** generate, render, convert or persist the file. It stays an orchestrator. md→pdf, file write, docx packaging, etc. are the caller's responsibility. Flussu only:

1. injects a "capability" instruction into the prompt sent to the AI (opt-in),
2. intercepts the LLM reply when it contains a `flussu-file` block,
3. forwards the content to the caller as a typed element inside `elms`.

## 2. Decision who decides

The **LLM** decides — from the user's request — whether a file is needed and which format. Flussu performs **no** intent-detection on the user input. This keeps the feature simple, language-agnostic and aligned with the existing AI chat flow.

## 3. Contract — LLM → Flussu

When the capability is **active**, the AI may close its response with **exactly one** delimited block:

````
   ```flussu-file
   {"format":"md","filename":"report.md","content":"# Titolo\n..."}
   ```
````

**Schema:**

| field      | required | values                       | notes                                                                 |
|------------|----------|------------------------------|-----------------------------------------------------------------------|
| `format`   | no       | `md` \| `json` \| `html`     | default `md` if missing                                               |
| `content`  | yes      | string                       | file body as a string (even for `json`/`html`)                        |
| `filename` | no       | string                       | suggested name; caller may override                                   |

Optional discursive prose **before** the block is the message for the user.
Anything **after** the block is ignored (with a warning log) to keep the contract crisp.

The capability prompt (the text Flussu prepends/appends when opt-in is on) makes these rules explicit to the model:
- emit the block only when the user explicitly asks for a file;
- exactly one block per reply;
- the prose before the block, if any, must address the user (not describe the file).

## 4. Contract — Flussu → Caller

When a valid `flussu-file` block is intercepted, Flussu emits, inside `elms`, a new typed element following the existing convention `"TIPO$ID": [content, metadata, value?]`:

```json
"DOC$1": [
  "<content-string>",
  { "display_info": { "type": "document", "format": "md", "filename": "report.md" } }
]
```

- The marker is **never** visible to the caller.
- If discursive prose precedes the block, it is emitted in the **same turn** as a normal `L$` label.
- `display_info.type:"document"` is a **new** type, distinct from the existing `display_info.type:"file"` (which carries a URL to a static asset). For `document` the `content` is a literal string the client must turn into a file.

**Backward compatibility.** Clients that don't recognise `document` should ignore the element or render the `content` as plain text. No existing key changes.

## 5. Activation — opt-in

The capability is **off by default**. It must be turned on per workflow step by the workflow author, to avoid wasting tokens on the capability prompt and to avoid the LLM generating files spontaneously.

**Surface** — extend the existing `Environment::sendToAi` signature with one optional positional parameter:

```php
// src/Flussu/Flussuserver/Environment.php:216
public function sendToAi($sendText, $varResponseName, $provider = 0, $allowFile = false)
```

The same param is propagated as a 4th item in the `$innerParams` array consumed by `Executor::sendToAi` (`src/Flussu/Flussuserver/Executor.php:296`).

When `$allowFile === true`, Flussu prepends a fixed **capability prompt** (short text, defined once as a constant; language choice in §12.1) to `$sendText`. The capability prompt is appended **before** the DocumentSpace context injection that already happens at `Executor.php:303-308`, so the final message order is:

```
[capability prompt] + [DocSpace context] + [user sendText]
```

## 6. Interception — always-on, innocuous

Parsing is performed on **every** AI reply, regardless of `$allowFile`. Rationale: cheap (one regex), bullet-proof against a model that emits the block when it shouldn't, and a future-proof safety net.

**Algorithm** (in `Executor::sendToAi`, immediately after the reply is received and before `assignVars`):

1. Run a regex against the reply looking for `~```flussu-file\s*\n(.*?)\n```~s` (multiline, non-greedy). If multiple blocks match, **keep the first only** and warn-log the others.
2. If **no match** → behaviour unchanged. The full reply goes into `$varResponseName` as today.
3. If **match AND `$allowFile === false`** (unsolicited emission, see §11):
   - Do **not** strip anything, do **not** push a `DOC$` element. Reply passes through as plain text (acceptance criterion §11: "Capability OFF → zero behavioural changes").
   - Log `[DOC_FILE_v1] unsolicited_block sess=… len=…` so the workflow author notices.
4. If **match AND `$allowFile === true`**:
   1. Extract the inner JSON payload (group 1).
   2. `json_decode($payload, true)`.
   3. Validate: `is_array($decoded)` AND `isset($decoded['content'])` AND `is_string($decoded['content'])` AND `$decoded['format']` (if present) ∈ `{md, json, html}`.
   4. **On success:**
      - Strip the entire fenced block from the reply (keep prose before it; drop anything after with a warn log).
      - `assignVars($varResponseName, $strippedReply)` — variable carries only the user-facing prose, as today.
      - Push a pending element via the new `Session::pushPendingElm()` (see §7).
   5. **On failure** (malformed JSON, missing `content`, unknown `format`):
      - Do **not** strip anything.
      - Do **not** push a `DOC$` element.
      - `assignVars` with the **original** reply (degrade to plain text).
      - Log via `General::Log_nocaller("[DOC_FILE_v1] parse_fail …", true)` for diagnostics.

The diag log tag follows the existing convention recorded in memory (`[DOC_FILE_v1]`), single-grep removable.

## 7. Emission — side-channel pending-elements buffer

A new buffer on `Session` carries elements that originate outside the current workflow block. The DOC element is the first user, but the buffer is generic and reusable (e.g. future tool-use outputs).

**Surface to add on `src/Flussu/Flussuserver/Session.php`:**

```php
/** Push an element that Engine should merge into the next `elms` response. */
public function pushPendingElm(string $key, array $payload): void;

/** Return AND clear all pending elements. Engine calls this after building $frmElms. */
public function flushPendingElms(): array;
```

Internally a private array `$_pendingElms`. Persisted across the request lifecycle of the current turn; cleared on flush. Not serialised to disk — the buffer is per-turn.

**Integration in `src/Flussu/Api/V40/Engine.php`** (after `$frmElms = $wwork->getExecElements(...)` at line 376, **before** the foreach at line 383 that decorates each element with `display_info`):

```php
foreach ($wSess->flushPendingElms() as $key => $payload) {
    $frmElms[$key] = $payload;
}
```

Placement matters: the decoration loop already handles arbitrary `display_info.type` values (`unk`, `image`, `movie`, `file`, `qrcode`) — adding `document` as a passthrough type in the same `switch` (or as a no-op in `default`) means the element gets the standard sanitisation pipeline.

**Key uniqueness.** `DOC$N` where `N` is a monotonic counter scoped to the current turn (resets each flush). Single AI reply ⇒ at most one DOC element ⇒ `DOC$1`. The counter is internal to `pushPendingElm` so callers don't have to know.

## 8. Files to read & touch — verified signatures

| File | Line(s) | What |
|------|---------|------|
| `src/Flussu/Flussuserver/Environment.php` | 216 | Add `$allowFile = false` to `sendToAi`; forward as 4th element in `_addToResArray` |
| `src/Flussu/Flussuserver/Executor.php` | 296-428 | Read `$innerParams[3]` as `$allowFile`; prepend capability prompt if set; intercept reply; call `pushPendingElm` on success |
| `src/Flussu/Flussuserver/Session.php` | new methods | `pushPendingElm` / `flushPendingElms` + private buffer |
| `src/Flussu/Api/V40/Engine.php` | ~377 | Merge `flushPendingElms()` into `$frmElms` before the decoration loop |
| `src/Flussu/Api/V40/Engine.php` | switch at line 397 | Add `case "DOC":` — passthrough, no sanitisation of `content` (it is a file body, not HTML to render) |
| `src/Flussu/Contracts/IAiProvider.php` | — | **No changes.** Capability lives in the prompt, providers are oblivious |

The capability prompt text is a constant in `Executor` (or a small helper class). One source of truth, easy to tune.

## 9. Validation & robustness checklist

- [ ] regex tolerates `\r\n` and stray whitespace around the fence
- [ ] `content` is preserved verbatim, **no** htmlspecialchars / sanitize on it (it is a file body)
- [ ] non-UTF-8 bytes in `content` → reject (validation failure → degrade to text)
- [ ] payload size cap (e.g. 256 KB) to avoid abusive responses bloating `elms`
- [ ] block appearing **without** opt-in → parsed defensively, NOT emitted (zero-change invariant), only a warn log so the workflow author notices
- [ ] two or more `flussu-file` blocks in one reply → keep the first, warn-log the others (decision recorded in §6.1)

## 10. Documentation updates

The user brief lists `wofoEnv-commands.md` and `API_V40_REFERENCE.md`. **Neither file exists in the repo as of 2026-05-28** — `find` returns no match. Open question for the implementation phase:

- locate the actual files used as workflow-command reference and v4.0 API reference (candidates under `Docs/`, `Docs/Man/`, or outside the repo)
- OR create them under `Docs/` following the convention of `DESIGN_AI_MEDIA_EXCHANGE.md` etc.

Either way, the new content to add is:

- **wofoEnv side:** describe the 4th positional parameter `allowFile` on `sendToAi`, with a one-liner example.
- **API v4.0 side:** add `display_info.type:"document"` to the table of element types, with a complete example response.

## 11. Acceptance criteria

- [ ] **Capability OFF** → zero behavioural changes. Existing workflows produce identical responses byte-for-byte (modulo timestamps).
- [ ] **Capability ON + user asks for a file** → API response contains a `DOC$N` element with correct `format` / `content`, no marker visible to the caller, any prose preceding the block emitted as a label in the same turn.
- [ ] **Capability ON + normal prompt** → no `DOC$N` element, response is plain text as today.
- [ ] **Malformed payload** → no crash. Reply degrades to plain text, `[DOC_FILE_v1] parse_fail` log line present.
- [ ] **Unsolicited block (capability OFF, model emits it anyway)** → defensive emission AND warning log; no crash.
- [ ] **Backward compatibility** → clients that don't know `document` either ignore the element or render the `content` as text. No existing element key changes.

## 12. Decisions (confirmed 2026-05-28)

1. **Capability prompt language: English.** Fixed text, independent of conversation language. LLMs follow English system-style instructions more reliably; the prompt only governs the contract, not the user-facing output.
2. **Size cap on `content`: 256 KB** (262144 bytes, measured on the decoded `content` string). Over cap → validation failure → degrade to plain text + `[DOC_FILE_v1] oversize` log.
3. **Counter scope: per turn.** `DOC$N` resets every flush in `Engine.php`. With the current "at most one block per reply" rule the effective key is always `DOC$1`, but the counter keeps the door open for future multi-emit scenarios without an API change.
4. **Doc-files location: acknowledged open.** `wofoEnv-commands.md` and `API_V40_REFERENCE.md` will be located or created during the implementation plan, not in this design.

---

*This design supersedes nothing. It is additive to the v5.0 codebase.*
