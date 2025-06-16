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

 La classe Session gestisce tutto lo stato di un processo 
 una volta eseguito.
 Contiene tutto lo stato del processo e tutte le variabili
 generate emodificate ad ogni step del processo

 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Flussuserver
 * CLASS NAME:       Session
 * CLASS-INTENT:     Statistics producer
 * USE ALDUS BEAN:   Session Handler
 * -------------------------------------------------------*
 * CREATED DATE:     10.01.2021 - Aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     06.06:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 *   - v4.3.20250606 - Aldus - Added support for long text variables needed by AI-chatbots
 * --------------------------------------------------------*/

/*
    NOTA
    -----------
    Per migliorare la velocità di elaborazione è necessario mettere le mani in questa classe.
    Sono state fatte delle sperimentazioni e migliorie ma le modifiche non sono finite.
    Quando tutti sarà completato, le parti di accesso al database dovranno andare nella relativa
    classe handler per permettere la centralizzazione dell'accesso al database e l'implementazione 
    dialettale per il supporto di più database.
*/

/**
 * The Session class is responsible for managing user sessions within the Flussu server.
 * 
 * This class handles various aspects of session management, including creating, updating, and terminating
 * sessions. It ensures that session data is securely stored and retrieved, and it manages session-related
 * operations such as authentication and state tracking.
 * 
 * Key responsibilities of the Session class include:
 * - Creating new sessions for users and storing session data.
 * - Updating session information, such as user activity, state, and error tracking.
 * - Terminating sessions and cleaning up session data when a user logs out or a session expires.
 * - Managing session-related errors and user-specific error states.
 * - Tracking subscription information and other session-specific metadata.
 * - Ensuring the integrity and security of session data throughout its lifecycle.
 * 
 * The class interacts with the database to store and retrieve session information, ensuring that all session
 * operations are performed efficiently and securely.
 * 
 */

namespace Flussu\Flussuserver;
use Flussu\General;
use Flussu\Flussuserver\Handler;
use Flussu\Flussuserver\NC\HandlerSessNC;
use Flussu\Beans;
use Flussu\Beans\Databroker;

use Exception;
use stdClass;

class Session {
    private $_WofoD;
    private $_WofoDNC;
    private $_sessId=null;
    private $_varRenewed=true;
    private $_is_starting=true;
    private $_is_expired=false;
    private $_history=[];
    private $_arVars=[];
    public  $arVarKeys=[];
    private $_lastVarCmd=[];
    private $_wfError=false;
    private $_wfId="";
    private $_hduration=2;
    // subs calling
    private $_doNotSaveHistory=false;
    private $_thisUuidBin=0;
    private $_thisUuidCal="";
    private $_deletes=[];
    private $_wrklogs=[];
    private $_stat=[];
    private $_alreadyLoaded=false;
    private $_MemSeStat;
    private $_execBid_id=0;
    public function _uuid2bin($uuidValue){
        if ($uuidValue=="")
            return "";
        return \str_replace("-","",$uuidValue);

        if (!(strpos("-",$uuidValue)===false) && $this->_thisUuidCal!=$uuidValue || $this->_thisUuidBin<1){
            $dec = 0;
            $hex=str_replace("-","",$uuidValue);
            $len = strlen($hex);
            for ($i = 1; $i <= $len; $i++) {
                $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
            }
            $this->_thisUuidCal=$uuidValue;
            $this->_thisUuidBin=$dec;
        }
        return $this->_thisUuidBin;
        //return hexdec(str_replace("-","",$uuidValue));
    }

    private $_timestart=0;

    public function __construct ($SessionId){
        //$this->_UBean = new \Beans\Databroker(\General::$Debug);
        
        $this->_timestart=microtime(true);
        
        $this->_WofoD = new Handler();
        $this->_WofoDNC = new HandlerSessNC();

        $isNew=false;
        if (!isset($SessionId)){
            $isNew=true;
            $SessionId=General::getUuidv4();
        }
        $this->_sessId=$SessionId;
        // load history
        if (!$isNew){
            $sessExists=$this->_chkExists($SessionId);
            if (!$sessExists){
                $this->_is_expired=true;
            } else {
                $hasHistory=$this->_loadHistory();
                if (isset($_SESSION["vars0"])){
                    $this->_arVars=$_SESSION["vars0"];
                    $this->arVarKeys=array_keys($this->_arVars);
                }
                $this->loadWflowVars();
                $initWid=$this->getVar("$"."WID")->value;
                if (!$hasHistory && (!isset($initWid) || empty($initWid)))
                    $this->_is_expired=true;
                    //throw new \Exception("Cannot read W-ID in existing session!");
                else
                    $this->_initMemSeStat($initWid);
                $this->_checkIsStarting();
            }
        } /*else
            $this->_checkIsStarting();*/
        $_SESSION["FlussuSid"]=$this->_sessId;
    }

    private $_wasBid="";
    public function setExecBid($bid){
        if (is_null($bid) || empty($bid) || $this->_wasBid===$bid)
            return;
        if (!is_numeric($bid) && !empty($bid)){
            $this->_wasBid=$bid;
            $bid=$this->_WofoD->getBlockIdFromUUID($bid);
        }
        $this->_execBid_id=$bid;
    }

    public function setFunctions($functions){
        return $this->assignVars("$"."___PRV_wf_functions",$functions);
    }

    public function getFunctions(){
        return $this->getVarValue("$"."___PRV_wf_functions");
    }

