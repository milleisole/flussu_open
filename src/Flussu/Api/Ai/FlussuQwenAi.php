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
 * CLASS-NAME:       Flussu Qwen (Alibaba) interface - v1.0
 * CREATED DATE:     26.01.2026 - Claude - Flussu v4.5
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

class FlussuQwenAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_qwen_ai;
    private $_qwen_ai_key="";
    private $_qwen_ai_model="";
    private $_qwen_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return true;
    }

    public function __construct($model="", $chat_model=""){
        if (!isset($this->_qwen_ai)){
            $this->_qwen_ai_key = config("services.ai_provider.qwen.auth_key");
            if (empty($this->_qwen_ai_key))
                throw new Exception("Qwen API key not configured. Set 'auth_key' in config services.ai_provider.qwen");
            if ($model)
                $this->_qwen_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.qwen.model")))
                    $this->_qwen_ai_model=config("services.ai_provider.qwen.model");
            }
            if ($chat_model)
                $this->_qwen_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.qwen.chat-model")))
                    $this->_qwen_chat_model=config("services.ai_provider.qwen.chat-model");
            }
            $this->client = new Client([
                'base_uri' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
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
            'model' => $this->_qwen_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000,
            'temperature' => (float) Config::init()->aiTemperature('qwen')
        ];
        // [DIAG_QWEN_v1] outbound payload introspection
        $diagRoles = [];
        foreach ($arrayText as $i => $m) {
            $role = $m['role'] ?? '?';
            $content = (string)($m['content'] ?? '');
            $diagRoles[] = $role . '(' . strlen($content) . 'c)';
            General::Log_nocaller('[DIAG_QWEN_v1] msg[' . $i . '] role=' . $role
                . ' content[0..200]=' . substr($content, 0, 200), true);
        }
        General::Log_nocaller('[DIAG_QWEN_v1] payload model=' . $this->_qwen_chat_model
            . ' temp=' . $payload['temperature']
            . ' msg_count=' . count($arrayText)
            . ' roles=[' . implode(',', $diagRoles) . ']', true);
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_qwen_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json'=>$payload
            ]);
            $data=$response->getBody();
            // [DIAG_QWEN_v1] inbound response
            General::Log_nocaller('[DIAG_QWEN_v1] http_status=' . $response->getStatusCode()
                . ' body[0..600]=' . substr((string)$data, 0, 600), true);

            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data, null];

            $data = json_decode($data, true);
            // Extract token usage if available
            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_qwen_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Qwen response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // v4.5.2 - AI Media Exchange: not supported by Qwen
    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        return [[], "Error: media analysis not supported by Qwen", null];
    }
    // v4.5.2 - Image Generation via DashScope text2image (async submit + poll)
    public function canGenerateImages(): bool { return true; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $imageModel = config("services.ai_provider.qwen.image-model");
        if (empty($imageModel))
            $imageModel = "wan2.2-t2i-flash";

        // DashScope expects "WIDTH*HEIGHT" instead of "WIDTHxHEIGHT"
        $dsSize = str_replace('x', '*', $size);

        $base = 'https://dashscope-intl.aliyuncs.com/api/v1/';
        $rest = new Client(['timeout' => 10.0]);
        $authHeaders = [
            'Authorization' => 'Bearer ' . $this->_qwen_ai_key,
            'Content-Type'  => 'application/json',
            'X-DashScope-Async' => 'enable',
        ];
        $payload = [
            'model' => $imageModel,
            'input' => [ 'prompt' => $prompt ],
            'parameters' => [ 'size' => $dsSize, 'n' => 1 ],
        ];
        try {
            $resp = $rest->post($base . 'services/aigc/text2image/image-synthesis', [
                'headers' => $authHeaders,
                'timeout' => 60,
                'connect_timeout' => 20,
                'json' => $payload,
            ]);
            $body = json_decode($resp->getBody(), true);
        } catch (\Throwable $e) {
            return ["error" => "Qwen image submit failed: " . $e->getMessage()];
        }

        $taskId = $body['output']['task_id'] ?? null;
        if (empty($taskId))
            return ["error" => "Qwen did not return task_id. Details: " . json_encode($body)];

        // poll task status (max ~120s)
        $deadline = time() + 120;
        $imageUrl = null;
        while (time() < $deadline) {
            sleep(2);
            try {
                $poll = $rest->get($base . 'tasks/' . rawurlencode($taskId), [
                    'headers' => [ 'Authorization' => 'Bearer ' . $this->_qwen_ai_key ],
                    'timeout' => 30,
                ]);
                $pd = json_decode($poll->getBody(), true);
            } catch (\Throwable $e) {
                return ["error" => "Qwen polling failed: " . $e->getMessage()];
            }
            $status = $pd['output']['task_status'] ?? '';
            if ($status === 'SUCCEEDED') {
                $imageUrl = $pd['output']['results'][0]['url'] ?? null;
                break;
            }
            if (in_array($status, ['FAILED','CANCELED','UNKNOWN'], true))
                return ["error" => "Qwen task " . $status . ": " . json_encode($pd)];
        }

        if (empty($imageUrl))
            return ["error" => "Qwen image generation timed out"];

        try {
            $http = new Client(['timeout' => 60, 'connect_timeout' => 20]);
            $resp = $http->get($imageUrl, ['http_errors' => false]);
            if ($resp->getStatusCode() !== 200) {
                General::addRowLog("Qwen URL fetch HTTP " . $resp->getStatusCode() . " for " . $imageUrl);
                return ["error" => "Qwen image fetch failed (HTTP " . $resp->getStatusCode() . ") for URL: " . $imageUrl];
            }
            $bin = (string)$resp->getBody();
        } catch (\Throwable $e) {
            General::addRowLog("Qwen URL fetch exception (" . $e->getMessage() . ") for " . $imageUrl);
            return ["error" => "Qwen image fetch failed: " . $e->getMessage()];
        }

        return [
            "b64_data" => base64_encode($bin),
            "revised_prompt" => $prompt,
            "tokenUsage" => [
                'model'  => $imageModel,
                'input'  => 0,
                'output' => 1,
            ],
        ];
    }

    /**
     * v5.x — Native web research via Qwen DashScope `enable_search`.
     * Returns [history, replyText, tokenUsage] like chat().
     */
    function chat_WebPreview($sendText, $session="", $max_output_tokens=1024, $temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('qwen');
        }
        $messages = [['role' => 'user', 'content' => $sendText]];
        $payload = [
            'model'         => $this->_qwen_chat_model,
            'messages'      => $messages,
            'max_tokens'    => $max_output_tokens,
            'temperature'   => $temperature,
            'enable_search' => true,
            'search_options'=> [
                'forced_search'   => true,
                'enable_citation' => true,
                'citation_format' => '[<number>]',
            ],
        ];
        try {
            $resp = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_qwen_ai_key,
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
            return [$messages, "Error: Qwen web search failed: " . $e->getMessage(), null];
        }
        if ($code !== 200 || !is_array($data)) {
            return [$messages, "Error: Qwen HTTP $code: " . substr($raw, 0, 500), null];
        }

        $textOut = trim((string)($data['choices'][0]['message']['content'] ?? ''));

        $citations = [];
        $searchInfo = $data['choices'][0]['message']['search_results']
            ?? ($data['search_info']['search_results'] ?? []);
        if (is_array($searchInfo)) {
            foreach ($searchInfo as $hit) {
                $url = $hit['url']  ?? null;
                $title = $hit['title'] ?? '';
                if (!empty($url) && !isset($citations[$url])) {
                    $citations[$url] = $title !== '' ? $title : $url;
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
            'model'  => $this->_qwen_chat_model,
            'input'  => $data['usage']['prompt_tokens']     ?? 0,
            'output' => $data['usage']['completion_tokens'] ?? 0,
        ];
        return [$messages, $textOut, $tokenUsage];
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
