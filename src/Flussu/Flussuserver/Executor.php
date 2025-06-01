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
 
 La classe Executor è stata estratta dalla classe Worker
 che era diventata ingestibile.
 Si occupa di eseguire il codice richiesto da Environment

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Flussuserver
 * CLASS NAME:       Executor
 * CLASS-INTENT:     Work/executor
 * -------------------------------------------------------*
 * CREATED DATE:     25.05:2024 - Aldus - Flussu v3.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * NEW: add Workflow Absolute Unique ID handling - 15-11-2024
 * -------------------------------------------------------*/

/**
 * The Executor class is responsible for executing various tasks and commands within the Flussu server.
 * 
 * This class handles the execution of different types of commands by interpreting the provided parameters
 * and invoking the appropriate methods. It manages the execution flow, logs activities, and handles errors
 * during the execution process.
 * 
 * Key responsibilities of the Executor class include:
 * - Setting reminder addresses and logging the activity.
 * - Sending emails using the `_sendEmail` method and handling any exceptions that occur.
 * - Executing OCR (Optical Character Recognition) tasks using the `_execOcr` method and storing the results.
 * - Sending SMS messages using the `_sendSms` method and handling any exceptions that occur.
 * - Managing the execution status and logging the execution process for debugging and monitoring purposes.
 * - Assigning variables and results to the session for further use in the application.
 * 
 * The class ensures that each command is executed in a controlled manner, with proper logging and error handling
 * to maintain the stability and reliability of the Flussu server.
 * 
 */


namespace Flussu\Flussuserver;

use Flussu\General;
use Flussu\Flussuserver\NC\HandlerNC;

