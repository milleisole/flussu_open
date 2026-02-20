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
 * CLASS-NAME:       Flussu DeepSeek interface - v1.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.5.1 20250820 
 * UPDATE DATE:      20.08:2025 - Aldus
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use DeepSeek\DeepSeekClient;
use DeepSeek\Enums\Models;
use Log;
use Exception;

class FlussuDeepSeekAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_deepseek;
    private $_deepseek_key="";
    private $_deepseek_model="";
    private $_deepseek_chat_model="";
    public function canBrowseWeb(){
        return true;
    }

    public function __construct($model="",$chat_model=""){
        if (!isset($this->_deepseek)){
            $this->_deepseek_key = config("services.ai_provider.deepseek.auth_key");
            if ($model)
                $this->_deepseek_model = $model;
            else {
                if (!empty(config("services.ai_provider.deepseek.model")))
                    $this->_deepseek_model=config("services.ai_provider.deepseek.model");
            }
            if ($chat_model)
                $this->_deepseek_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.deepseek.chat-model")))
                    $this->_deepseek_chat_model=config("services.ai_provider.deepseek.chat-model");
            }
            $this->_deepseek=DeepSeekClient::build($this->_deepseek_key, timeout:60);
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
            $result = $this->_deepseek->query(json_encode(["messages"=>$arrayText]))->withModel($this->_deepseek_chat_model)->run();
        } catch (\Throwable $e) {
            //Log::error("Claude API Error: " . $e->getMessage());
            return "Error: no response. Details: " . $e->getMessage();
        }
        $res=json_decode($result, true);
        // Extract token usage if available
        $tokenUsage = null;
        if (isset($res["usage"])) {
            $tokenUsage = [
                'model' => $this->_deepseek_chat_model,
                'input' => $res["usage"]["prompt_tokens"] ?? 0,
                'output' => $res["usage"]["completion_tokens"] ?? 0
            ];
        }
        return [$arrayText, ($res["choices"][0]["message"]["content"] ?? "Error: no DeepSeek response. Details: " . $result), $tokenUsage];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        $result = $this->_deepseek->query($sendText)->withTemperature($temperature)->run();
        return $result;
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