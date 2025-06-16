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
 * CLASS-NAME:       Flussu Grok interface - v1.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      31.05:2025 
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;
class FlussuGrokAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_grok_ai;
    private $_grok_ai_key="";
    private $_grok_ai_model="";
    private $client;

    public function __construct($model=""){
        if (!isset($this->_grok_ai)){
            $this->_grok_ai_key = config("services.ai_provider.xai_grok.auth_key");
            if ($model)
                $this->_grok_ai_model = $model;
            else {
                if (!empty(config("services.ai_provider.xai_grok.model")))
                    $this->_grok_ai_model=config("services.ai_provider.xai_grok.model");
            }
            $this->client = new Client([
                'base_uri' => 'https://api.x.ai/v1/', 
                'timeout'  => 10.0,
            ]);

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
        $payload = [
            'model' => $this->_grok_ai_model,
            'messages' => $arrayText,
            'max_tokens' => 2000
        ];
        try {
            $response = $this->client->post('chat/completions', [ 
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_grok_ai_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 60, // Total timeout in seconds
                'connect_timeout' => 30, // Connection timeout in seconds
                'json'=>$payload
            ]);
            $data=$response->getBody();

            if ($response->getStatusCode() !== 200)
                return [$arrayText,"Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data];

            $data = json_decode($data, true);
            if (isset($data['choices'][0]['message']['content'])) 
                return [$arrayText,$data['choices'][0]['message']['content']];
            else 
                return [$arrayText,"Error: no Grok response. Details: " . print_r($data, true)];

        } catch (Exception $e) {
            return [$arrayText,'Error: ' . $e->getMessage()];
        }
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