class Executor{
    private $_xcelm=array();
    private $_en=0;
    /**
     * A function that is called by the _evalCmd function. It is used to process the commands that are
    * returned by the _evalCmd function.
    * 
    * Args:
    *   evalRet: the result of the evaluation of the block
    *   res: the result of the evaluation
    *   block: the block object
    *   WID: the ID of the workflow
    * 
    * Returns:
    *   The return value is an array of arrays.
    * Each array has two elements:
    */
    function outputProcess( $Sess, $Handl, $evalRet, $sentData, $block, $WID){
        $res="";
        //$res=$sentData;
        for ($i=0;$i<count($evalRet);$i++){
            $retArrCmd=$evalRet[$i];
            foreach ($retArrCmd as $innerCmd => $innerParams){
                try{
                    if (is_a($innerParams,"DateTime")){
                        $Sess->recLog("$innerCmd -> [DATE]");
                    } else if (is_array($innerParams)){
                        $Sess->recLog("$innerCmd -> ".json_encode($innerParams));
                    }
                    else
                        $Sess->recLog("$innerCmd -> ".$innerParams);
                } catch (\Throwable $e){
                    $Sess->recLog("$innerCmd -> (complex/err)");
                }
                if (substr( $innerCmd, 0, 6 ) == 'debug_' )
                    $innerCmd="DBG";
                switch ($innerCmd){
                    case "WARNING":                            
                    case "DBG":
                        $Sess->recLog($innerParams);
                        break; 
                    case "ERROR":
                        $Sess->recLog($innerCmd." ".$innerParams);
                        break; 
                    case "lang":
                        // IMPOSTAZIONE LINGUA 
                        $Sess->setLang($innerParams);
                        break; 
                    case "genQrCode":
                        // GENERAZIONE QR-CODE
                        $uri="/qrc/flussu_qrc.php?data=".$innerParams[0];
                        $this->_xcelm = array_merge($this->_xcelm, array("M$".$this->_en => array($uri,"")));
                        break;
                    case "getXCmdKey":
                        // RICHIESTA CHIAVE PER COMANDI ESTERNI 
                        $theKey=$this->_getCmdKey($Sess,$innerParams);
                        $Sess->assignVars("\$XCmdKey",$theKey);
                        $Sess->recLog("requested OTP for ".$this->arr_print($innerParams)." -> received [$theKey]");
                        break;
                    case "chkCodFisc":
                        // ESECUZIONE CHECK CODICE FISCALE
                        $Sess->recLog("Check ".$this->arr_print($innerParams));
                        $codf= new Command();
                        $result=$codf->chkCodFisc($innerParams);
                        $Sess->recLog("Risultato check codice fiscale=".$result->isGood[1]);
                        $Sess->assignVars("\$".$result->isGood[0],$result->isGood[1]);
                        $S="U";
                        $B="1899-12-31";
                        if ($result->isGood[1]){
                            $S=$result->sex[1];
                            $B=$result->bDate[1];
                        }
                        $Sess->assignVars("\$".$result->sex[0],$S);
                        $Sess->assignVars("\$".$result->bDate[0],$B);
                        break;
                    case "chkPIva";
                        $Sess->recLog("Check ".$this->arr_print($innerParams));
                        $piva= new Command();
                        $result=$piva->chkPIva($innerParams);
                        $Sess->recLog("Risultato check partita iva=".$result->isGood);
                        $Sess->assignVars("\$".$innerParams[1],$result->isGood);
                        break;
                    case "sendXCmdData":
                        // INVIO DATI A COMANDI ESTERNI 
                        $Sess->recLog("send external command ".$this->arr_print($innerParams));
                        $result=$this->_sendCmdData($Sess,$innerParams);
                        //array_push($res,$result);
                        break;
                    case "data":
                        // GESTIONE DATI INTERNI
                        $Sess->assignVars("\$dummy","\$wofoEnv->setDataJson('$innerParams')");
                        break;
                    case "sess_duration_h":
                        $Sess->recLog("set session duration (hours): ".$this->arr_print($innerParams));
                        $Sess->setDurationHours($innerParams);
                        break;
                    case "exit_to":
                        //$Sess->recLog("set exit:".$this->arr_print($innerParams));
                        if (!is_array($res)) $res=[];
                        array_push($res,array("exit",$innerParams));
                        break;
                    case "go_to_flussu":
                        $Sess->recLog("goto flussu".json_encode($innerParams));
                        if (!is_array($res)) $res=[];
                        $WID=$Handl->getFlussuWID($innerParams);
                        General::log(" ---> Goto flussu ".json_encode($WID));
                        array_push($res,array("WID",$WID["WID"]));
                        break;
                    case "back_to_flussu":
                        $Sess->recLog("back to flussu caller".$this->arr_print($innerParams));
                        if (!is_array($res)) $res=[];
                        array_push($res,array("BACK",$innerParams));
                        General::log(" <--- Back to flussu ".json_encode($innerParams));
                        break;
                    case "reminder_to":
                        if (empty($innerParams)){
                            $Sess->recLog("unsset the reminder address");
                            $Sess->assignVars("\$reminder_to","");
                        }
                        else{
                            $Sess->recLog("set reminder address".$this->arr_print($innerParams));
                            $Sess->assignVars("\$reminder_to",$innerParams);
                        }
                        if (!is_array($res)) $res=[];
                        array_push($res,array("reminder_to",$innerParams));
                        break;
                    case "sendEmail":
                        $Sess->statusCallExt(true);
                        try{
                            $Sess->recLog("send Email ".json_encode($innerParams));
                            $result=$this->_sendEmail($Sess,$innerParams, $block["block_id"]);
                            //array_push($res,$result);
                        } catch (\Throwable $e){
                            $Sess->recLog(" SendMail - execution EXCEPTION:". json_encode($e));
                            $Sess->statusError(true);
                        }
                        $Sess->statusCallExt(false);
                        break;
                    case "execOcr":
                        $Sess->statusCallExt(true);
                        $filePath=$innerParams[0];
                        $retVarName=$innerParams[1];
                        $reslt=$this->_execOcr($filePath);
                        $Sess->assignVars("\$".$retVarName,$reslt[0]);
                        $Sess->assignVars("\$".$retVarName."_error",$reslt[1]);
                        break;
                    case "sendSms":
                        $Sess->statusCallExt(true);
                        $retVarName="";
                        if (count($innerParams)>3)
                            $retVarName=$innerParams[3];
                        try{
                            $Sess->recLog("send Sms ".$this->arr_print($innerParams));
                            $reslt=$this->_sendSms($Sess,$innerParams);
                            if (!empty($retVarName))
                                $Sess->assignVars("\$".$retVarName,$reslt);
                        } catch (\Throwable $e){
                            $Sess->recLog(" SendSms - execution EXCEPTION:".json_encode($e));
                            $Sess->statusError(true);
                            if (!empty($retVarName))
                                $Sess->assignVars("\$".$retVarName,"ERROR");
                        }
                        $Sess->statusCallExt(false);
                        break;
                    case "httpSend":
                        $Sess->statusCallExt(true);
                        $retVarName=null;
                        try{
                            $Sess->recLog("call http URI".$this->arr_print($innerParams));
                            $data=null;
                            $retVarName="";
                            if (count($innerParams)>2)
                                $retVarName=$innerParams[2];
                            if (count($innerParams)>1)
                                $data=$innerParams[1]; 
                            $reslt=$this->_httpSend($Sess,$innerParams[0],$data);
                            if (!empty($retVarName))
                                $Sess->assignVars("\$".$retVarName,$reslt);
                            } catch (\Throwable $e){
                            $Sess->recLog(" httpSend - execution EXCEPTION:".json_encode($e));
                            $Sess->statusError(true);
                            if (!empty($retVarName))
                                $Sess->assignVars("\$".$retVarName,"ERROR");
                        }
                        $Sess->statusCallExt(false);
                        break;
                    case "doZAP":
                        $Sess->statusCallExt(true);
                        try{
                            $Sess->recLog("call Zapier Uri".$this->arr_print($innerParams));
                            $data=null;
                            if (count($innerParams)>2)
                                $data=$innerParams[2];
                            $reslt=$this->_doZAP($Sess,$innerParams[0],$data);
                            if (!empty($innerParams[1]))
                                $Sess->assignVars("\$".$innerParams[1],$reslt);
                        } catch (\Throwable $e){
                            $Sess->recLog(" call Zapier - execution EXCEPTION:".json_encode($e));
                            if (!empty($innerParams[1]))
                                $Sess->assignVars("\$".$innerParams[1],"ERROR");
                            $Sess->statusError(true);
                        }
                        $Sess->statusCallExt(false);
                        break;
                    case "inited":
                        $Sess->recLog("Flussu Environment inited ".$innerParams->format("d/m/yy H:n:i"));
                        if (!is_array($res)) $res=[];
                        array_push($res,array("exit",0));
                        break;
                    case "callSubwf":
                        $Sess->statusCallExt(true);
                        try{
                            $Sess->recLog("call SubWorkflow ".$this->arr_print($innerParams));
                            $this->_callSubwf($innerParams, $block["block_id"]);
                        } catch (\Throwable $e){
                            $Sess->recLog(" callSubwf - execution EXCEPTION:".json_encode($e));
                            $Sess->statusError(true);
                        }
                        $Sess->statusCallExt(false);
                        break;

                    case "sendToAi":
                        // V4.3 - Send to AI
                        $Sess->statusCallExt(true);
                        $Sess->recLog("AI provider: ".$innerParams[0]);
                        $Sess->recLog("call AI: ".$innerParams[1]);
                        switch  ($innerParams[0]){
                            case 1:
                                $ctrl=new \Flussu\Controllers\AiChatController(\Flussu\Controllers\Platform::GROK );
                                break;
                            case 2:
                                $ctrl=new \Flussu\Controllers\AiChatController(\Flussu\Controllers\Platform::GEMINI );
                                break;
                            case 3:
                                $ctrl=new \Flussu\Controllers\AiChatController(\Flussu\Controllers\Platform::DEEPSEEK );
                                break;
                            default:
                                $ctrl=new \Flussu\Controllers\AiChatController(\Flussu\Controllers\Platform::CHATGPT );
                                break;
                        }
                        $reslt=$ctrl->Chat($innerParams[1]);
                        $Sess->assignVars($innerParams[2],$reslt);
                        $Sess->recLog("AI response: ".$reslt);
                        break;

                /*
                    case "openAi":
                        // V2.8 - Query openAI
                        $ctrl=new \Flussu\Controllers\OpenAiController();
                        $reslt=$ctrl->genQueryOpenAi($innerParams[0],0);
                        $Sess->assignVars($innerParams[1],$reslt["resp"]);
                        break;
                    case "explAi":
                        // V2.8 - Try to explain as openAI
                        $ctrl=new \Flussu\Controllers\OpenAiController();
                        $reslt=$ctrl->genQueryOpenAi($innerParams[0],1);
                        $Sess->assignVars($innerParams[1],$reslt["resp"]);
                        break;
                    case "bNlpAi":
                        $ctrl=new \Flussu\Controllers\OpenAiController();
                        $reslt=$ctrl->basicNlpIe($innerParams[0]);
                        $Sess->assignVars($innerParams[1],$reslt);
                        break;
                    case "openAi-stsess":
                        $ctrl=new \Flussu\Controllers\OpenAiController();
                        $reslt=$ctrl->createChatSession($innerParams[0]);
                        $Sess->assignVars("$"."_openAiChatSessionId",$reslt);
                        break;
                    case "openAi-chat":
                        $ctrl=new \Flussu\Controllers\OpenAiController();
                        $csid=$Sess->getVarValue("$"."_openAiChatSessionId");
                        if (empty($csid)){
                            $csid=$ctrl->createChatSession("");
                            $Sess->assignVars("$"."_openAiChatSessionId",$csid);
                        }
                        $reslt=$ctrl->sendChatSessionText($innerParams[0],$csid);
                        $Sess->assignVars($innerParams[1],$reslt);
                        break;
                */

                    case "newMRec":
                        // V2.8 - New MultiRecWorkflow
                        $mwc=new \Flussu\Controllers\MultiWfController();
                        $reslt=$mwc->registerNew($innerParams[0],$innerParams[1],$innerParams[2],$innerParams[3]);
                        $reslt="[".str_replace("_","",$reslt)."]";
                        $Sess->assignVars($innerParams[4],$reslt);
                        break;
                    case "addVarValue":
                        // V2.8 - Add a var with passed name and append passed value
                        $Sess->assignVars($innerParams[0],$innerParams[1]);
                        break;
                    case "print2Pdf":
                        // V2.8 - Print in PDF without header/footer
                        $pdfPrint=new \Flussu\Controllers\PdfController();
                        $tmpFile=$pdfPrint->printToTempFilename($innerParams[0],$innerParams[1]);
                        $Sess->assignVars($innerParams[2],$tmpFile);
                        break;
                    case "print2PdfwHF":
                        // V2.8 - Print in PDF with header/footer
                        $pdfPrint=new \Flussu\Controllers\PdfController();
                        $tmpFile=$pdfPrint->printToTempFilename($innerParams[0],$innerParams[1],$innerParams[2],$innerParams[3]);
                        $Sess->assignVars($innerParams[4],$tmpFile);
                        break;
                    case "printRawHtml2Pdf":
                        // V2.9.5 - Print a RAW HTML on a sheet as PDF
                        $pdfPrint=new \Flussu\Controllers\PdfController();
                        //$tmpFile=$pdfPrint->pippo($innerParams[0]);
                        $tmpFile=$pdfPrint->printHtmlPageToTempFilename($innerParams[0]);
                        //
                        $Sess->assignVars($innerParams[1],$tmpFile);
                        break;
                    case "getPaymentLink":
                        //   0                   1                     2         3          4           5                         6                             7                            8                             9
                        //$provider,$stripeCompanyAccountName,$stripeKeyType,$paymentId, $prodName,$prodPrice   ,$prodImg                          ,$successUri                    ,$cancelUri                  , $varStripeRetUriName
                        // stripe  , milleisole              , test OR prod , 123456  ,  puzzle  , 4999 (49,99) , https://www.sample.com/image.jpg, https://www.sample.com/ok.php, https://www.sample.com/ko.php, stripeRetUri
                        $res=$this->_getPaymentLink($innerParams);
                        $Sess->assignVars("$".$innerParams[9],$res);
                        break;
                    case "excelAddRow":
                        $fileName=$innerParams[0];
                        $excelData=$innerParams[1];

                        



                        break;

                    case "createLabel":
                        // V3.0.1 - Add a label to the current block
                        if (!is_array($res)){ 
                            $res=[];
                        }
                        else {
                            if (isset($res["addElements"]))
                                $elem_arr=$res["addElements"];
                            else
                                $elem_arr=[];
                        }
                        $elem_arr[]=["type"=>"L","text"=>$innerParams[0]];
                        $res["addElements"]=$elem_arr;
                        break;
                    case "createButton":
                        // V3.0.1 - Add a label to the current block
                        //$buttonVarName,$clickValue, $buttonText, $buttonExit=0)
                        //foreach ($frmElms as $Key => $aElm){
                        if (!is_array($res)){ 
                            $res=[];
                        }
                        else {
                            if (isset($res["addElements"]))
                                $elem_arr=$res["addElements"];
                            else
                                $elem_arr=[];
                        }
                        $elem_arr[]=["type"=>"B","text"=>$innerParams[2],"value"=>$innerParams[1],"varname"=>$innerParams[0],"exit"=>$innerParams[3]];
                        $res["addElements"]=$elem_arr;
                        break;

                    case "timedRecall":
                        // V3.0 - Set to recall a workflow at a specified date/time
                        $rmins=$innerParams[1];
                        $rdate=$innerParams[0];
                        if (is_null($rmins)){
                            //$to_time = strtotime("2008-12-13 10:42:00");
                            //$from_time = strtotime("2008-12-13 10:21:00");
                            $rdate=new \DateTime($rdate); 
                            $datenow=new \DateTime();
                            $rmins = round(abs(($datenow->getTimestamp() - $rdate->getTimestamp()))/60,2);
                        }
                        // minuti di attesa: $rmins
                        $varWidBid=$Sess->getvarValue("$"."_dtc_recallPoint");
                        if (!empty($varWidBid)){
                            $rwid=substr($varWidBid,0,strpos($varWidBid,":"));
                            $rbid=str_replace($rwid.":","",$varWidBid);
                        }
                        $rwid=substr_replace(substr_replace($rwid,"_",strlen($rwid)-1,1),"_",0,2);
                        $WofoId=General::demouf($rwid);
                        //$WofoId=General::demouf(str_replace(["[","]"],["_","_"],$rwid));
                        if ($Handl->createTimedCall($WofoId,$Sess->getId(),$rbid,"",$rmins))
                            $Sess->setDurationHours(round($rmins/60,2)+2);
                        break;
                    case "notify":
                        // V2.2 - Notifications
                        switch ($innerParams[0]){
                            case "A":
                                // alert
                                $Sess->setNotify(1,"",$innerParams[2]);
                                break;
                            case "AR":
                                // add Row to Chat
                                $Sess->setNotify(4,"",$innerParams[2]);
                                break;
                            case "CI":
                                // counter-init
                                $Sess->setNotify(2,$innerParams[1],$innerParams[2]);
                                break;
                            case "CV":
                                // counter value update
                                $Sess->setNotify(3,$innerParams[1],$innerParams[2]);
                                break;
                            case "NC":
                                // Notify Callback
                                $cbBid="";
                                $cbWid=$WID;
                                if (substr($innerParams[2],0,1)=="["){
                                    // è un wid o un wid/bid
                                    $prm=explode(":",$innerParams[2]);
                                    if (count($prm)>1)
                                        $cbBid=$prm[1];
                                    $cbWid=$prm[0];
                                    if ($cbBid==""){
                                        $aWid= HandlerNC::WID2Wofoid($cbWid);
                                        $res=$Handl->getFlussuNameFirstBlock($aWid);
                                        $cbBid=$res[0]["start_blk"];
                                    }
                                } elseif (substr($innerParams[2],0,4)=="exit"){
                                    //è un BID identificato dall'uscita # indicata
                                    // caricare il BID e selezionare il BID dell'exit prescelto.
                                    $prm=explode("(",$innerParams[2]);
                                    $prm=intval(str_replace(")","",$prm[1]));
                                    $cbBid=$block["exits"][$prm]["exit_dir"];
                                    //$cbBid=$block->exit[0];
                                } else {
                                    // è un BID
                                    $cbBid=$innerParams[2];
                                }

                                $cbWid=General::curtatone(substr(str_replace("-","",$Sess->getId()),5,5),$cbWid);
                                $cbBid=General::curtatone(substr(str_replace("-","",$Sess->getId()),5,5),$cbBid);

                                $Sess->setNotify(5,$cbWid,$cbBid);
                                break;
                            default:
                                // notify
                                try{
                                    $Sess->setNotify(0,$innerParams[1],$innerParams[2]);
                                } catch (\Throwable $e){
                                    // do nothing
                                }
                                break;
                        }
                        break;
                    default:
                        if (substr($innerCmd,0,1)=="$"){
                            $vval="";
                            if (is_array($innerParams) && count($innerParams)>0)
                                $vval=$innerParams[0];
                            if ($vval===true)
                                $vval="true";
                            else if ($vval===false)
                                $vval="false";
                            $Sess->assignVars($innerCmd,$vval);
                        } else 
                            $Sess->recLog("Command $innerCmd unknown!");
                        break;
                }
            }
        }
        return $res;
    }

