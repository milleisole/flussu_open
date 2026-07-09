<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * SVG truncation diagnostic  [DIAG_SVGTRUNC_v1]
 *
 * Reproduces the "second chart (histogram) truncated, no </svg>" bug
 * reported by the u-prompt front/back agents (model qwen3.5-plus,
 * CTO~2406, SVG cut mid-bar 9/10).
 *
 * Controlled experiment — ONE variable changes: max_tokens.
 *   ARM A: FlussuQwenAi::chat()  -> exactly the workflow path,
 *          max_tokens is HARDCODED to 2000 in _chatContinue().
 *   ARM B: same messages, same endpoint/key (read via Config), but
 *          max_tokens raised to 8000, and we ALSO print finish_reason
 *          (which _chatContinue discards) — the definitive truncation
 *          signal.
 *
 * Key is read through the Config singleton (config/.services.json).
 * No env vars, no curl, no key export.
 *
 * Run from project root:
 *   php src/Flussu/Test/test_svg_truncation_v1.php [model]
 * default model = qwen3.5-plus
 * -------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Config;
use Flussu\Api\Ai\FlussuQwenAi;
use GuzzleHttp\Client;

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

$CYAN="\033[36m"; $GREEN="\033[32m"; $RED="\033[31m"; $YEL="\033[33m"; $RESET="\033[0m";

$model = $argv[1] ?? 'qwen3.5-plus';

// A histogram request comparable to the failing "Genera istogramma" step:
// 10 bars, values, axes/grid/defs — the kind of SVG that overruns.
$USER = <<<TXT
Genera un istogramma a barre verticali come SVG inline completo e autosufficiente.
Requisiti: 10 barre con questi valori [42, 58, 31, 77, 65, 49, 88, 53, 72, 76],
un blocco <defs> con un gradiente lineare, un <style> interno, una griglia di
sfondo, asse X con 10 etichette (Gen..Ott), asse Y con tacche 0..100, il valore
numerico sopra ogni barra, titolo e legenda. Rispondi SOLO con lo <svg>...</svg>,
nessun testo prima o dopo.
TXT;

$SYS = "You are Flussu-AI. If the user asks for a chart/diagram that fits SVG, produce it as inline SVG.";

function analyze(string $svg): array {
    $len   = strlen($svg);
    $hasEnd= (stripos($svg, '</svg>') !== false);
    $tail  = substr($svg, -80);
    return [$len, $hasEnd, $tail];
}

echo "{$CYAN}".str_repeat("=",70)."{$RESET}\n";
echo "{$CYAN}[DIAG_SVGTRUNC_v1] model={$model}{$RESET}\n";
echo "{$CYAN}".str_repeat("=",70)."{$RESET}\n\n";

/* -------------------- ARM A : the real workflow path -------------------- */
echo "{$YEL}--- ARM A: FlussuQwenAi::chat()  (max_tokens HARDCODED = 2000) ---{$RESET}\n";
try {
    $preChat = [['role'=>'system','content'=>$SYS]];
    $client  = new FlussuQwenAi($model, $model);
    $res     = $client->chat($preChat, $USER, "user");
    $reply   = (string)($res[1] ?? '');
    $usage   = $res[2] ?? null;
    [$len,$hasEnd,$tail] = analyze($reply);
    echo "  output_tokens = " . ($usage['output'] ?? '?') . "\n";
    echo "  reply_chars   = {$len}\n";
    echo "  has </svg>    = " . ($hasEnd ? "{$GREEN}YES{$RESET}" : "{$RED}NO (TRUNCATED){$RESET}") . "\n";
    echo "  last 80 chars = ..." . str_replace("\n","\\n",$tail) . "\n\n";
} catch (\Throwable $e) {
    echo "{$RED}ARM A EXCEPTION:{$RESET} " . $e->getMessage() . "\n\n";
}

/* -------------------- ARM B : same call, cap raised, finish_reason shown -------------------- */
echo "{$YEL}--- ARM B: direct call, max_tokens = 8000, finish_reason exposed ---{$RESET}\n";
try {
    $key = config("services.ai_provider.qwen.auth_key");
    if (empty($key)) throw new \Exception("qwen.auth_key not configured");
    $http = new Client([
        'base_uri' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
        'timeout'  => 180,
    ]);
    $payload = [
        'model'       => $model,
        'messages'    => [
            ['role'=>'system','content'=>$SYS],
            ['role'=>'user','content'=>$USER],
        ],
        'max_tokens'  => 8000,
        'temperature' => (float) Config::init()->aiTemperature('qwen'),
    ];
    $resp = $http->post('chat/completions', [
        'headers' => ['Authorization'=>'Bearer '.$key, 'Content-Type'=>'application/json'],
        'json'    => $payload,
    ]);
    $data = json_decode((string)$resp->getBody(), true);
    $reply  = (string)($data['choices'][0]['message']['content'] ?? '');
    $finish = $data['choices'][0]['finish_reason'] ?? '?';
    $ctok   = $data['usage']['completion_tokens'] ?? '?';
    [$len,$hasEnd,$tail] = analyze($reply);
    echo "  finish_reason = " . ($finish==='length' ? "{$RED}{$finish}{$RESET}" : "{$GREEN}{$finish}{$RESET}") . "\n";
    echo "  completion_tk = {$ctok}\n";
    echo "  reply_chars   = {$len}\n";
    echo "  has </svg>    = " . ($hasEnd ? "{$GREEN}YES{$RESET}" : "{$RED}NO (TRUNCATED){$RESET}") . "\n";
    echo "  last 80 chars = ..." . str_replace("\n","\\n",$tail) . "\n\n";
} catch (\Throwable $e) {
    echo "{$RED}ARM B EXCEPTION:{$RESET} " . $e->getMessage() . "\n\n";
}

echo "{$CYAN}Interpretation:{$RESET}\n";
echo "  A NO</svg> + B YES</svg>  => root cause = hardcoded max_tokens=2000 cap.\n";
echo "  A output_tokens ~2000 & finish_reason=length confirms cap-driven truncation.\n";
