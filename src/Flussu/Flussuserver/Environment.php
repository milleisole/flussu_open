<?php
/* --------------------------------------------------------------------*
 * Flussu v4.4 - Mille Isole SRL - Released under Apache License 2.0
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
 
 Ad ogni esecuzione di uno STEP il sistema verifica se è
 presente del codice PHP da eseguire, se si attacca a quel
 codice questa classe per estendere i comandi PHP allo
 step in esecuzione

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS-NAME:     FlussuEnvironment.class
 * CLASS PATH:       /Flussu/Flussuserver
 * -------------------------------------------------------*
 * CREATED DATE:   25.01.2021 - Aldus
 * VERSION REL.:     4.3.20250625
 * UPDATES DATE:     30.05:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/

/**
 * The Environment class is responsible for managing the execution environment for the Flussu server.
 * 
 * This class handles various aspects of the environment setup and management, including session handling,
 * version control, media and channel settings, and debug information. It also integrates with external
 * components such as the Stripe API and web scraping clients.
 * 
 * Key responsibilities of the Environment class include:
 * - Initializing and managing session data.
 * - Storing and providing access to environment-specific settings and configurations.
 * - Handling version information and updates.
 * - Managing media and channel settings for different types of clients (e.g., web, mobile).
 * - Providing debug information and processing data for further use.
 * 
 * The class is designed to be instantiated with a session object, which it uses to manage session-specific
 * data and operations.
 * 
 */

namespace Flussu\Flussuserver;

use Flussu\Controllers\FluLuUriShrinkController;
require_once(__DIR__ . "/Gibberish.php");
require_once(__DIR__ . "/Command.php");

use Flussu\Flussuserver\Command;
use Flussu\General;
use Flussu\HttpCaller;

class Environment {
    private $_version="4.4.20250530";
    private $_exitNum=-1;
    private $_media="pc"; 
    private $_channel="web"; 
    private $_wofEnvRes=array();
    private $_debugRow=0;
    public  $_wofProcessedData=[];
    private $_mySess;
    private $_reminderTo="";
    private $_thisWid="";
    private $_thisBid="";
    private $_thisSid="";
    private $_isTimedCalled=false;
    public function __construct ($passedSess){
        $this->_mySess=$passedSess;
        $this->_thisWid=$passedSess->getWholeWID();
        $this->_thisBid=$passedSess->getBlockId();
        $this->_thisSid=$passedSess->getId();
        $this->_isTimedCalled=$passedSess->isTimedCalled();
    }