    /**
     * It takes an array and returns a string with each key and value on a separate line
     * 
     * Args:
     *   arr: The array to print
     */

    function arr_print($arr){
        $retStr = "";
        if (is_array($arr)){
            foreach ($arr as $key=>$val){
                $retStr .=  $key . '=';
                if (is_array($val))
                    $retStr .= $this->arr_print($val) . '\r\n';
                else
                    $retStr .= $val . '\r\n';
            }
        } else
            $retStr.=$arr;
        return $retStr;
    }

    /**
     * It takes an array of parameters, and returns a string
    * 
    * Arguments:
    * 
    * * `params`: 
    * 
    * Returns:
    * 
    * The return value is a JSON object with the following fields:
    *     - status: 
    *         - 0: success
    *         - 1: error
    *     - message: 
    *         - if status is 0, this field is empty
    *         - if status is 1, this field contains the error message
    *     - key: 
    *         - if status is 0, this
    */
    private function _getCmdKey($sess,$params){
        $sess->statusCallExt(true);
        // address  
        // command
        // userid
        // password
        $res=false;
        $wem=new Command();
        $thisIp=$_SERVER['SERVER_ADDR'];
        $jsonReq=json_encode(array("cmd"=>$params[1],"uid"=>$params[2],"uak"=>md5($params[3].$thisIp)));
        //v1.0 --- $result = $wem->execRemoteCommand( $params[0]."?C=G",$jsonReq);
        $result = $wem->execRemoteCommand($params[0],$jsonReq);
        $jr=json_decode($result);
        if (isset($jr->key)){
            $result=$jr->key;
            $sess->recLog("new command-key=$result from ".$params[0]);
            $sess->statusCallExt(false);
        } else {
            $sess->recLog("NO command-key from remote... Result=".$result);
            $result="";
        }
        return $result;
    }

