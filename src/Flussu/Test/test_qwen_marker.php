<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * QWEN marker emission diagnostic.
 *
 * Investigates "QWEN always interprets requests as image generation".
 * Runs the standard Flussu chat path (FlussuQwenAi::chat) with 4
 * combinations of role/model/prompt and prints the raw reply, so we
 * can isolate whether the FLUSSU_IMG marker is being emitted by the
 * model on non-image prompts — and if so, what flips the behaviour.
 *
 * Uses the existing Config singleton to read the DashScope key from
 * config/.services.json. No env vars or curl invocations needed.
 *
 * Run from project root:
 *   php src/Flussu/Test/test_qwen_marker.php
 * -------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Config;
use Flussu\Api\Ai\FlussuQwenAi;

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

$CYAN  = "\033[36m";
$GREEN = "\033[32m";
$RED   = "\033[31m";
$YEL   = "\033[33m";
$RESET = "\033[0m";

// System prompt, kept verbatim from AiChatController::initAgent() (working
// tree). Today's date is hard-coded for repeatability across runs.
$SYS = <<<TXT
You are a standard AI assistant designed to assist users answering in the same language the users write the questions, if the user writes the questions, so if the user writes in italian, you should reply in italian, if the user writes in english, you should reply in english, and so on.
Your name is Flussu-AI and be aware of your responses, it should be clear, concise, and helpful.
Today's date is Sunday, 10 May 2026.
# When asked to generate images:
## If the user asks for a chart, diagram, schematic or anything structural that fits SVG, produce it as inline SVG.
## If the user asks for a photo, picture, illustration, render, ritratto, foto, immagine, painting or any RASTER image (jpg/png), DO NOT describe it and DO NOT draw it as SVG. Reply with EXACTLY one line of JSON in this format, with no text before or after:
{"FLUSSU_IMG":"<a detailed English prompt suitable for an image generation model>"}
The backend will detect this marker, generate the actual raster image and replace your reply with the resulting picture. Never include explanations alongside the JSON marker.
# When asked to look something up on the web / scrape a site / get fresh information online:
## If the user says things like "cerca su internet", "search the web", "look it up online", "aggiornati con i dati disponibili sul web", or asks about events/people/products/news that may have changed since your training, OR mentions a specific URL/site to consult, reply with EXACTLY one line of JSON, no text before or after:
{"FLUSSU_WEB":{"action":"search","query":"<concise web search query>","lang":"<two-letter language code>"}}
---
* WARNING! * : If you do not know the right answer to a question, you should politely inform the user that you do not have that information.
TXT;

function verdict(string $reply): string {
    if (strpos($reply, '"FLUSSU_IMG"') !== false) return "IMG marker EMITTED";
    if (strpos($reply, '"FLUSSU_WEB"') !== false) return "WEB marker emitted";
    return "plain text (no marker)";
}

function runTest(string $label, string $sysRole, string $model, string $userMsg, string $sysPrompt): void {
    global $CYAN, $GREEN, $RED, $YEL, $RESET;

    echo $CYAN . str_repeat("=", 64) . $RESET . "\n";
    echo "{$CYAN}TEST: {$label}{$RESET}\n";
    echo "  sys_role={$sysRole}   model={$model}\n";
    echo "  user_msg={$userMsg}\n";
    echo str_repeat("-", 64) . "\n";

    // FlussuQwenAi::chat() builds the messages array as:
    //   $preChat (passed in) + ['role' => $role, 'content' => $sendText]
    // We want the system prompt to be the first message, so we pass it as
    // $preChat[0] with the requested role.
    $preChat = [
        ['role' => $sysRole, 'content' => $sysPrompt],
    ];

    try {
        $client = new FlussuQwenAi($model, $model);
        $result = $client->chat($preChat, $userMsg, "user");
    } catch (\Throwable $e) {
        echo "{$RED}EXCEPTION:{$RESET} " . $e->getMessage() . "\n\n";
        return;
    }

    $reply = is_array($result) && isset($result[1]) ? (string)$result[1] : '(no reply field)';
    $usage = is_array($result) && isset($result[2]) ? $result[2] : null;
    $v = verdict($reply);

    $verdictColor = (strpos($v, 'IMG') !== false) ? $YEL : $GREEN;
    echo "{$verdictColor}VERDICT: {$v}{$RESET}\n";
    if (is_array($usage)) {
        echo "  tokens: model=" . ($usage['model'] ?? '?')
           . " input=" . ($usage['input'] ?? '?')
           . " output=" . ($usage['output'] ?? '?') . "\n";
    }
    echo "REPLY:\n";
    foreach (explode("\n", trim($reply)) as $line) {
        echo "  " . $line . "\n";
    }
    echo "\n";
}

echo "{$CYAN}=== QWEN marker diagnostic ==={$RESET}\n";
echo "Config:  qwen.chat-model = " . (config('services.ai_provider.qwen.chat-model') ?: '(not set)') . "\n";
echo "         qwen.model      = " . (config('services.ai_provider.qwen.model') ?: '(not set)') . "\n\n";

// Test A: replicate exactly what Flussu currently does
//   (system prompt as role:user, qwen-turbo)
runTest(
    "A) baseline Flussu (role:user, qwen-turbo)",
    "user", "qwen-turbo",
    "ciao, come stai?",
    $SYS
);

// Test B: same payload but system prompt as role:system
runTest(
    "B) role:system, qwen-turbo",
    "system", "qwen-turbo",
    "ciao, come stai?",
    $SYS
);

// Test C: original role:user but on a larger model
runTest(
    "C) role:user, qwen-plus",
    "user", "qwen-plus",
    "ciao, come stai?",
    $SYS
);

// Test D: positive control — must emit FLUSSU_IMG
runTest(
    "D) positive control (image request, role:user, qwen-turbo)",
    "user", "qwen-turbo",
    "fammi un'immagine di un gatto astronauta",
    $SYS
);

echo $CYAN . str_repeat("=", 64) . $RESET . "\n";
echo "Cross-reference VERDICT lines with the matrix in:\n";
echo "  ~/.claude/plans/ricordi-che-abbiamo-lavorato-moonlit-sedgewick.md\n";
