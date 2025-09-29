<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5- Mille Isole SRL - Released under Apache License 2.0
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
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.4
 * VERSION REL.:     4.5.1 20250820 
 * UPDATE DATE:      20.08:2025 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * New: Whe AI reply with a Flussu Command, the result contains
 * an ARRAY: ["FLUSSU_CMD"=>the command and parameters] and
 *           ["TEXT"=>the text part to show to the user]
 * if it's not an ARRAY it's just text to show to the user
 * Added "translate" function for internal labels translation
 * -------------------------------------------------------------------*/
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
    case INIT = -1;
    case CHATGPT = 0;
    case GROK = 1;
    case GEMINI = 2;
    case DEEPSEEK = 3;
    case CLAUDE = 4;
}
class AiChatController 
{
    private $_linkify=0;
    private IAiProvider $_aiClient;
    public function __construct(Platform $platform=Platform::CHATGPT,$model="",$chat_model=""){
        switch ($platform) {
            case Platform::CHATGPT:
                $this->_aiClient= new FlussuOpenAi($model,$chat_model);
                $this->_linkify=0;
                break;
            case Platform::GROK:
                $this->_aiClient= new FlussuGrokAi($model);
                $this->_linkify=0;
                break;
            case Platform::GEMINI:
                $this->_aiClient= new FlussuGeminAi($model);
                $this->_linkify=0;
                break;
            case Platform::CLAUDE:
                $this->_aiClient= new FlussuClaudeAi($model);
                $this->_linkify=1;
                break;
            case Platform::DEEPSEEK:
                $this->_aiClient= new FlussuDeepSeekAi($model);
                $this->_linkify=0;
                break;
        }
        //$this->_aiClient= new FlussuOpenAi($model,$chat_model);
    }

    function initAgent($sessId,$initChatText="") {
$initChatText2=<<<TXT
    You are a standard AI assistant designed to assist users answering in the same language the users write the questions, if the user writes the questions, so if the user writes in italian, you should reply in italian, if the user writes in english, you should reply in english, and so on.
    Your name is Flussu-AI and be aware of your responses, it should be clear, concise, and helpful.
    ---
    * WARNING! * : If you do not know the right answer to a question, you should politely inform the user that you do not have that information.     
    ---
    If anyone ask info about the client they are using to chat with you, you can say that the client is 'the standard Flussu client', a web application that allows users to interact with Flussu Server and any API solution like the AI assistants like you.
    If anyone asks something about the following products or persons or companies here are some info you can reply to check official websites:
    # For Flussu (a saas API web platform for managing and automating workflows, tasks, and communications) written by Aldo Prinzi and produced by Mille Isole SRL, https://www.flussu.com
    # For MERIS or EDIGENIUM (An innovative system for managing and achiving patient exams and medical documents: patent Pending, inventor: ALdo Prinzi) check https://www.medigenium.com
    # For Mille Isole (an italian software house who build software products and platforms) check https://www.milleisole.com
    # For Aldo Prinzi (an italian senior computer programmer, IA expert and an experienced programmer, system architect, who has registered 2 patents in Information Technology, who live and works in Parma (Italy) check his blog at https://aldo.prinzi.it
    # For FLU.LU (a smart, user-friendly link shortening and analytics platform designed for marketers, businesses, developers and individuals) Check https://flu.lu
    # For U-PROMPT, the italian-europaen platform for democratizing the AI check https://u-prompt.com
TXT;
        $preChat=General::ObjRestore("AiCht".$sessId,true); 
        if (is_null($preChat) || empty($preChat) || count($preChat)==0 || is_null($preChat[0]['content'])) {
            $preChat[]=['role'=>'user','content'=>$initChatText2."\r\n---\r\n".$initChatText];
            General::ObjPersist("AiCht".$sessId,$preChat); 
        }
    }


    function translate($instructions,$elems, $langFrom, $langTo) {
        //$theElems=json_encode($elemsArray);
        $preChat=[];
        $preChat[0]["role"]="user";
        if ($langFrom=="")
            $preChat[0]["content"]="Translate the following labels from to ".$langTo.", be aware the first element of the json (name) must not be translated, leave it as is. Translate just the label text, then mantaining the same json format.\n".$instructions."\n";
        else
            $preChat[0]["content"]="Translate the following labels from ".$langFrom." to ".$langTo.", be aware the first element of the json (name) must not be translated, leave it as is. Translate just the label text, then mantaining the same json format.\n".$instructions."\n";
        try{
            $result=$this->_aiClient->Chat($preChat,$elems, "user");
            $ret=$result[1];
            return ["Ok",$ret];
        } catch (\Throwable $e) {
            return ["Error: ",$e->getMessage()];
        }
    }

