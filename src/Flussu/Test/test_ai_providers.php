<?php
/* --------------------------------------------------------------------*
 * Test completo per AI Providers Integration
 * Verifica accessibilità e presenza API keys per tutti i modelli AI
 * --------------------------------------------------------------------*
 * CLASS-NAME:       AI Providers Test - v1.0
 * CREATED DATE:     26.01.2026 - Claude - Flussu v4.5
 * VERSION REL.:     4.5.2 20260126
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Controllers\AiChatController;
use Flussu\Controllers\Platform;
use Flussu\Config;

// Colori per output console
class Console {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const RESET = "\033[0m";

    public static function success($msg) {
        echo self::GREEN . "[OK] " . $msg . self::RESET . "\n";
    }

    public static function error($msg) {
        echo self::RED . "[FAIL] " . $msg . self::RESET . "\n";
    }

    public static function info($msg) {
        echo self::BLUE . "[INFO] " . $msg . self::RESET . "\n";
    }

    public static function warning($msg) {
        echo self::YELLOW . "[WARN] " . $msg . self::RESET . "\n";
    }

    public static function section($title) {
        echo "\n" . self::CYAN . "=== " . $title . " ===" . self::RESET . "\n\n";
    }

    public static function divider() {
        echo self::BLUE . str_repeat("-", 60) . self::RESET . "\n";
    }
}

/**
 * AI Provider Configuration mapping
 */
$aiProviders = [
    [
        'name' => 'OpenAI (ChatGPT)',
        'platform' => Platform::CHATGPT,
        'config_key' => 'services.ai_provider.open_ai.auth_key',
        'model_key' => 'services.ai_provider.open_ai.model',
        'default_model' => 'gpt-4o-mini'
    ],
    [
        'name' => 'X.ai (Grok)',
        'platform' => Platform::GROK,
        'config_key' => 'services.ai_provider.xai_grok.auth_key',
        'model_key' => 'services.ai_provider.xai_grok.model',
        'default_model' => 'grok-3-mini-fast'
    ],
    [
        'name' => 'Google (Gemini)',
        'platform' => Platform::GEMINI,
        'config_key' => 'services.ai_provider.ggl_gemini.auth_key',
        'model_key' => 'services.ai_provider.ggl_gemini.model',
        'default_model' => 'gemini-2.0-flash'
    ],
    [
        'name' => 'DeepSeek',
        'platform' => Platform::DEEPSEEK,
        'config_key' => 'services.ai_provider.deepseek.auth_key',
        'model_key' => 'services.ai_provider.deepseek.model',
        'default_model' => 'deepseek-chat'
    ],
    [
        'name' => 'Anthropic (Claude)',
        'platform' => Platform::CLAUDE,
        'config_key' => 'services.ai_provider.ant_claude.auth_key',
        'model_key' => 'services.ai_provider.ant_claude.model',
        'default_model' => 'claude-3-5-sonnet-20241022'
    ],
    [
        'name' => 'Hugging Face',
        'platform' => Platform::HUGGINGFACE,
        'config_key' => 'services.ai_provider.huggingface.auth_token',
        'model_key' => 'services.ai_provider.huggingface.model',
        'default_model' => 'Helsinki-NLP/opus-mt-it-en'
    ],
    [
        'name' => '01.AI (Z-AI)',
        'platform' => Platform::ZEROONE,
        'config_key' => 'services.ai_provider.zeroone_ai.auth_key',
        'model_key' => 'services.ai_provider.zeroone_ai.model',
        'default_model' => 'yi-lightning'
    ],
    [
        'name' => 'Moonshot (Kimi)',
        'platform' => Platform::KIMI,
        'config_key' => 'services.ai_provider.kimi.auth_key',
        'model_key' => 'services.ai_provider.kimi.model',
        'default_model' => 'moonshot-v1-8k'
    ],
    [
        'name' => 'Alibaba (Qwen)',
        'platform' => Platform::QWEN,
        'config_key' => 'services.ai_provider.qwen.auth_key',
        'model_key' => 'services.ai_provider.qwen.model',
        'default_model' => 'qwen-turbo'
    ]
];

/**
 * Test results tracking
 */
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'details' => []
];

