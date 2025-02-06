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
 * TBD- UNFINISHED
 * 
 * CLASS-NAME:       Flussu OpenAi Controller - v2.8
 * CREATED DATE:     17.08.2023 - Aldus - Flussu v2.9
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Flussu\General;
use Flussu\Api\Ai\FopenAi;
use StopWords\StopWords;
use Amaccis\Stemmer\Stemmer;
use Amaccis\Stemmer\Enum\CharacterEncodingEnum;
use Log;

class OpenAiController 
{
    private $_aiErrorState=false;
    private $_open_ai=null;
    private $_open_ai_key="";
    private $_open_ai_model="text-davinci-003";
    private $_open_ai_chat_model="gpt-3.5-turbo";
    private function _initOpenAi(){
        if (!isset($this->_open_ai)){
            $this->_open_ai_key = $_ENV['open_ai_key'];
            $aa=$_ENV['open_ai_model'];
            if (!empty($aa))
                $this->_open_ai_model=$aa;
            $this->_open_ai = new FopenAi($this->_open_ai_key);
        }
    }

    function createChatSession($initText){
        $this->_initOpenAi();
        $complete =$this->_open_ai->createChatSession($initText);
        $arResp=json_decode($complete,false);
        return trim($arResp->choices[0]->text);
    }

    function sendChatSessionText($chatText,$sessionId){
        $this->_initOpenAi();
        $complete =$this->_open_ai->sendChatMessage($sessionId, $chatText);
        $arResp=json_decode($complete,false);
        return trim($arResp->choices[0]->text);
    }

    function aiTranslate($text,$which="english"){
        $this->_initOpenAi();
        $complete = $this->_open_ai->completion([
            'model' => $this->_open_ai_model,
            'prompt' => "translate the following text in ".$which." (if not in already in ".$which."):\r\n".$text,
            'temperature' => 0.1,
            'max_tokens' => 600,
            'frequency_penalty' => 0,
            'presence_penalty' => 0.6,
            'best_of' => 1
        ]);
        $arResp=json_decode($complete,false);
        if (isset($arResp->error)) {
            $this->_aiErrorState=true;
            return "OpenAi Server ERROR:".$arResp->error->message;
        }
        else
            return trim($arResp->choices[0]->text);
    }

    function aiGetPurpose($text,$which="purpose"){
        $this->_initOpenAi();
        $complete = $this->_open_ai->completion([
            'model' => $this->_open_ai_model,
            'prompt' => "Summarize purpose this as json:\r\n".$text,
            'temperature' => 0.5,
            'max_tokens' => 400,
            'frequency_penalty' => 1,
            'presence_penalty' => 1,
            'best_of' => 1
        ]);
        $arResp=json_decode($complete,false);
        if (isset($arResp->error)) {
            $resp=$arResp->error->message;
            $this->_aiErrorState=true;
            return "OpenAi Server ERROR:".$arResp->error->message;
        } else {
            $resp=trim($arResp->choices[0]->text);
            $res2=json_decode(trim($resp));
            if (!isset($res2)){
                preg_match_all('~\{(?:[^{}]|(?R))*\}~', $arResp->choices[0]->text,$mtch);
                $resp=trim($mtch["0"][0]);
                $res2=json_decode($resp);
            }
            $res=$res2->purpose;
            if (empty($res)) $res=$res2->Purpose;
            if (empty($res)) $res=$res2;
            if (empty($res)) $res=$arResp->choices[0]->text;
            return trim($res);
        }
    }

    function genQueryOpenAi($query,$qrType=0){
        $resp=["resp"=>"","elms"=>"","err"=>""];
        try{
            if ($qrType==0){
                $open_ai_key = $_ENV['open_ai_key'];
                $open_ai = new FopenAi($open_ai_key);
                $complete = $open_ai->completion([
                    'model' => $_ENV['open_ai_model'],
                    'prompt' => $query,
                    'temperature' => 0.8,
                    'max_tokens' => 1500,
                    'frequency_penalty' => 0.3,
                    'presence_penalty' => 0.2,
                ]);
                $arResp=json_decode($complete,false);
                if (isset($arResp->error)) {
                    $resp["err"]="OpenAi Server ERROR:".$arResp->error->message;
                } else {
                    $resp["resp"]="";
                    for($i=0;$i<count($arResp->choices);$i++)
                        $resp["resp"].=nl2br(htmlspecialchars(trim($arResp->choices[$i]->text)))."\r\n";
                }
            } else {
                //$resp["elms"]=$this->explain($query);
            }
        } catch (\Throwable $e){
            $resp["resp"]="";
            $resp["err"]=$e->getMessage();
        }
        return $resp;
    }

