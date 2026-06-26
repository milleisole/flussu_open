# Reasoning / Clarification-Loop Workflow — Design Spec

**Date:** 2026-06-26
**Status:** Approved (design), pending implementation plan
**Type:** Reference Flussu workflow (no engine changes)

## 1. Purpose

Provide a reusable Flussu **reference workflow** that reproduces the "modern
chatbot" behaviour: before answering a user's question (asked inside a Flussu
**form**), the workflow first *analyses/triages* it. Then:

- if the question is **clear** → send to the LLM and return the answer;
- if it needs **more internal reasoning** → loop an extra LLM analysis pass to
  enrich the context, then re-triage;
- if it needs **more data from the user** → emit a package of dynamic input
  fields to the form, collect the answers, then re-triage.

The reasoning is **internal** (the user only sees the final answer or the
clarifying questions). A bounded number of rounds guarantees termination with a
best-effort answer.

## 2. Constraints & decisions (locked)

- **Deliverable:** reference workflow built with **existing engine primitives
  only** — no PHP engine changes. Two artifacts: a documented blueprint + an
  importable flow JSON.
- **Triage:** an **LLM call with structured JSON output**; the flow parses the
  JSON and branches.
- **Reasoning visibility:** **internal** (never shown to the user).
- **Loop control:** **max rounds + best-effort answer** (counter; when exceeded,
  force the answer with whatever context exists).
- **Clarification UI:** **dynamic structured fields** — one input generated per
  missing datum listed by the triage.
- **Architecture:** **Approach A — "triage hub" with self-loop** (5 blocks).

## 3. Engine primitives used (reference)

From `Environment.php` / `Executor.php` / `Worker.php` / `Engine.php`:

- **LLM call:** `wofoEnv->sendToAi($prompt, '$outVar', $providerId)` — provider
  id is the `Platform` enum int (0=ChatGPT, 2=Gemini, 3/4=Claude, 99=modello
  flussu, …). Result stored in `$outVar`; token usage in `$INFO`.
- **Form inputs (blocking):** `createInputStandard($var,$val,$label,$mandatory)`,
  `createInputMultirow(...)`, `createSelect($var,$type,$values,$mandatory)`,
  `createButton($label,$exitNum)`, `createLabel($text)`.
- **Branching:** `wofoEnv->setExit($n)` selects exit `$n`; exits are graph edges
  (`exits[n].exit_dir` → destination block UUID).
- **Loops:** done via graph edges (a block's exit pointing back to an earlier
  block). Engine safety cap: 256 iterations per turn.
- **Session vars:** read with `wofoEnv->getVar('$x')`, written by direct
  assignment / `sendToAi` out-var; persisted across form request/response turns.

> Implementation-time verifications (low risk): (a) the `Environment` sandbox
> allows `json_decode`/string functions (the `medical-research` flow already
> uses heavy PHP, so almost certain); (b) exact `createButton` signature and
> `exit_num` mapping.

## 4. Architecture — block graph (Approach A)

```
        ┌─────────┐
        │  START  │  input: $userQuestion · init $round=0, $context="", $triage=""
        └────┬────┘
             │ exit0
        ┌────▼─────┐
   ┌───►│  TRIAGE  │  (harvest prev answers) → sendToAi(triage) → $triage(JSON); $round++
   │    └──┬──┬──┬─┘
   │  exit0│  │  │exit2
   │       │  │exit1
   │  ┌────▼┐ │  └────────┐
   │  │ANSWER│ │      ┌────▼───┐
   │  └──┬───┘ │      │  ASK   │  dynamic inputs · pause form
   │     │  ┌──▼────┐ └────┬───┘
   │     │  │REASON │      │ exit0
   │     │  └──┬────┘      │
   │     │     │ exit0     │
   │     │     └───────────┴──► back to TRIAGE
   │  ┌──▼──┐
   └──┤ END │
      └─────┘
```

**TRIAGE routing (priority order):**
1. `$round > MAX_ROUNDS` → exit0 → ANSWER (best-effort)
2. `status == "chiaro"` → exit0 → ANSWER
3. `status == "serve_ragionamento"` → exit1 → REASON
4. `status == "servono_dati"` (or any fallback) → exit2 → ASK