    /**
     * It checks if a string contains a bad command, and if it does, it replaces it with a random string
    * 
    * Args:
    *   str: the string to be checked
    *   badCmds: an array of strings that are considered bad.
    *   offset: The offset from the beginning of the string to start searching.
    * 
    * Returns:
    *   The return value is the number of replacements that were made.
    */
    private function CheckParentesys($str,$badCmds,$offset=0){
        // NON FINITO
        return $str;
        
        /*
        $start = strpos($str, '(',$offset);
        if ($start!==false){
            $end = strpos($str, ')', $start + 1);
            if ($end!==false){
                $length = $end - $start;
                // RIVEDERE DA QUI
                //======================================
                $string = preg_replace('/\s+/', '', substr($str, $start + 1, $length - 1));
                $bad=false;
                foreach ($badCmds as $line){
                    $string2=str_ireplace($line,"NO",$string);
                    if ($string2!=$string){
                        //GOTCHA!
                        $bad=true;
                        break;
                    }
                }
                //======================================
                // A QUI
                if ($bad){
                    $string2=str_replace(".","*",str_replace(["(",")"],["|","|"],str_replace(" ","?",$string)));
                    str_replace($string,$string2,$str);
                }
                $str=$this->CheckParentesys($str,$badCmds,$start+$length+1);
            }
        }
        return $str;
        */
    }

