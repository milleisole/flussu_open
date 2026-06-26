# Reasoning / Clarification-Loop Workflow — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Flussu reference workflow that triages a user's question with an LLM (structured JSON verdict), then either answers, loops an internal LLM reasoning pass, or asks the user for missing data — bounded by a round cap with a best-effort fallback.

**Architecture:** Approach A — a 5-block "triage hub" graph (START → TRIAGE → {ANSWER | REASON | ASK}; REASON/ASK loop back to TRIAGE). No engine changes: built entirely with existing block `exec` code (run via `eval`) and `wofoEnv` commands. Delivered as a documented blueprint + an importable flow JSON.

**Tech Stack:** Flussu workflow engine (PHP `eval` block exec, `wofoEnv` Environment API), JSON flow import format (`Docs/FLUSSU_JSON_WORKFLOW_SPEC.md`), provider ids via `Platform` enum.

---

## Verified engine facts (from code, not assumptions)

- Block `exec` runs via `@eval(...)` (`Worker.php:1098`) → **full PHP** available (`json_decode`, `foreach`, variable-variables).
- The environment object is **`wofoEnv`** written WITHOUT `$` in the JSON exec; the engine adds `$` (`Worker.php:1448-1449`; spec doc line 371). Normal PHP vars keep their `$`.
- Wrapper injects all session vars before eval (`// workflow vars`, `Worker.php:1046,1078`) and re-reads statically-named `$vars` after eval to persist them (`Worker.php:1139-1190`). ⇒ persist a value by naming its `$var` literally in the code.
- Confirmed `Environment` methods (`Environment.php`): `init()` (92), `setExit($n)` (135), `sendToAi($text,'$out',$provider=0,$allowFile=true)` (216), `initAiAgent($systemPrompt)` (215), `createInputStandard($var,$val,$label,$mand=false,$css="")` (273), `createInputMultirow(...)` (275), `createSelect($var,$selectType,$values,$mand=false,$css="")` (276), `createButton($var,$clickValue,$text,$exit=0,$css="",$skipValidation=false)` (271), `createLabel($text)` (272). **There is no `getVar`/`setVar`** — use direct PHP vars + variable-variables.
- START block must contain `wofoEnv->init();` (spec doc line 944).
- Provider ids: 0=ChatGPT, 2=Gemini, 4=Claude, 99=modello flussu (cheap). The 3rd `sendToAi` arg may be a PHP variable.
- Loop within one request: REASON/ASK are reached only from TRIAGE; TRIAGE→REASON→TRIAGE runs in a single request (no pause), so any `function` declared in exec must be guarded with `function_exists()` to avoid redeclare fatals.

## Design refinements locked here (update vs spec §5/§7)

1. **Clarification fields use FIXED variable slots** `$clar1..$clar6` (constant `MAX_SLOTS=6`). The LLM still controls how many fields, their labels, types and options — only the underlying var *names* are fixed. This makes harvesting reliable (static vars are always injected) instead of depending on dynamic var-name injection.
2. **`createButton` correct signature** is used (6 args).
3. **System prompts** are set via `wofoEnv->initAiAgent(...)` (not concatenated into the user message).
4. Slots are **cleared** after harvest to avoid stale carryover when a later round asks fewer fields.

## File structure

- Create: `workflows/reasoning-loop/reasoning_loop_workflow.json` — the importable flow (5 blocks).
- Create: `workflows/reasoning-loop/blueprint.md` — block-by-block documentation (purpose, exec, elements, wiring, prompts, edge cases, import instructions).
- Create (test helper, removed after): `src/Flussu/Test/test_reasoning_estraijson.php` — standalone assert script for the `estraiJson` extractor used inside the exec.
- Reference only (do not modify): `Docs/FLUSSU_JSON_WORKFLOW_SPEC.md`, `workflows/medical-research/medical_research_workflow.json`.

Shared constants/UUIDs used across tasks:
- Block UUIDs: START `b2a00001-rsn1-4a01-b001-000000000001`, TRIAGE `b2a00002-rsn1-4a01-b002-000000000001`, REASON `b2a00003-rsn1-4a01-b003-000000000001`, ASK `b2a00004-rsn1-4a01-b004-000000000001`, ANSWER `b2a00005-rsn1-4a01-b005-000000000001`.
- TRIAGE exits: `0`→ANSWER, `1`→REASON, `2`→ASK.

---

## Task 1: `estraiJson` balanced-brace extractor (TDD)

