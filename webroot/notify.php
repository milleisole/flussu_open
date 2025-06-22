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
 * CLASS-NAME:       NOTIFIER CLASS
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * NOTIFICATIONS CLASS HANDLER
 * -------------------------------------------------------*/
namespace Flussu\Api;
use Flussu\General;
use Flussu\Flussuserver\NC\HandlerNC;

//require_once '../autoloader.php';
header('Connection: keep-alive');
header('Cache-Control: no-cache');

// must contain a SID
$Sid=General::getGetOrPost("SID");
if (empty($Sid)){
    $ref=substr($_SERVER["HTTP_REFERER"],strpos($_SERVER["HTTP_REFERER"],"?SID="));
    if (!empty($ref)){
        if (substr($ref,0,5)=="?SID="){
            $Sid=trim(substr($ref,5));
        }
    }
}
// and the SID must be a UUID v4 whitout the "-" minus sign
$Sid=str_replace ("-","",$Sid);
if (strlen($Sid)>28 && strlen($Sid)<38 && ctype_xdigit($Sid)){
    header('Content-Type: text/event-stream');
    header('Access-Control-Allow-Origin: https://'.$_SERVER["HTTP_HOST"]);
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
    header('Content-Type: text/event-stream; charset=UTF-8');
    $res = getNotify($Sid);
    if (!is_null($res) && count($res)>0){
        echo "data: ".json_encode($res)."\n\n";
        flush();
        sleep(1);
        die(null);
    } else
        die ("data: {}\n\n");
} else{
    header('HTTP/1.0 403 Forbidden');
    die(\json_encode(["error"=>"403","message"=>"Unauthorized action"]));
}

function getNotify($sessId){
    $hd= new HandlerNC();
    // recuprera le notifiche dallo stack su database e prima di rispondere le cancella.
    $notyf=[];
    $SQL="select * from t203_notifications where c203_sess_id=? order by c203_recdate desc";
    $res=$hd->execSql($SQL,array($sessId));
    $rows=$hd->getData();
    if (is_array($rows) && isset($rows[0])){
        $deleId="";
        $cnt=0;
        foreach ($rows as $row){
            $notyf[$cnt++]=["id"=>General::getUuidv4(),"type"=>$row["c203_n_type"],"name"=>$row["c203_n_name"],"value"=>$row["c203_n_value"]];
            $deleId.=$row["c203_notify_id"].",";
        }
        if (!empty($deleId)){
            $SQL="delete from t203_notifications where c203_notify_id in (".substr($deleId,0,-1).")";
            $res=$hd->execSql($SQL);
        }
    }
    return $notyf;
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