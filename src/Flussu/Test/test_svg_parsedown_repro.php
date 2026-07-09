<?php
/* Repro [DIAG_SVGMD_v1]: Command::htmlSanitize mangles inline SVG.
 * The AI reply is wrapped {MD}...{/MD} (AiChatController), so it goes through
 * Parsedown. Parsedown safe-mode ESCAPES the raw <svg> tags but still runs
 * autolink / paragraph / indented-code transforms -> the SVG is fragmented.
 *   php src/Flussu/Test/test_svg_parsedown_repro.php
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
use Flussu\Flussuserver\Command;
use Flussu\Config;
if (!function_exists('config')) { function config(string $k,$d=null){ return Config::init()->get($k,$d);} }
$G="\033[32m";$R="\033[31m";$Y="\033[33m";$Z="\033[0m";

// SVG with the three Parsedown triggers: bare xmlns URL, blank lines, 4-space indent.
$svg = "<svg width=\"800\" height=\"600\" xmlns=\"http://www.w3.org/2000/svg\">\n"
     . "<!-- Background -->\n"
     . "<rect x=\"0\" y=\"0\" width=\"800\" height=\"600\" fill=\"#ffffff\" />\n\n"
     . "<!-- Grid lines -->\n"
     . "    <g font-family=\"Arial\">\n"
     . "    <line x1=\"80\" y1=\"540\" x2=\"760\" y2=\"540\" />\n"
     . "    <text x=\"75\" y=\"545\">0</text>\n"
     . "    </g>\n"
     . "</svg>";

$out = Command::htmlSanitize("{MD}".$svg."{/MD}");

$autolink = strpos($out, '<a href') !== false;
$paraWrap = strpos($out, '<p>') !== false;
$codeWrap = strpos($out, '<pre>') !== false || strpos($out, '<code>') !== false;
$verbatim = strpos($out, '<svg') !== false && strpos($out, '</svg>') !== false;

echo "{$Y}=== Command::htmlSanitize on {MD}-wrapped SVG ==={$Z}\n";
echo "  autolinked xmlns (<a href) = " . ($autolink ? "{$R}YES (CORRUPT){$Z}" : "{$G}no{$Z}") . "\n";
echo "  paragraph-wrapped (<p>)    = " . ($paraWrap ? "{$R}YES (CORRUPT){$Z}" : "{$G}no{$Z}") . "\n";
echo "  indented-code (<pre/code>) = " . ($codeWrap ? "{$R}YES (CORRUPT){$Z}" : "{$G}no{$Z}") . "\n";
echo "  SVG verbatim (<svg>..</svg>) = " . ($verbatim ? "{$G}YES{$Z}" : "{$R}no (fragmented/escaped){$Z}") . "\n";
echo "{$Y}--- output (first 600) ---{$Z}\n";
echo substr($out, 0, 600) . "\n";
