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
 * CLASS-NAME:       Flussu OpenAi interface - v1.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.5.1 20250820 
 * UPDATE DATE:      20.08:2025 - Aldus
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use OpenAI\Resources\Batches;
use OpenAI\Resources\Completions;
use OpenAI\Resources\Models;
use OpenAI\Client;
use Log;
use Exception;
use OpenAI\OpenAI;
use Flussu\Contracts\IAiProvider;
class FlussuOpenAi implements IAiProvider
{
    private $_aiErrorState=false;
    private Client $_open_ai;
    private $_open_ai_key="";
    private $_open_ai_model="";
    private $_open_ai_chat_model="";
    
    public function canBrowseWeb(){
        return false;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_open_ai)){
            $this->_open_ai_key = config("services.ai_provider.open_ai.auth_key");
            if (empty($this->_open_ai_key))
                throw new Exception("OpenAI API key not configured. Set 'auth_key' in config services.ai_provider.open_ai");
            if ($model)
                $this->_open_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.open_ai.model")))
                    $this->_open_ai_model=config("services.ai_provider.open_ai.model");
            }
            if ($chat_model)
                $this->_open_ai_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.open_ai.chat-model")))
                    $this->_open_ai_chat_model=config("services.ai_provider.open_ai.chat-model");
            }
            $this->_open_ai=\OpenAI::factory()
                ->withApiKey($this->_open_ai_key)
                ->withHttpClient($httpClient = new \GuzzleHttp\Client([]))
                ->withHttpHeader('X-workflowapp', 'flussu')
                /*->withOrganization('flussu') 
                ->withProject('flussu') 
                ->withModel($this->_open_ai_model)
                ->withChatModel($this->_open_ai_chat_model)*/
                ->make();

/*

            $response = $this->_open_ai->models()->list();

            $response->object; // 'list'

            foreach ($response->data as $result) {
                $result->id; // 'gpt-3.5-turbo-instruct'
                $result->object; // 'model'
                // ...
            }
            $response->toArray(); // ['object' => 'list', 'data' => [...]]
*/
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
            $result = $this->_open_ai->chat()->create([
                'model' => $this->_open_ai_chat_model,
                'messages' => $arrayText,
                /*'tools' => [
                    [
                        'type' => 'web_search'
                    ]
                ],*/
                'stream'      => false
                /*,'tool_choice' => 'auto',*/
                /*'parallel_tool_calls' => true*/
            ]);
        } catch (\Throwable $e) {
            return "Error: no response. Details: " . $e->getMessage();
        }
        // Extract token usage if available
        $tokenUsage = null;
        if (isset($result->usage)) {
            $tokenUsage = [
                'model' => $this->_open_ai_chat_model,
                'input' => $result->usage->promptTokens ?? 0,
                'output' => $result->usage->completionTokens ?? 0
            ];
        }
        return [$arrayText, $result->choices[0]->message->content, $tokenUsage];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        $response = $this->_open_ai->responses()->create([
            'model' => $this->_open_ai_chat_model,
            'tools' => [
                [
                    'type' => 'web_search'
                ]
            ],
            'input' => $sendText,
            'temperature' => $temperature,
            'max_output_tokens' => $max_output_tokens,
            'tool_choice' => 'auto',
            'parallel_tool_calls' => true,
            'store' => true,
            'metadata' => [
                /*'user_id' => '123',*/
                'session_id' => $session
            ]
        ]);

        $response->id; // 'resp_67ccd2bed1ec8190b14f964abc054267'
        $response->object; // 'response'
        $response->createdAt; // 1741476542
        $response->status; // 'completed'
        $response->model; // 'gpt-4o-mini'

        foreach ($response->output as $output) {
            $output->type; // 'message'
            $output->id; // 'msg_67ccd2bf17f0819081ff3bb2cf6508e6'
            $output->status; // 'completed'
            $output->role; // 'assistant'
            
            foreach ($output->content as $content) {
                $content->type; // 'output_text'
                $content->text; // The response text
                $content->annotations; // Any annotations in the response
            }
        }

        $response->usage->inputTokens; // 36
        $response->usage->outputTokens; // 87
        $response->usage->totalTokens; // 123

        $response->toArray(); // ['id' => 'resp_67ccd2bed1ec8190b14f964abc054267', ...]
        return $response;
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