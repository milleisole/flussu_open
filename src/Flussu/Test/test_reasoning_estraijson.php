<?php
// src/Flussu/Test/test_reasoning_estraijson.php
// Standalone test for the estraiJson() helper embedded in the reasoning-loop blocks.

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
