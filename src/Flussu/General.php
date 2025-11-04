<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS PATH:       App\Flussu
 * CLASS-NAME:       General.class
 * -------------------------------------------------------*
 * RELEASED DATE:    07.01:2022 - Aldus - Flussu v2.0
 * VERSION REL.:     4.4.20250621
 * UPDATES DATE:     21.06:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 *  4.4 - introducing code error handling
 * -------------------------------------------------------*/

/*
    La casse GENERAL è una sorta di contenitore di utilità, usata per diversi scopi in più parti di Flussu
    contiene una serie di routine di base, di utilità e generali.
*/

/**
 * The General class is responsible for providing general configuration and utility functions within the Flussu server.
 * 
 * This class manages various static properties and methods that are used throughout the Flussu server for configuration,
 * logging, and other general purposes. It serves as a central point for managing global settings and utilities.
 * 
 * Key responsibilities of the General class include:
 * - Managing global configuration settings such as document root, debug mode, password validity period, language, and development mode status.
 * - Initializing and managing the logging process, including starting the log and recording the start time.
 * - Providing utility functions that can be used across different components of the Flussu server.
 * 
 * The class is designed to be easily accessible and modifiable, allowing for quick adjustments to global settings and
 * the addition of new utility functions as needed.
 * 
 */

namespace Flussu;
use Flussu\Flussuserver\Handler;
use Flussu\Flussuserver\NC\HandlerNC;
class General {
    static $DocRoot="";
    static $DEBUG=true;
    static $UseCache=true;
    static $_CacheResults=true;
    static $monthsPwdValid=3;
    static $Lang="it";
    static $sTime=0;
    static $flussuIsInDev=true;
    static $cache_dir ="";
    static $log_dir = "";
    private static $_genKey="Aldo08Fede05";
    private static $_myHandler=null;
    private static $_myHandlerNC=null;

    static function initLog(){
        $_SESSION["Log"]=date("d/m H:i:s")." Start Log:\r\n";
        self::$sTime=hrtime(true);
    }    
    static function addLog($addString){
        if (self::$sTime==0 || !isset($_SESSION["Log"]))
            self::initLog();
        $_SESSION["Log"].=$addString;
    }
    static function addRowLog($addRow){
        self::addLog($addRow."\r\n");
    }
    static function getVersion(){
        return $_ENV["major"].".".$_ENV["minor"];
    }
    static function getLog(){
        return $_SESSION["Log"].=date("d/m H:i:s")." END Log\r\n(Consumed:".self::getTimeConsumedSec()." sec)";
    }
    static function getTimeConsumedSec(){
        return (self::getHrTimeDiff())/1e+9; 
    }
    static function getHrTimeDiff(){
        return (hrtime(true) - self::$sTime); 
    }
    static function DoCache():bool{
        return self::$_CacheResults;
    }
    static function Log_nocaller($log_msg,$forced=false)
    {
        $debug=isset($_ENV["debug_log"])?$_ENV["debug_log"]:false;
        $res=false;
        if ($debug || $forced) {
            $log_dir=$_SERVER['DOCUMENT_ROOT']."/../Logs/";
            $now = (\DateTime::createFromFormat('U.u', microtime(true)))->format("H:i:s.u");
            //$now=$now;
            if (isset($_SESSION["FlussuSid"]))
                $now.=" S:".$_SESSION["FlussuSid"];
            $now.="| ";
            if ($_ENV["debug_path"])
                $log_dir=$_SERVER['DOCUMENT_ROOT']."/../".$_ENV["debug_path"];
            try{
                $log_filename =self::getLogFile($log_dir);
                $res=file_put_contents($log_filename, $now.$log_msg."\n", FILE_APPEND);
            } catch (\Throwable $e) {
                $res=file_put_contents($log_filename, $now." ERRORE!: ".json_encode($e)."\n", FILE_APPEND);
            }
        }
        return $res;
    }
    static function Log2($log_msg,$logDir="")
    {
        // SYSTEM-SERVICE LOG
        if (!file_exists($logDir))
            $res=mkdir($logDir, 0777, true);
        $now = (\DateTime::createFromFormat('U.u', microtime(true)))->format("H:i:s.u");
        try{
            $log_filename =self::getLogFile($logDir);
            $res=file_put_contents($log_filename, $now." ".$log_msg."\n", FILE_APPEND);
        } catch (\Throwable $e) {
            $res=file_put_contents($log_filename, $now." ERRORE!: ".json_encode($e)."\n", FILE_APPEND);
        }
        return $res;
    }

