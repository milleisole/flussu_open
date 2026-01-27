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
 * CLASS-NAME:       Flussu Kimi (Moonshot AI) interface - v1.0
 * CREATED DATE:     26.01.2026 - Claude - Flussu v4.5
 * VERSION REL.:     4.5.2 20260126
 * UPDATE DATE:      26.01.2026 - Claude
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;

class FlussuKimiAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_kimi_ai_key="";
    private $_kimi_ai_model="";
    private $_kimi_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return false;
    }

    public function __construct($model="", $chat_model=""){
        $this->_kimi_ai_key = config("services.ai_provider.kimi.auth_key");

        // Validate API key is present and not a placeholder
        if (empty($this->_kimi_ai_key)) {
            throw new Exception("Kimi (Moonshot) API key not configured. Please set 'services.ai_provider.kimi.auth_key' in config/.services.json");
        }
        if (strpos($this->_kimi_ai_key, 'insert-your-api-key') !== false ||
            strpos($this->_kimi_ai_key, '6768-insert') !== false) {
            throw new Exception("Kimi (Moonshot) API key is still set to placeholder value. Please configure your actual API key in config/.services.json");
        }

        if ($model)
            $this->_kimi_ai_model = $model;
        else {
            if (!empty(config("services.ai_provider.kimi.model")))
                $this->_kimi_ai_model=config("services.ai_provider.kimi.model");
        }
        if ($chat_model)
            $this->_kimi_chat_model = $chat_model;
        else {
            if (!empty(config("services.ai_provider.kimi.chat-model")))
                $this->_kimi_chat_model=config("services.ai_provider.kimi.chat-model");
        }
        $this->client = new Client([
            'base_uri' => 'https://api.moonshot.cn/v1/',
            'timeout'  => 10.0,
        ]);
    }

    function chat($preChat,$sendText,$role="user"){
        foreach ($preChat as $message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["content"]);
            }
        }
        $preChat[]= [
            'role' => $role,
            'content' => $sendText,
        ];
        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        $payload = [
            'model' => $this->_kimi_ai_model,
            'messages' => $arrayText,
            'max_tokens' => 2000
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_kimi_ai_key,
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
            // Extract token usage if available
            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_kimi_ai_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Kimi response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
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