    private function _getPaymentLink($Params){
        $providerClass = 'Flussu\\Controllers\\' . ucfirst(strtolower($Params[0]))."Controller";
        if (!class_exists($providerClass)) {
            throw new \Exception("NoProvider", "Provider [".$Params[0]."] not found or not defined");
        }
        $stcn=new $providerClass();
        if ($stcn->init($Params[1],$Params[2])){
            $res=$stcn->createPayLink($Params[3],$Params[4],$Params[5],$Params[6],$Params[7],$Params[8]);
        }
        else {
            $res=$Params[0]." init ERROR! Company Name or Key Type not found!";
        }
        return $res;
    }




    /**
    * It sends an SMS message to a phone number
    * 
    * Args:
    *   params: an array of parameters to be passed to the function
    * 
    * Returns:
    *   The return value is the result of the function.
    */
    private function _sendSms($Sess,$params){
        //$senderName,$toPhoneNum,$message,$retVarName,$datetime
        $sentDt="";
        $retvName="";
        $sender=$params[0];
        $phoneNum=$params[1];
        $message=$params[2];
        //if (count($params)>3)
        //    $retvName=$params[3];
        if (count($params)>4)
            $sentDt=$params[4];
        $wem=new Command();
        $res=$wem->sendSMS($sender,$phoneNum,$message);
        $Sess->recLog("SMS sent to $phoneNum: $message");
        General::log("SMS sent to $phoneNum: $message");
        $Sess->recLog($this->arr_print($res));
        return $res;
    }

