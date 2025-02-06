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
 * CLASS-NAME:      Jo Mobile SMS provider
 * CREATED DATE:    24.09.2023 - Aldus - Flussu v2.9
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
/**
 * Provides functionality to send SMS messages using the JoMobile API.
 */
class JomobileSms extends AbsSmsSender
{
    public function sendSms($senderName,$toNumber,$textMessage){
        $opts=[
            "token"=>$this->_apiKey,
            "phone"=>$toNumber,
            "senderID"=>trim($senderName),
            "text"=>trim($textMessage),
            "type"=>"sms",
            "lifetime"=>120,
            "delivery"=>"false"
        ];
        $res0=$this->sendRequest("https://api.jomobile.online/send", "GET", $opts);
        $res=explode(":",str_replace(["{","}"],["",""],$res0));
        $res[0]=str_replace('"','',$res[0]);
        $res[1]=str_replace('"','',$res[1]);
        $ret=["status"=>0,"message"=>"error","ticket"=>"","sent"=>0];
        
        if (count($res)>1){
            if ($res[0]=="error"){
                //errore
                if (isset($res[1]))
                    $ret["message"]=$res0;
            } else {
                if (strpos($res[0],$toNumber)!==false || strpos($toNumber,$res[0])!==false){
                    // ha spedito.
                    //"{"status":"1","message":"OK","ticket":"280570602","cost":"1","credits":"179","total":"1","sent":"1","blacklisted":"0","duplicated":"0","invalid":"0","npai":"0"}"
                    $ret["status"]=1;
                    $ret["message"]="OK";
                    $ret["ticket"]=$res[1];
                    $ret["sent"]=1;
                }

            }
        }
        return json_encode($ret);
    }
   
}