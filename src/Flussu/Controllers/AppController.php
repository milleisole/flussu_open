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
 * CLASS-NAME:       Flussu APP Controller
 * CREATE DATE:     23.11.2023 - Aldus - Flussu v2.3
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Auth;
use Session;

use Flussu\General;
use Flussu\Flussuserver\NC\HandlerAppNC;

class AppController 
{
    private $_appDataFormat="";
    private $_appLangFormat="";
    private $_hndlr;

    function __construct() {
        // do nothing...
        $this->_appDataFormat=[
            "code"=>"",
            "logo"=>"",
            "name"=>"",
            "lang"=>[],
            "valid_from"=>"1899/12/31 23:59:59",
            "valid_until"=>"2999/12/31 23:59:59"
        ];
        $this->_appLangFormat=[
            "title"=>"",
            "website"=>"",
            "whoweare"=>"",
            "privacy"=>"",
            "menu"=>"",
            "operative"=>""
        ];
        $this->_hndlr=new HandlerAppNC();
    }
    public function getAppList(){
        $ret=["result"=>-1,"status"=>"not yet implemented"];

        return $ret;
    }
    public function getApp($appCode){
        $ret=["result"=>-1,"status"=>"unknown error"];
        $apc=$this->getData($appCode);
        $wofoid=$this->_hndlr->WID2Wofoid($apc["wid"]);
        $ret=$this->_getAppData($wofoid);
        return $ret;
    }
    public function getPublicAppInfo($appCode){
        $res=["result"=>-1,"status"=>"unknown error"];
        $apc=$this->getData($appCode);
        if (str_contains($apc["wid"],"error:")===false){
            $res=[];
            $wofoid=$this->_hndlr->WID2Wofoid($apc["wid"]);
            $ret=$this->_getAppData($wofoid);
            $res["api_code"]=$appCode;
            $res["status"]="ok";
            $res["server"]=$apc["url"];
            $res["system_message"]="";
            $res["wid_code"]=$apc["wid"];
            $res["languages"]=[];
            $res["title"]=[];
            $res["menu_labels"]=[];
            $res["operative_labels"]=[];
            $res["customer"]["logo"]=$ret["logo"];
            $res["customer"]["title"]=$ret["name"];
            $res["customer"]["email"]=$ret["email"];
            foreach ($ret["langs"] as $key=>$lang){
                $res["languages"][$key]=General::getLangName($key);
                $res["title"][$key]=$ret["langs"][$key]["title"];
                $res["menu_labels"][$key]=json_decode($ret["langs"][$key]["menu"]);
                $res["operative_labels"][$key]=json_decode($ret["langs"][$key]["operative"]);
                $res["btn_privacy"][$key]=json_decode($ret["langs"][$key]["btn_privacy"]);
                $res["btn_language"][$key]=json_decode($ret["langs"][$key]["btn_language"]);
                $res["errors"][$key]=json_decode($ret["langs"][$key]["errors"]);

                $res["customer"]["privacy_url"][$key]=$ret["langs"][$key]["privacy"];
                $res["customer"]["website_url"][$key]=$ret["langs"][$key]["website"];
                $res["customer"]["text_whoweare"][$key]=$ret["langs"][$key]["whoweare"];
            }
        }
        return $res;
    }
    public function createAppJ($wid,$appCode,$jsonData){
        $appData=json_decode($jsonData,true);
        return $this->createApp($wid,$appCode,$appData);
    }

