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
use Flussu\Contracts\IAiProvider;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Api\Ai\FlussuGeminAi;
use Flussu\Api\Ai\FlussuClaudeAi;
use Flussu\Api\Ai\FlussuDeepSeekAi;
use Log;


enum Platform: int {
    case CHATGPT = 0;
    case GROK = 1;
    case GEMINI = 2;
    case DEEPSEEK = 3;
    case CLAUDE = 4;
}
class AiChatController 
{
    private IAiProvider $_aiClient;
    public function __construct(Platform $platform=Platform::CHATGPT,$model="",$chat_model=""){
        switch ($platform) {
            case Platform::CHATGPT:
                $this->_aiClient= new FlussuOpenAi($model,$chat_model);
                break;
            case Platform::GROK:
                $this->_aiClient= new FlussuGrokAi($model);
                break;
            case Platform::GEMINI:
                $this->_aiClient= new FlussuGeminAi($model);
                break;
            case Platform::CLAUDE:
                $this->_aiClient= new FlussuClaudeAi($model);
                break;
            case Platform::DEEPSEEK:
                $this->_aiClient= new FlussuDeepSeekAi($model);
                break;
        }
    }

    function Chat($sessId, $sendText,$webPreview=false,$role="user"){
        
        // init 
        $preChat=General::ObjRestore("AiCht".$sessId,true); 
        if (is_null($preChat) || empty($preChat) || count($preChat)==0){
            $preChat[]=['role'=>'user','content'=>'You are a Flussu assistant. Flussu is a platform for managing and automating workflows, tasks, and communications, written by Aldo Prinzi, a programmer who had a blog at https://aldo.prinzi.it . You are designed to assist users answering in the samelanguage the users write te questions and also answering questions about Flussu features, and providing guidance on how to use it effectively. Your responses should be clear, concise, and helpful. If you do not know the answer to a question, you should politely inform the user that you do not have that information. The flussu website is https://www.flussu.com . The flussu producer is Mille Isole SRL an italian compay and the website is https://www.milleisole.com .'];
        }

        $result="(no result)";
        if (!$webPreview) 
            $result=$this->_aiClient->Chat($preChat,$sendText, $role); 
        else
            $result=$this->_aiClient->Chat_WebPreview($sendText, $sessId,150,0.7); 

        $History=$result[0];
        $History[]= [
            'role' => 'assistant',
            'content' => $result[1],
        ];
        General::ObjPersist("AiCht".$sessId,$History); 

        $result = preg_replace('/\n\s*\n+/', "\n", $result[1]);

        $pattern = '/\*\*(.*?)\*\*/';
        $replacement = '{b}$1{/b}';
        $retStr = preg_replace($pattern, $replacement, $result);

        $pattern = '/^###(.*)$/m';
        $replacement = '\n{t}$1{/t}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        $pattern = '/^##(.*)$/m';
        $replacement = '\n{t}{b}$1{/b}{/t}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        $pattern = '/^#(.*)$/m';
        $replacement = '{hr}{t}{b}$1{/b}{/t}{hr}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        return $retStr;
    }

}