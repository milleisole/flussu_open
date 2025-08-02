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
 * CLASS-NAME:       Flussu Gemini interface - v1.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      31.05:2025 
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Gemini\Enums\ModelVariation;
use Gemini\GeminiHelper;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Gemini;
use Log;
use Exception;

class FlussuGeminAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_gemini;
    private $_gemini_key="";
    private $_gemini_model=""; // ex: gemini-2.0-flash
    private $_gemini_chat_model=""; // ex: gemini-2.0-flash
    
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_gemini)){
            $this->_gemini_key = config("services.ai_provider.ggl_gemini.auth_key");
            if ($model)
                $this->_gemini_model = $model;
            else {
                if (!empty(config("services.ai_provider.ggl_gemini.model")))
                    $this->_gemini_model=config("services.ai_provider.ggl_gemini.model");
            }
            if ($chat_model)
                $this->_gemini_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.ggl_gemini.chat-model")))
                    $this->_gemini_chat_model=config("services.ai_provider.ggl_gemini.chat-model");
            }
            $this->_gemini=Gemini::client($this->_gemini_key);
        }
    }

    function chat($preChat,$sendText,$role="user"){
        foreach ($preChat as $message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["content"]); 
            }
        }
        return $this->_chatContinue($preChat,$sendText,$role);
    }

    private function _chatContinue($oldMsgArray,$sendText="",$role="user"){
        // Initialize the Gemini client

        // Converti l'array $messaggi in un array di oggetti Content
        $history = array_map(function ($msg) {
            $role = ($msg['role'] === 'user') ? Role::USER : Role::MODEL;
            return Content::parse(
                part: $msg['content'],
                role: $role
            );
        }, $oldMsgArray);

        try {
            // Inizializza la chat con la cronologia
            $chat = $this->_gemini->generativeModel($this->_gemini_chat_model)->startChat($history);

            $oldMsgArray[] = ['role' => $role, 'content' => $sendText];
            // Invia il nuovo messaggio
            $response = $chat->sendMessage($sendText);
            $responseText=$response->text();
        } catch (\Throwable $e) {
            $responseText=$e->getMessage();
        }
        return [
            $oldMsgArray,
            $responseText
        ];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        /*

        */
        return [];
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