    private $_origWid="";
    private function _initMemSeStat($initWid){
        if (isset($this->_sessId)){
            $multArvars=[];
            if (!empty($initWid) && strtoupper(substr($initWid,0,3))=="[M."){
                $multArvars=$this->_arVars;
            }
            $this->loadWflowVars();
            $this->_MemSeStat=isset($_SESSION[$this->_sessId])?$_SESSION[$this->_sessId]:new stdClass();

            $vvm=$this->getVarValue("$"."_MemSeStat");
            if (!is_null($vvm) && isset($vvm->sessid))
                $this->_MemSeStat=$vvm;

            if (!isset($this->_MemSeStat->sessid)){
                $rSet=$this->_WofoDNC->getActiveSess($this->_uuid2bin($this->_sessId));
                if ($rSet!= null && is_array($rSet) && count($rSet)==1){
                    $this->_wfId=$rSet[0]["c200_wid"];
                    $this->_MemSeStat->workflowId=$this->_wfId;
                    $HndNc=new \Flussu\Flussuserver\NC\HandlerNC();
                    $rec=$HndNc->getFlussuNameDefLangs($this->_wfId);
                    if (isset($rec) && is_array($rec)){
                        $this->_MemSeStat->title=$rec[0]["name"];
                        $this->_MemSeStat->supplangs=$rec[0]["supp_langs"];
                        $this->_MemSeStat->deflang=$rec[0]["def_lang"];
                    } else
                    {
                        $this->_MemSeStat->title="unknown";
                        $this->_MemSeStat->supplangs="unknown";
                        $this->_MemSeStat->deflang="N/A";
                    }
                    $this->_MemSeStat->sessid=$rSet[0]["c200_sess_id"];
                    $this->_MemSeStat->wid=$rSet[0]["c200_wid"];
                    $this->_MemSeStat->wfauid=$rSet[0]["wfauid"];
                    $this->_MemSeStat->lang=$rSet[0]["c200_lang"];
                    $this->_MemSeStat->blockid=$rSet[0]["c200_thisblock"];
                    $this->_MemSeStat->endblock=$rSet[0]["c200_blk_end"];
                    $this->_MemSeStat->enddate=$rSet[0]["c200_time_end"];
                    $this->_MemSeStat->userid=$rSet[0]["c200_user"];
                    $this->_MemSeStat->err=$rSet[0]["c200_state_error"];
                    $this->_MemSeStat->usrerr=$rSet[0]["c200_state_usererr"];
                    $this->_MemSeStat->exterr=$rSet[0]["c200_state_exterr"];
                    if (isset($rSet[0]["c200_subs"]) && !is_null($rSet[0]["c200_subs"]))
                        $this->_MemSeStat->subWID=json_decode($rSet[0]["c200_subs"]);
                    $this->_MemSeStat->workflowActive=$rSet[0]["wactive"];
                    $this->_MemSeStat->Wwid=$initWid;
                } else {
                    // This is INIT time: no data are stored on database.
                    // Then if data on MULT was stored, merge it.
                    if (count($multArvars)>0){
                        $elms=json_decode(str_replace('\"','"',substr($multArvars["$"."_mult_data"]->dbValue,1,-1)),true);
                        foreach ($elms as $key => $data){
                            $key="$"."_mult_".$key;
                            $this->assignVars($key,$data);
                        }
                        unset($multArvars["$"."_mult_data"]);
                        $this->_arVars=array_merge($multArvars,$this->_arVars);
                    }
                }
                $this->_updateStat();
            } 
        }
    }

    public function __clone(){
        $this->_WofoDNC = clone $this->_WofoDNC;
    }

    public function isStarting(){   return $this->_is_starting;        }
    public function getId()     {   return $this->_sessId;             }
    public function getLang()   {   return isset($this->_MemSeStat->lang)?$this->_MemSeStat->lang:"";}
    public function getWid()    {   return isset($this->_MemSeStat->wid)?$this->_MemSeStat->wid:"";}

    public function getWfAuid() {   return isset($this->_MemSeStat->wfauid)?$this->_MemSeStat->wfauid:""; }
    public function getBlockId(){   return isset($this->_MemSeStat->blockid)?$this->_MemSeStat->blockid:"";}
    public function getWfId()   {   return $this->_wfId;}
    public function getWholeWID(){  
        $thisWid=$this->getVarValue("$"."WID");
        return is_numeric($thisWid)?HandlerSessNC::Wofoid2WID($thisWid):$thisWid;
    }
    public function getWfTitle(){   return isset($this->_MemSeStat->title)?$this->_MemSeStat->title:"";} 
    public function getWfLangs(){   return isset($this->_MemSeStat->supplangs)?$this->_MemSeStat->supplangs:"";} 
    public function getWfDefLng(){  return isset($this->_MemSeStat->deflang)?$this->_MemSeStat->deflang:"";}
    public function getStarterWID(){return $this->getVarValue("$"."_StWID");}
    public function getStarterW_id(){return $this->getVarValue("$"."_St_WID");}
    
    public function getVarValue($varName){
        $var=$this->getVar($varName);

        if ($var===false){
            //throw new \Exception("var name:[".$varName."] not found");
            return null;
        }
        $ret=$var->value;
        try{
            if (is_string($ret))
                $ret=htmlspecialchars_decode($var->value);
        } catch(\Throwable $e){
            //echo $e->getMessage();
        }
        return $ret;
    }

    public function getVar($varName){
        $foundVar=null;
        if (array_key_exists($varName, $this->_arVars))
            $foundVar=$this->_arVars[$varName]; 
        if (is_array($foundVar)){
            if (count($foundVar)>0)
                $foundVar=$foundVar[array_keys($foundVar)[0]];
            else
                return false;
        }
        if (is_null($foundVar) || empty($foundVar))
            return false;
        return $foundVar;
    }

    public function getSuppLangs(){ 
        if (!empty($this->getWid())){
            $res=$this->_WofoD->getSuppLang($this->getWid());
            return explode(",",$res[0]["supp_langs"]);
        }
        return [];
    }

    public function getName(){       
        if (!empty($this->getWid())){
            return $this->_WofoD->getFlussuName($this->getWid());
        }
        return "";
    }
    public function isWorkflowActive(){
        return $this->_MemSeStat->workflowActive;
    }
    public function isWorkflowInError(){
        return $this->_wfError;
    }
    public function getEndBlockId(){   
        return isset($this->_MemSeStat->endblock)?$this->_MemSeStat->endblock:null;
    }

//////////  VERIFICARE CHE LA MODIFICA SIA CORRETTA PER EXPIRED!
    public function isExpired(){
        if ($this->_is_expired)
            return true;
        if (!isset($this->_MemSeStat->workflowId)){
            unset($_SESSION[$this->_sessId]);
            return true;
        }
        if (is_null($this->getEndBlockId())){
            // CHECK SCADENZA!
            return false;
        } else {
            return true;
        }
    }

