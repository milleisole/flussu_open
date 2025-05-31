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
 * CLASS-NAME:       Flussu OpenAi Controller - v3.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.2
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      30.05:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Flussu\General;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Contracts\IAiProvider;
use Log;


enum Platform: int {
    case CHATGPT = 0;
    case GROK = 1;
}
class AiChatController 
{
    private IAiProvider $_aiClient;
    public function __construct(Platform $platform=Platform::CHATGPT,$model="",$chat_model=""){
        if  ($platform->value ==0)
            $this->_aiClient= new FlussuOpenAi($model,$chat_model);
        else if ($platform->value == 1)
            $this->_aiClient= new FlussuGrokAi($model);
    }

    function Chat($sendText,$webPreview=false,$role="user"){
        $result="(no result)";
        if (!$webPreview) 
            $result=$this->_aiClient->Chat($sendText, $role); 
        else{
            $sess="6768768768768768";
            $result= $this->_aiClient->Chat_WebPreview($sendText, $sess,150,0.7); 
        }

        $result = preg_replace('/\n\s*\n+/', "\n", $result);

        $pattern = '/\*\*(.*?)\*\*/';
        $replacement = '{b}$1{/b}';
        $retStr = preg_replace($pattern, $replacement, $result);

        $pattern = '/^###(.*)$/m';
        $replacement = '\n{t}$1{/t}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        return $retStr;
    }

}