    function basicNlpIe($text){
        $this->_initOpenAi();
        $res=[];
        //$eText=$this->aiTranslate($text);
        //$eText=$this->aiGetPurpose($eText);

        // 
        // First of all translate the following customer request in english language, after that summarize the purpose of the translated text without write 'the customer' or 'customer', and so on, but just what he want. After that translate the summarized phrase in italian language and identify the key verbs, subjects and disease names in this last translation and write it in a json format:

        $complete = $this->_open_ai->completion([
            'model' => $this->_open_ai_model,
            'prompt' => "Summarize the purpose of the translated in english version of the following customer request, without write 'the customer' or 'customer' and so on, but just what he want. After that, translate that purpose text in italian language, then identify the key verbs, subjects and disease names in italian language as json format\r\n\"".$text."\"", 
            'temperature' => 0.2,
            'max_tokens' => 1000,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1,
        ]);
        $iaResp=json_decode($complete,false);
        if (isset($iaResp->error)) {
            $resp=$iaResp->error->message;
            $this->_aiErrorState=true;
            return "OpenAi Server ERROR:".$iaResp->error->message;
        } else {
            $subjs=[];
            $verbs=[];
            $medadv=[];
            $txt="";
            $trad="";
            $i=1;
            /*
            $elmsResp=explode("\n",$iaResp->choices[0]->text);
            if (trim($elmsResp[count($elmsResp)-2]==""))
                $rResp=[$elmsResp[count($elmsResp)-3],$elmsResp[count($elmsResp)-1]];
            else
                $rResp=[$elmsResp[count($elmsResp)-2],$elmsResp[count($elmsResp)-1]];
            $expl=":";
            if (strpos($rResp[0],"|")!==false)
                $expl="|";
            foreach ($rResp as $elem){
                $te=explode($expl,strtolower($elem));
                $ves=explode(",",strtolower($te[1]));
                if (strpos($te[0],"verb")!==false){
                    foreach ($ves as $ve)
                        array_push($verbs,trim($ve));
                }else{
                    foreach ($ves as $ve)
                        array_push($subjs,trim($ve));
                }
            }
            */
            $elmsResp=$iaResp->choices[0]->text;
            $fnd="Italian translation:";
            $mnSt=strpos($elmsResp,$fnd);
            if ($mnSt===false){
                $fnd="Italian:";
                $mnSt=strpos($elmsResp,$fnd);
            }
            if ($mnSt===false){
                $fnd="Scopo:";
                $mnSt=strpos($elmsResp,$fnd);
            }
            if ($mnSt===false){
                $fnd="";
                $mnSt=strpos($elmsResp,"Purpose:");
                if ($mnSt!==false)
                    $mnSt=strpos($elmsResp,"\n",$mnSt+5)+1;
            }
            $meaning=substr($elmsResp,$mnSt+strlen($fnd)+1,strpos($elmsResp,"\n",$mnSt+strlen($fnd)+1)-($mnSt+strlen($fnd)+2));
            $j_s_str=strpos($elmsResp,"{");
            $j_e_str=strpos($elmsResp,"}");
            $jresp="";
            if ($j_s_str===false && $j_e_str===false){
                // non è in JSON Format!
                $parts=explode("\n",$elmsResp);
                $jStr="{";
                for($i=0;$i<count($parts);$i++){
                    if (!empty(trim($parts[$i]))){
                        $pp=explode(":",$parts[$i]);
                        if (count($pp)==2){
                            if (strpos(strtolower($pp[0]),"verb")!==false || strpos(strtolower($pp[0]),"subj")!==false || strpos(strtolower($pp[0]),"dise")!==false){
                                $jStr.="\"".$pp[0]."\":[";
                                $els=explode(",",$pp[1]);
                                foreach ($els as $el){
                                    $jStr.="\"".trim($el)."\",";
                                }
                                $jStr=substr($jStr,0,strlen($jStr)-1);
                                $jStr.="],";
                            }
                        }
                    }
                }
                $jStr=substr($jStr,0,strlen($jStr)-1)."}";
                $j_s_str=0;
                $j_e_str=strlen($jStr);
                $elmsResp=$jStr;
            }
            if ($j_s_str!==false && $j_e_str!==false){
                $jresp=substr($elmsResp,$j_s_str,($j_e_str-$j_s_str)+1);
                $jelms=json_decode($jresp,true);
                if (!isset($jelms)||is_null($jelms)){
                    // Di tanto in tanto la risposta è {Verbs:prenotazione, Subjects:esame}
                    // ovvero un json senza virgolette, quindi provo a metterle io:
                    try{
                        $parts=explode(",",str_replace("{","",str_replace("}","",$jresp)));
                        if (count($parts)==2){
                            $el=explode(":",$parts[0]);
                            $jemls="{\"".str_replace('"',"",str_replace('"',"",$el[0]))."\":\"".str_replace('"',"",str_replace('"',"",$el[1]))."\",";
                            $el=explode(":",$parts[1]);
                            $jemls.="\"".str_replace('"',"",str_replace('"',"",$el[0]))."\":\"".str_replace('"',"",str_replace('"',"",$el[1]))."\"}";
                        }
                        $jelms=json_decode($jresp,true);
                    } catch (\Throwable $e){
                        return "Result analisys ERROR:".$e->getMessage()."\r\non:".$jresp;
                    }
                }
                foreach ($jelms as $jk=>$je){
                    if (strpos(strtolower($jk),"verb")!==false)
                        $verbs=$je;
                    elseif (strpos(strtolower($jk),"disease")!==false){
                        if (is_array($je))
                            $je=implode(",",$je);

                        $medadv=" ".$je." ";
                        $medadv=str_replace(","," , ",trim($medadv));
                        $medadv=str_replace([" di "," da "," in "," su "," tra "," fra "," il "," lo "," la "," i "," gli "," le "," un "," uno "," una "," a "," e "," o "," si "," mi "," è "," é "]," ",$medadv);
                        $medadv=str_replace([" , ",", "," ,"],",",$medadv);
                        $medadv=str_replace("  "," ",trim($medadv));
                        $medadv=str_replace("  "," ",trim($medadv));
                        $medadv=explode(",",$medadv);

                    }
                    else 
                        $subjs=$je;
                }
            }
            $iSubj=new \stdClass();
            $iVerb=new \stdClass();
            $iDunno=new \stdClass();
            $iVerb->pren=0;
            $iVerb->rich=0;
            $iVerb->info=0;

            // A volte succede che mette i soggetti nella lista verbi e viceversa...
            // quindi sono costretto a fare due check per i due array

            if (!is_array($verbs))
                $verbs=explode(" ",$verbs);
            if (!is_array($subjs))
                $subjs=explode(" ",$subjs);
            if (!is_array($medadv)){
                //$medadv=" ".$medadv." ";
                //$medadv=str_replace([" di "," da "," in "," su "," tra "," fra "," il "," lo "," la "," i "," gli "," le "," un "," uno "," una "," a "," e "," o "," si "," mi "," è "," é "]," ",$medadv);
                //$medadv=str_replace("  "," ",trim($medadv));
                $medadv=explode(" ",$medadv);
            }

            $this->_analver($iVerb,$iSubj,$iDunno,$verbs,1);
            $this->_analver($iVerb,$iSubj,$iDunno,$subjs,0);
            if (isset($iSubj->list) && !empty($iSubj->list))
                array_merge($subjs,explode(",",$iSubj->list));

            $this->_analelm($iVerb,$iSubjs,$iDunno,$subjs,1);
            $this->_analelm($iVerb,$iSubjs,$iDunno,$verbs,0);

            if (count($medadv)>0 && trim(strtolower($medadv[0]))!="n/a")
                $iSubjs.=implode("\r\n",$medadv);

            $iSubjs=implode("\n",$this->_stemArray(explode("\n",$iSubjs)));

            $iVerbs="(i:".$iVerb->info."/p:".$iVerb->pren."/r:".$iVerb->rich.") - ";
            if ($iVerb->info<1 && $iVerb->pren<1 && $iVerb->rich<1)
                $iVerbs.="(non ho capito...)\r\n>".$jresp;
            else {
                if ($iVerb->info<1 && $iVerb->pren<1 && $iVerb->rich>0)
                    $iVerbs.="richiesta visita o esame (prenotazione)";
                elseif ($iVerb->info>$iVerb->pren)
                    $iVerbs.="richiesta  informazioni";
                else
                    $iVerbs.="richiesta di prenotazione";
            }    
            $scarto="";
            if (isset($iDunno->list) && !empty($iDunno->list)){
                $dList=$this->_stemArray(explode(",",$iDunno->list));
                foreach ($dList as $el)
                    $scarto.=$el."\r\n";
            }
            //$res="Cosa ho capito?:{pl2}".$iVerbs."{/pl2}{hr}In relazione a:{pl2}".$iSubjs."{/pl2}{hr}Correlati:{pl2}{i}".$scarto."{/i}{/pl2}\r\n";
        }
        return ["res"=>$elmsResp,"meaning"=>$meaning,"unds"=>$iVerbs,"subj"=>$iSubjs,"rels"=>$scarto];
    }