Robustly pull the first balanced `{...}` JSON object out of an LLM reply that may include ```` ```json ```` fences or surrounding prose. Same idea as `AiChatController::extractFlussuJson`, but self-contained for use inside block exec.

**Files:**
- Test: `src/Flussu/Test/test_reasoning_estraijson.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// src/Flussu/Test/test_reasoning_estraijson.php
// Standalone test for the estraiJson() helper embedded in the reasoning-loop blocks.

function estraiJson($s) {
    // IMPLEMENTED IN STEP 3
    return "";
}

function check($name, $cond) {
    echo ($cond ? "PASS" : "FAIL") . " - $name\n";
    if (!$cond) { $GLOBALS['failed'] = true; }
}

$plain = '{"status":"chiaro"}';
check("plain object", estraiJson($plain) === '{"status":"chiaro"}');

$fenced = "ecco:\n```json\n{\"status\":\"servono_dati\",\"dati_mancanti\":[]}\n```\ngrazie";
check("fenced + prose", json_decode(estraiJson($fenced), true)['status'] === 'servono_dati');

$nested = 'noise {"a":{"b":[1,2]},"status":"x"} tail';
check("nested braces", json_decode(estraiJson($nested), true)['a']['b'][1] === 2);

$none = 'no json here';
check("no json -> empty", estraiJson($none) === "");

