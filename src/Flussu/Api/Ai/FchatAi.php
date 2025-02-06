<?php
/* --------------------------------------------------------------------*
 * Flussu v4.2 - Mille Isole SRL - Released under Apache License 2.0
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
 * TBD - INCOMPLETE
 * --------------------------------------------------------------------*/
namespace Flussu\Api\Ai;
use GuzzleHttp\Client;
use OpenAI;
class FchatAi
{
    private $_open_ai_key="";
    private $_client;
    function __construct() {
        $this->_open_ai_key = $_ENV['open_ai_key'];
        $this->_client=OpenAI::client($this->_open_ai_key);
    }

    function chat($prompt){
        $res=$this->_client->completions()->create([
            'model'=>'davinci',
            'prompt'=>$prompt
        ]);
        return $res['choices'][0]['text'];
    }
}