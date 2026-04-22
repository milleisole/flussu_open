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
use ClaudePhp\ClaudePhp;
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

            $this->_claude3 = new ClaudePhp(apiKey: $this->_claude_key);
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
            $response = $this->_claude3->messages()->create([
                'model'      => $this->_claude_chat_model,
                'max_tokens' => 1024,
                'messages'   => $sendArray,
            ]);
        } catch (\Throwable $e) {
            //Log::error("Claude API Error: " . $e->getMessage());
            return "Error: no response. Details: " . $e->getMessage();
        }
        $tokenUsage = [
            'model'  => $this->_claude_chat_model,
            'input'  => $response->usage->input_tokens,
            'output' => $response->usage->output_tokens,
        ];
        $textOut = '';
        foreach ($response->content as $block) {
            if (($block['type'] ?? null) === 'text') {
                $textOut .= $block['text'] ?? '';
            }
        }
        return [$sendArray, $textOut, $tokenUsage];
    }

    /**
     * v4.6 - Tool-use / function-calling path for $LLMextra.
     *
     * Builds an Anthropic Messages request with tools, tool_choice, system,
     * temperature, max_tokens (all optional) and returns a normalized
     * response shaped like the UPrompt `LLMextra` contract.
     *
     * @param string $userText   User prompt (becomes the single "user" message).
     * @param array  $extra      Parsed LLMextra payload (model, tools, tool_choice, system, temperature, max_tokens).
     * @return array             [string $textReply, ?array $tokenUsage, array $llmExtra]
     *                           - $textReply: '' on tool_use, else the assistant text
     *                           - $tokenUsage: ['model', 'input', 'output'] for $INFO
     *                           - $llmExtra: normalized response object to forward to the caller
     */
    public function chatExtra(string $userText, array $extra): array {
        $model = !empty($extra['model']) ? (string)$extra['model'] : $this->_claude_chat_model;
        $tools = isset($extra['tools']) && is_array($extra['tools']) ? $extra['tools'] : [];
        $toolChoice = isset($extra['tool_choice']) && is_array($extra['tool_choice'])
            ? $extra['tool_choice']
            : ['type' => 'any'];
        $system = isset($extra['system']) ? (string)$extra['system'] : null;
        $temperature = isset($extra['temperature']) ? (float)$extra['temperature'] : 0.0;
        $maxTokens = isset($extra['max_tokens']) ? (int)$extra['max_tokens'] : 1024;

        $payload = [
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'messages'    => [
                ['role' => 'user', 'content' => $userText],
            ],
        ];
        if ($system !== null && $system !== '') {
            $payload['system'] = $system;
        }
        $filteredTools = array_values(array_filter($tools, 'is_array'));
        if (!empty($filteredTools)) {
            $payload['tools'] = $filteredTools;
            $payload['tool_choice'] = $toolChoice;
        }

        try {
            $response = $this->_claude3->messages()->create($payload);
        } catch (\Throwable $e) {
            return ['', null, [
                'type' => 'error',
                'error' => $e->getMessage(),
                'model' => $model,
            ]];
        }

        $stopReason = $response->stop_reason;
        $content    = $response->content;
        $respModel  = $response->model ?? $model;
        $usage      = [
            'input_tokens'  => $response->usage->input_tokens,
            'output_tokens' => $response->usage->output_tokens,
        ];

        $toolUse = null;
        $textOut = '';
        if (is_array($content)) {
            foreach ($content as $block) {
                $bt = $block['type'] ?? null;
                if ($bt === 'tool_use' && $toolUse === null) {
                    $toolUse = [
                        'name'  => $block['name']  ?? '',
                        'input' => $block['input'] ?? new \stdClass(),
                    ];
                } elseif ($bt === 'text') {
                    $textOut .= $block['text'] ?? '';
                }
            }
        }

        $llmExtra = [
            'stop_reason' => $stopReason,
            'model'       => $respModel,
            'usage'       => [
                'input_tokens'  => $usage['input_tokens']  ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
            ],
        ];
        if ($toolUse !== null) {
            $llmExtra['type'] = 'tool_use';
            $llmExtra['tool_use'] = $toolUse;
            $textReply = '';
        } else {
            $llmExtra['type'] = 'text';
            $llmExtra['text'] = $textOut;
            $textReply = $textOut;
        }

        $tokenUsage = [
            'model'  => $respModel,
            'input'  => $llmExtra['usage']['input_tokens'],
            'output' => $llmExtra['usage']['output_tokens'],
        ];

        return [$textReply, $tokenUsage, $llmExtra];
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