echo (empty($GLOBALS['failed']) ? "ALL PASS\n" : "SOME FAILED\n");
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php src/Flussu/Test/test_reasoning_estraijson.php 2>&1 | grep -v "imagick\|Xdebug"`
Expected: several `FAIL` lines and `SOME FAILED`.

- [ ] **Step 3: Implement `estraiJson` (replace the stub body)**

```php
function estraiJson($s) {
    $s = (string)$s;
    $start = strpos($s, '{');
    if ($start === false) return "";
    $depth = 0; $i = $start; $len = strlen($s); $inStr = false; $esc = false;
    for (; $i < $len; $i++) {
        $ch = $s[$i];
        if ($inStr) {
            if ($esc) { $esc = false; }
            elseif ($ch === '\\') { $esc = true; }
            elseif ($ch === '"') { $inStr = false; }
            continue;
        }
        if ($ch === '"') { $inStr = true; }
        elseif ($ch === '{') { $depth++; }
        elseif ($ch === '}') { $depth--; if ($depth === 0) { $cand = substr($s, $start, $i - $start + 1); return (json_decode($cand) !== null) ? $cand : ""; } }
    }
    return "";
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php src/Flussu/Test/test_reasoning_estraijson.php 2>&1 | grep -v "imagick\|Xdebug"`
Expected: all `PASS` and `ALL PASS`.

- [ ] **Step 5: Commit**

```bash
git add src/Flussu/Test/test_reasoning_estraijson.php
git commit -m "test - reasoning-loop: estraiJson balanced-brace extractor"
```

---

## Task 2: Scaffold the flow JSON (top-level + empty blocks)

**Files:**
- Create: `workflows/reasoning-loop/reasoning_loop_workflow.json`

- [ ] **Step 1: Write the scaffold**

```json
{
  "workflow": [
    {
      "name": "Reasoning / Clarification Loop",
      "description": "Triages a user question with an LLM (JSON verdict), then answers, reasons more, or asks the user for missing data. Bounded rounds with best-effort fallback. Domain: generic assistant.",
      "lang": "EN",
      "supp_langs": "EN,IT",
      "is_active": 1,
      "userId": "1",
      "valid_from": "1899-12-31 23:59:59",
      "valid_until": "2100-01-01 00:00:00",
      "svc1": "",
      "svc2": "",
      "svc3": "",
      "start_block_id": "b2a00001-rsn1-4a01-b001-000000000001",
      "blocks": []
    }
  ]
}
```

- [ ] **Step 2: Validate JSON**

Run: `php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); echo json_last_error()===JSON_ERROR_NONE?"OK\n":"ERR: ".json_last_error_msg()."\n";' 2>&1 | grep -v "imagick\|Xdebug"`
Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: flow scaffold (top-level + start_block_id)"
```

---

## Task 3: START block (collect question + init config)

Renders the question input + submit button, and initialises all config/state. START exec runs on entry; its statically-named vars persist.

**Files:**
- Modify: `workflows/reasoning-loop/reasoning_loop_workflow.json` (append block to `blocks[]`)

- [ ] **Step 1: Append the START block object to `blocks[]`**

```json
{
  "block_id": "b2a00001-rsn1-4a01-b001-000000000001",
  "type": "",
  "description": "START - Question + config",
  "is_start": 1,
  "exec": "<?php\nwofoEnv->init();\n$round = 0;\n$context = \"\";\n$triage = \"\";\n$MAX_ROUNDS = 3;\n$MAX_SLOTS = 6;\n$PROV_TRIAGE = 99;\n$PROV_ANSWER = 4;\n$DOMINIO = \"You are a general-purpose assistant. A question is COMPLETE when it can be answered accurately without guessing the user's intent, scope, constraints or any essential specifics. Otherwise essential specifics are missing.\";\nwofoEnv->setExit(0);\n?>",
  "x_pos": 80,
  "y_pos": 80,
  "note": "",
  "error": "",
  "elements": [
    {
      "elem_id": "e2a00001-rsn1-4a01-e001-000000000001",
      "var_name": "",
      "c_type": "0",
      "d_type": "MESSAGE",
      "e_order": "0",
      "exit_num": "",
      "css": {},
      "note": "",
      "langs": { "EN": { "label": "Ask me anything:", "uri": "" }, "IT": { "label": "Fammi una domanda:", "uri": "" } }
    },
    {
      "elem_id": "e2a00001-rsn1-4a01-e002-000000000001",
      "var_name": "$userQuestion",
      "c_type": "1",
      "d_type": "INPUT",
      "e_order": "1",
      "exit_num": "",
      "css": {},
      "note": "",
      "langs": { "EN": { "label": "Type your question...", "uri": "" }, "IT": { "label": "Scrivi la tua domanda...", "uri": "" } }
    },
    {
      "elem_id": "e2a00001-rsn1-4a01-e003-000000000001",
      "var_name": "$start_btn",
      "c_type": "2",
      "d_type": "BUTTON",
      "e_order": "2",
      "exit_num": "0",
      "css": {},
      "note": "",
      "langs": { "EN": { "label": "Send", "uri": "" }, "IT": { "label": "Invia", "uri": "" } }
    }
  ],
  "exits": [
    { "exit_num": 0, "exit_dir": "b2a00002-rsn1-4a01-b002-000000000001" }
  ]
}
```

- [ ] **Step 2: Validate JSON + lint the START exec**

Run:
```bash
php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); $b=$d["workflow"][0]["blocks"][0]; file_put_contents("/tmp/exec_start.php", str_replace("wofoEnv->","$wofoEnv->",$b["exec"])); echo "json:".(json_last_error()===JSON_ERROR_NONE?"OK":"ERR")."\n";' 2>&1 | grep -v "imagick\|Xdebug"
php -l /tmp/exec_start.php 2>&1 | grep -v "imagick\|Xdebug"
```
Expected: `json:OK` and `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: START block (question + config)"
```

---

## Task 4: TRIAGE block (harvest → LLM verdict → route)

The hub. Harvests the previous round's answers from fixed slots, runs the triage LLM call, parses the JSON, increments the round counter, and routes.

**Files:**
- Modify: `workflows/reasoning-loop/reasoning_loop_workflow.json` (append block)

- [ ] **Step 1: Append the TRIAGE block object to `blocks[]`**

`exec` (shown unescaped for readability — escape newlines as `\n` and quotes as `\"` when embedding in JSON):

```php
<?php
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
?>
```

JSON block object (the `exec` value must be the above with `\n`/`\"` escaping; no static elements; three exits):

```json
{
  "block_id": "b2a00002-rsn1-4a01-b002-000000000001",
  "type": "",
  "description": "TRIAGE - harvest, classify, route",
  "is_start": 0,
  "exec": "<?php ...escaped exec from Step 1... ?>",
  "x_pos": 320,
  "y_pos": 80,
  "note": "",
  "error": "",
  "elements": [],
  "exits": [
    { "exit_num": 0, "exit_dir": "b2a00005-rsn1-4a01-b005-000000000001" },
    { "exit_num": 1, "exit_dir": "b2a00003-rsn1-4a01-b003-000000000001" },
    { "exit_num": 2, "exit_dir": "b2a00004-rsn1-4a01-b004-000000000001" }
  ]
}
```

- [ ] **Step 2: Validate JSON + lint the TRIAGE exec**