    // Script process/handling
    public function init                ()                        {$this->__init();}
    public function thisServer          ()                       {return $_ENV["server"];}
    public function getNow              ($langId=null)          {return $this->getDateNow($langId);}
    public function getDateNow          ($langId=null)          {$defTZ=$_ENV['timezone'];
                                                                            if (!isset($defTZ) || empty($defTZ))
                                                                                $defTZ="Europe/Rome";
                                                                            date_default_timezone_set($defTZ);
                                                                            $dateNowEN=date('D d M, Y - H:i:s', time());
                                                                            $lid=$this->getLang();
                                                                            if (isset($langId) && !empty($langId))
                                                                                $lid=strtoupper($langId);
                                                                            switch($lid){
                                                                                case "IT" : return str_replace(["Mon","Sat","Sun","Fri","Thu","Wed","Tue","Dec","Nov","Oct","Sep","Aug","Jul","Jun","May","Apr","Mar","Feb","Jan"],["Lun","Sab","Dom","Ven","Gio","Mer","Mar","Dic","Nov","Ott","Set","Ago","Lug","Giu","Mag","Apr","Mar","Feb","Gen"],$dateNowEN);
                                                                                case "FR" : return str_replace(["Mon","Sat","Sun","Fri","Thu","Wed","Tue","Dec","Nov","Oct","Sep","Aug","Jul","Jun","May","Apr","Mar","Feb","Jan"],["Lun", "Sam", "Dim", "Ven", "Jeu", "Mer", "Mar", "Déc", "Nov", "Oct", "Sep", "Aoû", "Jui", "Jiu", "Mai", "Avr", "Mar", "Fév", "Jan"],$dateNowEN);
                                                                                case "SP" : return str_replace(["Mon","Sat","Sun","Fri","Thu","Wed","Tue","Dec","Nov","Oct","Sep","Aug","Jul","Jun","May","Apr","Mar","Feb","Jan"],["Lun", "Sáb", "Dom", "Vie", "Jue", "Mié", "Mar", "Dic", "Nov", "Oct", "Sep", "Ago", "Jul", "Jun", "May", "Abr", "Mar", "Feb", "Ene"],$dateNowEN);
                                                                                case "DE" : return str_replace(["Mon","Sat","Sun","Fri","Thu","Wed","Tue","Dec","Nov","Oct","Sep","Aug","Jul","Jun","May","Apr","Mar","Feb","Jan"],["Mo", "Sa", "So", "Fr", "Do", "Mi", "Di", "Dez", "Nov", "Okt", "Sep", "Aug", "Jul", "Jun", "Mai", "Apr", "Mär", "Feb", "Jan"],$dateNowEN);
                                                                                default   : return $dateNowEN;
                                                                            }
                                                                        }
    public function endScript           ()                       {
                                                                            $this->_addToResArray("data", $this->getDataJson()); 
                                                                            return $this->_wofEnvRes;
                                                                        }
    public function get_EnvVersion      ()                      {return $this->_version;}
    public function get_EnvMedia        ()                      {return $this->_media;}
    public function get_EnvChannel      ()                      {return $this->_channel;}
    // Workflow exec environment data
    public function getWID              ()                {return $this->getSelfWid();}
    public function getSelfWid          ()                {return $this->_thisWid;}
    public function getBlockId          ()                {return $this->getSelfBid();}
    public function getSelfBid          ()                {return $this->_thisBid;}
    public function getSessionId        ()                {return $this->getSelfSid();}
    public function getSelfSid          ()                {return $this->_thisSid;}
    public function backHereUri         ()                      {return $this->_buildBackHereUri();} 
    public function isTimedcalled       ()                  {return $this->_isTimedCalled;}