    private function _execOcr($filePath){
        $res[0]="";
        $res[1]="error: unknown";
        if (file_exists($filePath)){
            $filename = pathinfo($filePath, PATHINFO_FILENAME);
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $filename=explode(".".$ext,$filename)[0];
            $to=explode("Uploads".DIRECTORY_SEPARATOR."flussus",$filePath)[0]."Uploads".DIRECTORY_SEPARATOR."OCR".DIRECTORY_SEPARATOR.$filename;
            $ocr=$to.".txt";
            $to=$to.".".$ext;
            $res[1]="error: Cannot copy file";
            if (copy($filePath, $to)){
                $res[1]="error: OCR file not found";
                $i=0;
                do{
                    sleep(1);
                    if (file_exists($ocr)){
                        $res[0]=file_get_contents($ocr);
                        $res[1]="";
                        unlink($ocr);
                        unlink($filePath);
                        break;
                    }
                } while ($i++ < 6);
            } else 
                $res[1]="error: cannot copy file to ".$to;
        } else 
            $res[1]="error: source file not found";
        return $res;
    }

    /**
    * It sends an email.
    * 
    * Args:
    *   params: 
    *   bid: the bid of the user who is sending the email
    * 
    * Returns:
    *   The return value is the result of the last command executed.
    */
    private function _sendEmail($Sess,$params, $bid=""){
        $res=false;
        $wem=new Command();
        $providerCode=null;
        $attaches=[];
        $usrEmail=""; // rilevato da usename usato per autenticare l'account email.
        $usrName= 'Flussu Service';
        if (count($params)>4){
            if (!empty($params[4]) && !is_array($params[4]))
                $usrName=$params[4];
            if (!empty($params[4]) && is_array($params[4]))
                $attaches=$params[4];
        }
        if (count($params)>5){
            if (is_array($params[5]))
                $attaches=$params[5];
        }
        $Sess->recLog("Mail send:");
        $result = $wem->localSendMail($Sess, $usrEmail, $usrName,$params[0],$params[1],$params[2],$params[3],$bid,$attaches,$providerCode); 
        $Sess->recLog($result);
        $res=true;
        return $res;
    }

