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
 * CLASS-NAME:       Flussu OpenAi interface - v2.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * Added: Vision (GPT-4o) and Image Generation (DALL-E 3)
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use OpenAI\Resources\Batches;
use OpenAI\Resources\Completions;
use OpenAI\Resources\Models;
use OpenAI\Client;
use Log;
use Exception;
use OpenAI\OpenAI;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
class FlussuOpenAi implements IAiProvider
{
    private $_aiErrorState=false;
    private Client $_open_ai;
    private $_open_ai_key="";
    private $_open_ai_model="";
    private $_open_ai_chat_model="";
    
    public function canBrowseWeb(){
        return true;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_open_ai)){
            $this->_open_ai_key = config("services.ai_provider.open_ai.auth_key");
            if (empty($this->_open_ai_key))
                throw new Exception("OpenAI API key not configured. Set 'auth_key' in config services.ai_provider.open_ai");
            if ($model)
                $this->_open_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.open_ai.model")))
                    $this->_open_ai_model=config("services.ai_provider.open_ai.model");
            }
            if ($chat_model)
                $this->_open_ai_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.open_ai.chat-model")))
                    $this->_open_ai_chat_model=config("services.ai_provider.open_ai.chat-model");
            }
            $this->_open_ai=\OpenAI::factory()
                ->withApiKey($this->_open_ai_key)
                ->withHttpClient($httpClient = new \GuzzleHttp\Client([]))
                ->withHttpHeader('X-workflowapp', 'flussu')
                /*->withOrganization('flussu') 
                ->withProject('flussu') 
                ->withModel($this->_open_ai_model)
                ->withChatModel($this->_open_ai_chat_model)*/
                ->make();

