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
 *
 * CLASS-NAME:       Flussu Qwen AI interface - v1.0
 * CREATED DATE:     16.11.2025 - Claude - Flussu v4.5
 * VERSION REL.:     4.5.1
 * UPDATE DATE:      16.11.2025
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;

class FlussuQwenAi implements IAiProvider
{
    private $_aiErrorState = false;
    private $_qwen_ai;
    private $_qwen_ai_key = "";
    private $_qwen_ai_model = "";
    private $_qwen_ai_chat_model = "";
    private $client;

    public function canBrowseWeb(){
        return false;
    }

    public function __construct($model = "", $chat_model = ""){
        if (!isset($this->_qwen_ai)){
            $this->_qwen_ai_key = config("services.ai_provider.qwen.auth_key");

            if ($model)
                $this->_qwen_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.qwen.model")))
                    $this->_qwen_ai_model = config("services.ai_provider.qwen.model");
            }

            if ($chat_model)
                $this->_qwen_ai_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.qwen.chat-model")))
                    $this->_qwen_ai_chat_model = config("services.ai_provider.qwen.chat-model");
                else
                    $this->_qwen_ai_chat_model = $this->_qwen_ai_model;
            }

            // Qwen API uses OpenAI-compatible endpoints
            $this->client = new Client([
                'base_uri' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
                'timeout'  => 10.0,
            ]);
        }
    }

    function chat($preChat, $sendText, $role = "user"){
        // Normalize message format
        foreach ($preChat as &$message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["message"]);
            }
        }

        $preChat[] = [
            'role' => $role,
            'content' => $sendText,
        ];

        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        $payload = [
            'model' => $this->_qwen_ai_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000
        ];

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_qwen_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120, // Total timeout in seconds
                'connect_timeout' => 20, // Connection timeout in seconds
                'json' => $payload
            ]);

            $data = $response->getBody();

            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data];

            $data = json_decode($data, true);

            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content']];
            else
                return [$arrayText, "Error: no Qwen response. Details: " . print_r($data, true)];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage()];
        }
    }

    function chat_WebPreview($sendText, $session = "123-231-321", $max_output_tokens = 150, $temperature = 0.7){
        // Web preview not supported for Qwen AI yet
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
