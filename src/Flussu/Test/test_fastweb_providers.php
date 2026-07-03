<?php
/* --------------------------------------------------------------------*
 * Test Fastweb AI Factory providers (FW_MIIA / FW_QWEN / FW_GPT)
 * Chat round-trip + vision probe per ogni modello. Manual CLI test.
 * --------------------------------------------------------------------*
 * CREATED DATE:     03.07.2026 - Flussu v5.0
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Config;
use Flussu\Api\Ai\FlussuFastwebAi;
use Flussu\Controllers\Platform;

// config() helper is defined inline by the HTTP entrypoints (api.php); the
// provider constructors call it, so define it here too (same as sibling tests).
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::init()->get($key, $default);
    }
}

$models = [
    'fw_27001_miia' => Platform::FW_MIIA,
    'fw_27001_qwen' => Platform::FW_QWEN,
    'fw_27001_gpt'  => Platform::FW_GPT,
];

// 128x128 red PNG for the vision probe, generated with GD: a hardcoded
// base64 blob proved fragile (a corrupt IDAT made the endpoint reject the
// image and masked the real vision capability of the model).
$tmpImg = sys_get_temp_dir() . '/fw_vision_probe.png';
if (!function_exists('imagecreatetruecolor')) {
    echo "[WARN] GD extension missing: vision probes will be skipped\n";
} else {
    $probe = imagecreatetruecolor(128, 128);
    imagefilledrectangle($probe, 0, 0, 127, 127, imagecolorallocate($probe, 220, 30, 30));
    imagepng($probe, $tmpImg);
    imagedestroy($probe);
}

$pass = 0; $fail = 0;
foreach ($models as $cfgKey => $platform) {
    echo "\n=== $cfgKey (Platform {$platform->value}) ===\n";
    try {
        $ai = new FlussuFastwebAi($cfgKey);
    } catch (\Throwable $e) {
        echo "[FAIL] constructor: " . $e->getMessage() . "\n"; $fail++; continue;
    }

    // --- chat round-trip
    [$hist, $reply, $usage] = $ai->chat([], "Reply with the single word: PONG");
    if (is_string($reply) && $reply !== "" && stripos($reply, "Error") !== 0) {
        echo "[OK] chat reply: " . substr(trim($reply), 0, 80) . "\n";
        echo "     usage: " . json_encode($usage) . "\n"; $pass++;
    } else {
        echo "[FAIL] chat: $reply\n"; $fail++;
    }

    // --- vision probe: only when the config flag is on; the result tells
    // the user whether the flag value is right for that model.
    if ($ai->canAnalyzeMedia() && file_exists($tmpImg)) {
        [$h2, $vReply, $vUsage] = $ai->analyzeMedia([], $tmpImg, "What color is this image? One word.");
        echo "[INFO] vision probe: " . substr(trim((string)$vReply), 0, 120) . "\n";
    } else {
        echo "[INFO] vision: flag OFF in config block '$cfgKey' (set \"vision\": true and re-run to probe)\n";
    }
}

@unlink($tmpImg);
echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
