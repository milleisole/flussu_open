<?php
/* SVG truncation verify (ZaiGlm class path) [DIAG_SVGTRUNC_v1]
 *   php src/Flussu/Test/test_svg_truncation_zai.php
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
use Flussu\Config;
use Flussu\Api\Ai\FlussuZaiGlmAi;
if (!function_exists('config')) { function config(string $k,$d=null){ return Config::init()->get($k,$d);} }
$G="\033[32m";$R="\033[31m";$Z="\033[0m";
$USER=<<<TXT
Genera un istogramma a barre verticali come SVG inline completo e autosufficiente.
Requisiti: 10 barre [42,58,31,77,65,49,88,53,72,76], <defs> con gradiente, <style>
interno, griglia, asse X con 10 etichette, asse Y 0..100, valore sopra ogni barra,
titolo e legenda. Rispondi SOLO con lo <svg>...</svg>, nessun testo prima o dopo.
TXT;
$SYS="You are Flussu-AI. If the user asks for a chart that fits SVG, produce it as inline SVG.";
try {
    $c = new FlussuZaiGlmAi();
    $res = $c->chat([['role'=>'system','content'=>$SYS]], $USER, "user");
    $reply=(string)($res[1]??''); $usage=$res[2]??null;
    echo "  output_tokens = ".($usage['output']??'?')."\n";
    echo "  reply_chars   = ".strlen($reply)."\n";
    echo "  has </svg>    = ".(stripos($reply,'</svg>')!==false?"{$G}YES{$Z}":"{$R}NO (TRUNCATED){$Z}")."\n";
    echo "  last 80 chars = ...".str_replace("\n","\\n",substr($reply,-80))."\n";
} catch (\Throwable $e) { echo "{$R}SKIP/EXC:{$Z} ".$e->getMessage()."\n"; }
