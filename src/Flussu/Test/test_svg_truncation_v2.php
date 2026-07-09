<?php
/* --------------------------------------------------------------------*
 * SVG truncation diagnostic v2  [DIAG_SVGTRUNC_v1]
 * Replicates FlussuQwenAi::_chatContinue() EXACTLY (max_tokens=2000,
 * non-stream) via a direct call so we can read finish_reason and the
 * full usage block (incl. reasoning_tokens) that _chatContinue drops.
 *
 *   php src/Flussu/Test/test_svg_truncation_v2.php [model] [max_tokens]
 * -------------------------------------------------------------------*/
require_once __DIR__ . '/../../../vendor/autoload.php';
use Flussu\Config;
use GuzzleHttp\Client;
if (!function_exists('config')) { function config(string $k,$d=null){ return Config::init()->get($k,$d);} }

$C="\033[36m";$G="\033[32m";$R="\033[31m";$Y="\033[33m";$Z="\033[0m";
$model = $argv[1] ?? 'qwen3.5-plus';
$maxtk = (int)($argv[2] ?? 2000);

$USER = <<<TXT
Genera un istogramma a barre verticali come SVG inline completo e autosufficiente.
Requisiti: 10 barre con questi valori [42, 58, 31, 77, 65, 49, 88, 53, 72, 76],
un blocco <defs> con un gradiente lineare, un <style> interno, una griglia di
sfondo, asse X con 10 etichette (Gen..Ott), asse Y con tacche 0..100, il valore
numerico sopra ogni barra, titolo e legenda. Rispondi SOLO con lo <svg>...</svg>,
nessun testo prima o dopo.
TXT;
$SYS = "You are Flussu-AI. If the user asks for a chart/diagram that fits SVG, produce it as inline SVG.";

echo "{$C}[DIAG_SVGTRUNC_v1 v2] model={$model} max_tokens={$maxtk} (non-stream, == _chatContinue){$Z}\n\n";
try {
    $key = config("services.ai_provider.qwen.auth_key");
    if (empty($key)) throw new \Exception("qwen.auth_key not configured");
    $http = new Client(['base_uri'=>'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/','timeout'=>300]);
    $payload = [
        'model'=>$model,
        'messages'=>[['role'=>'system','content'=>$SYS],['role'=>'user','content'=>$USER]],
        'max_tokens'=>$maxtk,
        'temperature'=>(float)Config::init()->aiTemperature('qwen'),
    ];
    $t0=microtime(true);
    $resp=$http->post('chat/completions',['headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'json'=>$payload]);
    $dt=round(microtime(true)-$t0,1);
    $data=json_decode((string)$resp->getBody(),true);
    $reply=(string)($data['choices'][0]['message']['content'] ?? '');
    $finish=$data['choices'][0]['finish_reason'] ?? '?';
    $reasoning=$data['choices'][0]['message']['reasoning_content'] ?? null;
    echo "  wall_time     = {$dt}s\n";
    echo "  finish_reason = ".($finish==='length'?"{$R}{$finish}{$Z}":"{$G}{$finish}{$Z}")."\n";
    echo "  usage         = ".json_encode($data['usage'] ?? [])."\n";
    echo "  reasoning_len = ".($reasoning!==null?strlen($reasoning)." chars (THINKING MODEL)":"(none)")."\n";
    echo "  reply_chars   = ".strlen($reply)."\n";
    echo "  has </svg>    = ".(stripos($reply,'</svg>')!==false?"{$G}YES{$Z}":"{$R}NO (TRUNCATED){$Z}")."\n";
    echo "  last 80 chars = ...".str_replace("\n","\\n",substr($reply,-80))."\n";
} catch (\Throwable $e) {
    echo "{$R}EXCEPTION:{$Z} ".$e->getMessage()."\n";
}
