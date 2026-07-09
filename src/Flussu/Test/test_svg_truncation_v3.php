<?php
/* SVG truncation diagnostic v3 [DIAG_SVGTRUNC_v1] - test the FIX:
 * disable thinking (enable_thinking=false) so reasoning tokens stop
 * eating the output budget. Tries top-level flag AND extra_body.
 *   php src/Flussu/Test/test_svg_truncation_v3.php [model] [max_tokens]
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
use Flussu\Config;
use GuzzleHttp\Client;
if (!function_exists('config')) { function config(string $k,$d=null){ return Config::init()->get($k,$d);} }
$C="\033[36m";$G="\033[32m";$R="\033[31m";$Y="\033[33m";$Z="\033[0m";
$model=$argv[1]??'qwen3.5-plus'; $maxtk=(int)($argv[2]??3000);
$USER=<<<TXT
Genera un istogramma a barre verticali come SVG inline completo e autosufficiente.
Requisiti: 10 barre con questi valori [42, 58, 31, 77, 65, 49, 88, 53, 72, 76],
un blocco <defs> con un gradiente lineare, un <style> interno, una griglia di
sfondo, asse X con 10 etichette (Gen..Ott), asse Y con tacche 0..100, il valore
numerico sopra ogni barra, titolo e legenda. Rispondi SOLO con lo <svg>...</svg>,
nessun testo prima o dopo.
TXT;
$SYS="You are Flussu-AI. If the user asks for a chart/diagram that fits SVG, produce it as inline SVG.";
$key=config("services.ai_provider.qwen.auth_key");
$http=new Client(['base_uri'=>'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/','timeout'=>300]);
function run($http,$key,$model,$maxtk,$USER,$SYS,$label,$extra){
    global $G,$R,$Z,$Y;
    echo "{$Y}--- {$label} ---{$Z}\n";
    $payload=array_merge([
        'model'=>$model,
        'messages'=>[['role'=>'system','content'=>$SYS],['role'=>'user','content'=>$USER]],
        'max_tokens'=>$maxtk,
        'temperature'=>(float)Config::init()->aiTemperature('qwen'),
    ],$extra);
    try{
        $t0=microtime(true);
        $resp=$http->post('chat/completions',['headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'json'=>$payload]);
        $dt=round(microtime(true)-$t0,1);
        $d=json_decode((string)$resp->getBody(),true);
        $reply=(string)($d['choices'][0]['message']['content']??'');
        $fin=$d['choices'][0]['finish_reason']??'?';
        $rt=$d['usage']['completion_tokens_details']['reasoning_tokens']??'n/a';
        echo "  wall={$dt}s  finish_reason=".($fin==='length'?"{$R}{$fin}{$Z}":"{$G}{$fin}{$Z}")
            ."  completion_tk=".($d['usage']['completion_tokens']??'?')."  reasoning_tk={$rt}\n";
        echo "  reply_chars=".strlen($reply)."  has </svg>=".(stripos($reply,'</svg>')!==false?"{$G}YES{$Z}":"{$R}NO{$Z}")."\n\n";
    }catch(\Throwable $e){ echo "  {$R}EXC:{$Z} ".$e->getMessage()."\n\n"; }
}
echo "{$C}[DIAG_SVGTRUNC_v1 v3] FIX TEST  model={$model} max_tokens={$maxtk}{$Z}\n\n";
run($http,$key,$model,$maxtk,$USER,$SYS,"enable_thinking=false (top-level)",['enable_thinking'=>false]);
run($http,$key,$model,$maxtk,$USER,$SYS,"enable_thinking=false (extra_body)",['extra_body'=>['enable_thinking'=>false]]);