REASON.exit0 → TRIAGE; ASK.exit0 → TRIAGE. The counter increments on **every**
TRIAGE pass, so non-converging reasoning is also bounded.

## 5. Triage JSON contract

The triage LLM must reply with **only** this JSON:

```json
{
  "status": "chiaro | serve_ragionamento | servono_dati",
  "ragionamento": "internal note on why (never shown to the user)",
  "focus": "if serve_ragionamento: what to deepen next",
  "dati_mancanti": [
    {
      "var": "variable_name",
      "label": "question to show the user",
      "tipo": "testo | testo_lungo | scelta",
      "opzioni": ["A", "B", "C"],
      "obbligatorio": true
    }
  ]
}
```

- `status` drives routing.
- `ragionamento` is internal; goes to context/log, never to the form.
- `focus` used only by REASON.
- `dati_mancanti[]` used only by ASK; each item → one input:
  - `testo` → `createInputStandard`
  - `testo_lungo` → `createInputMultirow`
  - `scelta` → `createSelect` (with `opzioni`)

**Robust parsing:** extract the first balanced `{...}` block (same technique as
`AiChatController::extractFlussuJson`), tolerating ```` ```json ```` fences or
surrounding prose. On decode failure / unknown `status` / empty
`dati_mancanti` → **safe fallback**: treat as `servono_dati` with a single
generic text field ("Can you provide a bit more detail?").

**Configurable DOMAIN section:** `PROMPT_TRIAGE` embeds a flow-author-provided
`DOMINIO` block describing what makes a question "complete" in the target use
case. Without it the triage has no notion of "missing data".

Prompts (`PROMPT_TRIAGE`, `PROMPT_REASON`, `PROMPT_ANSWER`) are in **English**
(instruction convention); `label`/`opzioni`/final answer in the user language
(`$lang`).

## 6. Session variables

| Var | Role |
|---|---|
| `$userQuestion` | initial user question |
| `$round` | round counter (best-effort cap) |
| `$context` | accumulated context: user answers + reasoning notes |
| `$triage` | last raw triage JSON string |
| `$answer` | final answer |

Constants at top of START: `MAX_ROUNDS` (e.g. 3), `PROV_TRIAGE` /
`PROV_ANSWER` (provider ids — e.g. triage/reason on the cheap *modello flussu*
= 99, final answer on a stronger provider; both configurable), `DOMINIO`, and
the three prompt strings.

**Answer accumulation timing:** a Flussu block with inputs renders and pauses;
on submit the vars are assigned and execution moves to the exit destination —
so ASK cannot accumulate its own answers. Therefore **TRIAGE harvests** the
previous round's answers at its top (if the previous `$triage.status` was
`servono_dati`), keeping the graph at 5 blocks.

## 7. Block exec sketches (pseudo wofoEnv)

**START** — render `$userQuestion` input + submit button; init `$round=0`,
`$context=""`, `$triage=""`, define constants. exit0 → TRIAGE.

**TRIAGE**
```php
// 1) harvest previous round answers (if it had asked for data)
if ($triage != "") {
  $prev = json_decode(estraiJson($triage), true);
  if (($prev['status'] ?? '') == 'servono_dati') {
    foreach (($prev['dati_mancanti'] ?? []) as $f) {
      $v = wofoEnv->getVar('$'.$f['var']);
      if ($v != "") $context .= "\nD: ".$f['label']."\nR: ".$v;
    }
  }
}
// 2) new triage
$round = $round + 1;
$prompt = PROMPT_TRIAGE." DOMINIO: ".DOMINIO."\nLINGUA: ".$lang
        ."\nDOMANDA: ".$userQuestion."\nCONTESTO:\n".$context;
