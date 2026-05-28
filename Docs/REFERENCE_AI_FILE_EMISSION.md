# REFERENCE — AI File Emission

> **Audience:** workflow authors (using `sendToAi` in `$wofoEnv` expressions) and API consumers (parsing the `elms` map).
> **Scope:** the `flussu-file` capability — letting an LLM hand back a typed file payload that Flussu forwards to the caller.
> **See also:** `DESIGN_AI_FILE_EMISSION.md` (rationale + decisions), source under `src/Flussu/Helpers/FlussuFileBlock.php`.

## 1. Workflow side — `sendToAi(..., allowFile)`

`Environment::sendToAi` accepts an optional 4th positional parameter. **Default is `true`**: the capability is on for every call, the workflow author opts out per node if needed.

```php
$wofoEnv->sendToAi($sendText, $varResponseName, $provider = 0, $allowFile = true);
```

| Parameter         | Type | Default | Meaning |
|-------------------|------|---------|---------|
| `sendText`        | string | — | The user message to send to the AI. |
| `varResponseName` | string | — | Session variable that will hold the AI's prose reply. |
| `provider`        | int    | `0`   | `Platform` enum value (`0` = workflow-declared default). |
| `allowFile`       | bool   | `true` | **AI file emission capability.** When `true` (default), Flussu prepends a capability prompt that tells the LLM it MAY emit a `flussu-file` fenced block when the user explicitly asks for a file. Pass `false` to silence the capability on a specific node (saves ~300 input tokens). |

**When you might want `allowFile=false`.** On nodes where you are absolutely certain a file emission would be noise — long-running classifiers, query planners, intent routers — or to shave tokens on extremely high-volume paths. Default is on, you only pass it when you want it off.

**What happens (default behaviour):**

1. The capability prompt is prepended to `sendText`. The prompt is in English, single source of truth at `FlussuFileBlock::CAPABILITY_PROMPT`.
2. The LLM reply is parsed.
3. If a valid `flussu-file` block is present:
   - the block is **stripped** from the reply text before it is stored in `$varResponseName` — `$varResponseName` carries only the prose
   - the file payload is forwarded to the caller as a `DOC$N` element inside `elms` (see §2)
4. If no block is present: identical to a normal chat turn.

**What happens when explicitly off (`allowFile=false`):**

- The capability prompt is NOT prepended.
- The LLM reply is still parsed defensively. If a block is detected, it is passed through verbatim as text and a `[DOC_FILE_v1] unsolicited_block` log line is written so you notice.

## 2. API response side — `display_info.type:"document"`

When `allowFile=true` and the LLM returns a valid block, the API response's `elms` map gains an extra entry of type `document`:

```json
{
  "sid": "abcd-1234-…",
  "bid": "…",
  "elms": {
    "L$1":   ["Here's your file.", { "display_info": { "type": "unk" } }],
    "DOC$1": [
      "# Report\n\nLine 1\nLine 2\n",
      { "display_info": {
          "type":     "document",
          "format":   "md",
          "filename": "report.md"
      } }
    ]
  }
}
```

**Element shape.** Same convention as every other element type: `[content, metadata, value?]`.

| Field in `display_info`     | Type    | Notes |
|-----------------------------|---------|-------|
| `type`                      | string  | Always the literal `"document"`. |
| `format`                    | string  | One of `md`, `json`, `html`. |
| `filename`                  | string  | **Optional**, **caller-suggested**, **unsanitised** — must be passed through `basename()` and null-byte stripped before any filesystem use. |

**Content.** `elms["DOC$N"][0]` is the **literal file body** as a string — not a URL, not base64, not HTML to render. Caller is expected to turn it into an actual file (md→pdf, json save, etc.). Flussu does not render or persist the file.

**Difference vs. `display_info.type:"file"`.** The pre-existing `file` type carries a URL to a static asset hosted by Flussu. The new `document` type carries the body inline. They are distinct on purpose; clients should not conflate them.

**Backward compatibility.** Clients that don't recognise `document`:
- can safely ignore the element (no semantics is lost from the rest of the response), or
- can render `elms["DOC$N"][0]` as a plain string in the chat — the user sees the raw text, which is acceptable for the markdown case and recoverable for json/html.

## 3. Contract the LLM follows

When `allowFile=true`, the LLM is told (in English, via `CAPABILITY_PROMPT`):

> Emit, **only when the user explicitly asks for a file**, exactly one fenced block in this form, with nothing after it:
> ```
> ```flussu-file
> {"format":"md","filename":"report.md","content":"<file body as a string>"}
> ```
> ```
> Rules: `format` ∈ {md, json, html}, default `md`; `content` is the body as a string; `filename` is a suggestion. Prose before the block is the user-facing message.

Flussu validates the payload:

| Validation failure              | `reason` tag in log                 |
|---------------------------------|-------------------------------------|
| Block found but JSON malformed  | `json_decode_failed`                |
| `content` missing or non-string | `missing_or_nonstring_content`      |
| `content` > 256 KB              | `oversize`                          |
| `content` non-UTF-8             | `invalid_utf8` (defense-in-depth)   |
| `format` not in allowed list    | `unknown_format`                    |

On any validation failure: no `DOC$` element is emitted, the original reply (including the malformed block) is passed through, and a `[DOC_FILE_v1] parse_fail` line is logged.

## 4. Quick example — workflow node

```php
// in a workflow's UProm/expression node
$wofoEnv->sendToAi(
    "$user_message",
    "AI_REPLY",
    0,        // provider: workflow default
    true      // allowFile: ON for this node
);
```

When the user later types "fammi un markdown con i punti chiave", the response will contain:
- `L$N`: `[ "Ecco i punti chiave:\n\n", { display_info: { type: "unk" } } ]`
- `DOC$1`: `[ "- punto 1\n- punto 2\n- punto 3\n", { display_info: { type: "document", format: "md" } } ]`

## 5. Diagnostic logs

All emission-related log lines carry the tag `[DOC_FILE_v1]`, single-grep removable. Tags:

- `emit sess=… fmt=… bytes=… extras=N trailing=yes|no` — DOC element successfully pushed
- `parse_fail sess=… reason=…` — block detected but invalid, plain-text degradation
- `unsolicited_block sess=… len=…` — capability off but the LLM emitted a block anyway

Found in the standard daily log under `Logs/log_DD-Mon-YYYY.log`.