    public function getUUID             ()                {return General::getUuidv4();}
    // Lang settings
    public function setLang             ($langId)                 {$this->_mySess->setLang(trim(strtoupper($langId)));} 
    public function getLang             ()                      {return trim(strtoupper($this->_mySess->getLang()));} 
    // Session duration  
    public function setSessDurationHours($intHours)               {$this->_mySess->setDurationHours($intHours);}
    // Exits handling 
    public function setExit             ($exitNum)                {$this->_exitNum=$exitNum; $this->_addToResArray("exit_to", $exitNum);}
    public function getExit             ()                       {return $this->_exitNum;}
    // SubWorkflow handling 
    public function goToFlussu($subprocWid) {
        if (isset($subprocWid))
            $this->_addToResArray("go_to_flussu", $subprocWid);
    }
    public function return              ($varArr=null)            {$this->_addToResArray("back_to_flussu", $varArr);}
    // DEBUGGING
    public function debugWrite          ($text)                   {$this->_addToResArray("debug_".$this->_debugRow, $text); $this->_debugRow++;}
    // Reminder handling 
    public function setReminderTo       ($reminderAddr)           {$this->_reminderTo=$reminderAddr; $this->_addToResArray("reminder_to", $reminderAddr);}
    public function getReminderTo       ()                       {return $this->_reminderTo;}
    // External connection
    public function checkCodFiscale     ($codFisc,$retIfGood,$retSexVarName,$retBirthdateVarName)  {$this->_addToResArray("chkCodFisc", array($codFisc,$retIfGood,$retSexVarName,$retBirthdateVarName));}
    public function checkPIva           ($pIva,$retIfGood)        {$this->_addToResArray("chkPIva", array($pIva,$retIfGood));}
    public function getCodFiscaleInfo   ($codFisc)            {return $this->_getCodFiscInfo($codFisc);}
    public function generateQrCode      ($data)                   {$this->_addToResArray("genQrCode", array(urlencode($data)));}
    public function getXCmdKey          ($srvAddr,$srvCmd,$user,$pass)  {$this->_addToResArray("getXCmdKey", array($srvAddr,$srvCmd,$user,$pass));}
    public function sendXCmdData        ($srvAddr,$cmdKey,$cmdJData,$retVarName)    {$this->_addToResArray("sendXCmdData", array($srvAddr,$cmdKey,$cmdJData,$retVarName));}
    public function sendEmail           ($toAddress,$subject,$message,$replyTo="")  {$this->_addToResArray("sendEmail", array($toAddress,$subject,$message,$replyTo)); }
    public function sendPremiumEmail    ($toAddress,$subject,$message,$replyTo="",$senderName="")  {$this->_addToResArray("sendEmail", array($toAddress,$subject,$message,$replyTo,$senderName)); }
    public function sendEmailwAttaches  ($toAddress,$subject,$message,$replyTo="",$attachFiles=[])  {$this->_addToResArray("sendEmail", array($toAddress,$subject,$message,$replyTo,$attachFiles)); }
    public function sendPremiumEmailwAttaches ($toAddress,$subject,$message,$replyTo="",$senderName="",$attachFiles=[])  {$this->_addToResArray("sendEmail", array($toAddress,$subject,$message,$replyTo,$senderName,$attachFiles)); }
    public function httpSend            ($URI,$dataArray=null,$retResVarName=null) {$this->_addToResArray("httpSend", array($URI, (is_null($dataArray))?"":json_encode($dataArray),(is_null($retResVarName))?"":$retResVarName));}
    public function getStripeChargeInfo ($stripeChargeId,$keyName=""){return $this->_getStripeChargeInfo($stripeChargeId,$keyName);}
    public function getStripeSessInfo   ($configId, $keyType,$stripeSessId){return $this->_getStripeSessInfo($stripeSessId, $configId, $keyType);}
    
    public function getResultFromHttpApi($URI,$method="GET"){
        $HT=new HttpCaller();
        return $HT->exec($URI,$method);
    }
    
    public function getPaymentLink($provider, $configId, $keyType, $paymentId, $description,$amount,$imageUri,$successUri,$cancelUri,$varStripeRetUriName){
        $this->_addToResArray("getPaymentLink", array($provider, $configId, $keyType, $paymentId, $description,$amount,$imageUri,$successUri,$cancelUri,$varStripeRetUriName));
    }

    public function log($logString)             {General::Log($logString,true);}
    public function logDebug($logString)          {General::Log($logString);}
    public function doZAP               ($URI,$retResVarName,$dataArray=null) {$this->_addToResArray("doZAP", array($URI,$retResVarName,(is_null($dataArray))?"":json_encode($dataArray)));}
    public function getShortUri         ($longUri)    {return $this->_getShortUri($longUri);}