    /**
    * It calls a sub-workflow, passing it the parameters in the array , and returns the result
    * to the workflow with the ID 
    * 
    * Args:
    *   params: an array of parameters to pass to the sub-workflow.
    *   bid: the current block id
    * 
    * Returns:
    *   The return value of the sub-workflow.
    */
    private function _callSubwf($params, $bid){
        $res=false;
        $subWID=$params[0];
        $subParams=$params[1];
        $returnTo=$bid;

        return $res;
    }


    /**
    * It takes a URI and an array of data, and sends it to the URI using the http_build_query function
    * 
    * Args:
    *   uri: The URI to call.
    *   arrayData: an array of data to be sent to the server.
    * 
    * Returns:
    *   The result of the call to the URI.
    */
    private function _httpSend($Sess,$uri,$arrayData){
        $Sess->statusCallExt(true);
        $res=false;
        $wem=new Command();
        $thisIp=$_SERVER['SERVER_ADDR'];
        $data="";
        if (!empty($arrayData)){
            $data=http_build_query($arrayData)."\n";
        }
        $result = $wem->callURI($uri,$data);
        return $result;
    }


    /**
    * It sends a request to the ZAP server, and returns the response
    * 
    * Arguments:
    * 
    * * `uri`: the URI of the service to call
    * * `params`: the parameters to be passed to the external service.
    * 
    * Returns:
    * 
    * The result of the call.
    */
    private function _doZAP($Sess,$uri,$params){
        $data=[];
        if (!empty($params))
            $data["data"]=json_decode($params,true);
        $data["info"]=["server"=>"flussu","recall"=>GENERAL::getHttpHost(),"WID"=>$Sess->getStarterWID(),"SID"=>$Sess->getId(),"BID"=>$Sess->getBlockId()];
        $jsonReq=json_encode($data);
        $Sess->statusCallExt(true);
        $cmd=new Command();
        $result = $cmd->doZAP($uri,$jsonReq);
        return $result;
    }

