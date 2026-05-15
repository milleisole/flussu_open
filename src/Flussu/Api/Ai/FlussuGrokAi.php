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
 * TBD- UNFINISHED
 * 
 * CLASS-NAME:       Flussu Grok interface - v1.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
class FlussuGrokAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_grok_ai;
    private $_grok_ai_key="";
    private $_grok_ai_model="";
    private $_grok_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return true;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_grok_ai)){
            $this->_grok_ai_key = config("services.ai_provider.xai_grok.auth_key");
            if (empty($this->_grok_ai_key))
                throw new Exception("Grok API key not configured. Set 'auth_key' in config services.ai_provider.xai_grok");
            if ($model)
                $this->_grok_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.xai_grok.model")))
                    $this->_grok_ai_model=config("services.ai_provider.xai_grok.model");
            }
            if ($chat_model)
                $this->_grok_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.xai_grok.chat-model")))
                    $this->_grok_chat_model=config("services.ai_provider.xai_grok.chat-model");
                else
                    $this->_grok_chat_model = $this->_grok_ai_model;
            }
            $this->client = new Client([
                'base_uri' => 'https://api.x.ai/v1/',
                'timeout'  => 10.0,
            ]);

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
            'model' => $this->_grok_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000,
            'temperature' => (float) Config::init()->aiTemperature('xai_grok')
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_grok_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120, // Total timeout in seconds
                'connect_timeout' => 20, // Connection timeout in seconds
                'json'=>$payload
            ]);
            $data=$response->getBody();

            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data, null];

            $data = json_decode($data, true);
            // Extract token usage if available
            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_grok_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Grok response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }
    // v4.5.2 - AI Media Exchange: not supported by Grok
    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        return [[], "Error: media analysis not supported by Grok", null];
    }
    // v4.5.2 - Image Generation via grok-2-image (OpenAI-compatible images endpoint)
    public function canGenerateImages(): bool { return true; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $imageModel = config("services.ai_provider.xai_grok.image-model");
        if (empty($imageModel))
            $imageModel = "grok-imagine-image";

        // grok-2-image does not currently accept size/quality params; ignore them
        $payload = [
            'model'  => $imageModel,
            'prompt' => $prompt,
            'n'      => 1,
            'response_format' => 'b64_json',
        ];
        try {
            $response = $this->client->post('images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_grok_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json' => $payload,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $raw  = (string)$response->getBody();
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            General::addRowLog("Grok image transport error: " . $e->getMessage());
            return ["error" => "Grok image generation failed: " . $e->getMessage()];
        }

        if ($code !== 200) {
            General::addRowLog("Grok image HTTP $code body=" . substr($raw, 0, 500));
            return ["error" => "Grok HTTP $code: " . substr($raw, 0, 500)];
        }

        $first = $data['data'][0] ?? null;
        if (!$first) {
            General::addRowLog("Grok image empty data: " . substr($raw, 0, 500));
            return ["error" => "No image data returned by Grok. Body: " . substr($raw, 0, 500)];
        }

        $tokenUsage = [
            'model'  => $imageModel,
            'input'  => 0,
            'output' => 1,
        ];
        if (!empty($first['b64_json']))
            return [
                "b64_data" => $first['b64_json'],
                "revised_prompt" => $first['revised_prompt'] ?? $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        if (!empty($first['url'])) {
            $bin = $this->_fetchUrl($first['url']);
            if ($bin === null) {
                General::addRowLog("Grok URL fetch failed: " . $first['url']);
                return ["error" => "Grok returned URL but fetch failed: " . $first['url']];
            }
            return [
                "b64_data" => base64_encode($bin),
                "revised_prompt" => $first['revised_prompt'] ?? $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        }
        General::addRowLog("Grok image no url/b64: " . substr($raw, 0, 500));
        return ["error" => "No image data returned by Grok"];
    }

    /**
     * v5.x — Native web research via xAI Grok Live Search (search_parameters).
     * Returns [history, replyText, tokenUsage] like chat().
     */
    function chat_WebPreview($sendText, $session="", $max_output_tokens=1024, $temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('xai_grok');
        }
        $messages = [['role' => 'user', 'content' => $sendText]];
        $payload = [
            'model'       => $this->_grok_chat_model,
            'messages'    => $messages,
            'max_tokens'  => $max_output_tokens,
            'temperature' => $temperature,
            'search_parameters' => [
                'mode'                => 'auto',
                'max_search_results'  => 5,
                'return_citations'    => true,
            ],
        ];
        try {
            $resp = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_grok_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout'         => 120,
                'connect_timeout' => 20,
                'json'            => $payload,
                'http_errors'     => false,
            ]);
            $code = $resp->getStatusCode();
            $raw  = (string)$resp->getBody();
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            return [$messages, "Error: Grok web search failed: " . $e->getMessage(), null];
        }
        if ($code !== 200 || !is_array($data)) {
            return [$messages, "Error: Grok HTTP $code: " . substr($raw, 0, 500), null];
        }

        $textOut = (string)($data['choices'][0]['message']['content'] ?? '');
        $textOut = trim($textOut);

        $citations = [];
        if (!empty($data['citations']) && is_array($data['citations'])) {
            foreach ($data['citations'] as $u) {
                if (is_string($u) && $u !== '' && !isset($citations[$u])) {
                    $citations[$u] = $u;
                }
            }
        }
        if (!empty($citations)) {
            $lines = ["", "---", "> **Fonti:**"];
            $i = 1;
            foreach ($citations as $u => $t) {
                $lines[] = "> [$i] [$t]($u)";
                $i++;
            }
            $textOut .= "\n" . implode("\n", $lines);
        }

        $tokenUsage = [
            'model'  => $this->_grok_chat_model,
            'input'  => $data['usage']['prompt_tokens']     ?? 0,
            'output' => $data['usage']['completion_tokens'] ?? 0,
        ];
        return [$messages, $textOut, $tokenUsage];
    }

    private function _fetchUrl(string $url): ?string {
        try {
            $http = new Client(['timeout' => 60, 'connect_timeout' => 20]);
            $resp = $http->get($url, ['http_errors' => false]);
            if ($resp->getStatusCode() !== 200) {
                General::addRowLog("URL fetch HTTP " . $resp->getStatusCode() . " for " . $url);
                return null;
            }
            return (string)$resp->getBody();
        } catch (\Throwable $e) {
            General::addRowLog("URL fetch exception (" . $e->getMessage() . ") for " . $url);
            return null;
        }
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