    // Internal data handling
    public function recData             ($varName, $varValue)           {$this->_wofProcessedData[$varName]=$varValue;}
    // v2.8.5 - Acquire file (ex to send it as API's parameter)
    public function recDataFile         ($fileName, $filePath)          {
        $fileCont = file_get_contents($filePath);
        $fileCont = base64_encode($fileCont);
        $this->_wofProcessedData["§FILE§:".$fileName]=$fileCont;
    }
    public function getData             ($varName)                      {return $this->_wofProcessedData[$varName];}
    public function clearData           ()                              {return $this->_wofProcessedData=[];}
    public function getDataJson         ()                              {
        $ret=""; 
        if (!is_null($this->_wofProcessedData)){
                $ret= json_encode($this->_wofProcessedData,JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_APOS);
            } 
            return $ret;
        }
    public function setDataJson         ($theJsonData)                  {if (!is_null($theJsonData) && !empty($theJsonData)) { $this->_wofProcessedData= json_decode ($theJsonData, true);}}
    // other export handlers
    public function makeJson            ($varArray)                     {return json_encode ($varArray, JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES); }
    public function readJson            ($varArray)                     {return json_decode ($varArray); }
    // random functions
    public function generateNewPassword ($len,$chars="abcfghijkmpq@rstuvwxyz?ABCDFGHIJKMN#PRSTUVWXYZ+1234*56789!"){ return $this->_generateRandomCode($len,$chars);}
    public function generateNewCheckCode($len,$chars="123456789ACDFGHKNPSTUVXYZ"){ return $this->_generateRandomCode($len,$chars); }
    public function getRnd              ($from,$to)                     {srand((double)microtime()*1000000); return intval(rand(intval($from),intval($to)));}
    // v2.1 subsCall
    public function callSubwf           ($subWID,$varArray)             {$this->_addToResArray("callSubwf", array($subWID,json_encode ($varArray, JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES)));}
    // v2.2 notifications
    public function alert               ($message)                      {$this->_addToResArray("notify", array("A","",$message));}
    public function addRowToChat        ($message)                      {$this->_addToResArray("notify", array("AR","",$message));}
    public function counterInit         ($cntId,$description,$min,$max) {$this->_addToResArray("notify", array("CI",$cntId,array("desc"=>$description,"min"=>$min,"max"=>$max)));}
    public function counterValue        ($cntId,$value)                 {$this->_addToResArray("notify", array("CV",$cntId,$value));}
    public function notifyClient        ($dataName,$dataValue)          {$this->_mySess->setNotify(0,$dataName,$dataValue);}
    public function notify              ($dataName,$dataValue)          {$this->_addToResArray("notify", array("N",$dataName,$dataValue));}
    // callingBidIdentifier can be:  "1234-1234-1234-1234" OR "exit(0)" OR "[W456756546AB]"(wid) OR "[W456756546AB]:1234-1234-1234-1234"(wid/bid);
    public function notifyCallback      ($callingBidIdentifier)         {$this->_addToResArray("notify", array("NC","",$callingBidIdentifier));}




    // v4.3 Ai Chat
    public function sendToAi            ($sendText, $varResponseName,$provider=0)  {$this->_addToResArray("sendToAi", array($provider,$sendText, $varResponseName));}



/*
    // v2.8 OpenAi Query
    public function queryOpenAi         ($textQuery,$varResponseName)   {$this->_addToResArray("openAi", array($textQuery,$varResponseName));}
    public function explainOpenAi       ($textQuery,$varResponseName)   {$this->_addToResArray("explAi", array($textQuery,$varResponseName));}
    // v2.8.5 - Ai Basic NLP
    public function basicAiNlpIe        ($textQuery,$varResponseName)   {$this->_addToResArray("bNlpAi", array($textQuery,$varResponseName));}
    // v2.9 OpenAi Chat
    public function startOpenAiChat     ($initText)                     {$this->_addToResArray("openAi-stsess", array($initText));}
    public function chatOpenAi          ($chatText, $varResponseName)   {$this->_addToResArray("openAi-chat", array($chatText, $varResponseName));}
*/



