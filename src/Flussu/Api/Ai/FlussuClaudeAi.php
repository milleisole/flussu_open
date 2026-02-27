<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu Claude 3 interface - v2.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.5.2 20260222
 * UPDATE DATE:      22.02:2026 - Aldus
 * Added: Vision support (Claude 3.5 Sonnet)
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Claude\Claude3Api\Client;
use Claude\Claude3Api\Config;
use Log;
use Exception;

class FlussuClaudeAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_claude3;
    private $_claude_key="";
    private $_claude_model="";
    private $_claude_chat_model="";
    
    public function canBrowseWeb(){
        return false;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_claude3)){
            $this->_claude_key = config("services.ai_provider.ant_claude.auth_key");
            if (empty($this->_claude_key))
                throw new Exception("Claude API key not configured. Set 'auth_key' in config services.ai_provider.ant_claude");
            if ($model)
                $this->_claude_model = $model;
            else {
                if (!empty(config("services.ai_provider.ant_claude.model")))
                    $this->_claude_model=config("services.ai_provider.ant_claude.model");
            }
            if ($chat_model)
                $this->_claude_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.ant_claude.chat-model")))
                    $this->_claude_chat_model=config("services.ai_provider.ant_claude.chat-model");
            }

            $config = new Config($this->_claude_key );
            $this->_claude3 = new Client($config);
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

    private function _chatContinue($sendArray){
        try{
            $response = $this->_claude3->chat([
                'model' => $this->_claude_chat_model,
                'messages' => $sendArray
            ]);
        } catch (\Throwable $e) {
            //Log::error("Claude API Error: " . $e->getMessage());
            return "Error: no response. Details: " . $e->getMessage();
        }
        // Extract token usage if available
        $tokenUsage = null;
        if (method_exists($response, 'getUsage')) {
            $usage = $response->getUsage();
            $tokenUsage = [
                'model' => $this->_claude_chat_model,
                'input' => $usage['input_tokens'] ?? 0,
                'output' => $usage['output_tokens'] ?? 0
            ];
        }
        $resChat=[$sendArray, $response->getContent()[0]['text'], $tokenUsage];
        return $resChat;
    }

    // v4.5.2 - AI Media Exchange: Vision
    public function canAnalyzeMedia(): bool {
        return true; // Claude 3.5 Sonnet supports vision
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

        // Claude API multimodal format
        $contentParts = [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => $base64Data
                ]
            ],
            ['type' => 'text', 'text' => $prompt]
        ];

        $preChat[] = [
            'role' => $role,
            'content' => $contentParts,
        ];

        return $this->_chatContinue($preChat);
    }

    // v4.5.2 - Claude does not support image generation
    public function canGenerateImages(): bool {
        return false;
    }

    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by Claude"];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        /*

        */
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