    static private function getLogFile($logDir)
    {
        $log_filename ="";
        if ($logDir!=""){
            if (!file_exists($logDir))
                mkdir($logDir, 0777, true);
            $log_filename = str_replace("//","/",$logDir.'/log_' . date('d-M-Y') . '.log');
            $oldlog= $logDir.'log_' . date('d-M-Y', strtotime('-1 month')). '.log';
            if (file_exists($oldlog)){
                try{
                    unlink($oldlog);
                } catch (\Throwable $e) {
                    $e->getMessage();
                }
            }
            // elimima anche il 31 se il mese è di 30.
            if (date("d")==1){
                $oldlog=$logDir.'log_31' . date('-M-Y', strtotime('-1 month')). '.log';
                if (file_exists($oldlog)){
                    try{
                        unlink($oldlog);
                    } catch (\Throwable $e) {
                        $e->getMessage();
                    }
                }
            }
        }
        return $log_filename;
    }


    static function Log($log_msg,$forced=false)
    {
        $debug=isset($_ENV["debug_log"])?$_ENV["debug_log"]:false;
        if ($debug || $forced) {
            $caller=self::getCaller(debug_backtrace());
            $caller=str_replace($log_msg,"[message]",$caller);
            self::Log_nocaller(" #".$caller."# ".$log_msg,$forced=false);
        }
    }
    static function Persist($objId,$objImg,$refType="gen",$refId="gen")
    {
        if (self::$UseCache){
            $fname=hash('sha256', $objId);
            $cache_dir=$_SERVER['DOCUMENT_ROOT']."/../Cache/";
            $dpath=str_replace("//","/",$cache_dir.$refType."_".$refId);
            $cfname=str_replace("//","/",$dpath."/".$fname.".php");
            if (!file_exists($cfname)){
                try{
                    if (!file_exists($dpath."/"))
                        mkdir($dpath."/", 0775, true);
                    $cacheContent=json_encode(["timestamp"=>time(),"image"=>self::_smartEncrypt($objImg)]);
                    file_put_contents($cfname, $cacheContent, FILE_APPEND);
                    self::Log_nocaller(" #".self::getCaller(debug_backtrace())."# - Persist -> ".$objId);  
                } catch (\Throwable $e) {
                    self::Log("GENERAL:PERSIST ERROR: ".json_encode($e));
                }
            }
        }
    } 
    static function getCaller($v){
        //$v=debug_backtrace();
        $caller="[call from:???]";
        try{
            foreach ($v as $k=>$val){
                if (isset ($val["file"]) && stripos($val["file"],"general.php")===false && stripos($val["class"],"general")===false){
                    $exp=explode("/",$val["file"]);
                    $func="???";
                    if (count($val["args"])>0 && is_object($val["args"][0]))
                        $args="[object]";
                    else if (is_array($val["args"])){
                        $args=implode(",",$val["args"]);
                        $func=$val["function"];
                    }
                    $caller=end($exp)."->".$func."(".$args.")";
                    break;
                }
            }
        } catch (\Throwable $e) {
            $caller="[CALLER GET ERROR:".$e->getMessage()."]";
        }
        return $caller;
    } 
    static function Deserialize($objId,$refType="gen",$refId="gen")
    {
        $fname=hash('sha256', $objId);
        $cache_dir=$_SERVER['DOCUMENT_ROOT']."/../Cache/";
        $fpath=str_replace("//","/",$cache_dir."/".$refType."_".$refId);
        if (self::$UseCache){
            try{
                if (!file_exists($fpath."/"))
                    return null;
                $fname=str_replace("//","/",$fpath."/".$fname.".php");
                
                // ------------------------------------------------------------
                // GESTIONE ERRORE FILE INESISTENTE
                // ------------------------------------------------------------
                // Mettendo @ davanti file get contents, si evita di avere l'errore di "file inesistente".
                // Si può quindi controllare il risultato: se FALSO, allora il file non eisste.
                $cacheContent=@file_get_contents($fname);
                if ($cacheContent===false)
                    return null;
                // ------------------------------------------------------------

                //self::Log_nocaller(" #".self::getCaller(debug_backtrace())."# - Deserialize <- ".$objId);   
                return self::_smartDecrypt(json_decode($cacheContent)->image);
            } catch (\Throwable $e) {
                self::Log("GENERAL:DESERIALIZE ERROR: ".json_encode($e));
            }
        }
    }
    static function ObjPersist($id,$obj)
    {
        $fname=hash('sha256', $id);
        $cache_dir=$_SERVER['DOCUMENT_ROOT']."/../Cache/Objects/";
        $fname=str_replace("//","/",$cache_dir.$fname);
        try{
            if (!file_exists($cache_dir."/"))
                mkdir($cache_dir."/", 0775, true);
            $cacheContent=self::_smartEncrypt(json_encode($obj));
            file_put_contents($fname, $cacheContent);
            //self::Log_nocaller(" # OBJ_Persist -> ".$id);  
        } catch (\Throwable $e) {
            self::Log("GENERAL: OBJ PERSIST ERROR: ".json_encode($e));
        }
    } 
   