    public function setSessionEnd($EndBlock=null){
        if (is_null($EndBlock))
            $EndBlock=$this->_MemSeStat->blockid;
        if (empty($EndBlock))
            $EndBlock="N/A";
        $this->_setEndBlock($EndBlock);
    }

    private $_actualBlock=[];

    public function createNew(string $WID, string $IP, string $Lang, $userAgentSign, int $userId, string $app="", string $origWid="",$newSessId=null){
        $newSessId=null;
        $bid=0;
        $data="unknown";
        $isWeb=false;
        $isZap=false;
        $isForm=false;
        $isMobile=false;
        $isTelegram=false;
        $isMessenger=false;
        $isWhatsapp=false;
        $isAndroidApp=false;
        $isIosApp=false;
        $appVersion="";
        $appDeviceId="";
        $isStarting=false;
        $this->_wfError=false;

        $app=trim($app);
        $channel=0;
        if(is_null($userAgentSign))
            $userAgentSign="(no useragent data!)";

        $res=$this->_WofoD->getFlussuNameFirstBlock($WID);
        if (empty ($this->_origWid))
            $this->_origWid=$WID;
        if ($res!= null && is_array($res) && count($res)==1 && $res[0]["active"]==true){
            if (!isset($newSessId))
                $newSessId=General::getUuidv4();
            $this->_wfError=false;
            $this->_sessId=$newSessId;
            $this->_initMemSeStat($origWid);
            $this->_MemSeStat->workflowActive=true;
            
            $wname=$res[0]["name"];
            $stblk=$res[0]["start_blk"];
            $bid=intval($res[0]["bid"]);

            $done=$this->recLog("Starting workflow [$WID/$wname] for user [$userId] from [$IP] using $userAgentSign",$newSessId);
            $done=$this->recLog($userId,$newSessId,1);
            $done=$this->recLog($IP,$newSessId,2);
            $this->assignHistory("WID",$WID);
            $this->assignHistory("wname",$wname);
            $this->assignHistory("IP",$IP);
            $this->assignHistory("userAgentSign",$userAgentSign);
            $this->assignHistory("userId",$userId);
            $channel=0;
            if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$userAgentSign)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($userAgentSign,0,4)))
                $isMobile=true;
            if (!empty($app)){
                $done=$this->recLog(strtoupper($app)." ".$userAgentSign,$newSessId,3);
                //$done=$this->recLog($app,$newSessId,3);
                if ((substr(strtolower($app),0,7)=="appand!")||(substr(strtolower($app),0,7)=="appios!")){
                    if ((substr(strtolower($app),0,7)=="appand!")){
                        $done=$this->recLog("aan",$newSessId,104);
                        $isAndroidApp=true;
                        $channel=4;
                    } else {
                        $done=$this->recLog("aio",$newSessId,105);
                        $isIosApp=true;
                        $channel=5;
                    }
                    $apppart=explode("!",$app);
                    $appVersion=$apppart[1];
                    $appDeviceId=$apppart[2];
                    $done=$this->recLog("AppVersion",$appVersion);
                    $done=$this->recLog("AppDeviceId",$appDeviceId);
                } else {
                    switch(strtolower($app)){
                        case "tg":
                        case "tgm":
                        case "telegram":
                            $done=$this->recLog("tgm",$newSessId,101);
                            $isTelegram=true;
                            $channel=1;
                            break;
                        case "wa":
                        case "wzp":
                        case "whatsapp":
                            $isWhatsapp=true;
                            $done=$this->recLog("wzp",$newSessId,102);
                            $channel=2;
                            break;
                        case "fb":
                        case "msg":
                        case "messenger":
                            $isMessenger=true;
                            $done=$this->recLog("msg",$newSessId,103);
                            $channel=3;
                            break;
                        case "zap":
                        case "ZAP":
                        case "zapier":
                        case "ZAPIER":
                            $isZap=true;
                            $done=$this->recLog("zap",$newSessId,110);
                            $channel=10;
                            break;
                        default:
                            $done=$this->recLog("UNK:".$app,$newSessId,100);
                    }
                }
            } else {
                $done=$this->recLog($userAgentSign,$newSessId,3);
            }

            if ($channel>0) $isMobile=true;
            if (stripos($userAgentSign,"mozilla")!==false || stripos($userAgentSign,"gecko")!==false)
                $isWeb=true;
            //if (stripos($userAgentSign,"guzzle")!==false)
            //    $isTelegram=true;
            $done=$this->recLog("Type - isWeb?:$isWeb, isForm?:$isForm, isMobile?:$isMobile, isTelegramChat?:$isTelegram, isMessengerChat?:$isMessenger, isWhatsapp?:$isWhatsapp, isZapier?:$isZap",$newSessId,4);
            
            $log ="Starting ".$newSessId." workflow [$WID/$wname]\n";
            $log.=" - -  Agent :".$userAgentSign."\n";
            $log.=" - -  Client:".($isWeb?"web":"mobile")."\n";
            $log.=" - -  Type  :isWeb?:$isWeb, isForm?:$isForm, isMobile?:$isMobile, isTgm?:$isTelegram, isMessenger?:$isMessenger, isWtsapp?:$isWhatsapp, isZapier?:$isZap";
            General::Log($log);

            $this->assignHistory("client",$isWeb?"web":"mobile");
            $this->assignHistory("<START>",$newSessId);
            $this->_initHistory($newSessId);
            $p_res=$this->_WofoDNC->startSession($WID,$Lang,$stblk,$userId,$this->_hduration,$this->_uuid2bin($newSessId));
            $isStarting=true;
            $data="{'uid':'$userId','CIP':'$IP','UA':'$userAgentSign'}";
        }
        else if ($res!= null && is_array($res) && count($res)>0 && $res[0]["active"]==false){
            $this->_MemSeStat->workflowActive=false;
            $bid=-1;
            $newSessId="0000-0000-0000-0000-0000";
            $data="Cannot start workflow [$WID] because it is inactive";
            $done=$this->recLog($data,$newSessId,4);
        } 
        else {
            $this->_wfError=true;
            $bid=-1;
            $newSessId="0000-0000-0000-0000-0000";
            $mean="E00-unspecified error";
            if (is_null($res))
                $mean="E01-cannot get workflow or first block (res is null)";
            if (!is_null($res) && count($res)!=1)
                $mean="E02-count of res=".count($res)." getting workflow / first block";
            $data="Cannot start workflow [$WID]:".$mean;
            $done=$this->recLog($data,$newSessId,4);
        }
        $this->_sessId=$newSessId;
        $this->_initMemSeStat($origWid);
        $this->recUseStat($bid,$data,$newSessId,true,$channel);

        if ($isStarting){
            if ($isAndroidApp || $isIosApp)
                $this->assignVars("$"."isApp",true);
            else
                $this->assignVars("$"."isApp",false);
            $this->assignVars("$"."isAndroidApp",$isAndroidApp);
            $this->assignVars("$"."isIosApp",$isIosApp);
            $this->assignVars("$"."appVersion",$appVersion);
            $this->assignVars("$"."appDeviceId",$appDeviceId);

            if ($isZap){
                $this->assignVars("$"."isZapier",true);
                $isWeb=false;
            }
            else
                $this->assignVars("$"."isZapier",false);

            if ($isForm)
                $this->assignVars("$"."isForm",true);
            else
                $this->assignVars("$"."isForm",false);

            if ($isMobile)
                $this->assignVars("$"."isMobile",true);
            else
                $this->assignVars("$"."isMobile",false);
            
            if ($isTelegram){
                $this->assignVars("$"."isTelegram",true);
                $isWeb=false;
            }
            else
                $this->assignVars("$"."isTelegram",false);

            if ($isWhatsapp){
                $this->assignVars("$"."isWhatsapp",true);
                $isWeb=false;
            }
            else
                $this->assignVars("$"."isWhatsapp",false);

            if ($isMessenger){
                $this->assignVars("$"."isMessenger",true);
                $isWeb=false;
            }
            else
                $this->assignVars("$"."isMessenger",false);
            
            if ($isWeb)
                $this->assignVars("$"."isWeb",true);
            else
                $this->assignVars("$"."isWeb",false);

            $this->assignVars("$"."WID",$origWid);
            $this->assignVars("$"."_StWID",$origWid);
            $this->assignVars("$"."_StW_ID",$WID);
            
            if (!isset($this->_MemSeStat->StarterWid)){
                $this->_MemSeStat->StarterWid=$origWid;
                $this->_MemSeStat->Wwid=$origWid;
            }
        }
        $this->_updateStat();
        return $this->_sessId;
    }

    // --------------------------------
    //  v2.1 - SUBROUTINE ENGINE
    // --------------------------------
    private $_subWid=[];
    public function moveTo(string $WID, string $backToBlockId, string $gotoBlockId=null){
        //$this->_subWid=$this->_MemSeStat->subWID;
        $this->_MemSeStat->subWID[]=((object)array("wid"=>$this->_MemSeStat->wid,"wwid"=>$this->_MemSeStat->Wwid,"bid"=>$backToBlockId,"title"=>$this->_MemSeStat->title));
        $this->_MemSeStat->returnToWid=$this->_MemSeStat->wid;
        $this->_MemSeStat->returnToWwid=$this->_MemSeStat->Wwid;
        $this->_MemSeStat->returnToBid=$backToBlockId;
        //$this->assignVars("$"."_callerWID",$this->getWholeWID());
        //if ($backToBlockId!=null)
            //$this->assignVars("$"."_callerBLOCK",$backToBlockId);
        //$bid=0;
        $stblk=0;
        $w_id=HandlerSessNC::WID2Wofoid($WID,$this);
        $res=$this->_WofoD->getFlussuNameFirstBlock($w_id);
        if ($res!= null && is_array($res) && count($res)==1 && $res[0]["active"]==true){
            $this->_MemSeStat->wid=$w_id;
            $this->_MemSeStat->Wwid=$WID;
            $this->_MemSeStat->bid=$res[0]["start_blk"];
            $this->_MemSeStat->title=$res[0]["name"];
            $swh=count($this->_MemSeStat->subWID)-1;
            $this->_MemSeStat->subWID[$swh]->title=$res[0]["name"];
            $this->_MemSeStat->subWID[$swh]->st_bid=$res[0]["start_blk"];
            $stblk=$this->_MemSeStat->bid;
            $this->assignVars("$"."WID",$this->_MemSeStat->wid);
            $done=$this->recLog("Moving to workflow [$WID/".$this->_MemSeStat->title."]",$this->_sessId);
            $this->assignHistory("wid",$this->_MemSeStat->Wwid);
            $this->assignHistory("wname",$this->_MemSeStat->title);
        } else {
            $this->_wfError=true;
            //$bid=-1;
            $stblk="0000-0000-0000-0000-0000";
            $mean="E00-unspecified error";
            if (is_null($res))
                $mean="E001-cannot get workflow or first block (res is null)";
            if (!is_null($res) && count($res)!=1)
                $mean="E002-count of res=".count($res)." getting workflow / first block";
            $data="Cannot start workflow [$WID]:".$mean;
            $done=$this->recLog($data,null,4);
        }
        //$this->_MemSeStat->subWID=$this->_subWid;
        $this->_MemSeStat->movedToBid=$stblk;
        $this->_updateStat();
        return $stblk;
    }