    // v2.8 - create MultiRec workflow
    public function createNewMultirecWf ($wid,$uid,$uemail,$arrData,$varName) {$this->_addToResArray("newMRec", array($wid,$uid,$uemail,$arrData,$varName));}
    public function addProcessVariable  ($varName,$varValue)            {$this->_addToResArray("addVarValue", array($varName,$varValue));}
    // v2.8.5 - Build Pdf
    public function printToPdf    ($title,$txt2Prn,$var4Filename)       {$this->_addToResArray("print2Pdf", array($title,$txt2Prn,$var4Filename));}
    public function print2PdfwHF  ($title,$txt2Prn,$flxTxtHead,$flxTxtFoot,$var4Fname) {$this->_addToResArray("print2PdfwHF", array($title,$txt2Prn,$flxTxtHead,$flxTxtFoot,$var4Fname));}
    public function printRawHtml2Pdf ($theHtml,$var4Fname)              {$this->_addToResArray("printRawHtml2Pdf", array($theHtml,$var4Fname));}
    public function normalizeSurvey ($arraySurvey)                      {return $this->_normSurvey($arraySurvey);}
    // v2.9 - sendSMS & generateOTP
    public function generateOTP     ($charQty=5)                        {   $_SDRndNum = rand(1,9);
                                                                            $_SDRndNum .= mt_rand(10,99);
                                                                            $_SDRndNum .= rand(10,99);
                                                                            if ($charQty>5)
                                                                                $_SDRndNum .= mt_rand(1,9);
                                                                            if ($charQty>6)
                                                                                $_SDRndNum .= rand(1,9);
                                                                            if ($charQty>7)
                                                                                $_SDRndNum .= mt_rand(1,9);
                                                                            if ($charQty>8)
                                                                                $_SDRndNum .= rand(1,9);
                                                                            return $_SDRndNum;
                                                                        }
    public function sendSms         ($senderName,$toPhoneNum,$message,$retVarName)  {$this->_addToResArray("sendSms", array($senderName,$toPhoneNum,$message,$retVarName));}
    public function sendTimedSms    ($senderName,$toPhoneNum,$message,$datetime,$retVarName) {$this->_addToResArray("sendSms", array($senderName,$toPhoneNum,$message,$retVarName,$datetime));}
    public function requestUserInfo ($retVarName)                       {$this->_addToResArray("requestUserInfo", array($retVarName));}
    public function execOcr         ($filepath,$retVarName)             {$this->_addToResArray("execOcr", array($filepath,$retVarName));}
    // v3.0 batchExec 
    public function setRecallPoint  ()                                  {$this->_mySess->assignVars("$"."_dtc_recallPoint",$this->_mySess->getWholeWID().":".$this->_mySess->getBlockId());}
    public function timedRecallIn   ($minutes)                          {$this->_addToResArray("timedRecall", array(null,$minutes));}
    public function timedRecallAt   ($dateTime)                         {$this->_addToResArray("timedRecall", array($dateTime,null));}
    public function excelAddRow     ($fileName,$arrData)                {$this->_addToResArray("excelAddRow", array($fileName,$arrData));}
    // v2.0 batchExec 
    public function getHtmlFromFlussuText($theFlussuText)       {return Command::htmlSanitize($theFlussuText,$this->_mySess->getVarValue("$"."isTelegram"));}
    public function createButton   ($buttonVarName,$clickValue, $buttonText, $buttonExit=0) {$this->_addToResArray("createButton", array($buttonVarName,$clickValue,$buttonText,$buttonExit));}
    public function createLabel    ($labelText)                         {$this->_addToResArray("createLabel", array($labelText));}
    public function execBatch           ($batchName)                    {
        $fname=$_SERVER['DOCUMENT_ROOT']."/../Uploads/scripts/".str_replace("/"," ",str_replace("\\"," ",$batchName));
        //return $fname;
        exec($fname, $output, $err);
        $ret="";
        if (!empty($err)){
            if (is_array($err))
            $ret="ERROR: ".implode($err)." \r\n";
            else
            $ret="ERROR: ".$err." \r\n";
        }
        $ret.= implode($output);
        return $ret;
        //return shell_exec($fname);
    }
    public function readFile            ($fileName)                     {
        $fileName=$_SERVER['DOCUMENT_ROOT']."../Uploads/files/".str_replace("/"," ",$fileName);
        return file($fileName, FILE_USE_INCLUDE_PATH & FILE_SKIP_EMPTY_LINES);
    }
    //public function getDataJson         ()                              {$ret=""; if (!is_null($this->_wofProcessedData)){$ret= json_encode ($this->_wofProcessedData, JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);} return $ret;}
    // Complex functions
    public function normalizePhoneNum ($baseIntlCode,$thePhonenumInput) {
        $res="";
        if (!is_null($thePhonenumInput) && !empty($thePhonenumInput)) { 
            $res=trim($thePhonenumInput);
            $res=str_replace(" ","",$res);
            $res=str_replace(".","",$res);
            $res=str_replace("-","",$res);
            if (substr($res,0,1)!="+" && substr($res,0,2)!="00")
                $res=$baseIntlCode+$res;
        }
        return $res;
    }

