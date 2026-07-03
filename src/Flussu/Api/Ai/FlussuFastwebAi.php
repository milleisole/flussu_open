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
            return [[], "Error: model '".$this->_fw_model."' does not accept media (config '".$this->_configKey."' has vision disabled)", null];
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
                'temperature' => (float) Config::init()->aiTemperature($this->_configKey),
                'stream' => false
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
