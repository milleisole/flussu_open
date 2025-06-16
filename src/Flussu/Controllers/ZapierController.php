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
 * CLASS-NAME:       Flussu Zapier API Controller
 * UPDATED DATE:     04.11.2022 - Aldus - Flussu v2.2.4
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Auth;
//use Session;

use Flussu\General;
use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Flussuserver\Session;
use Flussu\Flussuserver\Worker;

use Log;

// This class handles API calls from Zapier.
// The apiCall method processes the incoming request and performs the necessary actions based on the API page.
// The _extractData method extracts data from the request payload.
// The _reportErrorAndDie method reports an error and terminates the script.
// The _getWidZapierVars method retrieves workflow variables for Zapier.

class ZapierController 
{
    public function apiCall(Request $request, $apiPage){
        $isZapier=(isset($_SERVER["HTTP_USER_AGENT"]) && $_SERVER["HTTP_USER_AGENT"]=="Zapier");
        $isPost=(isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"]=="POST");

        $usrName=isset($_SERVER["PHP_AUTH_USER"])?$_SERVER["PHP_AUTH_USER"]:"";
        $usrPass=isset($_SERVER["PHP_AUTH_PW"])?$_SERVER["PHP_AUTH_PW"]:"";
        
        //$usrName=General::getGetOrPost("usr");
        //$usrPass=General::getGetOrPost("pass");
               
        $SentWID=isset($_SERVER["HTTP_WID"])?$_SERVER["HTTP_WID"]:"";
        $rawdata = file_get_contents('php://input');
        $theData=\json_decode($rawdata,true);
        if (isset($theData["WID"]) && !empty($theData["WID"]))
            $SentWID=$theData["WID"];
        else {
            if ($SentWID=="" && isset($theData["WID"])){
                $SentWID=$theData=$theData["WID"];
            }
        }
        $wid= HandlerNC::WID2Wofoid($SentWID);

        if (isset($theData) && is_array($theData) && array_key_exists("data", $theData))
            $theData=$this->_extractData($theData["data"]);

        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');

        $uid=0;
        $theFlussuUser=new \Flussu\Persons\User();
        
        // TEMPORANEO
        if ($usrName=="pippuzzo" && ($usrPass=="giannuzzo123" || $usrPass=="giannuzzo"))
            $theFlussuUser->load(16);
        // DOVRà DIVENTARE
        //$theFlussuUser->authenticate($usrName,$usrPass);
        if ($usrName=="aldus" && ($usrPass=="pattavina"))
            $theFlussuUser->load(16);




        if ($theFlussuUser->getId()<1){
            $this->_reportErrorAndDie("403","Unauthenticated");
        }
        if (strpos($apiPage,"zap?auth")!==false)
            $this->_reportErrorAndDie("200","OK authenticated");

        if (strpos($apiPage,"zap?list")!==false){
            $db= new HandlerNC();
            $res=$db->getFlussu(false,$theFlussuUser->getId());
            $retArr=[];
            $i=1;
            foreach($res as $wf){
                $wc=new \stdClass();
                $wc->id=$i++;
                $wc->wid=$wf["wid"];
                $wc->title=$wf["name"];
                array_push($retArr,$wc); 
            }
            die(json_encode($retArr));
        }

        if ($SentWID=="[__wzaptest__]")
            $this->_reportErrorAndDie("200","Hi Zapier, I'm Alive :), how are u?");
           
        if ($wid<1)
            $this->_reportErrorAndDie("406","Wrong Flussu WID");

        if (is_null($theData) || empty($theData))
            $this->_reportErrorAndDie("406","No Data Received");

        if (is_null($usrName) || empty($usrName))
            $this->_reportErrorAndDie("406","No Username received");

        if (is_null($usrPass) || empty($usrPass))
            $this->_reportErrorAndDie("406","No Password received");

        error_reporting(0); 

        switch ($apiPage){
            case "zap":
                // Fai partire un workflow
                $res=$this->_getWidZapierVars($wid,$SentWID,$theFlussuUser->getId(),$theData);
                $sid=$res[0];
                $vars=\json_encode($res[1]);
                $vars="{".str_replace(["{","}","[","]"],"",$vars)."}";
                $vvv=\json_encode(["result"=>"started","res"=>"","WID"=>$SentWID,"SID"=>$sid]);
                die(str_replace("\"res\":\"\"","\"res\":".$vars,$vvv));
                //die($vars.\json_encode(["result"=>"started","WID"=>$SentWID,"SID"=>"sessionis..223.4.5.6"]));

                break;
            default:
                $this->_reportErrorAndDie("403","Forbidden");
        }
        error_reporting(E_ALL); 
    }
    private function _extractData($dr){
        $values="";
        $res=[];
        if (is_array($dr)){
            for ($i=0;$i<count($dr);$i++){
                foreach($dr[$i] as $key=>$val){
                    if (strpos($values,$val)===false)
                        $values.=",".$val;
                }
            }
        } else {
            foreach($dr as $key=>$val)
            if (strpos($values,$val)===false)
                $values.=",".$val;
        }

        $vll=explode(",",substr($values,1));
        foreach ($vll as $vl){
            $vl=explode(":",$vl);
            if (count($vl)>0){
                for ($i=2;$i<count($vl);$i++)
                    $vl[1]=$vl[1].":".$vl[$i];
            }
            $vl[1]=preg_replace('~^"?(.*?)"?$~', '$1', $vl[1]);
            if (array_key_exists($vl[0], $res) && $res[$vl[0]]!=$vl[1]){
                $res[$vl[0]]=$res[$vl[0]].",".$vl[1];
            } else {
                $res=array_merge($res,[trim($vl[0])=>trim($vl[1])]);
            }
        }
        return $res;
    }

    private function _reportErrorAndDie($httpErr,$errMsg){
        //header('HTTP/1.0 $httpErr $errMsg');
        die(\json_encode(["error"=>$httpErr,"message"=>$errMsg]));
    }

    private function _getWidZapierVars($wid,$origWid,$userId,$theData){
        $ret=[];
        $sid="";
        $handl= new HandlerNC();
        $res=$handl->getFirstBlock($wid);
        if (isset($res[0]["exec"])){

            $LNG="IT";
            $wSess=new Session(null);
            $IP=General::getCallerIPAddress();
            $UA=General::getCallerUserAgent();
            $wSess->createNew($wid,$IP,$LNG,$UA,$userId,"ZAPIER",$origWid);
            $sid=$wSess->getId();

            //$wSess->loadWflowVars();
            $wwork= new Worker($wSess);
            $frmBid=$wSess->getBlockId();

            $rows=explode("\n",$res[0]["exec"]);
            foreach($rows as $row){
                $row=trim($row);
                if (substr($row,0,5)=="$"."zap_"){
                    $extName=substr($row,5,strpos($row,"=")-5);
                    $intName="$"."zap_".$extName;
                    $wSess->assignVars($intName,isset($theData[$extName])?$theData[$extName]:"");
                    array_push($ret,[$intName=>isset($theData[$extName])?$theData[$extName]:"---"]);
                }
            }

            $hres=$wwork->execNextBlock($frmBid,"",false);
            //$frmBid=$wwork->getBlockId();

        }
        return [$sid,$ret];
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