    public function createApp($wid,$appCode,$appData){
        $ret=["result"=>-1,"status"=>"unknown error"];
        if (!is_numeric($wid)){
            if (empty($wid))
                $wid=$this->getData($appCode)["wid"];
            $wid=$this->_hndlr->WID2Wofoid($wid);
        }
        if ($wid>0){
            //if (!$this->_appExists($wid)){
                $vFrom="2024/01/01";
                $vUntil="2029/12/31";
                if ($this->_hndlr->recApp($wid,$appData["customer"]["logo"],$appData["customer"]["title"],$appData["customer"]["email"],$vFrom,$vUntil)){
                    foreach ($appData["languages"] as $key=>$data){
                        $lngMenu="";
                        switch(strtoupper($key)){
                            case "IT":
                                $lngMenu="[\"Scegli lingua\",\"Imposta lingua\"]"; break;
                            case "FR":
                                $lngMenu="[\"Choisir la langue\", \"Définir la langue\"]"; break;
                            case "DE":
                                $lngMenu="[\"Sprache auswählen\",\"Sprache festlegen\"]"; break;
                            case "ES":
                                $lngMenu="[\"Elegir idioma\",\"Establecer idioma\"]"; break;
                            default:
                                $lngMenu="[\"Choose a language\",\"Set language\"]"; break;
                        }
                        $ret=$this->_hndlr->recAppLang($wid,$key,
                            $appData["title"][$key],
                            $appData["customer"]["website_url"][$key],
                            $appData["customer"]["text_whoweare"][$key],
                            $appData["customer"]["privacy_url"][$key],
                            json_encode($appData["btn_privacy"][$key]),
                            $lngMenu,
                            json_encode($appData["menu_labels"][$key]),
                            "{\"cache\":\"Delete the APP cache from the phone menu and then re-run the APP, thanks.\"}",
                            json_encode($appData["operative_labels"][$key]),
                            ""
                        );
                    }
                    return true;
                }
                return false;
            //} else
            //    return $this->updateApp($appCode,$appData);
        }
        return $ret;
    }
    public function updateAppJ($appCode,$jsonData){
        return $this->updateApp($appCode,json_decode($jsonData));
    }
    public function updateApp($appCode,$appData){
        $ret=["result"=>-1,"status"=>"unknown error"];
        $apc=$this->getData($appCode);
        $wid=$this->_hndlr->WID2Wofoid($apc["wid"]);
        if ($this->_appExists($wid)){



        } else
            $ret=["result"=>0,"status"=>"App does not exist"];
        return $ret;
    }
    public function deleteApp($appCode){
        $ret=["result"=>-1,"status"=>"unknown error"];
        $apc=$this->getData($appCode);
        $wid=$this->_hndlr->WID2Wofoid($apc["wid"]);
        if ($this->_appExists($wid)){



        } else
            $ret=["result"=>0,"status"=>"App does not exist"];
        return $ret;
    }
    
    
    public function getCode($wid){
        $serverUrl=General::getSitename();
        return $this->getSrvCode($serverUrl,$wid);
    }
    public function getSrvCode($serverUrl,$wid){
        if (is_numeric($wid))
            $wid=$this->_hndlr->Wofoid2WID($wid);
        return $this->_obfus($serverUrl,$wid);
    }
    public function getData($code){
        $d=explode("[W]",$this->_defus($code));
        return ["url"=>$d[1],"wid"=>$d[0]];
    }
    private function _obfus($url,$wid,$ln=3){
        $a=$this->_s2h($wid."[W]".$url);
        $ca = sprintf("%0".($ln*2)."d", $this->_crc($a));
        $ca1=substr($ca,0,$ln);
        $ca2=substr($ca,$ln*-1,$ln);
        $fa=substr($a,0, $ln);
        $fb=substr($a,$ln*-1,$ln);
        $d=substr($a, $ln,strlen($a)-$ln*2);
        return $ca2.$fb.$d.$fa.$ca1;
    }
    private function _defus($value,$ln=3){
        $ret="?[W]error:#800A";
        $ca=intval(substr($value,$ln*-1,$ln).substr($value,0, $ln));
        $a=substr($value, $ln,strlen($value)-($ln*2));
        $fb=substr($a,0, $ln);
        $fa=substr($a,$ln*-1,$ln);
        $a=$fa.substr($a, $ln,strlen($a)-($ln*2)).$fb;
        if ($this->_crc($a)==$ca)
            $ret=$this->_h2s($a);
        return $ret;
    }
    private function _crc($data){
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
        }
        return $crc;
    }
    private function _s2h($string){
        $hex = '';
        for ($i=0; $i < strlen($string); $i++)
            $hex .= dechex(ord($string[$i]));
        return $hex;
    }
    private function _h2s($hex) {
        $string = '';
        for ($i=0; $i < strlen($hex)-1; $i+=2)
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        return $string;
    }
    private function _appExists($wofoid){
        if (is_numeric($wofoid)){
            $row=$this->_hndlr->getApp($wofoid);
            return (is_array($row) && count($row)>0);
        } 
        return false;
    }
    private function _getAppData($wofoid){
        $res=null;
        $app=[];
        if (is_numeric($wofoid)){
            $row=$this->_hndlr->getApp($wofoid);
            $langs=[];
            if (is_array($row) && count($row)>0){
                $app["code"]=$this->_hndlr->Wofoid2WID($row[0]["id"]);
                $app["logo"]=$row[0]["logo"];
                $app["name"]=$row[0]["name"];
                $app["email"]=$row[0]["email"];
                $app["valid_from"]=$row[0]["valid_from"];
                $app["valid_until"]=$row[0]["valid_until"];
                $rows=$this->_hndlr->getApplang($row[0]["id"]);
                foreach ($rows as $row){
                    $lng=[];
                    $lng["lang"]=General::getLangName($row["lang"]);
                    if (!empty($lng["lang"])){
                        $lng["title"]=$row["title"];
                        $lng["website"]=$row["website"];
                        $lng["whoweare"]=$row["whoweare"];
                        $lng["privacy"]=$row["privacy"];
                        $lng["menu"]=$row["menu"];
                        $lng["operative"]=$row["operative"];
                        $lng["btn_privacy"]=$row["btn_privacy"];
                        $lng["btn_language"]=$row["btn_language"];
                        $lng["errors"]=$row["errors"];
                        $lng["openai"]=$row["openai"];
                        $langs[$row["lang"]]=$lng;
                    }
                }
            }
            $res=$app;
            if (count($langs)>0)
                $res["langs"]=$langs;
        }
        return $res;
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