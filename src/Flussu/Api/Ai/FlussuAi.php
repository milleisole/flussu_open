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
 * CLASS-NAME:       Flussu AI interface ("modello flussu") - v2.0
 * CREATED DATE:     25.06.2026 - Flussu v5.0
 * VERSION REL.:     5.0 -def- 20260625
 * UPDATE DATE:      25.06:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * "Modello flussu": riferimento interno STABILE per chi sviluppa
 * applicazioni Flussu (operazioni agentiche piccole, economiche),
 * text-only/agentico. Vision, immagini e web sono redirezionati da
 * Platform ad altri provider (resolveImageProvider/resolveWebProvider).
 *
 * v2.0 — Backend selezionabile via "inherit":
 *   - "inherit" sceglie il tipo di backend e ne CONDIVIDE la chiave API
 *     e l'endpoint: "zai_glm" | "qwen" | "deepseek". La auth_key viene
 *     letta dal blocco del backend (es. services.ai_provider.zai_glm.auth_key).
 *   - MODELLO e TEMPERATURA sono propri di "flussu" (model/chat-model,
 *     default_temperature); il modello, se assente, ricade su quello del
 *     backend ereditato.
 * Esempio:
 *   "flussu": {
 *     "inherit": "zai_glm",
 *     "model": "GLM-4.7-FlashX",
 *     "default_temperature": 0.6
 *   }
 * Reasoning disattivato di default (latenza minima); riattivabile con
 * "thinking": true. "base-uri" opzionale per endpoint custom.
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
class FlussuAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_flussu_key="";
    private $_flussu_model="";
    private $_flussu_chat_model="";
    private $_baseUri="";
    private $_thinkingDialect="none"; // 'zai' | 'qwen' | 'none'
    private $client;

    public function canBrowseWeb(){
        return false;
    }
    public function __construct($model="",$chat_model=""){
        $inherit = strtolower(trim((string) config("services.ai_provider.flussu.inherit", "")));
        if ($inherit === "")
            throw new Exception("Flussu AI backend not configured. Set 'inherit' (e.g. \"zai_glm\", \"qwen\", \"deepseek\") in config services.ai_provider.flussu");
        if ($inherit === "flussu")
            throw new Exception("Flussu AI: 'inherit' cannot be \"flussu\" (would recurse). Point it to a real backend.");

        // 'inherit' seleziona endpoint di default, dialetto reasoning e il
        // blocco config da cui CONDIVIDERE la chiave API.
        [$defaultBase, $dialect, $configKey] = $this->_resolveDialect($inherit);
        $this->_thinkingDialect = $dialect;

        // chiave CONDIVISA col backend in 'inherit'
        $this->_flussu_key = (string) config("services.ai_provider.{$configKey}.auth_key", "");
        if ($this->_flussu_key === "")
            throw new Exception("Flussu AI API key not configured. Set 'auth_key' in config services.ai_provider." . $configKey . " (shared with the inherited backend \"" . $inherit . "\")");

        // modello PROPRIO di flussu (ctor arg > flussu.model > modello del backend ereditato)
        $this->_flussu_model = $model ?: (string) config("services.ai_provider.flussu.model", "");
        if ($this->_flussu_model === "")
            $this->_flussu_model = (string) config("services.ai_provider.{$configKey}.model", "");
        $this->_flussu_chat_model = $chat_model ?: (string) config("services.ai_provider.flussu.chat-model", $this->_flussu_model);
        if ($this->_flussu_chat_model === "")
            $this->_flussu_chat_model = (string) config("services.ai_provider.{$configKey}.chat-model", $this->_flussu_model);
        if ($this->_flussu_chat_model === "")
            throw new Exception("Flussu AI model not configured. Set 'model' in config services.ai_provider.flussu");

        // 'base-uri' esplicito ha la precedenza (consente endpoint custom)
        $this->_baseUri = (string) config("services.ai_provider.flussu.base-uri", "");
        if ($this->_baseUri === "")
            $this->_baseUri = $defaultBase;
        if ($this->_baseUri === "")
            throw new Exception("Flussu AI: unknown 'inherit' \"" . $inherit . "\" and no 'base-uri' set. Provide an explicit 'base-uri' in config services.ai_provider.flussu");

        $this->client = new Client([
            'base_uri' => $this->_baseUri,
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Maps the 'inherit' identifier to [default base_uri, reasoning-dialect, configKey].
     * All three backends are OpenAI-compatible (chat/completions); they differ
     * only in endpoint and in HOW the reasoning/thinking mode is disabled.
     * configKey is the config block whose auth_key is shared (e.g. "zai_glm").
     */
    private function _resolveDialect(string $inherit): array {
        switch ($inherit) {
            case 'zai_glm': case 'zai': case 'glm':
                return ['https://api.z.ai/api/paas/v4/', 'zai', 'zai_glm'];
            case 'qwen':
                return ['https://dashscope-intl.aliyuncs.com/compatible-mode/v1/', 'qwen', 'qwen'];
            case 'deepseek':
                return ['https://api.deepseek.com/v1/', 'none', 'deepseek'];
            default:
                // unknown backend: require an explicit base-uri; share the auth_key
                // from a config block named exactly like 'inherit'.
                return ['', 'none', $inherit];
        }
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
            'model' => $this->_flussu_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000,
            'temperature' => (float) Config::init()->aiTemperature('flussu')
        ];
        // "Modello flussu" è pensato per operazioni agentiche piccole e VELOCI:
        // disattiviamo il reasoning di default. Il parametro dipende dal dialetto
        // del backend ('inherit'). Override da config:
        //   flussu.thinking = true | "enabled" -> reasoning attivo
        //   flussu.thinking = "none" | "omit"  -> non invia alcun parametro
        $thinkingCfg = config("services.ai_provider.flussu.thinking");
        if ($thinkingCfg !== 'none' && $thinkingCfg !== 'omit') {
            $enabled = ($thinkingCfg === true || $thinkingCfg === 'enabled');
            switch ($this->_thinkingDialect) {
                case 'zai':
                    $payload['thinking'] = ['type' => $enabled ? 'enabled' : 'disabled'];
                    break;
                case 'qwen':
                    $payload['enable_thinking'] = $enabled;
                    break;
                // 'none': il backend non espone un toggle di reasoning, niente da inviare
            }
        }
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_flussu_key,
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
                    'model' => $this->_flussu_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Flussu AI response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // "Modello flussu" è text-only: vision redirezionato ad altri provider via Platform.
    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        return [[], "Error: media analysis not supported by Flussu AI (text-only model)", null];
    }

    // "Modello flussu" è text-only: generazione immagini redirezionata via Platform.
    public function canGenerateImages(): bool { return false; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by Flussu AI (text-only model)"];
    }

    // Nessun web nativo: la web-research è redirezionata da Platform::resolveWebProvider().
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