    static function ObjRestore($id,$associative=false)
    {
        $fname=hash('sha256', $id);
        $cache_dir=$_SERVER['DOCUMENT_ROOT']."/../Cache/Objects/";
        $fname=str_replace("//","/",$cache_dir.$fname);
        if (file_exists($fname)){
            try{
                $cacheContent=@file_get_contents($fname);
                if ($cacheContent===false)
                    return [];
                //self::Log_nocaller(" # OBJ_Restore <- ".$id);  
                return json_decode(self::_smartDecrypt($cacheContent),$associative);
            } catch (\Throwable $e) {
                self::Log("GENERAL: OBJ RESTORE ERROR: ".json_encode($e));
            }
        }
        return [];
    } 

    static private function _smartEncrypt($value){
        return strrev(bin2hex($value));
    }
    static private function _smartDecrypt($value){
        return hex2bin(strrev($value));
    }
    static function PutCache($id, $cacheable,$refType,$refId){
        if (self::$_CacheResults){ 
            self::Persist($id,json_encode($cacheable),$refType,$refId);
        }
        return $cacheable;
    }
    static function GetCache($id,$refType,$refId) {
        if (self::$_CacheResults){ 
            $ret=self::Deserialize($id,$refType,$refId);
            if ($ret!==false && !is_numeric($ret) && !is_null($ret))
                return json_decode($ret,true);
            return $ret;
            //return json_decode($_SESSION[$id],true);
        }
        return null;
    }
    static function DelCache($refType,$refId){
        self::$cache_dir=$_SERVER['DOCUMENT_ROOT']."/../Cache/";
        try{
            if(self::deleteDirectory(self::$cache_dir.$refType."_".$refId."/")){
                //self::Log("Removed ".$refType.":".$refId);   
            } else
                self::Log("NOT REMOVED!!! ".$refType.":".$refId);   

            //return rmdir(self::$cache_dir.$refType."_".$refId."/");
        } catch (\Throwable $e) {
            self::Log("GENERAL:DELETE CACHE ERROR: ".json_encode($e));
        }
        return false;
    }
    static function deleteDirectory($dir) {
        if (!file_exists($dir)) 
            return true;
        if (!is_dir($dir)) 
            return unlink($dir);
    
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..')
                continue;
            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
                return false;
        }
        return rmdir($dir);
    }

    static function getCallerIPAddress() {  
        //whether ip is from the share internet  
         if(!empty($_SERVER['HTTP_CLIENT_IP'])) {  
            $ip = $_SERVER['HTTP_CLIENT_IP'];  
        }  
        //whether ip is from the proxy  
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];  
        }  
        //whether ip is from the remote address  
        else{  
            $ip = $_SERVER['REMOTE_ADDR'];  
        }  
        return $ip;  
    }  
    static function callVars($request){
        $a=$request;
        $scheme="https";
        if (strpos($request->url(),"https://")===false) 
            $scheme="http";
        //$host=substr($request->url(),strlen($scheme)+3,strpos($request->url(),"/",strlen($scheme)+3)-strlen($scheme)+3);
        $host=$request->server("HTTP_HOST");
        return [
            "scheme"=>$scheme,
            "host"=>$host,
            "uri"=>$request->path()
        ];
    }
    static function getCallerUserAgent(){
        return $_SERVER['HTTP_USER_AGENT'];
    }
    static function clearLog(){
        self::delSession("Log");
    }
    static function camouf($pid){
        if (trim($pid)=="" || intval($pid)<1)
            return "";
        $cpid=self::generateCamoufRnd($pid);
        $hpid=dechex($pid);
        $cn=strlen($hpid);
        $ck=12-$cn;
        
        $cp1=substr($cpid,0,4);
        $cp2=substr($cpid,4,4);
        $cp3=substr($cpid,8,4);
        if (strlen($hpid)>4){
            $cp1=substr_replace($cp1, substr($hpid,0,4), 0, 4);
            $hpid=substr($hpid,4);
        } else {
            $cp1=substr_replace($cp1, $hpid, 0, strlen($hpid));
            $hpid="";
        }
        if (strlen($hpid)>4){
            $cp2=substr_replace($cp2, substr($hpid,0,4), 0, 4);
            $hpid=substr($hpid,4);
        } else {
            $cp2=substr_replace($cp2, $hpid, 0, strlen($hpid));
            $hpid="";
        }
        if (strlen($hpid)>0)
            $cp3=substr_replace($cp3, $hpid, 0, strlen($hpid));
        return "_".substr($cpid,$ck,1).dechex($cn).$cp2.$cp3.$cp1.dechex($ck).substr($cpid,8,1)."_";
    }
    static function demouf($camouf_pid){
        if (trim($camouf_pid)=="" || strlen($camouf_pid)<16)
            return $camouf_pid;
        $cp=substr($camouf_pid,11,4).substr($camouf_pid,3,4).substr($camouf_pid,7,4);
        $cn=hexdec(substr($camouf_pid,2,1));
        $ck=hexdec(substr($camouf_pid,15,1));
        if ($ck+$cn==12)
            return hexdec(substr($cp,0,$cn));
        return $camouf_pid;
    }
    static function generateCamoufRnd($elmId) {
        $length = 14;
        $chars = '0123456789abcdef';
        $str = '';
        srand($elmId);
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[rand(0, 15)];
        }
        return $str;
    }
    static function setNextCookie($cookie_name,$cookie_value,$cookie_seconds_duration,$cookie_domain="/"){
        self::setSession("next_cookie_name", $cookie_name);
        self::setSession("next_cookie_value", $cookie_value);
        self::setSession("next_cookie_duration", $cookie_seconds_duration);
        self::setSession("next_cookie_domain", $cookie_domain);
    }
    
    static function doPutCookies(){
        if (self::getSession("next_cookie_name")!=""){
            $CN=self::getSession("next_cookie_name");
            $CV=self::getSession("next_cookie_value");
            $CD=self::getSession("next_cookie_duration");
            $CM=self::getSession("next_cookie_domain");

            if (self::getCookie($CN)!=$CV){
                setcookie($CN, $CV, time()+$CD, $CM);
            }
            self::delSession("next_cookie_name");
            self::delSession("next_cookie_value");
            self::delSession("next_cookie_duration");
            self::delSession("next_cookie_domain");
        }
    }

    static function getUuidv4($qty=1){
        if ($qty>1){
            $arGuid=[];
            for ($i=0;$i<$qty;$i++){
                array_push($arGuid,self::_getUUID());
            }
            return $arGuid;
        } else {
            return self::_getUUID();
        }
    }
    private static function _getUUID(){
        $nuuid=bin2hex(random_bytes(2)).str_replace(".","",uniqid("",true)).bin2hex(random_bytes(3));
        return substr($nuuid,0,8)."-".substr($nuuid,9,4)."-".substr($nuuid,12,4)."-".substr($nuuid,16,4)."-".substr($nuuid,20);
    }

    static function getDocRoot(){
        if (isset($_SERVER["DOCUMENT_ROOT"]))
            $DocRoot=$_SERVER["DOCUMENT_ROOT"];
        return $DocRoot;

    }
    static function getSitename(){
        $ret=self::getHttpHost();
        if ($ret!="")
            return $ret;
        else
            return "localhost";
    }
    static function getReferer(){
        if (isset($_SERVER["HTTP_REFERER"]) && !is_null($_SERVER["HTTP_REFERER"]))
            return $_SERVER["HTTP_REFERER"];
        else
            return "";
    }
    static function getHttpHost(){
        if (isset($_SERVER["HTTP_HOST"]) && !is_null($_SERVER["HTTP_HOST"]))
            return $_SERVER["HTTP_HOST"];
        else
            return "";
    }
    static function getRequestUri(){
        if (isset($_SERVER["REQUEST_URI"]) && !is_null($_SERVER["REQUEST_URI"]))
            return $_SERVER["REQUEST_URI"];
        else
            return "";
    }
    static function getCookie($key){
        if (isset($_COOKIE[$key]) && !is_null($_COOKIE[$key]))
            return $_COOKIE[$key];
        else
            return "";
    }
    static function getSession($key){
        if (isset($_SESSION[$key]) && !is_null($_SESSION[$key]))
            return $_SESSION[$key];
        else
            return "";
    }
    static function setSession($key,$val){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION[$key]=$val;
    }
    private static function _startHandler(){
        if (!isset(self::$_myHandler)){
            self::$_myHandler=new Handler();
            self::$_myHandlerNC=new HandlerNC();
        }
    }
    static function setGlobal($key,$val){
        self::_startHandler();
        self::$_myHandlerNC->setGlobalData(self::$_genKey,$key,$val);
    }
    static function getGlobal($key){
        self::_startHandler();
        return self::$_myHandlerNC->getGlobalData(self::$_genKey,$key);
    }
    static function delGlobal($key){
        self::_startHandler();
        return self::$_myHandlerNC->delGlobalData(self::$_genKey,$key);
    }
    static function delSession($key){
        if (isset($_SESSION[$key])){
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }
    static function cleanSession(){
        foreach($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
    }
    static function getPost($key){
        try{
            if (isset($_POST[$key]) && !is_null($_POST[$key])) {
                return $_POST[$key];
            } else {
                return "";
            }
        } catch (\Throwable $e) {
            return "";
        }
    }
    static function getGet($key){
        try{
            if (isset($_GET[$key]) && !is_null($_GET[$key])) {
                return $_GET[$key];
            } else {
                return "";
            }
        } catch (\Throwable $e) {
            return "";
        }
    }
    static function getGetOrPost($key){
        $ret=self::getGet($key);
        if ($ret=="")
            $ret=self::getPost($key);
        return $ret;
    }
    static function getPageRequestTerm(){
        $urip=explode("?",self::getRequestUri());
        if (is_array($urip) && count($urip)>1)
            return $urip[count($urip)-1];
        return "";
    }
    static function isEmailAddress($var){
        $ema=new EmailAddressValidator();
        return $ema->check_email_address($var);
    }    
    static function getSiteRoot(){
        return $_SERVER['DOCUMENT_ROOT'];
    }
    static function getPageName(){
        $pn=explode("/",$_SERVER['REQUEST_URI']);
        return $pn[count($pn)-1];
    }
    static function separateCwid($CWID){
        $ccwid=$CWID;
        $ret = new \stdClass();
        $ret->auk="";
        $ret->wid="";
        if (!empty($CWID)){
            $parts=explode("|",$CWID);
            if (count($parts)==2 && strlen($parts[0])<25){
                $auk=substr($parts[1],0,-1);
                $CWID=$parts[0]."]";
            } else {
                if (count($parts)>1)
                    $CWID="[".$parts[1];
                $auk=substr($parts[0],1);
            }
            $ret->auk=$auk;
            $ret->wid=$CWID;
        }
        return $ret;
    }
    static function calcSeed($seed){
        $sid=strval($seed);
        if (strlen($sid)>5)
            $seed=intval(substr($sid,strlen($sid)-5));
        if ($seed>1000000)
            $seed=$seed/10565;
        if ($seed>100000)
            $seed=$seed/1029;
        if ($seed>10000)
            $seed=$seed/105;
        if ($seed>1000)
            $seed=$seed/8;
        do{
            $seed/=2;
        } while ($seed>11);
        if ($seed<1)
            $seed=1;

        $seed=intval($seed);
        if($seed % 2 == 0) $seed++;

        return $seed;
    }
    static function curtatone($id,$text){
        $text=trim($text);
        if (!empty($text)){
            $text=substr(self::generateCamoufRnd($id),0,10).$text;
            $seed=self::calcSeed($id);
            $critxt=bin2hex(strrev($text));
            $critxt=substr($critxt,$seed).substr($critxt,0,$seed);
            if($seed>4)
                $critxt="A7".$critxt."D0";
            else
                $critxt="FE".$critxt."DE";
            return "A7D0".strtoupper(bin2hex(strrev(self::alice(hex2bin($critxt),self::mastrodichiavi($id,20)))))."FEDE";
        }
        return "";
    }
    static function isCurtatoned($text){
        return (!is_null($text) && strlen($text)>18 && substr($text,0,4)=="A7D0" && substr($text,strlen($text)-4)=="FEDE");
    }
    static function montanara($text,$id){
        $text=trim($text);
        try {
            if (self::isCurtatoned($text)){
                $text=substr($text,4,strlen($text)-8);
                $seed=self::calcSeed($id);
                $text=bin2hex(self::alice(strrev(hex2bin($text)),self::mastrodichiavi($id,20)));
                $critxt=substr($text,2,strlen($text)-4);
                $critxt=substr($critxt,strlen($critxt)-$seed).substr($critxt,0,strlen($critxt)-$seed);
                return substr(strrev(hex2bin($critxt)),10);
            }
        }
        catch(\Throwable $e){
            // echo $e.getMessage();
        }
        return "";
    }
    static $DTIday=["5","4","3","2","1","Z","y","X","w","V","u","L","k","J","i","H","g","F","t","S","r","Q","p","O","n","M","e","D","c","B","a"];
    static $DTImonth=["1","P","(",")","|","!","7","0","L","?","[","]"];
    static $DTIhour=["A","B","C","d","e","f","X","Y","Z","2","4","7","M","a","b","c","D","E","F","x","y","z","1","3","9","N"];
    static $DTImin=["0","9","8","J","K","l","T","r","q","r","O","I"];
    static function getDateTimedApiKeyFromUser($userId,$minutes){
        // Generatore AUK
        $M=round($minutes/5);
        //$now = new DateTime(time());
        $future = new \DateTime(date('Y-m-d H:i:s', strtotime('+'.($M * 5).' minutes')));
        //$future=$now->add(new DateInterval("PT".($M * 5)."M"));
        $res=self::$DTImonth[intval($future->format('m'))-1].self::$DTIhour[intval($future->format('H'))].self::$DTIday[intval($future->format('d'))-1].self::$DTImin[(intval($future->format('i')/12))];
        $a=substr(self::camouf(intval($userId)),1,16);
        $res=substr($a,0,9).self::curtatone(intval($userId),$res).substr($a,9);
        return $res;
    }
    
    // cwid=[wid|auk]  =>  auk=camouf(userId)+curtatone(userId,dateTimed)
    //[WID|AUK] EX: [w222afeaf278f11aa|921b4df49A7D0EABB9A2766BA57892E575807061F288EFEDE01095af]
    
    static function getUserFromDateTimedApiKey($passKey){
        //reverse AUK
        $res=0;
        if (!empty($passKey)){
            try{
                $cam=substr($passKey,0,9);
                $p=strpos($passKey,"FEDE",strlen($passKey)-20)+4;
                $cam.=substr($passKey,$p,strlen($passKey)-$p);
                $tmd=substr($passKey,9,$p-9);
                
                $uid=self::demouf("_".$cam."_");
                if ($uid>0){

                    $tmd=self::montanara($tmd,$uid);
                    $m=array_search(substr($tmd,0,1),self::$DTImonth)+1;
                    $h=array_search(substr($tmd,1,1),self::$DTIhour);
                    $d=array_search(substr($tmd,2,1),self::$DTIday)+1;
                    $i=(array_search(substr($tmd,3,1),self::$DTImin)+1)*5;
                    $future=new \DateTime(date_create("now")->format("Y")."-".$m."-".$d." ".$h.":".$i.":30");
                    if ($future<date_create("now"))
                        $res=0;
                    else
                        $res=$uid;
                }
                //return self::camouf($userId).self::curtatone($res,$userId);
            } catch (\Throwable $e){
                // non fare niente
                $res=0;
            }
        }
        return $res;
    }
    static function mastrodichiavi($id,$numkeys){
        $key="";
        mt_srand($id,MT_RAND_MT19937);
        for ($i=0;$i<$numkeys;$i++)
            $key.=str_pad(dechex(mt_rand(0,127)),2,"0",STR_PAD_LEFT);
        if (strlen($key)%2!=0)
            $key.="F";
        return hex2bin($key);
    }
    static function alice($text,$key){
        $str_len = strlen($text);
        $key_len = strlen($key);
        for($i = 0; $i < $str_len; $i++)
            $text[$i] = $text[$i] ^ $key[$i % $key_len];
        return $text;
    }
    static function dieHome($errCode){                              self::dieToAnUrl("/index");}
    static function dieReferer($errCode){$ref=self::getReferer();   self::dieToAnUrl($ref);}
    static function dieToAnUrl($urlTarget){
        echo "<script>window.location.href = \"$urlTarget\";</script>";
        echo "</html>";
        die($urlTarget);
    }
    static function articleAnchorNormalize(string $articleText){
        $goodLinks=[];
        $parts=explode("<a ",$articleText);
        $j=0;
        foreach ($parts as $part){
            $p2=explode("/a>",$part);
            $k="[[[$[[[anchor".$j."]]]$]]]";
            $goodLinks+=array($k=>$p2[0]);
            $articleText=str_replace($p2[0],$k,$articleText);
            $j++;
        }
        $articleText=preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $articleText);
        foreach ($goodLinks as $key=>$value){
            $articleText=str_replace($key,$value,$articleText);
        }
        return $articleText;
    }
    static function fixJSON($json) {
        $regex = <<<'REGEX'
    ~
        "[^"\\]*(?:\\.|[^"\\]*)*"
        (*SKIP)(*F)
      | '([^'\\]*(?:\\.|[^'\\]*)*)'
    ~x
    REGEX;
    
        return preg_replace_callback($regex, function($matches) {
            return '"' . preg_replace('~\\\\.(*SKIP)(*F)|"~', '\\"', $matches[1]) . '"';
        }, $json);
    }
    static function getLangName($langCode){
        switch (trim(strtoupper($langCode))){
            case "IT": return "Italiano";
            case "EN": return "English";
            case "DA": return "Dansk";
            case "DE": return "Deutsch";
            case "CS": return ucfirst("český");
            case "FR": return "Français";
            case "ES": return "Español";
            case "SV": return "Svenska";
            case "NO": return "Norsk";
            case "ZH": return "(中文)";
            default:return "";
        }
    }

    static function hasScriptErrors($code,$description="") {
        /* create lint result using a temporary file */
        $log_dir=$_SERVER['DOCUMENT_ROOT']."/../Logs/";
        $tmpfname = tempnam($log_dir, "phpcheck");
        file_put_contents($tmpfname, "<?php    ".$code);
        $ret="";
        $output = [];
        $return_var = 0;
        $ris=exec("php -el " . escapeshellarg($tmpfname)." 2>&1", $output, $return_var);
        unlink($tmpfname);
        try{
            $ret=str_replace("  "," ",str_replace(" in ".explode(" ",$ris)[2],"",str_replace(" ".$ris,"",implode(" ",$output))));
        } catch (\throwable $e){
            $ret=implode("\r\n",$output);
        }
        if ($return_var !== 0) {
            /*
            if (preg_match('/unexpected variable "\$wofoEnv"/i', $description)) {
                echo "Trovata la variabile \$wofoEnv!";
            } else {
                echo "Nessun errore trovato.";
            }
            */
            self::Log("PHP LINT ERROR: ".$ret."\r\n\t\t\t\t\t\t\t\t\t\t\t\t".$description);
        }
        return [
            'ok' => ($return_var === 0),
            'output' => $ret
        ];
    }

}


class EmailAddressValidator {
/**
 * If the email address passes the local portion check and the domain portion check, then it's valid.
 * 
 * Args:
 *   strEmailAddress: The email address you want to validate.
 */
    public function check_email_address($strEmailAddress) {

        // If magic quotes is "on", email addresses with quote marks will
        // fail validation because of added escape characters. Uncommenting
        // the next three lines will allow for this issue.
        //if (get_magic_quotes_gpc()) { 
        //    $strEmailAddress = stripslashes($strEmailAddress); 
        //}

        // Control characters are not allowed
        if (preg_match('/[\x00-\x1F\x7F-\xFF]/', $strEmailAddress)) {
            return false;
        }

        // Split it into sections using last instance of "@"
        $intAtSymbol = strrpos($strEmailAddress, '@');
        if ($intAtSymbol === false) {
            // No "@" symbol in email.
            return false;
        }
        $arrEmailAddress[0] = substr($strEmailAddress, 0, $intAtSymbol);
        $arrEmailAddress[1] = substr($strEmailAddress, $intAtSymbol + 1);

        // Count the "@" symbols. Only one is allowed, except where 
        // contained in quote marks in the local part. Quickest way to
        // check this is to remove anything in quotes.
        $arrTempAddress[0] = preg_replace('/"[^"]+"/'
                                         ,''
                                         ,$arrEmailAddress[0]);
        $arrTempAddress[1] = $arrEmailAddress[1];
        $strTempAddress = $arrTempAddress[0] . $arrTempAddress[1];
        // Then check - should be no "@" symbols.
        if (strrpos($strTempAddress, '@') !== false) {
            // "@" symbol found
            return false;
        }

        // Check local portion
        if (!$this->check_local_portion($arrEmailAddress[0])) {
            return false;
        }

        // Check domain portion
        if (!$this->check_domain_portion($arrEmailAddress[1])) {
            return false;
        }

        // If we're still here, all checks above passed. Email is valid.
        return true;

    }

    
  /**
   * The local portion of the email address may only consist of alphanumeric characters, or the
   * following characters: `!#$%&'*+/=?^_`{|}~-`, period, and quotation mark
   * 
   * Args:
   *   strLocalPortion: The local portion of the email address (i.e., the part before the @ sign)
   */
    protected function check_local_portion($strLocalPortion) {
        // Local portion can only be from 1 to 64 characters, inclusive.
        // Please note that servers are encouraged to accept longer local
        // parts than 64 characters.
        if (!$this->check_text_length($strLocalPortion, 1, 64)) {
            return false;
        }
        // Local portion must be:
        // 1) a dot-atom (strings separated by periods)
        // 2) a quoted string
        // 3) an obsolete format string (combination of the above)
        $arrLocalPortion = explode('.', $strLocalPortion);
        for ($i = 0, $max = sizeof($arrLocalPortion); $i < $max; $i++) {
             if (!preg_match('.^('
                            .    '([A-Za-z0-9!#$%&\'*+/=?^_`{|}~-]' 
                            .    '[A-Za-z0-9!#$%&\'*+/=?^_`{|}~-]{0,63})'
                            .'|'
                            .    '("[^\\\"]{0,62}")'
                            .')$.'
                            ,$arrLocalPortion[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     * It checks if the domain portion of the email address is valid
     * 
     * Args:
     *   strDomainPortion: The domain portion of the email address.
     */
    protected function check_domain_portion($strDomainPortion) {
        // Total domain can only be from 1 to 255 characters, inclusive
        if (!$this->check_text_length($strDomainPortion, 1, 255)) {
            return false;
        }
        // Check if domain is IP, possibly enclosed in square brackets.
        if (preg_match('/^(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])'
           .'(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}$/'
           ,$strDomainPortion) || 
            preg_match('/^\[(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])'
           .'(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}\]$/'
           ,$strDomainPortion)) {
            return true;
        } else {
            $arrDomainPortion = explode('.', $strDomainPortion);
            if (sizeof($arrDomainPortion) < 2) {
                return false; // Not enough parts to domain
            }
            for ($i = 0, $max = sizeof($arrDomainPortion); $i < $max; $i++) {
                // Each portion must be between 1 and 63 characters, inclusive
                if (!$this->check_text_length($arrDomainPortion[$i], 1, 63)) {
                    return false;
                }
                if (!preg_match('/^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|'
                   .'([A-Za-z0-9]+))$/', $arrDomainPortion[$i])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * If the length of the string is less than the minimum or greater than the maximum, return false.
     * Otherwise, return true
     * 
     * Args:
     *   strText: The text to check
     *   intMinimum: The minimum number of characters the string can be.
     *   intMaximum: The maximum number of characters allowed in the text.
     * 
     * Returns:
     *   A boolean value.
     */
    protected function check_text_length($strText, $intMinimum, $intMaximum) {
        // Minimum and maximum are both inclusive
        $intTextLength = strlen($strText);
        if (($intTextLength < $intMinimum) || ($intTextLength > $intMaximum)) {
            return false;
        } else {
            return true;
        }
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