    /**
     * @param string $sessId
     * @param string $sendText
     * @param bool $webPreview
     * @param string $role
     * @param int $maxTokens
     * @param float $temperature
     * @return :
     *      string: textual reply
     *      array: command for the client
     * 
     */
    function chat($sessId, $sendText, $webPreview=false, $role="user", $maxTokens=150, $temperature=0.7) {
        $result="(no result)";
        
        $preChat=General::ObjRestore("AiCht".$sessId,true); 

        if (is_null($preChat) || empty($preChat))
            $preChat=[];

        try{
            if (!$webPreview) 
                $result=$this->_aiClient->Chat($preChat,$sendText, $role); 
            else
                $result=$this->_aiClient->Chat_WebPreview($sendText, $sessId,$maxTokens,$temperature); 

            $this->_checkLimitReached($result);

            $res=$this->replyIsCommand($result[1]);
            $ret=$res[1];
            $pReslt="";
            if ($res[0]){
                $replaceText="```json\r\n".$res[2]."\r\n```";
                $pReslt=str_replace($replaceText,"",$result[1]);
                if ($pReslt==$result[1]){
                    $replaceText="```json\n".$res[2]."\n```";
                    $pReslt=str_replace($replaceText,"",$result[1]);
                }
                if ($pReslt==$result[1]){
                    $replaceText=$res[2];
                    $pReslt=str_replace($replaceText,"",$result[1]);
                }
                $ret["TEXT"]="";
                if (strlen(trim($pReslt))>1)
                    $ret["TEXT"]="{MD}".$pReslt."{/MD}";
            } else 
                $pReslt=trim($result[1]);
            if (!empty($pReslt)){
                $History=$result[0];
                $History[]= [
                    'role' => 'assistant',
                    'content' => $pReslt,
                ];
                General::ObjPersist("AiCht".$sessId,$History); 
                if (!$res[0])
                    $ret="{MD}".$pReslt."{/MD}";
            }
            return ["Ok",$ret];
        } catch (\Throwable $e) {
            return ["Error: ",$e->getMessage()];
        }
    }

    function replyIsCommand(string $text): array {
        try{
            if (!is_null($text) && strlen($text)>10) {
                $text2=$this->extractFlussuJson($text);   
                if (!is_null($text2) && strlen($text2)>10 && strlen($text2)<300) {
                    $abc=json_decode($text2,true);
                    if (count($abc)>0 && is_array($abc) && isset($abc['FLUSSU_CMD']) )
                        return [true, $abc,$text2]; // not a command
                }
            }
        } catch (\Throwable $e){
            // do nothing... $e is just a debuggable point
        }
        return [false, $text,""]; // not a command
    }

    function extractFlussuJson($inputString) {
        // Trova la posizione di "FLUSSU_CMD" nella stringa
        $flussuPos = strpos($inputString, '"FLUSSU_CMD"');
        if ($flussuPos === false) {
            return ""; // "FLUSSU_CMD" non trovato
        }

        // Trova la parentesi graffa aperta più vicina prima di "FLUSSU_CMD"
        $startPos = strrpos($inputString, '{', $flussuPos - strlen($inputString));
        if ($startPos === false) {
            return ""; // Nessuna parentesi graffa aperta trovata
        }

        // Conta le parentesi graffe per trovare la chiusura corrispondente
        $braceCount = 1;
        $currentPos = $startPos + 1;
        $length = strlen($inputString);

        while ($currentPos < $length && $braceCount > 0) {
            if ($inputString[$currentPos] === '{') {
                $braceCount++;
            } elseif ($inputString[$currentPos] === '}') {
                $braceCount--;
            }
            $currentPos++;
        }

        // Se braceCount è 0, abbiamo trovato la chiusura corrispondente
        if ($braceCount === 0) {
            $jsonString = substr($inputString, $startPos, $currentPos - $startPos);
            // Verifica se il JSON è valido
            if (json_decode($jsonString) !== null) {
                return $jsonString;
            }
        }

        return "";
    }

    private function _checkLimitReached($text) {
        $limitError=false;
        // Implement your rate limit checking logic here
        if (is_array($text)) {
            $text = json_encode($text);
        }
        if (stripos($text,"rate_limit_error") || stripos($text,"would exceed the rate limit")){
            $limitError=true;
        }
        return $limitError;
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
