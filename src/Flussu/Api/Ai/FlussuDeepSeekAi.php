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
class FlussuDeepSeekAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_deepseek;
    private $_deepseek_key="";
    private $_deepseek_model="deepseek-reasoner
";
    private $_deepseek_chat_model="deepseek-chat";
    
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_deepseek)){
            $this->_deepseek_key = config("services.ai_provider.deepseek.auth_key");
            if ($model)
                $this->_deepseek_model = $model;
            else {
                if (!empty(config("services.ai_provider.deepseek.model")))
                    $this->_deepseek_model=config("services.ai_provider.deepseek.model");
            }
            $this->_deepseek=DeepSeekClient::build($this->_deepseek_key, timeout:60);
        }
    }

    function chat($sendText,$role="user"){

        $result = $this->_deepseek->query(json_encode(["role"=>$role,"message"=>$sendText]))->run();
        $res=json_decode($result, true);
        return $res["choices"][0]["message"]["content"] ?? "Error: no DeepSeek response. Details: " . $result;
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        $result = $this->_deepseek->query($sendText)->withTemperature($temperature)->run();
        return $result;
    }
}