    public function checkEmailAddress   ($emailAddr){
        if (filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            if ($this->_smtp_exists($emailAddr)){
                $this->_addToResArray("\$isGoodEmailAddr", true);
                return true;
            }
        }            
        $this->_addToResArray("\$isGoodEmailAddress", false);
        return false;
    }

    // v3.0.4
    // get shorter uri from HTTP API
    private function _getShortUri($longUri){
        $shrinker=new FluLuUriShrinkController();
        return $shrinker->shrink($longUri);
    }

    public function isDisposableEmailAddress($emailAddr){
        $badEmail1 = array("yopmail", "throwawaymail", "temp-mail","getairmail","youmail", "igoqu", "clop40", "spam", "spam4", "spam3", "spam2", "spam1", "guerrillamail","nedoz","10minutemail","minuteinbox","emailtemporal","maildrop","fakemailgenerator");
        $badEmail2 = array("squeeze.org","tempr.email","crazymailing.com","dropmail.me","mytemp.email","altcen.com","mailinator.com", "text.gq", "wait.cf", "grr.la", "sharklasers.com","moakt.com","mohmal.com","byom.de", "pokemail.net","guerrillamailblock.com","firemailbox.club");
        try{
            $parts=explode("@",$emailAddr);
            if (is_array($parts)){
                $prts=explode(".",trim(strtolower($parts[1])));
                if (is_array($prts)){
                    if (in_array (trim($prts[0]) , $badEmail1))
                        return 1;
                    if (in_array (trim($prts[0]).".".trim($prts[1]) , $badEmail2))
                        return 1;

                    $hc=new HttpCaller();
                    $response =$hc->exec('https://api.mailcheck.ai/email/' . $emailAddr,"GET");
                    if (!empty($response)){
                        $resp=json_decode($response);
                        if ($resp->disposable)
                            return 1;
                    } else {
                        $this->_addToResArray("WARNING", "Api for mailcheck returns a bad or empty response: [".$response."]");
                    }
                }
                return 0;
            }
        } catch (\Throwable $e){}
        return null;
    }
    public function isGoodText($theText){
        $WG=new Gibberish();
        return !$WG->isGibberish($theText);
    }
    public function getHtml($url){
        $client = \Symfony\Component\Panther\Client::createChromeClient();
        $crawler = $client->request('GET', $url);
        $client->waitFor('body');
        return $crawler->html();
        /*

        $httpClient = new \Goutte\Client();
        return $httpClient->request('GET', $url);

        */
    }
    // PRIVATE - INTERNAL FUNCTIONS - - - - - - - - - - - - - - - - - - - - - - - -
    private function __init(){
        $this->_addToResArray("inited", date_create('now')); return true;
        if ($this->_mySess->getVarValue("$"."isTelegram"))
            $this->_channel="Telegram";
        if ($this->_mySess->getVarValue("$"."isApp"))
            $this->_channel="FlussuApp";
        if ($this->_mySess->getVarValue("$"."isMessenger"))
            $this->_channel="Messenger";
        if ($this->_mySess->getVarValue("$"."isWhatsapp"))
            $this->_channel="Whatsapp";
    }

