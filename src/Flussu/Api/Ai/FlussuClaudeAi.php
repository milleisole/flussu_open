<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu Claude 3 interface - v1.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      31.05:2025 
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
    private $_claude_model="claude-3-5-sonnet-20241022";
    private $_claude_chat_model="claude-3-5-sonnet-20241022";
    
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_claude3)){
            $this->_claude_key = config("services.ai_provider.ant_claude.auth_key");
            if ($model)
                $this->_claude_model = $model;
            else {
                if (!empty(config("services.ai_provider.ant_claude.model")))
                    $this->_claude_model=config("services.ai_provider.ant_claude.model");
            }
            $config = new Config($this->_claude_key );
            $this->_claude3 = new Client($config);
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

    private function _chatContinue($sendArray){
        $response = $this->_claude3->chat($sendArray);
        /*if ($response->isError()) {
            //Log::error("Claude API Error: " . $response->getErrorMessage());
            return "Error: no Claude response. Details: " . $response->getErrorMessage();
        } */       
        return [$sendArray,$response->getContent()[0]['text']];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        /*

        */
        return [];
    }
}