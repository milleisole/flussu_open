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
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      31.05:2025 
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
        $result = $this->_deepseek->query(json_encode(["messages"=>$arrayText]))->run();
        $res=json_decode($result, true);
        return [$arrayText,($res["choices"][0]["message"]["content"] ?? "Error: no DeepSeek response. Details: " . $result)];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        $result = $this->_deepseek->query($sendText)->withTemperature($temperature)->run();
        return $result;
    }
}
 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //--------------- 