    private function _smtp_exists($email){
        $part=explode("@",$email);
        if (is_array($part) && count($part)!=2)
            return false;
        if (is_array($part) && count($part)==2)
            $email=$part[1];
        return checkdnsrr($email, "MX");
    }
    private function _generateRandomCode($rcgLen,$rcgChars){
        srand((double)microtime()*1000000); 
        $rcgCode=""; 
        $rcgLenCT=strlen($rcgChars)-1;
        while (strlen($rcgCode) <= $rcgLen) { 
            $rcgCode .= substr($rcgChars, rand() % $rcgLenCT, 1);
        } 
        return $rcgCode; 
    }
    private function _addToResArray($key, $value){
        if (is_null($value))
            $value="";
        if ($this->_wofEnvRes==null)
            $this->_wofEnvRes=[];
        //check for duplicated exit values
        if ($key=="exit_to"){
            $elim=-1;
            for ($i=0; $i<count($this->_wofEnvRes);$i++){
                $elm=$this->_wofEnvRes[$i];
                if (array_keys($elm)[0]==$key){
                    $elim=$i;
                    break;
                }
            }
            if ($elim>=0)
                $this->_wofEnvRes[$elim]=array($key => $value);
            else
                array_push($this->_wofEnvRes,(array($key => $value)));
        }
        else
            array_push($this->_wofEnvRes,(array($key => $value)));
    }

    private function _buildBackHereUri(){
        $ret=$this->_mySess->getVarValue("$"."_scriptCallerUri")."&SID=".$this->_mySess->getId()."&BID=".$this->_mySess->getBlockId();
        return $ret;
    }
    private function _getStripeChargeInfo($stripeChargeId,$keyName){
        $stcn=new \Flussu\Controllers\StripeController();
        $res=$stcn->getChargeInfo($stripeChargeId,$keyName);
        /* ---------------------------------------
                    RETURN -NULL- OR:
        ------------------------------------------
        session id;
        intent id;
        charge id;
        event-> date & time;
        customer->name ->email & ->phone;
        email (receipt email);
        total amount with decimal comma (1,00);
        currency string (EUR, USD, ecc.);
        paid as boolean;
        receipt as the receipt URL;
        metadata if any it's an array (key->value);
        reference if any;
        product -> metadata , description & price -> id, amount & metadata
        custom_fields -> key (ex. codicesconto), type (ex. text), label (ex. Codice Sconto), value (ex. HORIGANO)
        ------------------------------------------ */
        return $res;
    }

    private function _getStripeSessInfo($stripeSessId, $configId, $keyType){
        $stcn=new \Flussu\Controllers\StripeController();
        $stcn->init($configId, $keyType);
        
        $res=$stcn->getStripePaymentResult($stripeSessId);
        /* ---------------------------------------
                    RETURN -NULL- OR:
        ------------------------------------------
        session id;
        intent id;
        charge id;
        event-> date & time;
        customer->name ->email & ->phone;
        email (receipt email);
        total amount with decimal comma (1,00);
        currency string (EUR, USD, ecc.);
        paid as boolean;
        receipt as the receipt URL;
        metadata if any it's an array (key->value);
        reference if any;
        product -> metadata , description & price -> id, amount & metadata
        custom_fields -> key (ex. codicesconto), type (ex. text), label (ex. Codice Sconto), value (ex. HORIGANO)
        ------------------------------------------ */
        return $res;
    }

    /*private function _createStripePromoCode($couponId,$promoCode,$maxRedemptions=1,$metadataPair=null){
        $res=new \stdClass;
        $res->error=true;
        $res->promoCodeObj=null;
        $res->promoCode="";
        $res->message="Stripe Cli/Key not found!";
        $strkey=$_ENV["stripe_key"];
        if (!empty($strkey)){
            $stripe = new \Stripe\StripeClient($strkey);
            $res->message="You must attach this promo-code to an already created stripe Coupon_Id! You can create coupons using the stripe's dashboard.";
            if (!empty($couponId)){
                if (!empty($promoCode))
                    $promoCode=$this->_generateRandomCode(8,"245679-ACDFGHKNPSTUVXYZ");
                if (strlen($promoCode)<8)
                    $promoCode=$promoCode.$this->_generateRandomCode(7-strlen($promoCode),"245679-ACDFGHKNPSTUVXYZ");
                $res->message="Cannot create this Promo-Code (coupon_id:[".$couponId."], promo_id:[".$promoCode."])";
                if (is_null($metadataPair))
                $metadataPair=["flussu_wid:sid",$this->_thisWid.":".$this->_mySess];
                $promoCodeObj=$stripe->promotionCodes->create ([
                    "coupon" => $couponId,
                    "code" => $promoCode,
                    "active"=>true,
                    "max_redemptions"=>$maxRedemptions,
                    "metadata"=>json_encode($metadataPair),
                ]);
                $res->error=false;
                $res->message="PromoCode created";
                $res->promoCode=$promoCode;
                $res->promoCodeObj=$promoCodeObj;
            }
        }
        return $res;
    }*/

