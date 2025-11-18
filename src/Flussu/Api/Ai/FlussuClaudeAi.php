<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     5.0 20251113 
 * UPDATE DATE:      13.11:2025 - Aldus
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
        try{
            $response = $this->_claude3->chat($sendArray);
        } catch (\Throwable $e) {
            //Log::error("Claude API Error: " . $e->getMessage());
            return "Error: no response. Details: " . $e->getMessage();
        }
        
        // Extract response text
        $responseText = $response->getContent()[0]['text'];
        
        // Extract token usage (Claude API returns usage.input_tokens and usage.output_tokens)
        $tokenIn = 0;
        $tokenOut = 0;
        
        $usage = $response->getUsage();
        if ($usage) {
            $tokenIn = isset($usage['input_tokens']) ? $usage['input_tokens'] : 0;
            $tokenOut = isset($usage['output_tokens']) ? $usage['output_tokens'] : 0;
        }
        
        // Return standardized structure with retrocompatibility
        return [
            0 => $sendArray,               // retrocompatibility: conversation history
            1 => $responseText,            // retrocompatibility: response text
            'conversation' => $sendArray,
            'response' => $responseText,
            'token_in' => $tokenIn,
            'token_out' => $tokenOut
        ];
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