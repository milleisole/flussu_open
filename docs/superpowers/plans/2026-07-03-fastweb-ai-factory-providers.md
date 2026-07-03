# Fastweb AI Factory Providers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose three Fastweb AI Factory models (MIIA-7B, Qwen3.6-35B, gpt-oss-120b) as Flussu AI providers via one parameterized class and three new Platform enum entries (30/31/32).

**Architecture:** A single `FlussuFastwebAi` class (modeled on `FlussuMistralAi`: raw Guzzle, OpenAI-compatible `chat/completions`) constructed with the config key (`fw_27001_miia` / `fw_27001_qwen` / `fw_27001_gpt`). Three `Platform` enum entries map to those keys; the existing resolvers redirect image-gen → TOGETHER and web → GEMINI; the existing small-model gate handles MIIA's `"small": true` with no new code. Vision is per-block opt-in via a `vision` config flag.

**Tech Stack:** PHP 8.1+, GuzzleHTTP, existing `IAiProvider` contract, `config()` helper. No PHPUnit — verification via `php -l`, `php -r` smoke checks and a manual CLI test script (project convention).

**Spec:** `docs/superpowers/specs/2026-07-03-fastweb-ai-factory-providers-design.md`

---

## File map

| File | Action | Responsibility |
|---|---|---|
| `src/Flussu/Api/Ai/FlussuFastwebAi.php` | Create | The one provider class, parameterized by config key |
| `src/Flussu/Controllers/Platform.php` | Modify | Enum cases 30/31/32 + `configKey()` + both resolvers |
| `src/Flussu/Controllers/AiChatController.php` | Modify | 3 switch cases + `use` import |
| `src/Flussu/Controllers/AiMediaController.php` | Modify | 3 switch cases + `use` import |
| `config/.services.json.sample` | Modify | 3 example blocks with vendor-prefixed model ids |
| `src/Flussu/Test/test_fastweb_providers.php` | Create | Manual CLI test: chat + vision probe per model |

---

### Task 1: `FlussuFastwebAi` provider class

**Files:**
- Create: `src/Flussu/Api/Ai/FlussuFastwebAi.php`

- [ ] **Step 1.1: Write the class**

Full content of `src/Flussu/Api/Ai/FlussuFastwebAi.php`:

```php
<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0- Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * CLASS-NAME:       Flussu Fastweb AI Factory interface - v1.0
 * CREATED DATE:     03.07.2026 - Flussu v5.0
 * VERSION REL.:     5.0 -def- 20260703
 * UPDATE DATE:      03.07.2026 - Aldus
 * --------------------------------------------------------------------*
 * One class for every model hosted on the Fastweb AI Factory
 * (ISO-27001 pod): the endpoint is OpenAI-compatible and identical for
 * all models, so the instance is parameterized by its config key
 * (services.ai_provider.<configKey>): fw_27001_miia, fw_27001_qwen,
 * fw_27001_gpt, or any future block. The 'model'/'chat-model' values
 * must carry the full vendor-prefixed id (e.g. "openai/gpt-oss-120b").
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Exception;
use GuzzleHttp\Client;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;

class FlussuFastwebAi implements IAiProvider
{
    private const DEFAULT_BASE_URI = 'https://bpod1.ai-factory.fastweb.it/v1/';

    private string $_configKey;
    private $_fw_key = "";
    private $_fw_model = "";
    private $_fw_chat_model = "";
    private bool $_fw_vision = false;
    private int $_fw_max_tokens = 2000;
    private $client;

    public function canBrowseWeb(){
        return false;
    }

    public function __construct(string $configKey, $model="", $chat_model=""){
        $this->_configKey = $configKey;
        $cfg = "services.ai_provider.".$configKey;
        $this->_fw_key = config($cfg.".auth_key");
        if (empty($this->_fw_key))
            throw new Exception("Fastweb AI Factory API key not configured. Set 'auth_key' in config ".$cfg);
        if ($model)
            $this->_fw_model = $model;
        else {
            $this->_fw_model = config($cfg.".model");
            if (empty($this->_fw_model))
                throw new Exception("Fastweb AI Factory model not configured. Set 'model' (vendor-prefixed, e.g. 'openai/gpt-oss-120b') in config ".$cfg);
        }
        if ($chat_model)
            $this->_fw_chat_model = $chat_model;
        else {
            if (!empty(config($cfg.".chat-model")))
                $this->_fw_chat_model = config($cfg.".chat-model");
            else
                $this->_fw_chat_model = $this->_fw_model;
        }
        $this->_fw_vision = (bool) config($cfg.".vision");
        if (!empty(config($cfg.".max_tokens")))
            $this->_fw_max_tokens = (int) config($cfg.".max_tokens");
        $baseUri = config($cfg.".base-uri");
        if (empty($baseUri))
            $baseUri = self::DEFAULT_BASE_URI;
        if (substr($baseUri, -1) !== '/')
            $baseUri .= '/';
        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout'  => 10.0,
        ]);
    }

    function chat($preChat,$sendText,$role="user"){
        foreach ($preChat as &$message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["message"]);
            }
        }
        unset($message);
        $preChat[]= [
            'role' => $role,
            'content' => $sendText,
        ];
        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        $payload = [
            'model' => $this->_fw_chat_model,
            'messages' => $arrayText,
            'max_tokens' => $this->_fw_max_tokens,
            'temperature' => (float) Config::init()->aiTemperature($this->_configKey),
            'stream' => false
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_fw_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json'=>$payload
            ]);
            $data=$response->getBody();

            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data, null];

            $data = json_decode($data, true);
            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_fw_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Fastweb AI response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // Vision is per-model on the AI Factory: opt-in via the 'vision' flag
    // in the config block, to be set after a live probe of the endpoint.
    public function canAnalyzeMedia(): bool { return $this->_fw_vision; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        if (!$this->_fw_vision)
            return [[], "Error: model '".$this->_fw_chat_model."' does not accept media (config '".$this->_configKey."' has vision disabled)", null];
        try {
            $mimeTypes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png', 'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];

            foreach ($preChat as &$message) {
                if (isset($message["message"]) && !isset($message["content"])) {
                    $message["content"] = $message["message"];
                    unset($message["message"]);
                }
            }
            unset($message);

            // accept one path (string) or several (array); attach every
            // readable image so the model sees them all in a single turn.
            $paths = is_array($mediaPath) ? array_values($mediaPath) : [$mediaPath];
            $contentParts = [['type' => 'text', 'text' => $prompt]];
            foreach ($paths as $p) {
                if (!file_exists($p)) continue;
                $imageData = @file_get_contents($p);
                if ($imageData === false) continue;
                $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';
                $contentParts[] = ['type' => 'image_url', 'image_url' => [
                    'url' => "data:{$mimeType};base64," . base64_encode($imageData)
                ]];
            }
            if (count($contentParts) < 2) {
                return [[], "Error: no readable image among: " . implode(', ', $paths), null];
            }

            $preChat[] = [
                'role' => $role,
                'content' => $contentParts,
            ];

            $payload = [
                'model' => $this->_fw_model,
                'messages' => $preChat,
                'max_tokens' => $this->_fw_max_tokens,
                'temperature' => (float) Config::init()->aiTemperature($this->_configKey)
            ];

            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_fw_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json' => $payload
            ]);

            $data = json_decode($response->getBody(), true);

            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_fw_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return [$preChat, $data['choices'][0]['message']['content'], $tokenUsage];
            }

            return [[], "Error: no Fastweb AI vision response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [[], "Error: Fastweb AI vision failed. Details: " . $e->getMessage(), null];
        }
    }

    public function canGenerateImages(): bool { return false; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by Fastweb AI Factory models"];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        return [];
    }
}
 /*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS |
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/
```

- [ ] **Step 1.2: Syntax check**

Run: `php -l src/Flussu/Api/Ai/FlussuFastwebAi.php`
Expected: `No syntax errors detected`

- [ ] **Step 1.3: Constructor smoke test (uses the real config on this machine)**