    private function _getCodFiscInfo($codFisc){
        $WC=new Command();
        $res= $WC->chkCodFisc([$codFisc]);
        if ($res->isGood){
            $datetime1 = new \DateTime($res->bDate);
            $datetime2 = new \DateTime(date('Y-m-d'));
            $diff = $datetime1->diff($datetime2);
            $res->yOld=$diff->format('%y');
        } else
            $res->yOld=0;
        return $res;
    }
    private function _normSurvey($inQuest){
        $outQuest=[];
        $outQuest["data"]=[];
        $outQuest["flussu"]=[];
        $qkeys=array_keys((array)$inQuest);
        if (is_array($qkeys) && count($qkeys)>1){
            sort($qkeys);
            // Normalize START -------------------------------------------
            $stc=0;
            $ok="";
            foreach ($qkeys as $key) {
                $value=((array)$inQuest)[$key];
                $k=$key;
                if (substr($key,0,1)=="Q")
                    $k=str_replace("Q","",$key);
                if (is_numeric($k)){
                    $k=intval($k);
                    $stc=0;
                    $ok=$k;
                } else {
                    $stc++;
                    $k=$ok;
                }
                $outQuest["data"][$k][$stc]["tit"]=$value->tit;
                if (isset($value->img))
                    $outQuest["data"][$k][$stc]["img"]=$value->img;
                else
                    $outQuest["data"][$k][$stc]["img"]="";
                $outQuest["data"][$k][$stc]["dat"]=$value->data;
                $outQuest["data"][$k][$stc]["res"]=$value->res;
            }
            // normalize END -------------------------------------------
            // prepare Flussu-Html-Format
            $righe="";
            $outQuest["data"]=json_decode(json_encode($outQuest["data"]),true);
            $keys=array_keys($outQuest["data"]);
            foreach ($keys as $key) {
                for ($i=0;$i<count($outQuest["data"][$key]);$i++){
                    if ($i==0)
                        $righe.="{hr}{pbr}{t}[Q".$key."] ".$outQuest["data"][$key][0]["tit"]."{/t}";
                    else
                        $righe.="{pl1}{b}".$outQuest["data"][$key][$i]["tit"]."{b}";
                    $value=$outQuest["data"][$key][$i];
                    
                    // c'è un valore di risultato?
                    if (isset($value["img"]) && !empty($value["img"])){
                        $righe.="{pl1}{img} style=\"height:200px;width:auto;max-height:200px\" src=\"".$value["img"]."\" {/img}{/pl1}";
                    }
                    $vres=$value["res"];

                    $righe.="{pl1}";
                    if (is_object($vres)){
                        $vres=array_keys(get_object_vars($value["res"]));
                    }
                    if (is_array($vres)) {
                        foreach($value["dat"] as $qv => $qt){
                            $isSelected=false;
                            foreach($vres as $vls){
                                $isSelected=($vls==$qv);
                                if ($isSelected)
                                    break;
                            }
                            $righe.=$isSelected?"{b}[X] ".$qt."{/b} - ":"[_] ".$qt." - ";
                        }
                        if (substr($righe,-3)===" - ")
                            $righe=substr($righe,0,strlen($righe)-3);
                    } elseif (!is_null($value["res"]) || !empty($value["res"])) {
                        $righe.="{i}".$value["res"]."{/i}";
                    }
                    $righe.=($i>0)?"{/pl1}{/pl1}":"{/pl1}";
                }
                $righe.="{/pbr}\r\n";
            }
            $outQuest["flussu"]=$righe;
            $righe="";
        }
        return $outQuest;
    }
}