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
 * CLASS-NAME:       Flussu Moonshot interface - v1.0
 * CREATED DATE:     14.11.2025 - Aldus - Flussu v5.0
 * VERSION REL.:     5.0.0 20251114
 * UPDATE DATE:      14.11:2025 - Aldus
 * DESCRIPTION:      Moonshot AI integration (OpenAI-compatible API)
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use OpenAI\Client;
use Log;
use Exception;
use OpenAI\OpenAI;
use Flussu\Contracts\IAiProvider;

class FlussuMoonshotAi implements IAiProvider
{
    private $_aiErrorState=false;
    private Client $_moonshot_ai;
    private $_moonshot_key="";
    private $_moonshot_model="";
    private $_moonshot_chat_model="";
    
    public function canBrowseWeb(){
        return false;
    }
    
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_moonshot_ai)){
            $this->_moonshot_key = config("services.ai_provider.moonshot.auth_key");
            
            if ($model)
                $this->_moonshot_model = $model;
            else {
                if (!empty(config("services.ai_provider.moonshot.model")))
                    $this->_moonshot_model=config("services.ai_provider.moonshot.model");
                else
                    $this->_moonshot_model="moonshot-v1-8k"; // Default model
            }
            
            if ($chat_model)
                $this->_moonshot_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.moonshot.chat-model")))
                    $this->_moonshot_chat_model=config("services.ai_provider.moonshot.chat-model");
                else
                    $this->_moonshot_chat_model=$this->_moonshot_model; // Use same as model
            }
            
            // Create OpenAI-compatible client pointing to Moonshot endpoint
            $this->_moonshot_ai=\OpenAI::factory()
                ->withApiKey($this->_moonshot_key)
                ->withBaseUri('https://api.moonshot.cn/v1') // Moonshot/Moonshot endpoint
                ->withHttpClient($httpClient = new \GuzzleHttp\Client([]))
                ->withHttpHeader('X-workflowapp', 'flussu')
                ->make();
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
        try{
            $result = $this->_moonshot_ai->chat()->create([
                'model' => $this->_moonshot_chat_model,
                'messages' => $arrayText,
                'stream'      => false
            ]);
        } catch (\Throwable $e) {
            return "Error: no response. Details: " . $e->getMessage();
        }
        
        // Extract response text
        $responseText = $result->choices[0]->message->content;
        
        // Extract token usage (Moonshot uses OpenAI-compatible format)
        $tokenIn = isset($result->usage->promptTokens) ? $result->usage->promptTokens : 0;
        $tokenOut = isset($result->usage->completionTokens) ? $result->usage->completionTokens : 0;
        
        // Return standardized structure with retrocompatibility
        return [
            0 => $arrayText,              // retrocompatibility: conversation history
            1 => $responseText,            // retrocompatibility: response text
            'conversation' => $arrayText,
            'response' => $responseText,
            'token_in' => $tokenIn,
            'token_out' => $tokenOut
        ];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        // Moonshot/Moonshot doesn't support web preview in the same way as OpenAI
        // Return empty array as per interface
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