/////////// <-------------------------------------------------------------------------------<<<
    public function moveBack($vars=null){
        $theblk="";
        $lastSW=array_pop($this->_MemSeStat->subWID);
        //$bWid=$lastSW->wwid;
        //$bBlk=$lastSW->bid;
        //$bWid=$this->getVarValue("$"."returnToWwid");
        //$bBlk=$this->getVarValue("$"."returnToBid");
        //$res=$this->_WofoD->getFlussuNameFirstBlock($lastSW->wid);
        //if ($res!= null && is_array($res) && count($res)==1 && $res[0]["active"]==true){
        $this->_MemSeStat->title=$lastSW->title;
        $theblk=$lastSW->bid;
        $this->assignVars("$"."WID",$lastSW->wid);
        $done=$this->recLog("Return back to workflow [".$lastSW->wid."/".$lastSW->title."]",$this->_uuid2bin($this->_sessId));
        $this->assignHistory("wid",$lastSW->wwid);
        $this->_MemSeStat->wid=$lastSW->wid;
        $this->assignHistory("wname",$lastSW->title);
        $this->_MemSeStat->returnToWid=$lastSW->wid;
        $this->_MemSeStat->returnToWwid=$lastSW->wwid;
        $this->_MemSeStat->returnToBid=$lastSW->bid;
        $this->_updateStat();
        return $theblk;
    }

    // --------------------------------
    //  v2.2 - NOTIFICATIONS ENGINE
    // --------------------------------
    public function setNotify($notifType,$dataName,$dataValue){
        $dataType="N";
        $channel=20;
        switch($notifType){
            case 1:
                $dataType="A";
                $channel=21;
                break;
            case 2:
                $dataType="CI";
                $channel=22;
                break;
            case 3:
                $dataType="CV";
                $channel=23;
                break;
            case 4:
                $dataType="AR";
                $channel=24;
                break;
            case 5:
                $dataType="CB";
                $channel=25;
                break;
        }
        $done=$this->_addNotify($dataType,$dataName,$dataValue,$channel);
        $this->loadWflowVars();
    }

    // --------------------------------
    //  v3.0.4 - NOTIFICATIONS ON DB
    // --------------------------------
    private function _addNotify($dataType,$dataName,$dataValue,$channel){
        // Aggiungere una notifica allo stack su database
        return $this->_WofoDNC->addNotify ($dataType,$dataName,$dataValue,$channel,$this->_MemSeStat->wid,$this->_MemSeStat->sessid,$this->_execBid_id);
    }   

    public function getNotify($sessId=""){
        // recuprera le notifiche dallo stack su database e prima di rispondere le cancella.
        if (empty($sessId) && !is_null($this->_MemSeStat))
            $sessId=$this->_MemSeStat->sessid;
        $notyf=[];
        if (!empty($sessId))
            $notyf=$this->_WofoDNC->getNotify($sessId);
        return $notyf;
    }

    // --------------------------------
    //  v3.0 - TIMED CALLS
    // --------------------------------
    public function setTimedCalled($value=true){
        $_SESSION["isTimedCalled"]=$value;
        $dt=new \DateTime();
        $this->assignVars("$"."_dtc_lastCall",$dt);
        $qty=$this->getVarValue("$"."_dtc_callsCount");
        if (empty($qty))
            $qty=0;
        else
            $qty++;
        $this->assignVars("$"."_dtc_callsCount",$qty);
    }

    public function isTimedCalled(){
        if (isset($_SESSION["isTimedCalled"]))
            return $_SESSION["isTimedCalled"];
        return false;
    }
    public function timedCalledCount(){
        $qty=$this->getVarValue("$"."_dtc_callsCount");
        if (!empty($qty))
            return $qty;
        return null;
    }
    public function timedCalledLast(){
        $dt=$this->getVarValue("$"."_dtc_lastCall");
        if (!empty($dt))
            return $dt;
        return null; 
    }