Run:
```bash
php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); foreach($d["workflow"][0]["blocks"] as $b){ if($b["block_id"]==="b2a00002-rsn1-4a01-b002-000000000001"){ file_put_contents("/tmp/exec_triage.php", str_replace("wofoEnv->","$wofoEnv->",$b["exec"])); }} echo "json:".(json_last_error()===JSON_ERROR_NONE?"OK":"ERR")."\n";' 2>&1 | grep -v "imagick\|Xdebug"
php -l /tmp/exec_triage.php 2>&1 | grep -v "imagick\|Xdebug"
```
Expected: `json:OK` and `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: TRIAGE block (harvest + classify + route)"
```

---

## Task 5: REASON block (internal deepening → back to TRIAGE)

**Files:**
- Modify: `workflows/reasoning-loop/reasoning_loop_workflow.json` (append block)

- [ ] **Step 1: Append the REASON block**

`exec` (unescaped):

```php
<?php
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
?>
```

JSON block object (escape exec; single exit back to TRIAGE; no elements):

```json
{
  "block_id": "b2a00003-rsn1-4a01-b003-000000000001",
  "type": "",
  "description": "REASON - internal deepening",
  "is_start": 0,
  "exec": "<?php ...escaped exec... ?>",
  "x_pos": 560,
  "y_pos": 20,
  "note": "",
  "error": "",
  "elements": [],
  "exits": [
    { "exit_num": 0, "exit_dir": "b2a00002-rsn1-4a01-b002-000000000001" }
  ]
}
```

- [ ] **Step 2: Validate JSON + lint the REASON exec**

Run:
```bash
php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); foreach($d["workflow"][0]["blocks"] as $b){ if($b["block_id"]==="b2a00003-rsn1-4a01-b003-000000000001"){ file_put_contents("/tmp/exec_reason.php", str_replace("wofoEnv->","$wofoEnv->",$b["exec"])); }} echo "json:".(json_last_error()===JSON_ERROR_NONE?"OK":"ERR")."\n";' 2>&1 | grep -v "imagick\|Xdebug"
php -l /tmp/exec_reason.php 2>&1 | grep -v "imagick\|Xdebug"
```
Expected: `json:OK` and `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: REASON block (internal deepening)"
```

---

## Task 6: ASK block (dynamic clarification fields → back to TRIAGE)

Generates one input per `dati_mancanti` item into fixed slots `$clar1..$clarN`, plus a submit button, then pauses. On submit the engine assigns the slot vars and routes to TRIAGE (which harvests them).

**Files:**
- Modify: `workflows/reasoning-loop/reasoning_loop_workflow.json` (append block)

- [ ] **Step 1: Append the ASK block**

`exec` (unescaped):

```php
<?php
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
?>
```

JSON block object (escape exec; single exit back to TRIAGE; no static elements — all created in exec):

```json
{
  "block_id": "b2a00004-rsn1-4a01-b004-000000000001",
  "type": "",
  "description": "ASK - dynamic clarification fields",
  "is_start": 0,
  "exec": "<?php ...escaped exec... ?>",
  "x_pos": 560,
  "y_pos": 160,
  "note": "",
  "error": "",
  "elements": [],
  "exits": [
    { "exit_num": 0, "exit_dir": "b2a00002-rsn1-4a01-b002-000000000001" }
  ]
}
```

- [ ] **Step 2: Validate JSON + lint the ASK exec**

Run:
```bash
php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); foreach($d["workflow"][0]["blocks"] as $b){ if($b["block_id"]==="b2a00004-rsn1-4a01-b004-000000000001"){ file_put_contents("/tmp/exec_ask.php", str_replace("wofoEnv->","$wofoEnv->",$b["exec"])); }} echo "json:".(json_last_error()===JSON_ERROR_NONE?"OK":"ERR")."\n";' 2>&1 | grep -v "imagick\|Xdebug"
php -l /tmp/exec_ask.php 2>&1 | grep -v "imagick\|Xdebug"
```
Expected: `json:OK` and `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: ASK block (dynamic clarification fields)"
```

---

## Task 7: ANSWER block (final / best-effort answer → END)

**Files:**
- Modify: `workflows/reasoning-loop/reasoning_loop_workflow.json` (append block)

- [ ] **Step 1: Append the ANSWER block**

`exec` (unescaped):