wofoEnv->sendToAi($prompt, '$triage', PROV_TRIAGE);
$t = json_decode(estraiJson($triage), true);
$status = $t['status'] ?? 'servono_dati'; // safe fallback
// 3) route
if ($round > MAX_ROUNDS)                 wofoEnv->setExit(0);
elseif ($status=='chiaro')               wofoEnv->setExit(0);
elseif ($status=='serve_ragionamento')   wofoEnv->setExit(1);
else                                     wofoEnv->setExit(2);
```

**REASON**
```php
$t = json_decode(estraiJson($triage), true);
$prompt = PROMPT_REASON."\nFOCUS: ".($t['focus'] ?? '')
        ."\nDOMANDA: ".$userQuestion."\nCONTESTO:\n".$context;
wofoEnv->sendToAi($prompt, '$reasonOut', PROV_TRIAGE);
$context .= "\n[ANALISI]: ".$reasonOut;
wofoEnv->setExit(0); // → TRIAGE
```

**ASK**
```php
$t = json_decode(estraiJson($triage), true);
$fields = $t['dati_mancanti'] ?? [];
if (empty($fields)) $fields = [['var'=>'dettaglio','label'=>'Puoi fornire qualche dettaglio in più?','tipo'=>'testo','obbligatorio'=>true]];
foreach ($fields as $f) {
  $tipo = $f['tipo'] ?? 'testo';
  if ($tipo=='scelta')        wofoEnv->createSelect('$'.$f['var'], 'SE', $f['opzioni'] ?? [], $f['obbligatorio'] ?? false);
  elseif ($tipo=='testo_lungo') wofoEnv->createInputMultirow('$'.$f['var'], "", $f['label'], $f['obbligatorio'] ?? false);
  else                        wofoEnv->createInputStandard('$'.$f['var'], "", $f['label'], $f['obbligatorio'] ?? false);
}
wofoEnv->createButton("Invia", 0); // exit0 → TRIAGE on submit
```

**ANSWER**
```php
$prompt = PROMPT_ANSWER."\nLINGUA: ".$lang."\nDOMANDA: ".$userQuestion
        ."\nCONTESTO:\n".$context;
wofoEnv->sendToAi($prompt, '$answer', PROV_ANSWER);
if (substr((string)$answer,0,5) === "Error")
  $answer = "Sorry, something went wrong producing the answer. Please try again.";
wofoEnv->createLabel($answer);
wofoEnv->setExit(0); // → END
```

`estraiJson()` = inline balanced-brace JSON extractor included in the blueprint.

## 8. Error handling & edge cases

- **Unparsable triage / `sendToAi` error string** → fallback `servono_dati` with
  one generic text field. Never blocks.
- **`servono_dati` with empty `dati_mancanti`** → same single-field fallback.
- **Non-convergence** → `$round` increments every TRIAGE pass → `MAX_ROUNDS`
  forces best-effort answer.
- **Best-effort** → `PROMPT_ANSWER` instructs the model to answer as best it can
  with available context, stating assumptions.
- **Provider/LLM down** → ANSWER guards `$answer` starting with `"Error"` and
  shows a polite retry message (English, error convention).
- **Language** → system prompts EN; user-facing labels/options/answer in `$lang`.
- **Context growth** → bounded by `MAX_ROUNDS`; engine 256-iteration cap as net.

## 9. Deliverables

1. **Blueprint** (`Docs/.../reasoning-loop-blueprint.md`): block-by-block —
   purpose, full `exec` code, elements, exit wiring, the JSON contract, the four
   configurable prompts + constants, edge cases, import instructions.
2. **Importable flow** (`workflows/reasoning-loop/reasoning_loop_workflow.json`):
   spec-format (like `workflows/medical-research/...`) with the 5 blocks,
   elements, exits and UUIDs; importable via `C=RD` / designer import.

## 10. Out of scope

- No engine/`Environment`/`Executor` code changes.
- No new AI provider work (uses existing `sendToAi` + Platform ids).
- No streaming / visible reasoning UI.
- No persistence of reasoning beyond the session.

## 11. Open verifications (carry into the plan)

- Confirm `json_decode` + string fns available in the block sandbox.
- Confirm `createButton` signature and `exit_num` → exit mapping.
- Confirm exact JSON import envelope expected by `C=RD` against
  `Docs/FLUSSU_JSON_WORKFLOW_SPEC.md` and the `medical-research` sample.