// >>>>>>>>>> ENTRAMBI DIFFERIBILI <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

    // --------------------------------
    //  v2.4.4 - SET DURATION HOURS
    // --------------------------------
    public function setDurationHours($hours=1){
        if (!empty($hours) && intval($hours)>0){
            if ($this->_WofoDNC->setDurationHours($hours, $this->_uuid2bin($this->_sessId))){
                $this->_hduration=intval($hours);
                return true;
            }
        }
        return false;
    }

    public function setDurationZero(){
        return $this->_WofoDNC->setDurationHours(0, $this->_uuid2bin($this->_sessId));
    }

/* ==================================================================================

     DATABASE OPERATIONS - TOGLIERE DA QUI E INSERIRE NELLA CLASSE HANDLER

 ================================================================================== */

    public function setBlockId($thisBlockId){
        $this->_MemSeStat->blockid=$thisBlockId;
        $SQL="select c20_start from t20_block where c20_uuid=?";
        $res= $this->_WofoDNC->execSql($SQL,array($thisBlockId));
        $this->_checkIsStarting($res);



// >>>>>>>>>>>> DIFFERIBILE        
        $SQL="update t200_worker set c200_thisblock=?, c200_time_end=CURRENT_TIMESTAMP where c200_sess_id=?";
        $res= $this->_WofoDNC->execSql($SQL,array($thisBlockId,$this->_uuid2bin($this->_sessId)));
        $done=$this->recLog("BID: ".$this->_MemSeStat->blockid);
        $this->_updateStat();
        //$this->assignVars("$"."_sessStat",$this->_MemSeStat);
        //$_SESSION[$this->_sessId]=$this->_MemSeStat;
    }
    private function _checkIsStarting($res=null){
        $this->_is_starting=false;
        if ($this->_is_expired) return false;
        if (!isset($res)){
            if (isset($this->_MemSeStat->blockid) && !empty($this->_MemSeStat->blockid)){
                $SQL="select c20_start from t20_block where c20_uuid=?";
                $res=$this->_WofoDNC->execSql($SQL,array($this->_MemSeStat->blockid));
            } else {
                //if (is_array($this->arVarValues) && count($this->arVarValues)>0)
                    $this->_is_starting=true;
                return;    
            }
        }
        if ($res){
            $res=$this->_WofoDNC->getData();
            if (isset($res) && is_array($res) && count($res)>0 && $res[0]["c20_start"]=="1"){
                //if (is_array($this->arVarValues) && count($this->arVarValues)>0)
                    $this->_is_starting=true;
            }
        }
    }

    public function setLang($newLangId){
        $this->assignVars("$"."lang",strtoupper($newLangId));
        $this->_MemSeStat->lang=$newLangId;
        $done=$this->recLog("set lang: ".$this->_MemSeStat->lang);
        
    }

    private function _setEndBlock($blockId){
        $endBid=9999;
        $SQL="select c20_id as id from t20_block where c20_uuid=?";
        $res=$this->_WofoDNC->execSql($SQL,array($blockId));
        if ($res)
            $endBid=$this->_WofoDNC->getData()[0]["id"];



// >>>>>>> INSERIRE IL CURRENT TIMESTAMP E' DIFFERIBILE?
//         SOSTITUIRE CON DATETIME "ADESSO" E DIFFERIRE   <<<<<<<<<<<<<<<<<<<<<<<<<<

        $SQL="update t200_worker set c200_blk_end=?, c200_time_end=? where c200_sess_id=?";
        $dt=new \DateTime();
        $res= $this->_WofoDNC->execSql($SQL,array($endBid,$dt->format("Y-m-d H:i:s"),$this->_uuid2bin($this->_sessId)));
        //$this->_getSessionObj();
        $this->_MemSeStat->endBlockId=$endBid;
        $this->_updateStat();
        $done=$this->recLog("set EndBlock: ".$endBid);
    }

    private function _updateStat(){
        $this->assignVars("$"."_MemSeStat",$this->_MemSeStat);
        $_SESSION[$this->_sessId]=$this->_MemSeStat;
    }

    public function removeVars($varName){
        if (is_null($varName) || empty($varName))
            return false;
        if ($this->arVarKeys==null)
        $this->loadWflowVars();
        unset($this->arVarKeys[$varName]);
        unset($this->_arVars[$varName]);
        array_push($this->_deletes,array('sid'=>$this->_uuid2bin($this->_sessId),'eid'=>$varName));
        $this->recLog("var $varName removed");
        $this->_varRenewed=true;
        return true;
    }

    public function assignVars($varName,$orig_varValue){

        if ($varName=="$"."wofoEnv")
            throw new Exception("Error, you cannot asign values to the "."$"."wofoEnv protected variable name");

        if (trim(strtolower($varName))=="$"."this"){
            throw new Exception("Cannot store a var named as $"."this !!");
        }
        if (substr($varName,0,1)!="$") $varName="$".$varName;
        if (strlen($varName)<2){
            return false;
            //throw new Exception("Cannot store a var named as '$' only !!");
        }
        $isArray=0;
        $isObject=is_object($orig_varValue);
        $exist=false;
        $differs=false;
        if (str_replace(["+","-",">","<","*","/",".","=","£","%","!","?","^","'","\"","#","@","§","°",":",",","|","\\"],"", $varName)!=$varName){
            $varName2=str_replace('"',"",str_replace('"$"."',"$",$varName));
            if ($varName2==$varName)
                $varName2=str_replace("'","",str_replace("'$'.'","$",$varName));
            if (str_replace(["+","-",">","<","*","/",".","=","£","%","!","?","^","'","\"","#","@","§","°",":",",","|","\\"],"", $varName2)!=$varName2){
                $bDesc=$this->_WofoD->getFlussuBlock(true,0,($this->_history[count($this->_history)-1][1]))[0]["description"];
                throw new Exception("Unacceptable var name:[$varName2] on block[".$bDesc."] - ".var_dump($this->_history[count($this->_history)-1]));
            } else
                $varName=$varName2;
        }
        $var=$this->getVar($varName);
       
        if ($var===false){
            $var=new stdClass();
            $var->title=$varName;
            $var->value=null;
            $var->jValue="[]";
            $var->isNull=true;
            $var->isObject=$isObject;
        } else{
            if ($exist && $var->value===$orig_varValue)
                return true;
        }

        $var->value=$orig_varValue;
        if (is_null($var->value))
            $var->isNull=true;
        else
            $var->isNull=false;
        
        //$mantainOrig=false;
        if ($isObject || is_array($orig_varValue) || (is_string($orig_varValue) && strpos($orig_varValue,"->setDataJson")===false && substr(trim($orig_varValue),0,1)!="$"))
            $var->jValue=json_encode($var->value);
        else{
            $var->jValue=$orig_varValue;
            $mantainOrig=true;
        }
        if(is_a($var->value, "DateTime" ) || is_a($var->value, "DateInterval" )){
            $var->dValue='"'.$var->value->format('Y-m-d H:i:s').'"';
        }

        $dbv=$var->jValue;
        /*
        if (!$mantainOrig){
            $inside=$dbv;
            if (substr($dbv,-1)===substr($dbv,0,1) && substr($dbv,-1)==='"' && strlen($dbv)>2){
                $dbv=str_replace('"','\\"',$dbv);
            }
        }*/
        $var->dbValue=$dbv;
        $this->_arVars[$var->title]=$var;
        if (!in_array($var->title,$this->arVarKeys)){
            array_push($this->arVarKeys,$var->title);
            $this->arVarKeys=array_keys($this->_arVars);
        }
        return true;
    }

    private function _updateWorklog($transExec){
        if (count($this->_wrklogs)>0){
            $TX=$this->_WofoDNC->updateWorklog($this->_wrklogs,$transExec);
            $this->_wrklogs=$TX[0];
            $transExec=$TX[1];
        }
        return $transExec;
    }

    public function loadWflowVars(){
        if (!$this->_alreadyLoaded || (is_array($this->arVarKeys) && count($this->arVarKeys)==0)){
            $this->_alreadyLoaded=true;
            $SQL="select * from t205_work_var where c205_sess_id=? and c205_elm_id='allValues'";
            $res=$this->_WofoDNC->execSql($SQL,array($this->_uuid2bin($this->_sessId)));
            $rows=$this->_WofoDNC->getData();
            //$d=new stdClass();
            if (is_array($rows) && isset($rows[0])){
                $d=$rows[0]['c205_elm_val'];
                if (isset($d) && !empty($d)){
                    $d=json_decode($d,false);
                    $this->_arVars=get_object_vars($d);
                    $this->arVarKeys=array_keys($this->_arVars);
                }
            }
        }
    }

    public function recLog($logtext, string $sessId=null, int $tpInfo=0){
        if (is_null($sessId))
            $sessId=$this->_sessId;

        if (is_array($logtext)){
            $lt="";
            $logtext=json_encode($logtext);
        }
        // valori per TpInfo
        // 0= riga log normale
        // 1= user id
        // 2= indirizzo ip
        // 3= user agent
        // 4= internal error
        // 5= external error
        // 6= user error
        // 7...= altre info speciali
        array_push($this->_wrklogs,array('sid'=>$this->_uuid2bin($sessId),'tpi'=>$tpInfo,'txt'=>$logtext));
        return true;
    }

    public function recUseStat(int $bid, $data=null, string $sid=null, bool $isStart=false, $channel=0){
        // ORIGINAL WID
        // JUMPED WID
        // DA MODIFICARE
        
        if (is_null($data))
            $data="";
        else if (is_array($data))
            $data=json_encode ($data);

        $isStart?$stv=1:$stv=0;
        if ($isStart || (!empty($data) && $data!="[]"))
            array_push($this->_stat,array('wid'=>$this->_MemSeStat->workflowId,'sid'=>$this->_uuid2bin($this->_MemSeStat->sessid),'bid'=>$bid,'stv'=>$stv,'chn'=>$channel,'sdt'=>$data));
    }

    public function getLog(string $sessId=null){
        if (is_null($sessId))
            $sessId=$this->_sessId;
        
        $this->_updateWorklog(null);
        $SQL="select c209_timestamp as e_date, c209_tpinfo as e_type,c209_row as e_desc from t209_work_log where c209_sess_id=?";
        $this->_WofoDNC->execSql($SQL,array($this->_uuid2bin($sessId)));
        return $this->_WofoDNC->getData();
    }

    public function statusStart(){
        //$this->_getSessionObj();

    }
    private $_isRunning=false;
    public function statusRunning($booVal){
        $this->_isRunning=$booVal;
    }
    private $_isExecuting=false;
    public function statusExec($booVal){
        $this->_isExecuting=$booVal;
    }
    private $_isEnd=false;
    public function statusEnd($booVal){
        $this->_isEnd=$booVal;
        $this->_setState(3);
    }
    private $_isRender=false;
    public function statusRender($booVal){
        $this->_isRender=$booVal;
    }
    private $_isCallExit=false;
    public function statusCallExt($booVal){
        $this->_isCallExit=$booVal;
    }
    private $_isError=false;
    private $_errType=0;
    public function statusError($booVal){
        $this->_isError=$booVal;
        if ($this->_isCallExit){
            $this->_errType=1;
            $this->_setState(0);
            $this->recLog("EXTERNAL ERROR STATE");
        } elseif ($this->_isExecuting || $this->_isRender){
            $this->_setState(1);
            $this->_errType=2;
            $this->recLog("INTERNAL ERROR STATE");
        } else{
            $this->_setState(2);
            $this->_errType=3;
            $this->recLog("USER ERROR STATE");
        }
    }
    public function getStateIntError(){ return ($this->_isError && $this->_errType==2); }
    public function getStateUsrError(){ return ($this->_isError && $this->_errType==3); }
    public function getStateExtError(){ return ($this->_isError && $this->_errType==1); }
    
    public function getWorkflowVars($forExecution=false) { $this->_genWflowVars(true,$forExecution); return $this->_wofoVars; }
    public function getLogWorkflowVars() { $this->_genWflowVars(false); return $this->_wofoVars;}
    
        
    private $_wofoVars="";
    private function _genWflowVars($alsoSysVars,$forExecution=false){
        $i=0;
        $this->loadWflowVars();
        $this->_wofoVars="\r\n";

        for ($i=0; $i<count($this->arVarKeys); $i++){
            $vKey=$this->arVarKeys[$i];
            if (!$alsoSysVars && $vKey=="$"."dummy"){
                // do nothing
            }
            elseif (!empty($vKey) && !empty(str_replace("$","",$vKey)) && substr(trim($vKey),0,1)=="$"){
                if ($forExecution && stripos($vKey,"$"."___")!==false){
                    // do nothing
                } else {
                    $var=$this->getVar($vKey);
                    $vValue=$var->value;
                    if (is_array($vValue) || is_object($vValue)){

                        $je=json_encode($vValue,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                        $taf="";
                        if (!$var->isObject)
                            $taf=",true";
                        $this->_wofoVars.=$vKey."=json_decode('$je'$taf);\r\n";
                    } else {
                        if (!empty($vValue)){
                            $vValue=trim($vValue);
                        }
                        if (!empty($vValue) && ($vValue=="true" || $vValue=="false" || $vValue=="null" || $vValue=="\"true\"" || $vValue=="\"false\"" || $vValue=="\"null\"")){
                            $this->_wofoVars.=$vKey."=".trim(str_replace('"','',$vValue)).";\r\n";
                        } else if ((empty($vValue) || $vValue=="") && !is_numeric($vValue)) {
                            if (is_bool($vValue))
                                $this->_wofoVars.=$vKey."=".($vValue?"true":"false").";\r\n";
                            else
                                $this->_wofoVars.=$vKey."=\"\";\r\n";
                        } else {
                            // è un numero. Ma potrebbe non esserlo, 00181 è un CAP è un numero ma va gestito come stringa
                            $is_numero=is_numeric($vValue);
                            $le=strlen(trim(strval($vValue)));
                            if ($is_numero && $le>0 && (substr(trim($vValue),0,1)==="0" || substr(trim($vValue),0,1)=="+")){
                                $is_numero=($vValue=="0" || (substr(trim($vValue),0,1)==="0" && substr(trim($vValue),0,2)==="0."));
                                //$is_numero=($le==1 || ($le>2 && substr(trim($vValue),0,2)==="0.") || ($le>1 && substr(trim($vValue),0,1)!="+"));
                            }
                            if ((strpos($vValue,"->setDataJson")!==false || substr(trim($vValue),0,1)=="$") || $is_numero) 
                                $this->_wofoVars.=$vKey."=".$vValue.";\r\n";
                            else{
                                $hasNewLine = (strpos($vValue, "\n") !== false || strpos($vValue, "\r") !== false);
                                $isVeryLong = (strlen($vValue) > 300);            // soglia a tuo piacere

                                if ($hasNewLine || $isVeryLong) {
                                    // 1) scegli un delimitatore che NON compaia nel testo
                                    $delim = 'TXT';
                                    while (strpos($vValue, "\n{$delim}") !== false) {
                                        $delim .= '_';
                                    }

                                    // 2) usa un NOWDOC per evitare interpolazioni indesiderate
                                    $this->_wofoVars .= $vKey."=<<<'{$delim}'\n{$vValue}\n{$delim};\r\n";
                                } else {
                                    // ----- FINE NUOVO BLOCCO -----

                                    // stringa "normale" con escape delle virgolette
                                    $this->_wofoVars .= $vKey."=\"".addslashes(stripslashes($vValue))."\";\r\n";
                                }
                            }
                        }
                    }
                }
            }
        }
        return $i;
    }

    private $_sstate;

    private function _setState($stateId,$stateValue=null){
        if (is_null($this->_sstate))
            $this->_sstate=new stdClass();

        $this->_sstate->err=0;
        $this->_sstate->exterr=0;
        $this->_sstate->usrerr=0;
        $this->_sstate->tend=date("Y/m/d H:i:s");

        $theBlkId=$this->_MemSeStat->blockid;

        $arrParm=[];
        if (!empty($theBlkId)){
            switch ($stateId){
                case 0:
                    $this->_sstate->exterr=1;
                    $SQL="update t200_worker set c200_state_exterr=1 ";
                    break;
                case 1:
                    $this->_sstate->err=1;
                    $SQL="update t200_worker set c200_state_error=1 ";
                    break;
                case 2:
                    $this->_sstate->err=1;
                    $SQL="update t200_worker set c200_state_usererr=1 ";
                    break;
                case 3:

// >>>>>>>>> SOSTITUIRE CON DATETIME "ADESSO" PER DIFFERIRE

                    $SQL="update t200_worker set c200_time_end=CURRENT_TIMESTAMP ";
                    break;
            }
            if (!empty($SQL)){
                array_push($arrParm,$this->_uuid2bin($this->_sessId));
                $res= $this->_WofoDNC->execSql($SQL." where c200_sess_id=?",$arrParm);
            }
        }
    }

/* ==================================================================================

    HISTORY OPERATIONS

 ================================================================================== */

    public function cleanLastHistoryBid($bid){
        // elimina dall'array solo l'ultimo block ID se esiste.
        $cnt=count($this->_history)-1;
        for ($i=$cnt;$i>=0;$i--){
            if (isset($this->_history[$i])){
                $hRow=$this->_history[$i];
                if ($hRow[1]==$bid)
                    unset($this->_history[$i]);
                else
                    break;
            }
        }
    }

    public function assignHistory($dataIdOrBlockId,$shownData){
        if ($this->_history==null)
            $this->_loadHistory();
        array_push($this->_history,[date("Y/m/d H:i:s"),$dataIdOrBlockId,$shownData]);
    }

    public function getHistoryRows($rowsQty,$doNotUseThisBid="",$addDate=false){
        $res=[];
        if ($rowsQty==0)
            $rowsQty=9999;
        $bid="";
        $_buildRowParts="";
        $c=count($this->_history);
        $cr=$c;
        foreach (array_reverse($this->_history) as $hRow) {
            if($hRow[1]=="<SESS_START>" || $hRow[1]=="<START>"){
                if ($c--<$cr)
                    break;
                else{
                    $hRow[1]="<EXT_CALL>";
                    $hRow[2]=["E>C","[{b}Restarted OR Called from external link{/b}]"];
                }
            }
            if (is_array($hRow[2])){
                if ($bid!=$hRow[1]){
                    $rowsQty--;
                    if ($rowsQty<0)
                        break;
                    $bid=$hRow[1];
                }
                $dontadd=false;
                    $arr=$hRow[2];
                    if ($addDate)
                        array_push($arr,$hRow[0]);
                    $dontadd=(count($res)>1 && $res[count($res)-1]==$arr);
                    $dontadd=(count($res)>2 && $res[count($res)-2]==$arr);
                    if (!$dontadd)
                        array_push($res,$arr);
                //}
            } else {
                if ($hRow[1]=="wname"){
                    $_buildRowParts=$hRow[2];
                }
                if ($hRow[1]=="wid"){
                    $rowsQty--;
                    array_push($res,["W",$hRow[2]." ".$_buildRowParts,$hRow[0]]);
                }
            }

        }
        return array_reverse($res);
    }
    private function _chkExists($sessId){
        $SQL="SELECT c200_sess_id as sid from t200_worker where c200_sess_id=?";
        $this->_WofoDNC->execSql($SQL,array(str_replace("-","",$sessId)));
        if (isset($this->_WofoDNC->getData()[0]["sid"])){
            return true;
        }
        return false;
    }
    private function _loadHistory(){
        $SQL="select c207_history from t207_history where c207_sess_id=?";
        $this->_WofoDNC->execSql($SQL,array($this->_uuid2bin($this->_sessId)));
        if (isset($this->_WofoDNC->getData()[0]["c207_history"])){
            $this->_history=json_decode($this->_WofoDNC->getData()[0]["c207_history"],true);
            return true;
        }
        return false;
    }
    private function _initHistory($sid){
        $SQL="insert into t207_history (c207_sess_id) values (?)";
        return $this->_WofoDNC->execSql($SQL,array($this->_uuid2bin($sid)));
    }

/* ==================================================================================

    SESSIONS LIST/ECHO OPERATIONS

 ================================================================================== */

    public function getSessionsList($whereClause){
        return $this->_WofoDNC->getSessionsList($whereClause);
    }

    function __destruct(){
        $durmsec=intval((microtime(true) - $this->_timestart) * 1000);
        $start_time = microtime(true);
        if (!$this->_doNotSaveHistory){
            if (isset($this->_MemSeStat->returnToWwid) && !empty($this->_MemSeStat->returnToWwid)){
                $this->assignVars("$"."_callerWID",$this->_MemSeStat->returnToWwid);
                $this->assignVars("$"."_callerBLOCK",$this->_MemSeStat->returnToBid);
            }
            $usessid=$this->_uuid2bin($this->_sessId);
            $this->_WofoDNC->closeSession($this->_MemSeStat,$this->_arVars,$this->_stat,$this->_history, $this->_wrklogs, $this->_subWid,$usessid);
        }
        // Handler Close Session
        $sessClose=intval((microtime(true) - $start_time) * 1000);
        //if ($_SESSION["Log"])
        //    General::log("End session : ".$_SESSION["Log"]);
        General::log("SID:".$this->_sessId.":".$durmsec+$sessClose."ms (Calc:".$durmsec."ms + Close:".$sessClose."ms)"); 
        $_SESSION["vars0"]=$this->_arVars;
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
