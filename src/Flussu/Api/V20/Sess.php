<?php
/* --------------------------------------------------------------------*
 * Flussu v4.0.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu API Interface
 * UPDATED DATE:     04.08.2022 - Aldus - Flussu v2.2
 *                   SESSION AND NOTIFY DATA
 * UPDATED DATE:     26.10:2024 - Aldus - Flussu v3.0.4
 *                   ASYNC/ON DB NOTIFICATIONS
 * UPDATED DATE:     18.11:2024 - Aldus - Flussu v3.0.6
 *                   solved bugs on notifications
 * -------------------------------------------------------*/

 /**
 * The Sess class is responsible for managing session-related operations within the Flussu server.
 * 
 * This class handles various tasks related to user sessions, including retrieving notifications and managing
 * session data. It interacts with the Session class to perform these operations and ensures that session-related
 * data is correctly processed and returned.
 * 
 * Key responsibilities of the Sess class include:
 * - Retrieving notifications for a given session ID.
 * - Interacting with the Session class to manage session data.
 * - Ensuring that session-related operations are performed securely and efficiently.
 * - Providing utility functions for session management within the Flussu server.
 * 
 * The class is designed to be a central point for managing session-related tasks, ensuring that all session
 * operations are handled correctly and efficiently.
 * 
 * @package App\Flussu\Api\V20
 * @version 3.0.0
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

namespace App\Flussu\Api\V20;

use App\Flussu\Flussuserver\Request;

use App\Flussu\General;
use App\Flussu\Beans;
use App\Flussu\Persons\User;
use App\Flussu\Flussuserver\Command;
use App\Flussu\Flussuserver\Handler;
use App\Flussu\Flussuserver\NC\HandlerNC;
use App\Flussu\Flussuserver\Session;
use App\Flussu\Flussuserver\Statistic;

class Sess {
    /* not more needed
    public function getNotifications($Sid){
        $data=null;
        if (!is_null($Sid) && !empty($Sid)){
            $wSess=new Session($Sid);
            if(!is_null($wSess) && $wSess->getId()==$Sid)
                $data=$wSess->getNotify();
        }
        if (is_array($data) && count($data)>0 && $data[0]["value"]!=null)
            return $data;
        return null;
    }
    */
    public function exec(Request $Req, User $theUser, $funcNum){
        $wSess=null;
        $terms=null;
        //$w3e=new Wofo3Env();
        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');
        
        $sess_id=General::getGetOrPost("SID");
        $wofo_id=General::getGetOrPost("WID");
        $res=["error"=>"wrong startup data"];

        $LNG=General::getGetOrPost("LNG");
        if (empty($LNG))
            $LNG="IT";

        $db= new Handler();
        $dbnc= new HandlerNC();
        $wSess=new Session("");

        $IP=General::getCallerIPAddress();
        $UA=General::getCallerUserAgent();

        //$cwid=\General::demouf($wofo_id);

        if (!isset($sess_id) || empty($sess_id)){
            $res=$dbnc->getUserFlussus($theUser->getId(),1);
            if (isset($wofo_id) && !empty($wofo_id)){
                $wofo_id=substr_replace(substr_replace($wofo_id,"_",strlen($wofo_id)-1,1),"_",0,2);
                $wid=General::demouf($wofo_id);
                $in="=";
                foreach($res as $elm){
                    if ($elm["id"]==$wid){
                        $in.=$wid;
                        break;
                    }
                }
                if ($in=="=")
                    die(json_encode(["ERR"=>"$IP\r\n$UA\r\nerror:800A\r\n   -> You cannot see this workflow data."]));
            } else {
                $in="(";
                foreach($res as $elm)
                    $in.=$elm["id"].",";
                $in="in ".substr($in,0,-1).")";
            }
            $res=$wSess->getSessionsList($in);
            if (is_array($res)){
                foreach ($res as $row => $value){
                    if (array_key_exists("wid",$value) && $value["wid"]>0){
                        $id=$value["wid"];
                        $var=General::camouf($value["wid"]);
                        $var=substr_replace(substr_replace($var,"]",strlen($var)-1,1),"[w",0,1);
                        $res[$row]["wid"]=$var;
                        $res[$row]["sess_start"]=str_replace("[$id/","[",$res[$row]["sess_start"]);
                    }
                }
            }
        } else {
            $wSess=new Session($sess_id);
            $wid=HandlerNC::WID2Wofoid($wSess->getStarterWID(),$wSess);
            //CHECK if $wid is IN -> res=$db->getUserFlofos($theUser->getId(),1);
            $res=$wSess->getHistoryRows(999,"",true);
        }

        $res=json_encode($res);
        die($res);
    }
} 