<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5- Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu Mistral AI interface - v1.0
 * CREATED DATE:     24.03.2026 - Flussu v4.5
 * VERSION REL.:     4.5.2 20260324
 * UPDATE DATE:      24.03:2026
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;
class FlussuMistralAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_mistral_ai;
    private $_mistral_ai_key="";
    private $_mistral_ai_model="";
    private $_mistral_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return false;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_mistral_ai)){
            $this->_mistral_ai_key = config("services.ai_provider.mistral.auth_key");
            if (empty($this->_mistral_ai_key))
                throw new Exception("Mistral API key not configured. Set 'auth_key' in config services.ai_provider.mistral");
            if ($model)
                $this->_mistral_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.mistral.model")))
                    $this->_mistral_ai_model=config("services.ai_provider.mistral.model");
                else
                    $this->_mistral_ai_model = "mistral-large-latest";
            }
            if ($chat_model)
                $this->_mistral_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.mistral.chat-model")))
                    $this->_mistral_chat_model=config("services.ai_provider.mistral.chat-model");
                else
                    $this->_mistral_chat_model = $this->_mistral_ai_model;
            }
            $this->client = new Client([
                'base_uri' => 'https://api.mistral.ai/v1/',
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
            'model' => $this->_mistral_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_mistral_ai_key,
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
                    'model' => $this->_mistral_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Mistral response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // v4.5.2 - AI Media Exchange: Mistral supports vision via Pixtral models
    public function canAnalyzeMedia(): bool { return true; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        try {
            $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png', 'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];
            $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';

            $imageData = file_get_contents($mediaPath);
            if ($imageData === false) {
                return [[], "Error: unable to read file " . $mediaPath, null];
            }
            $base64Image = base64_encode($imageData);

            foreach ($preChat as &$message) {
                if (isset($message["message"]) && !isset($message["content"])) {
                    $message["content"] = $message["message"];
                    unset($message["message"]);
                }
            }
            unset($message);

            $preChat[] = [
                'role' => $role,
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:{$mimeType};base64,{$base64Image}"
                    ]]
                ]
            ];

            $payload = [
                'model' => $this->_mistral_ai_model,
                'messages' => $preChat,
                'max_tokens' => 2000
            ];

            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_mistral_ai_key,
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
                    'model' => $this->_mistral_ai_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return [$preChat, $data['choices'][0]['message']['content'], $tokenUsage];
            }

            return [[], "Error: no Mistral vision response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [[], "Error: Mistral vision failed. Details: " . $e->getMessage(), null];
        }
    }

    public function canGenerateImages(): bool { return false; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by Mistral"];
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