Run:
```bash
php -r '
require "vendor/autoload.php";
use Flussu\Api\Ai\FlussuFastwebAi;
foreach (["fw_27001_miia","fw_27001_qwen","fw_27001_gpt"] as $k) {
    try { new FlussuFastwebAi($k); echo "[OK] $k constructed\n"; }
    catch (\Throwable $e) { echo "[FAIL] $k: ".$e->getMessage()."\n"; }
}
try { new FlussuFastwebAi("fw_no_such_key"); echo "[FAIL] missing key did NOT throw\n"; }
catch (\Throwable $e) { echo "[OK] missing key throws: ".$e->getMessage()."\n"; }
'
```
Expected: three `[OK] ... constructed` lines and one `[OK] missing key throws`. (Requires the user's `.env` + `/config/.services.json` on this machine, already present.)

- [ ] **Step 1.4: Commit**

```bash
git add src/Flussu/Api/Ai/FlussuFastwebAi.php
git commit -m "feat - ai provider: FlussuFastwebAi, one class for Fastweb AI Factory models (config-key parameterized)"
```

---

### Task 2: Platform enum entries 30/31/32

**Files:**
- Modify: `src/Flussu/Controllers/Platform.php` (cases after L34; `resolveImageProvider` L51-64; `resolveWebProvider` L78-92; `configKey` L124-143)

- [ ] **Step 2.1: Add the enum cases**

After `case FLUSSU = 99;` keep FLUSSU last is not required — cases are declared in numeric-comment order, so insert BEFORE the FLUSSU line:

```php
    case TOGETHER = 12;  // v5.0 - Together.ai - chat (Llama, Mixtral, ...) + image generation (FLUX.1)
    case FW_MIIA = 30;   // v5.0 - Fastweb AI Factory (ISO-27001 pod) - FastwebMIIA-7B, small model
    case FW_QWEN = 31;   // v5.0 - Fastweb AI Factory (ISO-27001 pod) - Qwen3.6-35B-A3B-FP8
    case FW_GPT = 32;    // v5.0 - Fastweb AI Factory (ISO-27001 pod) - gpt-oss-120b
    case FLUSSU = 99;    // v5.0 - "modello flussu" - chat/agentico text-only economico su z.ai (GLM)
```

- [ ] **Step 2.2: Redirect image generation to TOGETHER**

In `resolveImageProvider()`, extend the match arm (and its docblock list "Provider redirected → TOGETHER"):

```php
            self::MISTRAL,
            self::FW_MIIA,
            self::FW_QWEN,
            self::FW_GPT,
            self::FLUSSU     => self::TOGETHER,
```

- [ ] **Step 2.3: Redirect web research to GEMINI**

In `resolveWebProvider()`, extend the match arm (and its docblock list):

```php
            self::TOGETHER,
            self::FW_MIIA,
            self::FW_QWEN,
            self::FW_GPT,
            self::FLUSSU    => self::GEMINI,
```

- [ ] **Step 2.4: Map config keys**

In `configKey()`, before the `self::FLUSSU` line:

```php
            self::TOGETHER    => 'together',
            self::FW_MIIA     => 'fw_27001_miia',
            self::FW_QWEN     => 'fw_27001_qwen',
            self::FW_GPT      => 'fw_27001_gpt',
            self::FLUSSU      => 'flussu',
```

- [ ] **Step 2.5: Verify**

Run:
```bash
php -l src/Flussu/Controllers/Platform.php && php -r '
require "vendor/autoload.php";
use Flussu\Controllers\Platform;
foreach ([30=>"fw_27001_miia",31=>"fw_27001_qwen",32=>"fw_27001_gpt"] as $v=>$key) {
    $p = Platform::tryFrom($v);
    assert($p !== null, "Platform $v missing");
    assert($p->configKey() === $key, "configKey mismatch for $v");
    assert($p->resolveImageProvider() === Platform::TOGETHER, "img resolver for $v");
    assert($p->resolveWebProvider() === Platform::GEMINI, "web resolver for $v");
    echo "[OK] $v -> $key\n";
}
'
```
Expected: `No syntax errors detected` + three `[OK]` lines, no assertion output.

- [ ] **Step 2.6: Commit**

```bash
git add src/Flussu/Controllers/Platform.php
git commit -m "feat - Platform: FW_MIIA=30 FW_QWEN=31 FW_GPT=32 (Fastweb AI Factory) + configKey/resolver wiring"
```

---

### Task 3: Controller wiring

**Files:**
- Modify: `src/Flussu/Controllers/AiChatController.php` (`use` block ~L48; switch, after `case Platform::TOGETHER` ~L118-121)
- Modify: `src/Flussu/Controllers/AiMediaController.php` (`use` block ~L42; switch, after `case Platform::TOGETHER` ~L91-93)

- [ ] **Step 3.1: AiChatController**

Add to the imports (after `use Flussu\Api\Ai\FlussuAi;`):

```php
use Flussu\Api\Ai\FlussuFastwebAi;
```

Add to the constructor switch, after the `Platform::TOGETHER` case and before `Platform::FLUSSU`:

```php
            case Platform::FW_MIIA:
                $this->_aiClient= new FlussuFastwebAi('fw_27001_miia', $model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::FW_QWEN:
                $this->_aiClient= new FlussuFastwebAi('fw_27001_qwen', $model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::FW_GPT:
                $this->_aiClient= new FlussuFastwebAi('fw_27001_gpt', $model, $chat_model);
                $this->_linkify=0;
                break;
```

- [ ] **Step 3.2: AiMediaController**

Add to the imports (after `use Flussu\Api\Ai\FlussuTogetherAi;`):

```php
use Flussu\Api\Ai\FlussuFastwebAi;
```

Add to the constructor switch, after the `Platform::TOGETHER` case and before `default:`:

```php
            case Platform::FW_MIIA:
                $this->_aiClient = new FlussuFastwebAi('fw_27001_miia', $model, $chat_model);
                break;
            case Platform::FW_QWEN:
                $this->_aiClient = new FlussuFastwebAi('fw_27001_qwen', $model, $chat_model);
                break;
            case Platform::FW_GPT:
                $this->_aiClient = new FlussuFastwebAi('fw_27001_gpt', $model, $chat_model);
                break;
```

- [ ] **Step 3.3: Verify**

Run:
```bash
php -l src/Flussu/Controllers/AiChatController.php && php -l src/Flussu/Controllers/AiMediaController.php && php -r '
require "vendor/autoload.php";
use Flussu\Controllers\AiChatController;
use Flussu\Controllers\AiMediaController;
use Flussu\Controllers\Platform;
foreach ([Platform::FW_MIIA, Platform::FW_QWEN, Platform::FW_GPT] as $p) {
    new AiChatController($p);
    new AiMediaController($p);
    echo "[OK] controllers built for ".$p->name."\n";
}
'
```
Expected: two `No syntax errors detected` + three `[OK]` lines.

- [ ] **Step 3.4: Commit**

```bash
git add src/Flussu/Controllers/AiChatController.php src/Flussu/Controllers/AiMediaController.php
git commit -m "feat - controllers: wire Fastweb AI Factory platforms (30/31/32) to FlussuFastwebAi"
```

---

### Task 4: Config sample blocks

**Files:**
- Modify: `config/.services.json.sample` — inside `services.ai_provider`, after the `"flussu"` block (~L172-179), before `"web_research"`.

- [ ] **Step 4.1: Add the three blocks**

```json
      "fw_27001_miia":{
        "_comment":"Fastweb AI Factory (pod ISO-27001, endpoint OpenAI-compatible condiviso dai blocchi fw_27001_*). 'model'/'chat-model' DEVONO avere il prefisso vendor completo. 'base-uri' opzionale (default bpod1 nel codice). 'vision' abilita analyzeMedia solo se il modello accetta davvero immagini (verificare con test_fastweb_providers.php). 'small' su MIIA blocca gli allegati e tronca il prompt.",
        "auth_key":"your-fastweb-miia-jwt-here",
        "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
        "model":"Fastweb/FastwebMIIA-7B",
        "chat-model":"Fastweb/FastwebMIIA-7B",
        "vision": false,
        "small": true
      },
      "fw_27001_qwen":{
        "auth_key":"your-fastweb-qwen-jwt-here",
        "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
        "model":"Qwen/Qwen3.6-35B-A3B-FP8",
        "chat-model":"Qwen/Qwen3.6-35B-A3B-FP8",
        "vision": true
      },
      "fw_27001_gpt":{
        "auth_key":"your-fastweb-gpt-jwt-here",
        "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
        "model":"openai/gpt-oss-120b",
        "chat-model":"openai/gpt-oss-120b",
        "vision": false
      },
```

(Indentation: 6 spaces for the block key, matching the sibling blocks. Mind the trailing comma chain — the previous sibling block must end with `,`.)

- [ ] **Step 4.2: Validate JSON**

Run: `php -r 'json_decode(file_get_contents("config/.services.json.sample"), true, 512, JSON_THROW_ON_ERROR); echo "valid JSON\n";'`
Expected: `valid JSON`

- [ ] **Step 4.3: Commit**

```bash
git add config/.services.json.sample
git commit -m "docs - config sample: fw_27001_miia/qwen/gpt blocks (Fastweb AI Factory, vendor-prefixed model ids)"
```

---

### Task 5: CLI test script + live run

**Files:**
- Create: `src/Flussu/Test/test_fastweb_providers.php`

- [ ] **Step 5.1: Write the test script**

```php
<?php
/* --------------------------------------------------------------------*
 * Test Fastweb AI Factory providers (FW_MIIA / FW_QWEN / FW_GPT)
 * Chat round-trip + vision probe per ogni modello. Manual CLI test.
 * --------------------------------------------------------------------*
 * CREATED DATE:     03.07.2026 - Flussu v5.0
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Api\Ai\FlussuFastwebAi;
use Flussu\Controllers\Platform;

$models = [
    'fw_27001_miia' => Platform::FW_MIIA,
    'fw_27001_qwen' => Platform::FW_QWEN,
    'fw_27001_gpt'  => Platform::FW_GPT,
];

// 2x2 red PNG for the vision probe
$tmpImg = sys_get_temp_dir() . '/fw_vision_probe.png';
file_put_contents($tmpImg, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEElEQVR4nGP8z8DwnwEKAB' .
    'UZAf1t107cAAAAAElFTkSuQmCC'
));

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
    if ($ai->canAnalyzeMedia()) {
        [$h2, $vReply, $vUsage] = $ai->analyzeMedia([], $tmpImg, "What color is this image? One word.");
        echo "[INFO] vision probe: " . substr(trim((string)$vReply), 0, 120) . "\n";
    } else {
        echo "[INFO] vision: flag OFF in config block '$cfgKey' (set \"vision\": true and re-run to probe)\n";
    }
}

@unlink($tmpImg);
echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 5.2: Syntax check**

Run: `php -l src/Flussu/Test/test_fastweb_providers.php`
Expected: `No syntax errors detected`

- [ ] **Step 5.3: Live run (hits the Fastweb endpoint with the user's real keys)**

Run: `php src/Flussu/Test/test_fastweb_providers.php`
Expected: `RESULT: 3 passed, 0 failed`; the chat replies contain "PONG". The vision lines are informative: they tell the user which blocks should get `"vision": true` in the real config.
If a model rejects the un-prefixed id still present in the user's real config, the error message will say so — that's the signal to apply the config change (Task 6).

- [ ] **Step 5.4: Commit**

```bash
git add src/Flussu/Test/test_fastweb_providers.php
git commit -m "test - fastweb providers: chat round-trip + vision probe CLI script"
```

---

### Task 6: User's real config update (manual, documented)

No repo file: `/config/.services.json` is the user's secret store. The user replaces their three current blocks with (same keys, updated content — `auth_key` values stay as already set):

```json
"fw_27001_miia":{
    "auth_key":"<il tuo JWT MIIA, invariato>",
    "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
    "model":"Fastweb/FastwebMIIA-7B",
    "chat-model":"Fastweb/FastwebMIIA-7B",
    "vision": false,
    "small": true
},
"fw_27001_qwen":{
    "auth_key":"<il tuo JWT Qwen, invariato>",
    "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
    "model":"Qwen/Qwen3.6-35B-A3B-FP8",
    "chat-model":"Qwen/Qwen3.6-35B-A3B-FP8",
    "vision": true
},
"fw_27001_gpt":{
    "auth_key":"<il tuo JWT gpt-oss, invariato>",
    "base-uri":"https://bpod1.ai-factory.fastweb.it/v1",
    "model":"openai/gpt-oss-120b",
    "chat-model":"openai/gpt-oss-120b",
    "vision": false
}
```

Changes vs what the user already wrote: vendor prefix added to `model`/`chat-model` (`Fastweb/`, `Qwen/`, `openai/`), optional `base-uri` made explicit, `vision` flags added (final values decided by the Task 5 live probe). `small: true` on MIIA kept.

- [ ] **Step 6.1:** After the user updates the config, re-run `php src/Flussu/Test/test_fastweb_providers.php` — expected `3 passed, 0 failed`.

---

## Self-review checklist (done at plan time)

- Spec coverage: §3.1→Task 1, §3.2→Task 2, §3.3→Task 3, §3.4→Task 4, §3.5→Task 5, §7→Task 6. Covered.
- No placeholders: every code step carries full content.
- Type consistency: constructor signature `(string $configKey, $model="", $chat_model="")` used identically in Tasks 1, 3, 5; config keys `fw_27001_*` identical across Tasks 2, 3, 4, 6.
