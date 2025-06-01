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
 * CLASS-NAME:       Flussu Gemini interface - v1.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      30.05:2025 
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Gemini\Enums\ModelVariation;
use Gemini\GeminiHelper;
use Gemini;
use Log;
use Exception;

/* 

$yourApiKey = getenv('YOUR_API_KEY');
$client = Gemini::client($yourApiKey);

$result = $client->generativeModel(model: 'gemini-2.0-flash')->generateContent('Hello');
$result->text(); // Hello! How can I assist you today?

// Helper method usage
$result = $client->generativeModel(
    model: GeminiHelper::generateGeminiModel(
        variation: ModelVariation::FLASH,
        generation: 2.5,
        version: "preview-04-17"
    ), // models/gemini-2.5-flash-preview-04-17
);
$result->text(); // Hello! How can I assist you today
 */
class FlussuGeminAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_gemini;
    private $_gemini_key="";
    private $_gemini_model="gemini-2.0-flash";
    private $_gemini_chat_model="gemini-2.0-flash";
    
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_gemini)){
            $this->_gemini_key = config("services.ai_provider.ggl_gemini.auth_key");
            if ($model)
                $this->_gemini_model = $model;
            else {
                if (!empty(config("services.ai_provider.ggl_gemini.model")))
                    $this->_gemini_model=config("services.ai_provider.ggl_gemini.model");
            }
            $this->_gemini=Gemini::client($this->_gemini_key);
        }
    }

    function chat($sendText,$role="user"){

        $result = $this->_gemini->generativeModel(model: $this->_gemini_model)->generateContent(
            $sendText /*,
            role: $role , // 'user' or 'assistant'
            maxOutputTokens: 500, // Optional, default is 150
            temperature: 0.7 // Optional, default is 0.7*/
        );
        return $result->text();
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        /*

        */
        return [];
    }
}