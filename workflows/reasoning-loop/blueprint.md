# Reasoning / Clarification Loop — Block-by-Block Blueprint

Source of truth: `workflows/reasoning-loop/reasoning_loop_workflow.json`
Design spec: `docs/superpowers/specs/2026-06-26-reasoning-loop-design.md`

---

## 1. Overview

This reference workflow implements a "modern chatbot" reasoning loop inside a
Flussu form. Before producing a final answer the workflow triages the user's
question with a cheap, fast LLM. The triage returns a structured JSON verdict
that drives one of three branches:

- **chiaro** (clear) — the question can be answered now; go straight to ANSWER.
- **serve_ragionamento** (needs reasoning) — run an internal analysis pass to
  enrich the context, then re-triage. The user never sees this step.
- **servono_dati** (needs data) — emit dynamic input fields to the form, collect
  the user's answers, then re-triage.

A `$round` counter increments on every TRIAGE pass. When it exceeds `$MAX_ROUNDS`
the workflow forces an ANSWER regardless of the verdict (best-effort fallback),
guaranteeing termination. The engine's own 256-iteration safety cap acts as an
additional net.

```
START -> TRIAGE -exit0-> ANSWER -> END
             |-exit1-> REASON -> (back to TRIAGE)
             `-exit2-> ASK    -> (back to TRIAGE)