    private function _analver(&$iVerb,&$iSubj,&$iDunno,$arrElem,$getExceptions){
        foreach ($arrElem as $ve){
            switch(trim(strtolower($ve))){
                case "appuntamento":
                case "incontro":
                case "prenotare":
                case "prenoto":
                case "prendere":
                case "prenderò":
                case "prenderà":
                case "prenotazione":
                case "volere":
                case "vorrei":
                case "vuole":
                case "vorrebbe":
                case "eseguire":
                case "esecuzione":
                    $iVerb->pren++;
                    break;
                case "ottenere":
                case "trattare":
                case "avere":
                case "avere bisogno":
                case "avere necessità":
                case "bisogno":
                case "necessità":
                case "comunicare":
                case "servire":
                case "servirà":
                case "serve":
                case "farò":
                case "fare":
                    $iVerb->rich++;
                    break;
                case "sapere":
                case "aiuto":
                case "aiutare":
                case "potere":
                case "può":
                case "posso":
                case "vuole sapere":
                case "vorrebbe sapere":
                case "richiedere":
                case "richiesta":
                case "ricevere":
                case "chiedere":
                case "descrivi":
                case "descrivere":
                case "informarsi":
                case "informazioni":
                case "informazione":
                case "informare":
                    $iVerb->info++;
                    break;
                case "esame":
                case "esami":
                case "esamina":
                case "esamini":
                case "esaminare":
                    $iSubj->exam++;
                    $iSubj->list.="esame,";
                    break;
                case "può aiutare":
                case "aiutare":
                case "visita":
                case "visitare":
                    $iSubj->vist++;
                    $iSubj->list.="visita,";
                    break;
                default:
                    if ($getExceptions)
                        $iDunno->list.=$ve.",";
            }
        }
    }

