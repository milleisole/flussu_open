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
 * CLASS-NAME:       Flussu Gemini interface - v2.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * Added: Vision support (Gemini 2.0 Flash)
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Gemini\Enums\ModelVariation;
use Gemini\GeminiHelper;
use Gemini\Data\Content;
use Gemini\Data\Blob;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
use Gemini;
use Log;
use Exception;

class FlussuGeminAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_gemini;
    private $_gemini_key="";
    private $_gemini_model=""; // ex: gemini-2.0-flash
    private $_gemini_chat_model=""; // ex: gemini-2.0-flash
    
    public function canBrowseWeb(){
        return true;
    }
   public function __construct($model="",$chat_model=""){
        if (!isset($this->_gemini)){
            $this->_gemini_key = config("services.ai_provider.ggl_gemini.auth_key");
            if (empty($this->_gemini_key))
                throw new Exception("Gemini API key not configured. Set 'auth_key' in config services.ai_provider.ggl_gemini");
            if ($model)
                $this->_gemini_model = $model;
            else {
                if (!empty(config("services.ai_provider.ggl_gemini.model")))
                    $this->_gemini_model=config("services.ai_provider.ggl_gemini.model");
            }
            if ($chat_model)
                $this->_gemini_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.ggl_gemini.chat-model")))
                    $this->_gemini_chat_model=config("services.ai_provider.ggl_gemini.chat-model");
            }
            $this->_gemini=Gemini::client($this->_gemini_key);
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
        return $this->_chatContinue($preChat,$sendText,$role);
    }

    private function _chatContinue($oldMsgArray,$sendText="",$role="user"){
        // Initialize the Gemini client

        // Converti l'array $messaggi in un array di oggetti Content
        $history = array_map(function ($msg) {
            $role = ($msg['role'] === 'user') ? Role::USER : Role::MODEL;
            return Content::parse(
                part: $msg['content'],
                role: $role
            );
        }, $oldMsgArray);

        $tokenUsage = null;
        try {
            $temperature = (float) Config::init()->aiTemperature('ggl_gemini');
            // Inizializza la chat con la cronologia
            $chat = $this->_gemini->generativeModel($this->_gemini_chat_model)
                ->withGenerationConfig(new GenerationConfig(temperature: $temperature))
                ->startChat($history);

            $oldMsgArray[] = ['role' => $role, 'content' => $sendText];
            // Invia il nuovo messaggio
            $response = $chat->sendMessage($sendText);
            $responseText=$response->text();
            // Extract token usage if available
            // usageMetadata is a property (not a method) in google-gemini-php/client v2
            if (isset($response->usageMetadata)) {
                $usage = $response->usageMetadata;
                $tokenUsage = [
                    'model' => $this->_gemini_chat_model,
                    'input' => $usage->promptTokenCount ?? 0,
                    'output' => $usage->candidatesTokenCount ?? 0
                ];
            }
        } catch (\Throwable $e) {
            $responseText="Error: no response. Details: " . $e->getMessage();
        }
        return [
            $oldMsgArray,
            $responseText,
            $tokenUsage
        ];

    }

    // v4.5.2 - AI Media Exchange: Vision
    public function canAnalyzeMedia(): bool {
        return true; // Gemini 2.0 Flash supports vision
    }

    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        if (!file_exists($mediaPath))
            return [[], "Error: file not found at " . $mediaPath, null];

        $mimeType = mime_content_type($mediaPath);
        $fileData = file_get_contents($mediaPath);
        if ($fileData === false)
            return [[], "Error: cannot read file " . $mediaPath, null];

        $base64Data = base64_encode($fileData);
        $tokenUsage = null;

        try {
            $temperature = (float) Config::init()->aiTemperature('ggl_gemini');
            $response = $this->_gemini->generativeModel($this->_gemini_chat_model)
                ->withGenerationConfig(new GenerationConfig(temperature: $temperature))
                ->generateContent([
                    $prompt,
                    new Blob(
                        mimeType: $mimeType,
                        data: $base64Data
                    )
                ]);

            $responseText = $response->text();

            // usageMetadata is a property (not a method) in google-gemini-php/client v2
            if (isset($response->usageMetadata)) {
                $usage = $response->usageMetadata;
                $tokenUsage = [
                    'model' => $this->_gemini_chat_model,
                    'input' => $usage->promptTokenCount ?? 0,
                    'output' => $usage->candidatesTokenCount ?? 0
                ];
            }
        } catch (\Throwable $e) {
            $responseText = "Error: no response. Details: " . $e->getMessage();
        }

        $history = $preChat;
        $history[] = ['role' => $role, 'content' => '[media: ' . basename($mediaPath) . '] ' . $prompt];

        return [$history, $responseText, $tokenUsage];
    }

    // v4.5.2 - Image Generation via gemini-2.5-flash-image ("nano-banana") REST endpoint
    public function canGenerateImages(): bool {
        return true;
    }

    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $model = config("services.ai_provider.ggl_gemini.image-model");
        if (empty($model))
            $model = "gemini-2.5-flash-image";

        $payload = [
            "contents" => [[ "parts" => [[ "text" => $prompt ]] ]],
            "generationConfig" => [ "responseModalities" => ["IMAGE"] ],
        ];

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 60]);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent?key=" . urlencode($this->_gemini_key);
            $res = $client->post($url, [
                'json' => $payload,
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $body = json_decode($res->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            return ["error" => "Gemini image generation failed: " . $e->getMessage()];
        }

        $tokenUsage = [
            'model'  => $model,
            'input'  => 0,
            'output' => 1,
        ];
        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['inlineData']['data']))
                return [
                    "b64_data" => $part['inlineData']['data'],
                    "revised_prompt" => $prompt,
                    "tokenUsage" => $tokenUsage,
                ];
            // alcune SDK usano snake_case
            if (isset($part['inline_data']['data']))
                return [
                    "b64_data" => $part['inline_data']['data'],
                    "revised_prompt" => $prompt,
                    "tokenUsage" => $tokenUsage,
                ];
        }
        return ["error" => "No image data returned by Gemini"];
    }

    /**
     * v5.x — Native web research via Gemini google_search grounding tool.
     * Uses the REST endpoint directly (the google-gemini-php SDK doesn't always
     * expose tools on generateContent). Returns [history, replyText, tokenUsage].
     */
    function chat_WebPreview($sendText, $session="", $max_output_tokens=1024, $temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('ggl_gemini');
        }
        $model = $this->_gemini_chat_model ?: 'gemini-2.0-flash';
        $payload = [
            'contents' => [[ 'parts' => [['text' => $sendText]] ]],
            'tools'    => [['google_search' => new \stdClass()]],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $max_output_tokens,
            ],
        ];
        try {
            $http = new \GuzzleHttp\Client(['timeout' => 60]);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model)
                 . ":generateContent?key=" . urlencode($this->_gemini_key);
            $res = $http->post($url, [
                'json'        => $payload,
                'headers'     => ['Content-Type' => 'application/json'],
                'http_errors' => false,
            ]);
            $code = $res->getStatusCode();
            $raw  = (string)$res->getBody();
            $body = json_decode($raw, true);
        } catch (\Throwable $e) {
            return [[['role' => 'user', 'content' => $sendText]],
                    "Error: google_search via Gemini failed: " . $e->getMessage(),
                    null];
        }
        if ($code !== 200 || !is_array($body)) {
            return [[['role' => 'user', 'content' => $sendText]],
                    "Error: Gemini HTTP $code: " . substr($raw, 0, 500),
                    null];
        }

        $textOut = '';
        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) $textOut .= (string)$part['text'];
        }
        $textOut = trim($textOut);

        $citations = [];
        $gm = $body['candidates'][0]['groundingMetadata'] ?? [];
        if (!empty($gm['groundingChunks']) && is_array($gm['groundingChunks'])) {
            foreach ($gm['groundingChunks'] as $chunk) {
                $url   = $chunk['web']['uri']   ?? null;
                $title = $chunk['web']['title'] ?? '';
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
            'model'  => $model,
            'input'  => $body['usageMetadata']['promptTokenCount']     ?? 0,
            'output' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
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