```php
<?php
$sys = "You are a helpful general-purpose assistant. Answer the user's question clearly and concisely in language code ".$lang.". If the context is incomplete, give the best possible answer with the information available and briefly state the assumptions you made.";
wofoEnv->initAiAgent($sys);
$usr = "QUESTION:\n".$userQuestion."\n\nCONTEXT:\n".$context;
wofoEnv->sendToAi($usr, '$answer', $PROV_ANSWER);
if (substr((string)$answer, 0, 5) === "Error" || trim((string)$answer) === "") {
  $answer = "Sorry, something went wrong producing the answer. Please try again.";
}
wofoEnv->setExit(0);
?>
```

JSON block object (escape exec; a MESSAGE element that DISPLAYS `$answer` — the engine renders a MESSAGE by substituting variables into its `label`, so the label MUST be `"$answer"` (var_name alone does not render); exit `0` ends the flow with `exit_dir` `"0"`):

```json
{
  "block_id": "b2a00005-rsn1-4a01-b005-000000000001",
  "type": "",
  "description": "ANSWER - final / best-effort",
  "is_start": 0,
  "exec": "<?php ...escaped exec... ?>",
  "x_pos": 320,
  "y_pos": 260,
  "note": "",
  "error": "",
  "elements": [
    {
      "elem_id": "e2a00005-rsn1-4a01-e001-000000000001",
      "var_name": "$answer",
      "c_type": "0",
      "d_type": "MESSAGE",
      "e_order": "0",
      "exit_num": "",
      "css": {},
      "note": "",
      "langs": { "EN": { "label": "$answer", "uri": "" }, "IT": { "label": "$answer", "uri": "" } }
    }
  ],
  "exits": [
    { "exit_num": 0, "exit_dir": "0" }
  ]
}
```

- [ ] **Step 2: Validate JSON + lint the ANSWER exec**

Run:
```bash
php -r '$d=json_decode(file_get_contents("workflows/reasoning-loop/reasoning_loop_workflow.json"),true); foreach($d["workflow"][0]["blocks"] as $b){ if($b["block_id"]==="b2a00005-rsn1-4a01-b005-000000000001"){ file_put_contents("/tmp/exec_answer.php", str_replace("wofoEnv->","$wofoEnv->",$b["exec"])); }} echo "json:".(json_last_error()===JSON_ERROR_NONE?"OK":"ERR")."\n";' 2>&1 | grep -v "imagick\|Xdebug"
php -l /tmp/exec_answer.php 2>&1 | grep -v "imagick\|Xdebug"
```
Expected: `json:OK` and `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/reasoning_loop_workflow.json
git commit -m "feat - reasoning-loop: ANSWER block (final/best-effort) + END"
```

---

## Task 8: Whole-flow structural validation

Verify the 5 blocks form a consistent graph: start block exists, every non-zero `exit_dir` resolves to a real block, block UUIDs unique, every exec lints.

**Files:**
- Create (temporary): `src/Flussu/Test/test_reasoning_flow_struct.php`

- [ ] **Step 1: Write the structural validator**

```php
<?php
// src/Flussu/Test/test_reasoning_flow_struct.php
$path = __DIR__ . "/../../../workflows/reasoning-loop/reasoning_loop_workflow.json";
$d = json_decode(file_get_contents($path), true);
$fail = false;
function chk($n,$c){ global $fail; echo ($c?"PASS":"FAIL")." - $n\n"; if(!$c)$fail=true; }

chk("json valid", json_last_error()===JSON_ERROR_NONE);
$wf = $d["workflow"][0];
$blocks = $wf["blocks"];
chk("5 blocks", count($blocks)===5);
$ids = array(); foreach($blocks as $b){ $ids[$b["block_id"]]=true; }
chk("unique ids", count($ids)===5);
chk("start exists", isset($ids[$wf["start_block_id"]]));
$starts = 0; foreach($blocks as $b){ if(!empty($b["is_start"]))$starts++; }
chk("exactly one is_start", $starts===1);
foreach($blocks as $b){
  foreach($b["exits"] as $ex){
    $dir = (string)$ex["exit_dir"];
    chk("exit of ".$b["block_id"]." -> ".$dir, $dir==="0" || isset($ids[$dir]));
  }
  // lint exec
  $tmp = tempnam(sys_get_temp_dir(),"ex").".php";
  file_put_contents($tmp, str_replace("wofoEnv->","\$wofoEnv->", $b["exec"]));
  $out = shell_exec("php -l ".escapeshellarg($tmp)." 2>&1");
  chk("exec lints: ".$b["description"], strpos($out,"No syntax errors")!==false);
  @unlink($tmp);
}
echo ($fail?"SOME FAILED\n":"ALL PASS\n");
```

