<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:    FlussuSrv API Interface
 * CREATED DATE:  25.Feb.2021 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/

 /**
 * The Flow class is responsible for managing the execution of API requests within the Flussu server.
 * 
 * This class handles incoming HTTP requests, processes them, and coordinates with other components such as
 * User, Command, and Handler to execute the necessary operations. It ensures that the API requests are handled
 * efficiently and that appropriate responses are returned to the client.
 * 
 * Key responsibilities of the Flow class include:
 * - Handling incoming HTTP requests and setting appropriate headers for CORS (Cross-Origin Resource Sharing) and content type.
 * - Managing user sessions and integrating user-specific data into the execution flow.
 * - Coordinating with the Command and Handler classes to execute specific operations based on the request.
 * - Processing raw data files if provided and integrating them into the execution flow.
 * - Ensuring that the API responses are correctly formatted and returned to the client.
 * - Handling errors and exceptions that occur during the execution process.
 * 
 * The class is designed to be the central point for managing the execution of API requests, ensuring that all
 * components work together seamlessly to provide a reliable and efficient API service.
 * 
 * @package App\Flussu\Api\V40
 * @version 4.0.0
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

namespace Flussu\Api\V40;

use Flussu\Flussuserver\Request;

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Flussuserver\Command;
use Flussu\Flussuserver\Handler;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Controllers\AppController;
class Flow {
    public function exec(Request $Req, User $theUser, $file_rawdata=null){
        
        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');

        $mustBeLogged=false;
        $authLevel=0;

        $db= new Handler();
        $dbnc= new HandlerNC();
        $r_json = array();
        $elems = array();
        $qty=5;
        $leaveWid=false;
        $auk="";

        $_debug=[];

        $CMD=General::getGetOrPost("C");
        $LNG=General::getGetOrPost("L");
        $UUID=General::getGetOrPost("UUID");
        $WFAUID=General::getGetOrPost("WFAUID");
        $ON_UUID=General::getGetOrPost("ON_UUID");
        $WID=General::getGetOrPost("WID");
        $CWID=General::getGetOrPost("CWID");
        $NAM=General::getGetOrPost("N");
        $DBG=General::getGetOrPost("DBG");

        $_debug["DATE"]=date("Y-m-d H:i:s");
        $_debug["RCV"]="CMD=".$CMD."|UUID=".$UUID."|WID=".$WID."|WFAUID=".$WFAUID."|ON_UUID=".$ON_UUID."|CWID=".$CWID."|NAM=".$NAM."|LNG=".$LNG;

        $ccwid=$CWID;
        if (!empty($CWID)){
            $sep=General::separateCwid($CWID);
            $auk=$sep->auk;
            $CWID=$sep->wid;
            if (!empty($file_rawdata))
                $file_rawdata=str_replace($ccwid,$CWID,$file_rawdata);
        }

        if (empty($WID) && !empty($CWID))
            $WID=$CWID;

        if ($LNG=="")
            $LNG="EN";

        $WofoId="";
        if ($WID!=""){
            if (strlen($WID)<30 && strpos($WID, '[w') === 0 && strpos($WID, ']')===strlen($WID)-1) {
                $WID=substr_replace(substr_replace($WID,"_",strlen($WID)-1,1),"_",0,2);
                $WofoId=General::demouf($WID);
            }
            if ($WofoId=="" || $WID==$WofoId || !is_numeric($WofoId)){
                $result=json_encode(array("result"=>"ERR:2","message"=>"You must provide a valid WID (Workflow ID)."));
                die($result);
            }
        }
        $result="";
        $getJustFlowExec=false;
        $forEditingPurpose=false;
        if ($CMD=="e" || $CMD=="E"){
            // EDITING
            $forEditingPurpose=true;
            $CMD="G";
            $LNG="";
        }

        $_debug["START-EXEC"]=time();
        $_debug["ELAB"]="CMD=".$CMD."|UUID=".$UUID."|WID=".$WID."|WFAUID=".$WFAUID."|ON_UUID=".$ON_UUID."|CWID=".$CWID."|NAM=".$NAM."|LNG=".$LNG;
        $_debug["EXEC"]="";
        switch(strtoupper(trim($CMD))){
            case "DEPLOY":
                $_debug["EXEC"].="DEPLOY\r\n";
                $DeployTo=trim(General::getGetOrPost("TO"));
                if (is_numeric($WofoId) && intval($WofoId>0)){
                    $wflo=$db->getWorkflow($WofoId, "", false, true);
                    $encoded=json_encode($wflo);

                    $wid=General::camouf($WofoId);
                    $wid=substr_replace(substr_replace($wid,"]",strlen($wid)-1,1),"[w",0,1);

                    $wcmd= new Command();
                    $result =$wcmd->execRemoteCommand($DeployTo."?C=RD&WID=".$wid,$encoded);
                } 
                if ($result=="")
                    $result=json_encode(array("result"=>"ERR:0","message"=>"CURL error"));
                break;
            case "UUID":
                $_debug["EXEC"].="UUID\r\n";
                $Qty=1;
                $qty=General::getGetOrPost("QTY");
                if ($qty!="")
                    $Qty=intval($qty);
                if ($Qty>0 && $Qty<51){
                    $res=General::getUuidv4($Qty);
                    $result=(json_encode(["uuid"=>$res]));
                } else 
                    $result=(json_encode(["Error"=>"800A"]));
                break;
            case "US":
                $_debug["EXEC"].="US\r\n";
                $leaveWid=true;
                $res=$dbnc->getUserFlussus($theUser->getId());
                $result=json_encode($res);
                break;
            case "L":
                $_debug["EXEC"].="L\r\n";
                $res=$db->getFlussu(false,$theUser->getId());
                $result=json_encode($res);
                break;
            case "C":
                $_debug["EXEC"].="C\r\n";
                if ($NAM!="" && $theUser!=null && $theUser->getId()>0){
                    // CREA UN WORKFLOW E RESTITUISCI UN WID
                    $rawdata = $file_rawdata; //file_get_contents('php://input');
                    $data = json_decode($rawdata);
                    if (is_null($data)){
                        $data=new \stdClass();
                        $data->name=$NAM;
                        $data->description="";
                        $data->supp_langs="IT";
                    }
                    $result=json_encode($dbnc->createFlofo($data,$theUser->getId()));
                } else {
                    if ($theUser==null || $theUser->getId()<1)
                        $result=json_encode(array("result"=>"ERR:0","message"=>"No user provided. Have an user is mandatory."));
                    else
                        $result=json_encode(array("result"=>"ERR:0","message"=>"You must provide a Workflow NAME."));
                }
                break;
            case "P":
                $_debug["EXEC"].="P\r\n";
                if ($UUID!=""){
                    // DUPLICATE BLOCCO senza registrarlo nel DB
                    $result=$dbnc->duplicateFlussuBlock($UUID,false);
                    //$result=json_encode(array("newUuid"=>$dupedBlk));
                }
                else
                    $result=json_encode(array("result"=>"ERR:7","message"=>"You must provide a Block ID"));
                break;
            case "PR":
                $_debug["EXEC"].="PR\r\n";
                if ($UUID!=""){
                    // DUPLICATE BLOCCO registrandolo nel DB
                    $result=$dbnc->duplicateFlussuBlock($UUID,true);
                    //$result=json_encode(array("newUuid"=>$dupedBlk));
                }
                else
                    $result=json_encode(array("result"=>"ERR:7","message"=>"You must provide a Block ID"));
                break;
            case "S":
                $_debug["EXEC"].="S\r\n";
                $getJustFlowExec=true;
            case "G":
                $_debug["EXEC"].="G\r\n";
                if ($UUID!=""){
                    // GET UN BLOCCO
                    $buildBlk=$db->buildFlussuBlock("", $UUID, $LNG, $getJustFlowExec, $forEditingPurpose);
                    $result=json_encode($buildBlk);
                } else if ($WID!="" || $WFAUID!=""){
                    if (empty($WID)){
                        $ret=$db->getFlussuWID($WFAUID);
                        $wid=$ret["wid"];
                        $WID=$ret["WID"];
                    }
            
                    if ($getJustFlowExec && $LNG=="")
                        $LNG="EN";
                    
                    if ($WFAUID!=""){
                        $wflo=$db->getWorkflowByUuid($wid,$WID,$WFAUID,$LNG,$getJustFlowExec,$forEditingPurpose);
                        $WID=General::camouf($wflo["workflow"][0]["id"]);
                    } else {
                        $wid=General::demouf($WID);
                        $wflo=$db->getWorkflow($wid,$WID, $LNG, $getJustFlowExec, $forEditingPurpose);
                    }
                    
                    if ($wflo["workflow"][0]["app_id"]>0){
                        $apc=new AppController();
                        $wflo["workflow"][0]["app_id"]=$apc->getCode($WID);
                    }
                    $result=json_encode($wflo, JSON_UNESCAPED_UNICODE);
                    //$result=json_encode(array("workflow"=>$wflo));
                } 
                if ($result=="")
                    $result=json_encode(array("result"=>"ERR:1","message"=>"You must provide an UUID (Block ID) or a WID (Workflow ID)."));
                break;
            case "RD":
                $_debug["EXEC"].="RD\r\n";
                // Receive a DEPLOY
                // If a workflow does not exist create it then update it
                $rawdata = $file_rawdata; //file_get_contents('php://input');
                $data = json_decode($rawdata);
                if (empty($data))
                    $result=json_encode(array("result"=>"ERR:7","message"=>"You must provide some Workflow data to deploy..."));
                else{
                    $data->workflow[0]->description.="\r\n==================\r\nTransfer done on ".date("Y-m-d H:i:s");
                    $result=json_encode($dbnc->receiveFlofo($data)[0]);
                }
                break;
            case "IS":
                $_debug["EXEC"].="IS\r\n";
            case "IN":
                $_debug["EXEC"].="IN\r\n";
                // IMPORT AND SUBSTITUTE
                if ($theUser->getId()>0){
                    if ($WID!="" || $WFAUID!=""){
                        // IMPORT WORKFLOW
                        $rawdata = $file_rawdata; //file_get_contents('php://input');
                        $rawdata = subStr($rawdata,1,strlen($rawdata)-2);
                        $rawdata =base64_decode(base64_decode($rawdata));
                        //$rawdata=utf8_encode($rawdata);
                        $data = json_decode($rawdata);
                        if (empty($data))
                            $result=json_encode(array("result"=>"ERR:5","message"=>"You must provide some Workflow data in POST."));
                        else{
                            if (empty($WID)){
                                $ret=$db->getFlussuWID($WFAUID);
                                $WID=$ret["WID"];
                            }
                            if (strtoupper(trim($CMD))=="IS")
                                $result=json_encode($dbnc->importFlofo($data,$WID));
                            else
                                $result=json_encode($dbnc->addToFlofo($data,$WID));
                        }
                    }
                    if ($result=="")
                        $result=json_encode(array("result"=>"ERROR","message"=>"You must provide an UUID (Block ID) or a WID (Workflow ID)."));
                } else {
                    $result=json_encode(array("result"=>"ERROR","message"=>"You must be a trusted user."));
                }
                break; 
            case "U":
                // UPDATE
                $_debug["EXEC"].="U\r\n";
                if ($UUID!=""){
                    // UPDATE UN BLOCCO
                    $rawdata = $file_rawdata; //file_get_contents('php://input');
                    $data = json_decode($rawdata);
                    if (empty($data))
                        $result=json_encode(array("result"=>"ERR:6","message"=>"You must provide some Block data in POST."));
                    else
                        $result=json_encode($dbnc->updateFlofoBlock($data));
                } else if ($WID!="" || $WFAUID!=""){
                    // UPDATE WORKFLOW
                    $rawdata = $file_rawdata; //file_get_contents('php://input');
                    $data = json_decode($rawdata);
                    if (empty($data))
                        $result=json_encode(array("result"=>"ERR:5","message"=>"You must provide some Workflow data in POST."));
                    else {
                        if (empty($WID)){
                            $ret=$db->getFlussuWID($WFAUID);
                            $WID=$ret["WID"];
                        }
                        $result=json_encode($dbnc->updateFlofo($data,$WID));
                    }
                }
                if ($result=="")
                    $result=json_encode(array("result"=>"ERR:1","message"=>"You must provide an UUID (Block ID) or a WID (Workflow ID)."));
                break;
            case "AW":
                $_debug["EXEC"].="CWU\r\n";
                if ($WID!="" && $WFAUID!=""){
                    $wid=General::demouf($WID);
                    $result=$dbnc->change_wfAUId($wid,$WFAUID);
                } else
                    $result=json_encode(array("result"=>"ERR:","message"=>"You must provide both a WID and a WFAUID."));
                $result=json_encode($result);
                break;
            case "UWL":
                $_debug["EXEC"].="UWL\r\n";
                $WB=new HandlerNC();
                $rSet=$WB->getOwnProjFlofoList($theUser->getId());
                $result=json_encode($rSet);
                break; 
            case "PDU":
                $_debug["EXEC"].="PDU\r\n";
                $pUE=trim(General::getGetOrPost("UE"));
                $pUD=trim(General::getGetOrPost("UD"));
                $pNM=trim(General::getGetOrPost("NM"));
                $pDS=trim(General::getGetOrPost("DS"));
                $pDT=trim(General::getGetOrPost("DT"));
                $pPI=intval(General::getGetOrPost("PI"));
                $pMTP=intval(General::getGetOrPost("MTP"));
                $pCMD=intval(General::getGetOrPost("PDUC"));
                $TrLng=trim(General::getGetOrPost("TL"));
                $uuElabId=trim(General::getGetOrPost("ELABID"));
                //C=PDU&WID="+wid+"&PDUC=0&DT=PL
                switch($pCMD){
                    case 0:
                        // DATA GET
                        switch($pDT){
                            case "PL":
                                $_debug["EXEC"].="PL\r\n";
                                // project list
                                $result=json_encode($dbnc->getUserProjects($theUser->getId()));
                                break;
                            case "UL":
                                $_debug["EXEC"].="UL\r\n";
                                $result=json_encode($dbnc->getFlofoProjectUsersList($theUser->getId(),$WofoId));
                                break;
                            case "BL":
                                $_debug["EXEC"].="BL\r\n";
                                $result=json_encode($dbnc->getFlofoBackupList($theUser->getId(),$WofoId));
                                break;
                            case "BK":
                                $_debug["EXEC"].="BK\r\n";
                                $pBkId=trim(General::getGetOrPost("BID"));
                                $result=$dbnc->getFlofoBackup($theUser->getId(),$WofoId,$pBkId);
                                break;
                            case "SG":
                                $_debug["EXEC"].="SG\r\n";
                                // SMS Gateway data
                                $result=json_encode($dbnc->getFlofoProjectSGD($theUser->getId(),$WofoId));
                                break;
                            default:
                            $result=json_encode(array("result"=>"ERROR","message"=>"no data requested."));
                        }
                        break;
                    case 1:
                        // rinomina progetto id=$pPI newname=$pNM
                        $_debug["EXEC"].="1\r\n";
                        if ($pPI<1){
                            $result=json_encode(array("result"=>"ERROR","message"=>"Cannot rename a not existing project."));
                            break;
                        }
                        if ($dbnc->isProjectOnUser($pPI,$theUser->getId())){
                            // ESEGUIRE
                            $dbnc->updateProject($pPI,$pNM);
                            $result=json_encode(array("result"=>"OK","message"=>"Done."));
                        } else
                            $result=json_encode(array("result"=>"ERROR","message"=>"User does not belong to the project."));
                        break;
                    case 2:
                        $_debug["EXEC"].="2\r\n";
                        // crea nuovo progetto nome=$pNM descr=$pDS
                        if ($theUser->getId()>0){
                            if ($dbnc->createProject($pNM,$pDS,$WofoId)){
                                $result=json_encode(array("result"=>"OK","message"=>"Done."));
                                break;
                            }   
                        }
                        $result=json_encode(array("result"=>"ERROR","message"=>"Cannot create the project."));
                        break;
                    case 3:
                        $_debug["EXEC"].="3\r\n";
                        // elimina il progetto id=$pPI
                        if ($pPI<1){
                            $result=json_encode(array("result"=>"ERROR","message"=>"Cannot delete a not existing project."));
                            break;
                        }
                        break;
                    case 4:
                        $_debug["EXEC"].="4\r\n";
                        // spostare questo workflow nel progetto id=$pMTP
                        if ($pMTP>=0 && $WofoId>0){
                            if ($dbnc->isUserTheWorkflowMaster($theUser->getId(),$WofoId)){
                                if ($dbnc->moveWorkflowToProject($pMTP,$WofoId)){
                                    $result=json_encode(array("result"=>"OK","message"=>"Done."));
                                    break;
                                } else
                                    $result=json_encode(array("result"=>"ERROR","message"=>"Cannot move this Workflow."));
                            } else
                                $result=json_encode(array("result"=>"ERROR","message"=>"This user cannot move this Workflow."));
                            break;
                        }
                        $result=json_encode(array("result"=>"ERROR","message"=>"Cannot move to a not existing project."));
                        break;
                    case 5:
                        $_debug["EXEC"].="5\r\n";
                        // cambia proprietario con email=$pUE
                        if (!General::isEmailAddress($pUE))
                            $result=json_encode(array("result"=>"ERROR","message"=>"Wrong email address."));
                        else {
                            if ($dbnc->isUserTheWorkflowMaster($theUser->getId(),$WofoId)){
                                if ($dbnc->changeWorkflowProprietor($pUE,$WofoId))
                                    $result=json_encode(array("result"=>"OK","message"=>"Done."));
                                else
                                    $result=json_encode(array("result"=>"ERROR","message"=>"Cannot change the proprietor of this Workflow."));
                            } else
                                $result=json_encode(array("result"=>"ERROR","message"=>"This user cannot change this Workflow's properties."));
                        }
                        break;
                    case 6:
                        $_debug["EXEC"].="6\r\n";
                        // aggungi utente con email=$pUE al progetto=$pPI/workflow=$WofoId
                        if ($pPI>0){
                            if ($dbnc->addUserByEmailToProject($pUE,$pPI)){
                                $result=json_encode(array("result"=>"OK","message"=>"Done."));
                                break;
                            }
                        }
                        $result=json_encode(array("result"=>"ERROR","message"=>"Cannot add user from a not existing project."));
                        break;
                    case 7:
                        $_debug["EXEC"].="7\r\n";
                        //elimina utente con email=$pUD dal progetto=$pPI/workflow=$WofoId
                        if ($pPI>0){
                            if ($dbnc->delUserByNameFromProject($pUD,$pPI)){
                                $result=json_encode(array("result"=>"OK","message"=>"Done."));
                                break;
                            }
                            $result=json_encode(array("result"=>"ERROR","message"=>"Cannot remove $pUD from the project."));
                            break;
                        }
                        $result=json_encode(array("result"=>"ERROR","message"=>"Cannot delete user from a not existing project."));
                        break;
                    case 9:
                        $_debug["EXEC"].="9\r\n";
                        //informazioni sullo stato della traduzione.
                        if (!empty($uuElabId)){
                            $res=General::getGlobal(str_replace("-","",$uuElabId));
                            if (!empty($res)){
                                $state=$res[0]-$res[1];
                                $result=json_encode(array("Total"=>$res[0],"Done"=>$res[1],"State"=>$state));
                            } else 
                                $result=json_encode(array("Error"=>"800A","message"=>"Not found_"));
                        } else 
                            $result=json_encode(array("Error"=>"800A","message"=>"Not Found"));
                        break;
                    case 8:
                        $_debug["EXEC"].="8\r\n";
                        //traduci etichette/aggiungi lingue
                        if (!empty($TrLng)){
                            $chk=General::getGlobal("trExec");
                            if (isset($chk) && !empty($chk)){
                                $execId=$chk[1];
                                $res=General::getGlobal(str_replace("-","",$execId));
                                $state=$res[1]-$res[2];
                                if ($state<2){
                                    General::delGlobal("trExec"); //$WID.$TrLng;
                                    General::delGlobal($execId);
                                    $result=json_encode(array("translate_id"=>$chk[1],"message"=>"Ends."));
                                } else
                                    $result=json_encode(array("translate_id"=>$chk[1],"message"=>"Ongoing."));
                            } else {
                                if(empty($uuElabId))
                                    $execId=General::getUuidv4();
                                else
                                    $execId=$uuElabId;
                                General::setGlobal("trExec",[$WID.$TrLng,date("Y-m-d H:i:s"),$execId]);
                                //$check=General::getGlobal($WID.$TrLng);
                                $langs=json_decode($TrLng,true);
                                if (count($langs)==2){
                                    $startLang=$langs["lf"];
                                    $endLang="";
                                    $toLng=$langs["lt"];
                                    switch ($toLng){
                                        case "IT": $endLang="Italian"; break;
                                        case "EN": $endLang="English"; break;
                                        case "FR": $endLang="Francaise"; break;
                                        case "ES": $endLang="Spanish"; break;
                                        case "DE": $endLang="German"; break;
                                    }
                                    if (!empty($endLang)){


                                        $chat=new \Flussu\Api\Ai\FchatAi();
                                        $res=$chat->chat("come ti chiami?");




                                        /*
                                        ob_end_clean();
                                        header("Connection: close");
                                        ignore_user_abort(); // optional
                                        ob_start();
                                        */
                                        General::setGlobal(str_replace("-","",$execId),[10,0]);
                                        //$check=General::getSession($execId);
                                        echo (json_encode(array("translate_id"=>$execId,"message"=>"Started.")));
                                        /*
                                        $size = ob_get_length();
                                        header("Content-Length: $size");
                                        ob_flush(); 
                                        flush();            // Unless both are called !
                                        session_write_close(); // Added a line suggested in the comment
                                        */
                                        $dbnc->translateLabels($startLang,$toLng,$endLang,$WofoId,$execId);


                                        
                                        General::delGlobal($WID.$TrLng);
                                        General::delGlobal(str_replace("-","",$execId));
                                        /*
                                        ob_end_flush();
                                        ob_end_clean();
                                        ignore_user_abort(false);
                                        */
                                    }
                                    else
                                        $result=json_encode(array("result"=>"ERROR","message"=>"Cannot translate. Do not understand [$toLng] language."));
                                }

                            }
                        }
                        break;
                    default:
                        $result=json_encode(array("result"=>"ERROR","message"=>"Unknown PDUC command."));
                    }
                break;
            case "APP":
                $_debug["EXEC"].="APP\r\n";
                $appCmd=trim(General::getGetOrPost("APC"));
                $appCode=trim(General::getGetOrPost("COD"));
                $appData=json_decode(file_get_contents('php://input'),true);
                $apc=new AppController();
                switch($appCmd){
                    case 0:
                        $_debug["EXEC"].="0\r\n";
                        // List APPS in this server
                        $result=json_encode($apc->getAppList());
                        break;
                    case 1:
                        $_debug["EXEC"].="1\r\n";
                        // Get APP info
                        $result=json_encode($apc->getApp($appCode));
                        break;
                    case 2:
                        $_debug["EXEC"].="2\r\n";
                        // Create APP
                        $japp=trim(General::getGetOrPost("JAPP"));
                        $apc->createAppJ($WID,$appCode,json_encode($appData));
                        $result=json_encode($apc->getPublicAppInfo($appCode));
                        break;
                    case 3:
                        $_debug["EXEC"].="3\r\n";
                        // Update APP
                        $japp=trim(General::getGetOrPost("JAPP"));
                        $apc->createAppJ($WID,$appCode,json_encode($appData));
                        $result=json_encode($apc->getPublicAppInfo($appCode));
                        break;
                    case 4:
                        $_debug["EXEC"].="4\r\n";
                        // Delete APP
                        $result=json_encode($apc->deleteApp($appCode));
                        break;
                    case 5:
                        $_debug["EXEC"].="5\r\n";
                        // Generate Code
                        $appCode=$apc->getCode($WID);
                        $res=["status"=>"new key","SRV"=>General::getSitename(),"WID"=>$WID,"APP"=>$appCode];

                        $result=json_encode($res);    
                        break;
                    case 9:
                        $_debug["EXEC"].="9\r\n";
                        // Get Public App Info
                        $result=json_encode($apc->getPublicAppInfo($appCode));
                        break;
                }
                break;
            case "D":
                $_debug["EXEC"].="D\r\n";
                // DELETE
                if ($UUID!=""){
                    // DELETE UN BLOCCO
                    $result=json_encode($dbnc->deleteFlofoBlock($UUID));
                } else if ($WID!="" || $WFAUID!=""){
                    if (empty($WID)){
                        $ret=$db->getFlussuWID($WFAUID);
                        $WID=$ret["WID"];
                    }
                    $result=$dbnc->deleteFlofo($WID);

                } else if ($ON_UUID!=""){
                    // DELETE A BLOCK OBJECT: EXIT OR ELEMENT
                    $EXIT=General::getGetOrPost("EXIT");
                    if ($EXIT!=""){
                        // delete EXIT #$EXIT from BLOCK #$ON_UUID
                        $result=json_encode($dbnc->deleteFlofoExit($EXIT,$ON_UUID));
                    }
                    $ELEMENT=General::getGetOrPost("ELEMENT");
                    if ($ELEMENT!=""){
                        $result=json_encode($dbnc->deleteFlofoElement($ELEMENT,$ON_UUID));
                    }
                }
                if ($result=="")
                    $result=array("result"=>"ERR:1","message"=>"You must provide an UUID (Block ID) or a WID (Workflow ID) or a ON_UUID (ref Block Id).");
                $result=json_encode($result);
                break;                    
            default:
                $result=json_encode(array("result"=>"ERR:800A","message"=>"Error."));
        }
        $_debug["END-EXEC"]=time();
        //echo $result;
        if (!$leaveWid){
            $st=strpos($result,"\"wid\":\"");
            if ($st!==false){
                $ed=strpos($result,"]\"",$st+10);
                $sost=substr($result,$st+5,$ed-($st+4));
                $result=str_replace($sost,":\"".$ccwid,$result);
            }
        }
        $st=strpos($result,"\"wid_code\":\"");
        if ($st!==false){
            $result=str_replace("\"wid_code\":\"","\"wid\":\"",$result);
        }

        if (!empty($DBG)){
            $result=json_encode(array("RESULT"=>$result,"DEBUG"=>$_debug));
        }

        die($result);
        /*
        if ($W==""){
            $res=$db->getFlofo($getJustFlowExec,$theUser->getId());
            $json=json_encode($res);
            die($json);
        }
        die("800A");    
        */
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