```

Reasoning is entirely internal: the user sees only the final answer or
clarifying questions.

---

## 2. Import

**Option A — Flussu Designer**

Open the designer, click "Import Workflow", and upload
`workflows/reasoning-loop/reasoning_loop_workflow.json`.

**Option B — API**

```
POST /api.php?C=RD
Content-Type: application/json
Body: <contents of reasoning_loop_workflow.json>
```

The JSON envelope format is documented in `Docs/FLUSSU_JSON_WORKFLOW_SPEC.md`.

**Prerequisites:** `config/.services.json` must have providers 99 (modello
flussu — used for triage and reasoning) and 4 (Claude — used for the final
answer) configured under the appropriate AI provider keys. The workflow will
not run without valid credentials for both.

---

## 3. Configuration

All tunable knobs live at the top of the START block exec. Edit them there
(or in the designer) to adapt the workflow to a different deployment.

| Variable | Default | Purpose |
|---|---|---|
| `$MAX_ROUNDS` | `3` | Maximum number of TRIAGE passes before forcing best-effort ANSWER. Raise for harder questions; lower to cut costs. |
| `$MAX_SLOTS` | `6` | Maximum number of clarification fields ASK can emit in one round. Must match the number of `$clar1..$clarN` slot vars the engine knows about. |
| `$PROV_TRIAGE` | `99` | Provider id for TRIAGE and REASON calls. 99 = "modello flussu" (cheap, fast). Change to a stronger model if triage quality is poor. |
| `$PROV_ANSWER` | `4` | Provider id for the final ANSWER call. 4 = Claude. Use whichever model gives the best final-answer quality for your domain. |
| `$DOMINIO` | Generic assistant | The paragraph injected into the triage system prompt that defines what makes a question "complete". See note below. |

**Re-pointing `$DOMINIO` to a different domain**

The default value describes a general-purpose assistant where a question is
complete when it can be answered without guessing intent, scope, constraints, or
essential specifics. Replace this text to describe your vertical. Examples:

- Medical triage: "You are a medical information assistant. A question is
  COMPLETE when it names a symptom, its duration, the patient's age group, and
  any known conditions. Otherwise essential clinical specifics are missing."
- Legal Q&A: "You are a contract-law assistant. A question is COMPLETE when it
  identifies the contract type, the jurisdiction, and the specific clause or
  dispute at issue."

The triage LLM uses this text verbatim to judge whether to answer, reason
more, or ask for more data.

---

## 4. Triage JSON Contract

The triage LLM must reply with **only** the following JSON — no prose, no
markdown fences. The `estraiJson` helper tolerates surrounding noise (fences,
preamble) but a clean reply is preferable.

```json
{
  "status": "chiaro | serve_ragionamento | servono_dati",
  "ragionamento": "short internal note — never shown to the user",
  "focus": "if serve_ragionamento: what aspect to deepen in REASON",
  "dati_mancanti": [
    {
      "var": "slotN",
      "label": "question to show the user, in the user's language",
      "tipo": "testo | testo_lungo | scelta",
      "opzioni": ["Option A", "Option B"],
      "obbligatorio": true
    }
  ]
}
```

Field notes:

- `status` drives routing (see Section 1).
- `ragionamento` is appended to internal context/logs; never rendered.
- `focus` is read only by the REASON block.
- `dati_mancanti` is read only by the ASK block. Each item produces one input
  element via the corresponding `wofoEnv->create*` call.
- `tipo` controls the widget:
  - `testo` → single-line text input (`createInputStandard`)
  - `testo_lungo` → multirow textarea (`createInputMultirow`)
  - `scelta` → dropdown/select (`createSelect`, requires `opzioni`)
- `opzioni` is required when `tipo == "scelta"`, otherwise ignored.
- Maximum `$MAX_SLOTS` items in `dati_mancanti`; extras are silently dropped.
- `label` and `opzioni` must be written in the user's language (`$lang`).

### Worked Examples

**status: chiaro** — no missing data, answer immediately.

```json
{
  "status": "chiaro",
  "ragionamento": "The question names a concrete Python error with a clear stack trace; answerable directly.",
  "focus": "",
  "dati_mancanti": []
}
```

**status: serve_ragionamento** — the question is internally ambiguous; an
analysis pass will sharpen the context before re-triaging.

```json
{
  "status": "serve_ragionamento",
  "ragionamento": "The question asks about 'the best database' but the use-case type is unknown; internal analysis should surface the key trade-offs.",
  "focus": "Enumerate the relevant database categories (relational, document, graph, time-series) and what trade-offs matter for typical use cases.",
  "dati_mancanti": []
}
```

**status: servono_dati** — essential specifics are missing; the user must fill
in fields before the workflow can proceed.

```json
{
  "status": "servono_dati",
  "ragionamento": "Budget, travel dates, and departure city are missing; can't recommend a flight.",
  "focus": "",
  "dati_mancanti": [
    { "var": "clar1", "label": "What is your departure city?",  "tipo": "testo",       "opzioni": [], "obbligatorio": true },
    { "var": "clar2", "label": "What are your travel dates?",   "tipo": "testo",       "opzioni": [], "obbligatorio": true },
    { "var": "clar3", "label": "What is your budget range?",    "tipo": "scelta", "opzioni": ["< $500", "$500–$1000", "> $1000"], "obbligatorio": false }
  ]
}
```

---

## 5. Block-by-Block

> **Exec code convention:** The PHP snippets below are copied verbatim from the
> JSON, with the opening `<?php` and closing `?>` tags stripped for readability.

---

### START — Question + config

**Block ID:** `b2a00001-rsn1-4a01-b001-000000000001`

**Purpose:** Renders the initial question form. Initialises all session
variables and configuration constants so every subsequent block can rely on
them existing. Immediately exits to TRIAGE when the user submits.

**Elements:**

| Order | Type | Variable | Label (EN / IT) |
|---|---|---|---|
| 0 | MESSAGE | — | "Ask me anything:" / "Fammi una domanda:" |
| 1 | INPUT | `$userQuestion` | "Type your question..." / "Scrivi la tua domanda..." |
| 2 | BUTTON | `$start_btn` | "Send" / "Invia" (exit 0) |

**Exits:**

| Exit | Destination |
|---|---|
| 0 | TRIAGE (`b2a00002-rsn1-4a01-b002-000000000001`) |

**Exec:**

```php
wofoEnv->init();
$round = 0;
$context = "";
$triage = "";
$MAX_ROUNDS = 3;
$MAX_SLOTS = 6;
$PROV_TRIAGE = 99;
$PROV_ANSWER = 4;
$DOMINIO = "You are a general-purpose assistant. A question is COMPLETE when it can be answered accurately without guessing the user's intent, scope, constraints or any essential specifics. Otherwise essential specifics are missing.";
wofoEnv->setExit(0);
```

---

### TRIAGE — harvest, classify, route

**Block ID:** `b2a00002-rsn1-4a01-b002-000000000001`

**Purpose:** The central hub of the loop. On every pass it: (1) harvests the
user's answers from the previous ASK round into `$context`, then clears the
slot variables; (2) increments `$round` and calls the triage LLM; (3) parses
the JSON verdict and sets the exit accordingly. If `$round` exceeds
`$MAX_ROUNDS` it forces exit 0 (ANSWER) regardless of the verdict.

**Elements:** none (pure logic block).

**Exits:**

| Exit | Condition | Destination |
|---|---|---|
| 0 | `$round > $MAX_ROUNDS` OR `status == "chiaro"` | ANSWER (`b2a00005-rsn1-4a01-b005-000000000001`) |
| 1 | `status == "serve_ragionamento"` | REASON (`b2a00003-rsn1-4a01-b003-000000000001`) |
| 2 | `status == "servono_dati"` (or any fallback) | ASK (`b2a00004-rsn1-4a01-b004-000000000001`) |

**Exec:**

```php
if (!function_exists('estraiJson')) {
  function estraiJson($s){ $s=(string)$s; $start=strpos($s,'{'); if($start===false) return ""; $d=0;$i=$start;$len=strlen($s);$in=false;$e=false;
    for(;$i<$len;$i++){ $c=$s[$i];
      if($in){ if($e){$e=false;} elseif($c==='\\'){$e=true;} elseif($c==='"'){$in=false;} continue; }
      if($c==='"'){$in=true;} elseif($c==='{'){$d++;} elseif($c==='}'){$d--; if($d===0){$cand=substr($s,$start,$i-$start+1); return (json_decode($cand)!==null)?$cand:"";}} }
    return ""; }
}
// 1) harvest previous round's answers (if it asked for data), then clear slots
if ($triage != "") {
  $prev = json_decode(estraiJson($triage), true);
  if (is_array($prev) && ($prev['status'] ?? '') == 'servono_dati') {
    $miss = $prev['dati_mancanti'] ?? array();
    $idx = 1;
    foreach ($miss as $f) {
      $slot = 'clar'.$idx;
      $val = isset($$slot) ? $$slot : '';
      if ($val !== '' && $val !== null) $context .= "\nQ: ".($f['label'] ?? '')."\nA: ".$val;
      $idx++;
    }
  }
  for ($s = 1; $s <= $MAX_SLOTS; $s++) { $slot = 'clar'.$s; $$slot = ''; }
}
// 2) new triage call
$round = $round + 1;
$sys = "You are a triage classifier. Decide if a user question can be answered now, needs deeper internal reasoning, or needs more data from the user. ".$DOMINIO." Reply ONLY with valid JSON, no prose, in EXACTLY this shape: {\"status\":\"chiaro|serve_ragionamento|servono_dati\",\"ragionamento\":\"short internal note\",\"focus\":\"if serve_ragionamento: what to deepen\",\"dati_mancanti\":[{\"var\":\"slotN\",\"label\":\"question in the user's language\",\"tipo\":\"testo|testo_lungo|scelta\",\"opzioni\":[\"..\"],\"obbligatorio\":true}]}. Use at most ".$MAX_SLOTS." items in dati_mancanti. Write label/opzioni in language code: ".$lang.".";
wofoEnv->initAiAgent($sys);
$usr = "QUESTION:\n".$userQuestion."\n\nCONTEXT SO FAR:\n".$context;
wofoEnv->sendToAi($usr, '$triage', $PROV_TRIAGE);
// 3) parse + route
$t = json_decode(estraiJson($triage), true);
$status = (is_array($t) && isset($t['status'])) ? $t['status'] : 'servono_dati';
if ($round > $MAX_ROUNDS)                 { wofoEnv->setExit(0); }
elseif ($status == 'chiaro')              { wofoEnv->setExit(0); }
elseif ($status == 'serve_ragionamento')  { wofoEnv->setExit(1); }
else                                      { wofoEnv->setExit(2); }
```

---

### REASON — internal deepening

**Block ID:** `b2a00003-rsn1-4a01-b003-000000000001`

**Purpose:** Runs an internal analysis pass when the triage verdict is
`serve_ragionamento`. It reads the `focus` hint from the triage JSON, sends the
question plus accumulated context to the triage provider, and appends the
resulting analysis note to `$context` with an `[ANALYSIS]:` prefix. The user
never sees this output. Exits unconditionally back to TRIAGE.

**Elements:** none.

**Exits:**

| Exit | Condition | Destination |
|---|---|---|
| 0 | Always | TRIAGE (`b2a00002-rsn1-4a01-b002-000000000001`) |

**Exec:**

```php
if (!function_exists('estraiJson')) {
  function estraiJson($s){ $s=(string)$s; $start=strpos($s,'{'); if($start===false) return ""; $d=0;$i=$start;$len=strlen($s);$in=false;$e=false;
    for(;$i<$len;$i++){ $c=$s[$i];
      if($in){ if($e){$e=false;} elseif($c==='\\'){$e=true;} elseif($c==='"'){$in=false;} continue; }
      if($c==='"'){$in=true;} elseif($c==='{'){$d++;} elseif($c==='}'){$d--; if($d===0){$cand=substr($s,$start,$i-$start+1); return (json_decode($cand)!==null)?$cand:"";}} }
    return ""; }
}
$t = json_decode(estraiJson($triage), true);
$focus = (is_array($t) && isset($t['focus'])) ? $t['focus'] : '';
$sys = "You are an analyst. Think step by step to clarify the question's context. Produce a concise analysis (no final answer yet), surfacing assumptions and what would make the answer precise.";
wofoEnv->initAiAgent($sys);
$usr = "QUESTION:\n".$userQuestion."\n\nFOCUS:\n".$focus."\n\nCONTEXT SO FAR:\n".$context;
wofoEnv->sendToAi($usr, '$reasonOut', $PROV_TRIAGE);
$context = $context."\n[ANALYSIS]: ".$reasonOut;
wofoEnv->setExit(0);
```

---

### ASK — dynamic clarification fields

**Block ID:** `b2a00004-rsn1-4a01-b004-000000000001`

**Purpose:** Renders a dynamic form to collect the user data identified by the
triage. It reads `dati_mancanti` from the last triage JSON and emits one input
element per field using the appropriate `wofoEnv->create*` call (`testo`,
`testo_lungo`, `scelta`). A submit button (exit 0) pauses execution; when the
user submits, TRIAGE harvests the slot values on its next pass. The slot
variables are named `$clar1` through `$clarN` (fixed names; TRIAGE reads them
by index).

ASK does not itself read or accumulate any answers — that responsibility belongs
to TRIAGE at the top of the next pass, which is why the harvest logic lives
there.

**Elements:** none in the JSON (all inputs are created dynamically at runtime
via `wofoEnv->create*` calls in exec).

**Exits:**

| Exit | Condition | Destination |
|---|---|---|
| 0 | User submits | TRIAGE (`b2a00002-rsn1-4a01-b002-000000000001`) |

**Exec:**

```php
if (!function_exists('estraiJson')) {
  function estraiJson($s){ $s=(string)$s; $start=strpos($s,'{'); if($start===false) return ""; $d=0;$i=$start;$len=strlen($s);$in=false;$e=false;
    for(;$i<$len;$i++){ $c=$s[$i];
      if($in){ if($e){$e=false;} elseif($c==='\\'){$e=true;} elseif($c==='"'){$in=false;} continue; }
      if($c==='"'){$in=true;} elseif($c==='{'){$d++;} elseif($c==='}'){$d--; if($d===0){$cand=substr($s,$start,$i-$start+1); return (json_decode($cand)!==null)?$cand:"";}} }
    return ""; }
}
$t = json_decode(estraiJson($triage), true);
$fields = (is_array($t) && !empty($t['dati_mancanti'])) ? $t['dati_mancanti'] : array();
if (empty($fields)) {
  $fields = array(array('label' => ($lang=='IT' ? 'Puoi fornire qualche dettaglio in piu\'?' : 'Could you provide a bit more detail?'), 'tipo' => 'testo', 'obbligatorio' => true));
}
$idx = 1;
foreach ($fields as $f) {
  if ($idx > $MAX_SLOTS) break;
  $slot = '$clar'.$idx;
  $label = isset($f['label']) ? $f['label'] : '...';
  $tipo = isset($f['tipo']) ? $f['tipo'] : 'testo';
  $mand = !empty($f['obbligatorio']);
  if ($tipo == 'scelta' && !empty($f['opzioni'])) {
    wofoEnv->createSelect($slot, 'SE', $f['opzioni'], $mand);
  } elseif ($tipo == 'testo_lungo') {
    wofoEnv->createInputMultirow($slot, '', $label, $mand);
  } else {
    wofoEnv->createInputStandard($slot, '', $label, $mand);
  }
  $idx++;
}
wofoEnv->createButton('$ask_btn', '1', ($lang=='IT' ? 'Invia' : 'Send'), 0);
```

---

### ANSWER — final / best-effort

**Block ID:** `b2a00005-rsn1-4a01-b005-000000000001`

**Purpose:** Produces the final response. Calls the answer provider (Claude by
default) with the original question and all accumulated context. If the answer
string starts with `"Error"` or is empty the block substitutes a polite retry
message (English, following the project's error-message convention). The answer
is rendered by a MESSAGE element whose `label` is the literal string `$answer`
— the engine performs variable substitution before rendering, so the user sees
the LLM's reply. Exits to END.

**Elements:**

| Order | Type | Variable | Label (EN / IT) |
|---|---|---|---|
| 0 | MESSAGE | `$answer` | `$answer` / `$answer` |

**Exits:**

| Exit | Condition | Destination |
|---|---|---|
| 0 | Always | END (`0`) |

**Exec:**

```php
$sys = "You are a helpful general-purpose assistant. Answer the user's question clearly and concisely in language code ".$lang.". If the context is incomplete, give the best possible answer with the information available and briefly state the assumptions you made.";
wofoEnv->initAiAgent($sys);
$usr = "QUESTION:\n".$userQuestion."\n\nCONTEXT:\n".$context;
wofoEnv->sendToAi($usr, '$answer', $PROV_ANSWER);
if (substr((string)$answer, 0, 5) === "Error" || trim((string)$answer) === "") {
  $answer = "Sorry, something went wrong producing the answer. Please try again.";
}
wofoEnv->setExit(0);
```

---

## 6. Edge Cases

**Unparsable or empty triage response**

If `estraiJson` finds no balanced JSON object, or `json_decode` returns null,
`$status` defaults to `'servono_dati'`. ASK then fires its own empty-field
fallback (see below). The loop never blocks on a bad LLM reply.

**`servono_dati` with empty `dati_mancanti`**

If the triage JSON has `"dati_mancanti": []` (or the key is absent), ASK falls
back to a single generic text field: "Could you provide a bit more detail?" (EN)
/ "Puoi fornire qualche dettaglio in piu'?" (IT), mandatory.

**Non-convergence / infinite loop**

`$round` increments on every TRIAGE pass — including passes triggered by REASON.
When `$round > $MAX_ROUNDS` TRIAGE forces exit 0 (ANSWER) unconditionally,
regardless of the verdict. ANSWER instructs the model to give the best possible
answer with available context and state any assumptions. The engine's own
256-iteration cap provides a secondary safety net.

**Provider error or empty answer**

ANSWER checks `substr((string)$answer, 0, 5) === "Error"` and
`trim((string)$answer) === ""`. Either condition replaces `$answer` with the
English polite retry message: "Sorry, something went wrong producing the answer.
Please try again." This follows the project convention that error messages stay
in English regardless of `$lang`.

**Slot variable cleanup between rounds**

At the top of each TRIAGE pass, after harvesting, a `for` loop explicitly resets
`$clar1` through `$clar{$MAX_SLOTS}` to empty strings. This prevents stale
answers from a previous ASK round from leaking into subsequent rounds.

**Language of prompts vs. user-facing labels**

System prompts sent to the LLM are in English (instruction convention). The
`$lang` session variable is injected into the triage system prompt so the model
writes `label` and `opzioni` values in the user's language. The final answer is
also instructed to use `$lang`.

---

## 7. Idioms and Notes

**`wofoEnv->` without `$`**

In stored block exec code the environment accessor is written as `wofoEnv->`
(no leading `$`). The engine's sandbox injects the object under this bare name
at runtime. Do not add a `$` prefix or the exec will produce a parse error.

**Session variables are plain PHP variables**

Within a block exec, session-persisted variables (`$round`, `$context`,
`$triage`, `$userQuestion`, `$answer`, `$clar1`…`$clarN`) are accessed as
ordinary PHP variables — not via `wofoEnv->getVar()`. The engine synchronises
them to the session store across blocks automatically.

**Fixed slot names for clarification fields**

Clarification inputs use a fixed naming scheme: `$clar1`, `$clar2`, …
`$clarN` (up to `$MAX_SLOTS`). The LLM-provided `label`, `tipo`, and `opzioni`
drive the widget rendered by ASK, but TRIAGE always reads the answers from
these fixed-index variables, not from the LLM-provided `var` name. This is why
`var` values in `dati_mancanti` (`"slotN"`) are informational only in the
current implementation — TRIAGE uses positional indexing (`clar1`, `clar2`, …).

**MESSAGE element renders its `label` with variable substitution**

A MESSAGE element (c_type 0 / d_type MESSAGE) displays its `label` string as
defined in the JSON. When the label is `$answer` (as in ANSWER's element), the
engine substitutes the current value of `$answer` before rendering, so the user
sees the actual LLM reply. This is why the ANSWER block does not call
`wofoEnv->createLabel()` in exec — the substituted MESSAGE element in the block
definition handles display.

**`estraiJson` helper**

Every block that parses JSON defines the same `estraiJson` function inside a
`function_exists` guard. This balanced-brace extractor handles responses that
contain surrounding prose or markdown fences. It returns the first well-formed
JSON object found, or an empty string if none is found — which causes
`json_decode` to return null, triggering the safe fallback in each block.

**`exit_dir: "0"` means END**

In the Flussu JSON spec, `"exit_dir": "0"` (the string zero) signals the engine
to terminate the workflow session. Only ANSWER uses this value.