- [ ] **Step 2: Run it**

Run: `php src/Flussu/Test/test_reasoning_flow_struct.php 2>&1 | grep -v "imagick\|Xdebug"`
Expected: all `PASS` and `ALL PASS`. If any FAIL, fix the JSON block(s) and re-run.

- [ ] **Step 3: Remove the temporary validator and commit**

```bash
rm -f src/Flussu/Test/test_reasoning_flow_struct.php
git add -A workflows/reasoning-loop/ src/Flussu/Test/
git commit -m "test - reasoning-loop: structural validation of the flow graph"
```

---

## Task 9: Blueprint documentation

**Files:**
- Create: `workflows/reasoning-loop/blueprint.md`

- [ ] **Step 1: Write the blueprint**

Content must include, in this order:
1. **Overview** — what the flow does and the 5-block graph diagram (copy the ASCII graph from the spec §4).
2. **Import instructions** — `POST /api.php?C=RD` with the JSON body (and that it can be imported in the designer); reference `Docs/FLUSSU_JSON_WORKFLOW_SPEC.md`.
3. **Configuration** — the START-block knobs and how to change them: `$MAX_ROUNDS` (default 3), `$MAX_SLOTS` (6), `$PROV_TRIAGE` (99 = modello flussu), `$PROV_ANSWER` (4 = Claude), `$DOMINIO` (the generic-assistant text; how to re-point to another domain).
4. **Triage JSON contract** — copy the contract from spec §5 verbatim, plus a worked example for each `status`.
5. **Block-by-block** — for each of START/TRIAGE/REASON/ASK/ANSWER: purpose, the full `exec` (unescaped, readable), its elements, and its exits. Use the exec code from Tasks 3–7.
6. **Edge cases** — copy spec §8 (unparsable triage → fallback; empty dati_mancanti → single field; non-convergence → round cap; provider error → polite message; slot clearing).
7. **Note on idioms** — `wofoEnv->` written without `$`; vars are direct PHP vars; clarification uses fixed slots `$clar1..$clarN` with LLM-driven labels/types.

- [ ] **Step 2: Verify links/paths resolve**

Run: `test -f workflows/reasoning-loop/reasoning_loop_workflow.json && test -f Docs/FLUSSU_JSON_WORKFLOW_SPEC.md && echo "refs OK"`
Expected: `refs OK`

- [ ] **Step 3: Commit**

```bash
git add workflows/reasoning-loop/blueprint.md
git commit -m "docs - reasoning-loop: block-by-block blueprint"
```

---

## Task 10: Live import smoke test (manual, optional but recommended)

The structural checks above are static. A real import/run requires a running Flussu instance and is the user's call (it consumes LLM calls).

- [ ] **Step 1: Ask the user to import the flow** (designer import, or `POST /api.php?C=RD` with the JSON body) and run one question that is obviously underspecified (should trigger ASK) and one that is clear (should answer directly).
- [ ] **Step 2: If errors appear**, capture the `Logs/log_<date>.log` lines and adjust the offending block exec (most likely: `initAiAgent`/`sendToAi` provider id, or the `wofoEnv->` idiom). Re-run Task 8 after edits.

---

## Self-review notes (done during planning)

- **Spec coverage:** triage JSON (Task 4) ✓, internal reasoning loop (Task 5) ✓, dynamic clarification fields (Task 6) ✓, round cap + best-effort (Tasks 4 & 7) ✓, internal reasoning not shown (no MESSAGE for reasoning) ✓, importable JSON + blueprint (Tasks 2–9) ✓.
- **Open verifications from spec §11:** `json_decode` in sandbox → RESOLVED (eval). `createButton` signature → RESOLVED (6 args, Task 6). Import envelope → RESOLVED (`C=RD`, top-level `workflow[0].blocks[]`).
- **Type/name consistency:** block UUIDs, exit indices (0/1/2), var names (`$round`, `$context`, `$triage`, `$userQuestion`, `$clar1..$clar6`, `$MAX_ROUNDS`, `$MAX_SLOTS`, `$PROV_TRIAGE`, `$PROV_ANSWER`, `$DOMINIO`, `$answer`, `$reasonOut`) used consistently across Tasks 3–7.
- **Residual risk (RESOLVED in review):** a MESSAGE element renders its `label` (with `$var` substitution), NOT its `var_name`. The ANSWER MESSAGE label is therefore `"$answer"` (matches the medical-research `$result_html` idiom). Empty answers are also guarded.
