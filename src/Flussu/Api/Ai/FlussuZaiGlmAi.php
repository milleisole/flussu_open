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
 * CLASS-NAME:       Flussu Z.AI GLM interface - v1.0
 * CREATED DATE:     07.04.2026 - Flussu v4.5
 * VERSION REL.:     4.5.2 20260407
 * UPDATE DATE:      07.04.2026
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;
class FlussuZaiGlmAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_zai_glm;
    private $_zai_glm_key="";
    private $_zai_glm_model="";
    private $_zai_glm_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return false;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_zai_glm)){
            $this->_zai_glm_key = config("services.ai_provider.zai_glm.auth_key");
            if (empty($this->_zai_glm_key))
                throw new Exception("Z.AI GLM API key not configured. Set 'auth_key' in config services.ai_provider.zai_glm");
            if ($model)
                $this->_zai_glm_model = $model;
            else {
                if (!empty(config("services.ai_provider.zai_glm.model")))
                    $this->_zai_glm_model=config("services.ai_provider.zai_glm.model");
                else
                    $this->_zai_glm_model = "glm-4.5v";
            }
            if ($chat_model)
                $this->_zai_glm_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.zai_glm.chat-model")))
                    $this->_zai_glm_chat_model=config("services.ai_provider.zai_glm.chat-model");
                else
                    $this->_zai_glm_chat_model = "glm-4.6";
            }
            $this->client = new Client([
                'base_uri' => 'https://api.z.ai/api/paas/v4/',
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
            'model' => $this->_zai_glm_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
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
                    'model' => $this->_zai_glm_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Z.AI GLM response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // v4.5.2 - AI Media Exchange: Z.AI GLM supports vision via glm-4.5v
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
                'model' => $this->_zai_glm_model,
                'messages' => $preChat,
                'max_tokens' => 2000
            ];

            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
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
                    'model' => $this->_zai_glm_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return [$preChat, $data['choices'][0]['message']['content'], $tokenUsage];
            }

            return [[], "Error: no Z.AI GLM vision response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [[], "Error: Z.AI GLM vision failed. Details: " . $e->getMessage(), null];
        }
    }

    public function canGenerateImages(): bool { return false; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by Z.AI GLM"];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        return [];
    }
}