// Main test routine
try {
    Console::section("AI PROVIDERS INTEGRATION TEST");
    Console::info("Testing " . count($aiProviders) . " AI providers");
    Console::info("Date: " . date('Y-m-d H:i:s'));
    Console::divider();

    // ===== TEST 0: Configuration Loading =====
    Console::section("Test 0: Configuration Loading");

    try {
        $config = Config::init();
        Console::success("Configuration loaded successfully");
    } catch (\Exception $e) {
        Console::error("Failed to load configuration: " . $e->getMessage());
        exit(1);
    }

    // ===== TEST 1: API Keys Verification =====
    Console::section("Test 1: API Keys Verification");

    foreach ($aiProviders as $provider) {
        $testResults['total']++;
        $apiKey = $config->get($provider['config_key']);
        $model = $config->get($provider['model_key'], $provider['default_model']);

        $result = [
            'provider' => $provider['name'],
            'platform' => $provider['platform']->name,
            'api_key_present' => false,
            'api_key_valid' => false,
            'model' => $model,
            'connectivity_test' => null,
            'error' => null
        ];

        if (empty($apiKey)) {
            Console::warning($provider['name'] . ": API key NOT configured");
            $result['error'] = 'API key not configured';
            $testResults['skipped']++;
        } elseif (strpos($apiKey, 'insert-your-api-key') !== false ||
                  strpos($apiKey, '6768-insert') !== false) {
            Console::warning($provider['name'] . ": API key is placeholder (not configured)");
            $result['error'] = 'API key is placeholder';
            $testResults['skipped']++;
        } else {
            Console::success($provider['name'] . ": API key present");
            $result['api_key_present'] = true;

            // Mask API key for display (show first 4 and last 4 chars)
            $maskedKey = substr($apiKey, 0, 4) . str_repeat('*', 20) . substr($apiKey, -4);
            Console::info("  Key: " . $maskedKey);
            Console::info("  Model: " . $model);
            $result['api_key_valid'] = true;
            $testResults['passed']++;
        }

        $testResults['details'][] = $result;
    }

    Console::divider();
    Console::info("API Keys Summary: " . $testResults['passed'] . " present, " .
                  $testResults['skipped'] . " missing/placeholder");

    // ===== TEST 2: Provider Instantiation =====
    Console::section("Test 2: Provider Instantiation");

    $instantiationResults = [];

    foreach ($aiProviders as $provider) {
        $apiKey = $config->get($provider['config_key']);

        // Skip if no valid API key
        if (empty($apiKey) || strpos($apiKey, 'insert-your-api-key') !== false) {
            Console::warning($provider['name'] . ": Skipped (no API key)");
            $instantiationResults[$provider['name']] = 'skipped';
            continue;
        }

        try {
            $controller = new AiChatController($provider['platform']);
            Console::success($provider['name'] . ": Controller instantiated successfully");
            $instantiationResults[$provider['name']] = 'success';
        } catch (\Throwable $e) {
            Console::error($provider['name'] . ": Failed to instantiate - " . $e->getMessage());
            $instantiationResults[$provider['name']] = 'failed: ' . $e->getMessage();
        }
    }

    // ===== TEST 3: Connectivity Test (Optional) =====
    Console::section("Test 3: Connectivity Test (API Call)");
    Console::warning("This test will make actual API calls and may incur costs.");
    Console::info("Do you want to run connectivity tests? (y/n): ");

    $runConnectivityTests = false;
    if (php_sapi_name() === 'cli') {
        $answer = trim(fgets(STDIN));
        $runConnectivityTests = (strtolower($answer) === 'y' || strtolower($answer) === 's');
    }

    if ($runConnectivityTests) {
        $testPrompt = "Respond with exactly: 'TEST OK'. Nothing else.";
        $testSessionId = "test_" . uniqid();

        foreach ($aiProviders as $provider) {
            $apiKey = $config->get($provider['config_key']);

            // Skip if no valid API key
            if (empty($apiKey) || strpos($apiKey, 'insert-your-api-key') !== false) {
                Console::warning($provider['name'] . ": Skipped (no API key)");
                continue;
            }

            Console::info("Testing " . $provider['name'] . "...");

            try {
                $controller = new AiChatController($provider['platform']);
                $controller->initAgent($testSessionId . "_" . $provider['platform']->value);

                $startTime = microtime(true);
                $result = $controller->chat(
                    $testSessionId . "_" . $provider['platform']->value,
                    $testPrompt,
                    false,
                    "user",
                    50,
                    0.1
                );
                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000, 2);

                if ($result[0] === "Ok") {
                    Console::success($provider['name'] . ": API call successful");
                    Console::info("  Response time: " . $responseTime . " ms");

                    // Show token usage if available
                    if (isset($result[2]) && is_array($result[2])) {
                        Console::info("  Input tokens: " . ($result[2]['input'] ?? 'N/A'));
                        Console::info("  Output tokens: " . ($result[2]['output'] ?? 'N/A'));
                    }

                    // Update test results
                    foreach ($testResults['details'] as &$detail) {
                        if ($detail['provider'] === $provider['name']) {
                            $detail['connectivity_test'] = 'passed';
                            $detail['response_time_ms'] = $responseTime;
                            break;
                        }
                    }
                } else {
                    Console::error($provider['name'] . ": API call returned error");
                    Console::info("  Error: " . ($result[1] ?? 'Unknown error'));

                    foreach ($testResults['details'] as &$detail) {
                        if ($detail['provider'] === $provider['name']) {
                            $detail['connectivity_test'] = 'failed';
                            $detail['error'] = $result[1] ?? 'Unknown error';
                            break;
                        }
                    }
                }

            } catch (\Throwable $e) {
                Console::error($provider['name'] . ": Exception - " . $e->getMessage());

                foreach ($testResults['details'] as &$detail) {
                    if ($detail['provider'] === $provider['name']) {
                        $detail['connectivity_test'] = 'exception';
                        $detail['error'] = $e->getMessage();
                        break;
                    }
                }
            }

            Console::divider();
        }
    } else {
        Console::info("Connectivity tests skipped.");
    }

    // ===== TEST SUMMARY =====
    Console::section("TEST SUMMARY");

    echo "\n";
    printf("%-25s %-12s %-15s %-15s\n", "Provider", "API Key", "Instantiation", "Connectivity");
    echo str_repeat("-", 70) . "\n";

    foreach ($testResults['details'] as $detail) {
        $apiStatus = $detail['api_key_present'] ? Console::GREEN . "Present" . Console::RESET : Console::YELLOW . "Missing" . Console::RESET;

        $instStatus = isset($instantiationResults[$detail['provider']])
            ? ($instantiationResults[$detail['provider']] === 'success'
                ? Console::GREEN . "OK" . Console::RESET
                : ($instantiationResults[$detail['provider']] === 'skipped'
                    ? Console::YELLOW . "Skipped" . Console::RESET
                    : Console::RED . "Failed" . Console::RESET))
            : Console::YELLOW . "N/A" . Console::RESET;

        $connStatus = Console::YELLOW . "N/A" . Console::RESET;
        if ($detail['connectivity_test'] === 'passed') {
            $connStatus = Console::GREEN . "OK" . Console::RESET;
        } elseif ($detail['connectivity_test'] === 'failed' || $detail['connectivity_test'] === 'exception') {
            $connStatus = Console::RED . "Failed" . Console::RESET;
        }

        printf("%-25s %-22s %-25s %-25s\n",
            $detail['provider'],
            $apiStatus,
            $instStatus,
            $connStatus
        );
    }

    echo "\n";
    Console::divider();

    // Final statistics
    $configuredProviders = array_filter($testResults['details'], fn($d) => $d['api_key_present']);
    $testedProviders = array_filter($testResults['details'], fn($d) => $d['connectivity_test'] === 'passed');

    Console::info("Total providers: " . count($aiProviders));
    Console::info("Configured providers (with API key): " . count($configuredProviders));
    Console::info("Successfully tested providers: " . count($testedProviders));

    if (count($configuredProviders) === 0) {
        Console::warning("\nNo AI providers are configured!");
        Console::info("Please edit your config/.services.json file to add API keys.");
    } elseif (count($configuredProviders) < count($aiProviders)) {
        Console::warning("\nSome providers are not configured. Missing API keys for:");
        foreach ($testResults['details'] as $detail) {
            if (!$detail['api_key_present']) {
                Console::info("  - " . $detail['provider']);
            }
        }
    }

    Console::section("AVAILABLE PLATFORMS (Enum)");
    Console::info("Use these Platform enum values in your code:");
    echo "\n";
    foreach ($aiProviders as $provider) {
        Console::info("  Platform::" . $provider['platform']->name . " => " . $provider['name']);
    }

    Console::section("CONFIGURATION REFERENCE");
    Console::info("Add these sections to your config/.services.json:");
    echo "\n";
    $configExample = <<<JSON
