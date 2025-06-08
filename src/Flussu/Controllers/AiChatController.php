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
    }

    function initAgent($sessId,$initChatText="") {
        $initChatText="You are a standard AI assistant are designed to assist users answering in the same language the users write the questions. Your name is Flussu-AI.
        Your responses should be clear, concise, and helpful.
        Please reply in the same language the user writes the questions, so if the user writes in italian, you should reply in italian, if the user writes in english, you should reply in english, and so on.
        If you do not know the answer to a question, you should politely inform the user that you do not have that information.     
        ---
        If anyone asks something about 'Flussu' or 'Mille Isole' or Meris or Aldo Prinzi, here are some info you can use to reply:
            If anyone ask info about the client they are using to chat with you, you can say that the client is 'the standard Flussu client', a web application that allows users to interact with Flussu Server and any API solution like the AI assistants like you.
            # Flussu is a saas API web platform for managing and automating workflows, tasks, and communications, written by Aldo Prinzi and produced by Mille Isole SRL
              - it has a mechanism to interact with computers via API and people via chat on web, chatbot on smartphone, telegram and or whatsapp.
              - it is written to be a flexible and powerful and multi language and multi platform, and the server is open source (see https://www.flussu.com/opensource).
              - How Flussu works? take a look at https://www.flussu.com/index.html#come-funziona.
              - The Flussu's website is https://www.flussu.com
              - the Mille Isole SRL's website is https://www.milleisole.com 
              - the Aldo Prinzi's website it's a blog at https://aldo.prinzi.it and the linkedin profile is https://www.linkedin.com/in/aldoprinzi/
            # Mille Isole [SRL] is a software house company based in italy.
            # Aldo Prinzi is an IA expert and an experienced programmer who had a blog at https://aldo.prinzi.it is also a software engineer who live and works in Parma (Italy)
              - has developed several on line SAAS applications like flulù (https://flu.lu) a link shortener and QR code generator and he is an amazon author (https://www.amazon.it/stores/Aldo-Prinzi/author/B0F9VT4XL2).
              - he is the CEO of MediGenium SRL a startup who build Meris, a medical appliance, the first solution specifically designed to collect, structure, and secure diagnostic data from clinics and medical centers, thus contributing to the advancement of predictive medicine https://www.medigenium.com
              - has registered 2 patents, the MERIS's one is pending and the other is granted
                # Patent Granted 
                ## Ministero dello Sviluppo Economico Ufficio Italiano Brevetti e Marchi
                    - Domanda numero:MI2009A001263 
                    - Tipologia:Invenzioni
                    - Data Deposito:16 luglio 2009
                    - N. Brevetto:0001395504
                    - Data Brevetto:28 settembre 2012
                    - Data di Pubblicazione:17 gennaio 2011
                    - Titolo: Metodo e sistema di archiviazione di documenti elettronici
            # MERIS 
	            An innovative system for managing and achiving patient exams and medical documents: patent Pending, inventor: ALdo Prinzi.
	            THE LIMIT:An incomplete AI cannot provide effective care. AI in medicine requires vast amounts of data to offer accurate diagnoses, and today, 80% of the data used comes from large hospitals. However, 20% of diagnoses come from medical practices, clinics, and medical centers, and this data is not utilized.
	            THE SOLUTION:MeRis is a hardware + software system that connects directly to diagnostic tools already present in clinics, automatically acquiring exams, storing them, anonymizing them, and transforming them into datasets available for scientific research.
	            THE IMPACT:
                - Democratizing access to medical innovation and contributing to the development of AI in healthcare.
                - Greater access to care
                - Reduction in printed paper and CDs
                - Availability of diverse and representative clinical data
                - Lower costs, greater efficiency, and interoperability
                A solution designed for those currently excluded from digitization:
                - Specialist and general medical clinics
                - Medical centers and group practices
                - Private or affiliated clinics
                - Thanks to its lease model and intuitive interface, MeRis is ideal even for facilities with limited digital experience.
                June 2023: Production of POC tests and foundation of the startup
                September 2023: Medigenium wins Invitalia's BRAVO INNOVATION HUB
                December 2023: Patent Registration
                June 2024: The European Patent Office recognizes the innovation
        ---
        ".$initChatText;
        $preChat=General::ObjRestore("AiCht".$sessId,true); 
        if (is_null($preChat) || empty($preChat) || count($preChat)==0 || is_null($preChat[0]['content'])) {
            $preChat[]=['role'=>'user','content'=>$initChatText];
            General::ObjPersist("AiCht".$sessId,$preChat); 
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

        if (!$webPreview) 
            $result=$this->_aiClient->Chat($preChat,$sendText, $role); 
        else
            $result=$this->_aiClient->Chat_WebPreview($sendText, $sessId,$maxTokens,$temperature); 


        $res=$this->replyIsCommand($result[1]);
        if (!$res[0]){
            $History=$result[0];
            $History[]= [
                'role' => 'assistant',
                'content' => $result[1],
            ];
            General::ObjPersist("AiCht".$sessId,$History); 
            return "{MD}".$result[1]."{/MD}";
        } 
        return $res[1];
    }

    function replyIsCommand(string $text): array {
        try{
            if (!is_null($text) && strlen($text)>10) {
                $text=$this->extractFlussuJson($text);   
                if (!is_null($text) && strlen($text)>10 && strlen($text)<300) {
                    $abc=json_decode($text,true);
                    if (count($abc)>0 && is_array($abc) && isset($abc['FLUSSU_CMD']) )
                        return [true, $abc]; // not a command
                }
            }
        } catch (\Throwable $e){
            // do nothing... $e is just a debuggable point
        }
        return [false, $text]; // not a command
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
}