    /**
     * It sends a command to a remote server, and returns the result
     * 
     * Arguments:
     * 
     * * `params`: array of parameters
     * 
     * Returns:
     * 
     * - if the result is a string, it is returned as is
     *     - if the result is a json string, it is decoded and returned as an object
     *     - if the result is an object, it is returned as is
     *     - if the result is an array, it is returned as is
     *     - if the result is a boolean, it is returned as
     */
    private function _sendCmdData($Sess,$params){
        $Sess->statusCallExt(true);
        // address
        // key
        // json
        // resultVar
        $res=false;
        $wem=new Command();
        //v1.0 --- $result = $wem->execRemoteCommand($params[0]."?C=E&K=".$params[1],$params[2]);
        $jsonReq=json_encode(array("key"=>$params[1],"data"=>$params[2]));
        $result = $wem->execRemoteCommand($params[0],$jsonReq);

        $jr=json_decode($result);
        if (!isSet($jr->original) || is_null($jr->original->result)){
            $Sess->recLog("response problem after calling ".$params[0]." (K=".$params[1].")");
            $Sess->assignVars("\$".$params[3],false);
        } else {
            if (!(is_string($jr->original->result) || is_numeric($jr->original->result)))
                $result=json_encode($jr->original->result);
            $Sess->recLog("ext command data set=$result from ".$params[0]." (K=".$params[1].")");
            if (strlen($result)>4 && substr($result,0,5)=="ERROR")
                $Sess->assignVars("\$".$params[3],false);
            else {
                if ($this->isJson($result))
                    $result=json_decode($result);
                if (isset($result->original->result) && $this->isJson($result->original->result)){
                    $result=json_decode($result->original->result);
                }
                $Sess->assignVars("\$".$params[3],$result);
            }
        }
        $Sess->statusCallExt(false);
        return $result;
    }

    
    /**
     * If the string is valid JSON, it will return true, otherwise it will return false
     * 
     * Arguments:
     * 
     * * `string`: The string to be checked.
     * 
     * Returns:
     * 
     * function isJson() {
     *         json_decode();
     *         return json_last_error() === JSON_ERROR_NONE;
     *     }
     */
    function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


 }