"ai_provider": {
    "open_ai": {
        "auth_key": "sk-your-openai-key",
        "model": "gpt-4o-mini",
        "chat-model": "gpt-4o"
    },
    "xai_grok": {
        "auth_key": "your-xai-key",
        "model": "grok-3-mini-fast"
    },
    "ggl_gemini": {
        "auth_key": "your-gemini-key",
        "model": "gemini-2.0-flash"
    },
    "deepseek": {
        "auth_key": "your-deepseek-key",
        "model": "deepseek-chat"
    },
    "ant_claude": {
        "auth_key": "your-claude-key",
        "model": "claude-3-5-sonnet-20241022"
    },
    "huggingface": {
        "auth_token": "hf_your-token",
        "model": "Helsinki-NLP/opus-mt-it-en"
    },
    "zeroone_ai": {
        "auth_key": "your-01ai-key",
        "model": "yi-lightning"
    },
    "kimi": {
        "auth_key": "your-moonshot-key",
        "model": "moonshot-v1-8k"
    },
    "qwen": {
        "auth_key": "your-dashscope-key",
        "model": "qwen-turbo"
    }
}
JSON;
    echo $configExample . "\n";

    Console::section("TEST COMPLETED");
    Console::success("AI Providers test completed!");

} catch (\Exception $e) {
    Console::error("CRITICAL ERROR: " . $e->getMessage());
    Console::error("Stack trace:");
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