    private function _analelm(&$iVerb,&$iSubjs,&$iDunno,$arrElem,$getExceptions){
        foreach ($arrElem as $sb){
            $el=strtolower(trim($sb));
            $found=false;
            switch (true){
                case stristr($el,'appuntam'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'esam'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'visit'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'sindr'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'neuro'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'medic'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'logic'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'malatt'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'dott'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'dr.'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'terap'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
                case stristr($el,'cost'):
                    $iSubjs.=$el."\r\n";
                    $found=true;
                    break;
            }
            if (!$found){
                if(substr(trim($el),-3)=="fia" || substr(trim($el),-3)=="ica" || substr(trim($el),-3)=="emg" || substr(trim($el),-3)=="ecg" || substr(trim($el),-3)=="eeg")
                    $iSubjs.=$el."\r\n";
                else{
                    if (strpos(trim(strtolower($el)),"ssn")!==false || strpos(trim(strtolower($el)),"convenz")!==false)
                        $iSubjs.="regime convenzionato"."\r\n";
                    else{
                        if ($getExceptions)
                            $iDunno->list.=$el.",";
                    }
                }
            }
        }
    }

    private function _stemArray($inArray){
        $outArray=[];

        foreach ($inArray as $in){
            if (!(in_array($in,$outArray))){
                $add=true;
                foreach ($outArray as $out){
                    similar_text(strtolower($in), strtolower(str_replace([">> "," <<"],"",$out)), $perc);
                    $add=($perc<50);
                    if (!$add)
                        break;
                }
                if ($add){
                    if(stristr($in, 'convenzion'))
                        $in=">> ".$in." <<";
                    elseif(stristr($in, 'SSN'))
                        $in=">> ".$in." <<";
                    array_push($outArray,$in);
                }
            }
        }

        return $outArray;

        /*


        $theArray=[];
        $stemArr=[];
        
        $algorithms = Stemmer::algorithms();
        $algorithm = "italian";
        $stemmer = new Stemmer($algorithm, CharacterEncodingEnum::ISO_8859_1);
        foreach ($inArray as $elm){
            $elm=trim(strtolower($elm));
            $stem = $stemmer->stemWord($elm);
            if (!in_array($stem,$stemArr)){
                array_push($stemArr,$stem);
                array_push($theArray,$elm);
            }
        }
        return $theArray;
        */
    }



    /*
    function tokenize($text){
        $nlp = new \Web64\Nlp\NlpClient('http://localhost:6400/');
        $summary = $nlp->summarize( $text );


        //$tok = new WhitespaceTokenizer();
        //$stones = new TokensDocument($tok->tokenize($text)); // $stones now represents the "Sympathy for the devil" song
        //$doors = new TokensDocument($tok->tokenize($text)); // $doors now represents the "Hello, I love you" song
        //return $stones.",".$doors;


        return $summary;

    }

    function completion($text){
        //Function to process a text and convert it into pseudo-code language commands
        //Call the OpenAi API to convert the text into pseudo-code language commands 
        $ch = curl_init(); 
        curl_setopt($ch,CURLOPT_URL,"https://api.openai.com/v1/engines/davinci/completions"); 
        curl_setopt($ch,CURLOPT_POST, 1); 
        curl_setopt($ch,CURLOPT_POSTFIELDS, 
                "prompt=".$text."&max_tokens=125&temperature=1.0&top_p=1.0"); 
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
        
        $headers = array(); 
        $headers[] = "Content-Type: application/x-www-form-urlencoded"; 
        $headers[] = "Authorization: Bearer ".$this->_open_ai_key; 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        
        $result = curl_exec($ch); 
        if (curl_errno($ch)) { 
            return array("Error"=>curl_error($ch),"Result"=>"");
        } 
        curl_close ($ch); 
        //Decode the json response and extract the pseudo-code language commands  
        $resultData = json_decode($result, true);  

        //Return the pseudo-code language commands  
        return array("Error"=>"","Result"=>$resultData['choices'][0]['text']);  
    }


    function aiExtractElements($text){
        $this->_initOpenAi();
        $res=[];
        $complete = $this->_open_ai->completion([
            'model' => 'text-davinci-003',
            'prompt' => "table summarize in json format: if anyone ask me to do that, what I must do?\r\n".$text, 
            'temperature' => 0,
            'max_tokens' => 300,
            'frequency_penalty' => 1,
            'presence_penalty' => 1,
            'top_p' => 1,
            'best_of' => 1
        ]);
        $elmsResp=json_decode($complete,false);
        if (isset($elmsResp->error)) {
            $resp=$elmsResp->error->message;
            $this->_aiErrorState=true;
            return "OpenAi Server ERROR:".$elmsResp->error->message;
        } else {
            $elmsResp=trim($elmsResp->choices[0]->text);
            if (!(substr($elmsResp,0,1)=="[") && !(substr($elmsResp,0,1)=="{")){
                $p1=strpos($elmsResp,"{");
                $p2=strpos($elmsResp,"[");
                $p0=false;
                if (!($p1===false) && $p1 < $p2)
                    $p0=$p1;
                else    
                    $p0=$p2;
                if (!($p0===false)){
                    $elmsResp=trim(substr($elmsResp,$p0));
                }
            }
            if (!(substr($elmsResp,-1)=="]") && !(substr($elmsResp,-1)=="}")){
                $p2=strpos($elmsResp,"}");
                $p1=strpos($elmsResp,"]");
                $p0=false;
                if (!($p1===false) && $p1 < $p2)
                    $p0=$p2;
                else    
                    $p0=$p1;
                if (!($p0===false)){
                    $elmsResp=trim(substr($elmsResp,0,$p0+1));
                }
            }
            if ((substr($elmsResp,0,1)=="[" && substr($elmsResp,-1)=="]") || (substr($elmsResp,0,1)=="{" && substr($elmsResp,-1)=="}")){
                $elmsResp=General::fixJSON($elmsResp);
                // è un array
                $elementi=json_decode(strtolower($elmsResp),true);
                $elmsResp="";
                $words=[];
                $verbs=[];
                if (is_array($elementi)){
                    // verbs
                    // words
                    $words=$elementi["stopwords"];
                    $verbs=$elementi["verbs"];
                } else {
                    $words=$elementi;
                }

                //$word = strtok($str, $token_symbols);

                $leave=[];
                $stopwords = new StopWords('en');
                $partTxt=$stopwords->clean(str_replace([",",".",";",":","/","-","_","!","?"],[" "," "," "," "," "," "," "," "," "],$text));
                for($i=0;$i<count($words);$i++){
                    $words[$i]=trim($words[$i])." ";
                }
                for($i=0;$i<count($verbs);$i++){
                    $verbs[$i]=trim($verbs[$i])." ";
                }
                $partTxt=str_replace($words,array_fill(0,count($words)," "),$partTxt." ");
                $partTxt=str_replace($verbs,array_fill(0,count($verbs)," "),$partTxt." ");

                $partTxt=$this->SpellCheck($partTxt=$stopwords->clean($partTxt));

                $cleanwords =str_replace(" |","|",str_replace("| ","|",str_replace("| |","|",str_replace("| |","|",$stopwords->clean(implode(" | ",$words))))));
                
                if (substr($cleanwords,-1)=="|")
                    $cleanwords=substr($cleanwords,0,-1);
                if (substr($cleanwords,1)=="|")
                    $cleanwords=substr($cleanwords,1);
                $cleanwords=str_replace("||","|",$cleanwords);
                $words=explode("|",$cleanwords);

            }
        }





        return $res;
    }
    */
    /*
    function explain($text){
        $elems=$this->basicNlpIe($text);
        //$tran=$this->aiTranslate($text,"english");
        //$purp=$this->aiGetPurpose($tran);
        //$tokens=$this->tokenize($tran);
        //$elems=$this->aiExtractElements($tokens);
        if (count($elems)>0){
            $resp="--------[1mo razionale]---------\r\n".$resp."\r\n------[Elementari]---------\r\n".$elmsResp."\r\n------[2do razionale]---------\r\n";
            
            //$paResp="";
            //$paRes=explode(" is ",$res);
            //for ($pau=1;$pau<count($paRes);$pau++)
            //    $paResp.=" ".$paRes[$pau];
            
            $complete = $open_ai->completion([
                'model' => 'text-davinci-003',
                'prompt' => "Extract as json all keywords from this text:\r\n".$res, //"A table summarizing the asking as json:\r\n".$res,
                'temperature' => 0.2,
                'max_tokens' => 400,
                'top_p' =>1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0.2,
                'best_of' => 2
            ]);
            $arResp=json_decode($complete,false);
            $txt=trim($arResp->choices[0]->text);
            $jres=json_decode($txt,true);
            if (!isset($jres)){
                preg_match_all('~\{(?:[^{}]|(?R))*\}~', $txt,$mtch);
                $jres=json_decode(trim($mtch["0"][0]));
            }
            if ($jres!=null){
                $resp.=json_encode($jres)."[-!-]\r\n";
                $alreadyDone=false;
                foreach ($jres as $key => $res){
                    switch (strtolower($key)){
                        case("product"): 
                        case("products"): 
                        case("subjects"): 
                        case("comparison"): 
                            if (!$alreadyDone){
                                $alreadyDone=true;
                                if (is_array($res)){
                                    $resp.="compare ";
                                    foreach ($res as $pkey=>$pres){
                                        $resp.=" ".$pkey." and";
                                    }
                                    $resp=substr($resp,0,-4);
                                } else {
                                    $resp.=$res;
                                }
                                $resp.="\r\n";
                            }else{
                                if (is_array($res)){
                                    foreach ($res as $pkey => $pres){
                                        if (strpos($resp,$pkey)===false)
                                            $resp.=" ".$pkey."\r\n";
                                    }
                                } else {
                                    $resp.="product: ".$res."\r\n";
                                }
                            }
                            break;
                        case("request"): 
                        case("purpose"): 
                        case("question"): 
                        case("inquiry"): 
                        case("message"): 
                            if (!$alreadyDone){
                                $alreadyDone=true;
                                $resp.=$res."\r\n";
                            }
                            break;
                        case("answer"): 
                            if (!$alreadyDone && strlen($res)>5){
                                $alreadyDone=true;
                                $resp.=$res."\r\n";
                            }
                            break;
                        case("recipient"): 
                        case("person"): 
                            if (strpos($resp,$res)===false)
                                $resp.="for ".$res."\r\n";
                            break;
                        case("review"): 
                            if (!$alreadyDone){
                                $alreadyDone=true;
                                $resp.="for ".$res."\r\n";
                            }
                            break;
                        default:
                            if (!$alreadyDone){
                                $rsp="";
                                if (is_array($res)){
                                    if (isset($res[0]["Brand"])){
                                        $rsp=$res[0]["Brand"];
                                    }
                                    if (isset($res[0]["Model"])){
                                        $rsp.=" ".$res[0]["Brand"];
                                    } 
                                    if (empty($rsp))
                                        $rsp=$key;
                                } else {
                                    if (strtolower($key)=="purpose")
                                        $rsp.=$res."\r\n";
                                }
                                if (empty($rsp))
                                    $rsp=$res;
                                if(strpos(strtolower($resp),strtolower(trim($rsp)))===false)
                                    $resp.=$rsp."\r\n";
                            }
                            break;
                    }
                }
            }

            if (   !(strpos(strtolower($resp),"urgent")===false))
            {
                $this->_addElements("0.Urgente");
            }

            if (   !(strpos(strtolower($resp),"looking for")===false) 
                || !(strpos(strtolower($resp),"search for")===false)
                || !(strpos(strtolower($resp),"need")===false) 
                || !(strpos(strtolower($resp),"search of")===false))
            {
                $this->_addElements("1.Cerca");
            } else if (   !(strpos(strtolower($resp),"answer")===false) 
                || !(strpos(strtolower($resp),"request")===false) 
                || !(strpos(strtolower($resp),"inquire")===false)
                || !(strpos(strtolower($resp),"question")===false))
            {
                $this->_addElements("1.Chiede");
            } 

            if (   !(strpos(strtolower($resp),"appointment")===false) 
                || !(strpos(strtolower($resp),"appoint")===false))
            {
                $this->_addElements("2.Appuntamento");
            }
            
            if (   !(strpos(strtolower($resp),"job post")===false) 
                || !(strpos(strtolower($resp),"job oppo")===false))
            {
                $this->_addElements("2.Annuncio Lavoro");
            } else if (!(strpos(strtolower($resp),"job")===false))
            {
                $this->_addElements("3.Lavoro");
            }

            if (   !(strpos(strtolower($resp),"document")===false))
            {
                $this->_addElements("2.Documento");
            }

            if (   !(strpos(strtolower($resp),"availab")===false))
            {
                $this->_addElements("4.Disponibilità");
            }
            if (   !(strpos(strtolower($resp),"deliver")===false))
            {
                $this->_addElements("4.Consegna");
            }

            if (   !(strpos(strtolower($resp),"patient")===false)
                || !(strpos(strtolower($resp),"customer")===false)
                || !(strpos(strtolower($resp),"client")===false))
            {
                $this->_addElements("3.Cliente");
            }
            if (!(strpos(strtolower($resp),"exam")===false))
            {
                $this->_addElements("3.Esame");
            }
        } else 
            $resp.="\r\n".$res."\r\n";
    }
    */


}