/*

            $response = $this->_open_ai->models()->list();

            $response->object; // 'list'

            foreach ($response->data as $result) {
                $result->id; // 'gpt-3.5-turbo-instruct'
                $result->object; // 'model'
                // ...
            }
            $response->toArray(); // ['object' => 'list', 'data' => [...]]
*/
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
        try{
            $result = $this->_open_ai->chat()->create([
                'model' => $this->_open_ai_chat_model,
                'messages' => $arrayText,
                'temperature' => (float) Config::init()->aiTemperature('open_ai'),
                /*'tools' => [
                    [
                        'type' => 'web_search'
                    ]
                ],*/
                'stream'      => false
                /*,'tool_choice' => 'auto',*/
                /*'parallel_tool_calls' => true*/
            ]);
        } catch (\Throwable $e) {
            return "Error: no response. Details: " . $e->getMessage();
        }
        // Extract token usage if available
        $tokenUsage = null;
        if (isset($result->usage)) {
            $tokenUsage = [
                'model' => $this->_open_ai_chat_model,
                'input' => $result->usage->promptTokens ?? 0,
                'output' => $result->usage->completionTokens ?? 0
            ];
        }
        return [$arrayText, $result->choices[0]->message->content, $tokenUsage];
    }

    // v4.5.2 - AI Media Exchange: Vision
    public function canAnalyzeMedia(): bool {
        return true; // GPT-4o supports vision
    }

    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        foreach ($preChat as &$message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["message"]);
            }
        }
        unset($message);

        if (!file_exists($mediaPath))
            return [[], "Error: file not found at " . $mediaPath, null];

        $mimeType = mime_content_type($mediaPath);
        $fileData = file_get_contents($mediaPath);
        if ($fileData === false)
            return [[], "Error: cannot read file " . $mediaPath, null];

        $base64Data = base64_encode($fileData);
        $dataUri = "data:" . $mimeType . ";base64," . $base64Data;

        $contentParts = [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]]
        ];

        $preChat[] = [
            'role' => $role,
            'content' => $contentParts,
        ];

        return $this->_chatContinue($preChat);
    }

    // v4.5.2 - AI Media Exchange: Image Generation (DALL-E 3)
    public function canGenerateImages(): bool {
        return true;
    }

    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $imageModel = config("services.ai_provider.open_ai.image-model");
        if (empty($imageModel))
            $imageModel = "dall-e-3";

        $isGptImage = ($imageModel === 'gpt-image-1' || strpos($imageModel, 'gpt-image') === 0);

        // For gpt-image-1 use direct REST call: the openai-php/client SDK does not always
        // expose the response fields properly for this newer endpoint.
        if ($isGptImage) {
            $qMap = ['standard' => 'medium', 'hd' => 'high'];
            $payload = [
                'model'   => $imageModel,
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => $size,
                'quality' => $qMap[$quality] ?? $quality,
            ];
            try {
                $http = new \GuzzleHttp\Client(['timeout' => 120]);
                $resp = $http->post('https://api.openai.com/v1/images/generations', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->_open_ai_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $payload,
                    'http_errors' => false,
                ]);
                $code = $resp->getStatusCode();
                $raw  = (string)$resp->getBody();
                $body = json_decode($raw, true);
            } catch (\Throwable $e) {
                return ["error" => "Image generation HTTP failed: " . $e->getMessage()];
            }
            if ($code !== 200)
                return ["error" => "OpenAI image API HTTP $code: " . substr($raw, 0, 500)];
            // gpt-image-1 returns real token usage (input/output tokens for the rendering pipeline)
            $tokenUsage = [
                'model'  => $imageModel,
                'input'  => $body['usage']['input_tokens']  ?? 0,
                'output' => $body['usage']['output_tokens'] ?? 1,
            ];
            $b64 = $body['data'][0]['b64_json'] ?? null;
            if (empty($b64)) {
                if (!empty($body['data'][0]['url'])) {
                    $bin = @file_get_contents($body['data'][0]['url']);
                    if ($bin !== false)
                        return [
                            "b64_data" => base64_encode($bin),
                            "revised_prompt" => $body['data'][0]['revised_prompt'] ?? $prompt,
                            "tokenUsage" => $tokenUsage,
                        ];
                }
                return ["error" => "No image data in response. Body: " . substr($raw, 0, 500)];
            }
            return [
                "b64_data" => $b64,
                "revised_prompt" => $body['data'][0]['revised_prompt'] ?? $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        }

        // DALL-E 2 / DALL-E 3 path via SDK
        $params = [
            'model'  => $imageModel,
            'prompt' => $prompt,
            'n'      => 1,
            'size'   => $size,
            'quality' => $quality,
            'response_format' => 'b64_json',
        ];
        try {
            $result = $this->_open_ai->images()->create($params);
        } catch (\Throwable $e) {
            return ["error" => "Error: image generation failed. " . $e->getMessage()];
        }

        $imageData = $result->data[0]->b64Json ?? null;
        if (empty($imageData)) {
            // Fallback: some SDK versions expose the array form
            $arr = method_exists($result, 'toArray') ? $result->toArray() : null;
            if (is_array($arr)) {
                $imageData = $arr['data'][0]['b64_json'] ?? null;
                if (empty($imageData) && !empty($arr['data'][0]['url'])) {
                    $bin = @file_get_contents($arr['data'][0]['url']);
                    if ($bin !== false) $imageData = base64_encode($bin);
                }
            }
        }
        if (empty($imageData))
            return ["error" => "Error: no image data returned"];

        return [
            "b64_data" => $imageData,
            "revised_prompt" => $result->data[0]->revisedPrompt ?? $prompt,
            "tokenUsage" => [
                'model'  => $imageModel,
                'input'  => 0,
                'output' => 1,
            ],
        ];
    }

    /**
     * v5.x — Native web research via OpenAI Responses API + web_search tool.
     * Returns the same [history, replyText, tokenUsage] shape as chat() so that
     * AiChatController and AiWebController can treat it uniformly.
     */
    function chat_WebPreview($sendText, $session="", $max_output_tokens=1024, $temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('open_ai');
        }
        try {
            $response = $this->_open_ai->responses()->create([
                'model'              => $this->_open_ai_chat_model,
                'tools'              => [['type' => 'web_search']],
                'input'              => $sendText,
                'temperature'        => $temperature,
                'max_output_tokens'  => $max_output_tokens,
                'tool_choice'        => 'auto',
                'parallel_tool_calls'=> true,
                'store'              => true,
                'metadata'           => ['session_id' => (string)$session],
            ]);
        } catch (\Throwable $e) {
            return [[['role' => 'user', 'content' => $sendText]],
                    "Error: web_search via OpenAI Responses API failed: " . $e->getMessage(),
                    null];
        }

        $textOut   = '';
        $citations = [];
        if (isset($response->output) && is_iterable($response->output)) {
            foreach ($response->output as $output) {
                if (!isset($output->content) || !is_iterable($output->content)) continue;
                foreach ($output->content as $content) {
                    $type = $content->type ?? null;
                    if ($type === 'output_text' || $type === 'text') {
                        $textOut .= (string)($content->text ?? '');
                        $annots = $content->annotations ?? [];
                        if (is_iterable($annots)) {
                            foreach ($annots as $a) {
                                $url = $a->url ?? ($a->source ?? null);
                                $title = $a->title ?? '';
                                if (!empty($url) && !isset($citations[$url])) {
                                    $citations[$url] = $title !== '' ? $title : $url;
                                }
                            }
                        }
                    }
                }
            }
        }
        $textOut = trim($textOut);
        if (!empty($citations)) {
            $lines = ["", "---", "> **Fonti:**"];
            $i = 1;
            foreach ($citations as $u => $t) {
                $lines[] = "> [$i] [$t]($u)";
                $i++;
            }
            $textOut = $textOut . "\n" . implode("\n", $lines);
        }

        $tokenUsage = [
            'model'  => $this->_open_ai_chat_model,
            'input'  => $response->usage->inputTokens  ?? 0,
            'output' => $response->usage->outputTokens ?? 0,
        ];
        $history = [['role' => 'user', 'content' => $sendText]];
        return [$history, $textOut, $tokenUsage];
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