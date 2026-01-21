<?php
/* --------------------------------------------------------------------*
 * Flussu v.5.0 - Mille Isole SRL - Released under Apache License 2.0
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

 La classe handler si prende carico di eseguire la maggior
 parte delle operazioni dei dati dei processi nel database:
    - inserimento
    - estrazione
    - cancellazione

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS-NAME:       FlussuHandlerApplicationWf.class
 *    EXTENDS:       FlussuHandlerBase.class
 * CLASS PATH:       /Flussu/Flussuserver/NC
 * FOR ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     (04.11.2020) 30.11:2023 - Aldus
 * VERSION REL.:     5.0.0.20251103
 * UPDATES DATE:     11.03:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/


/**
 * The Handler classes are responsible for managing database operations.
 * 
 * This class serves as a central point for handling data for the Smartphone APP
 * 
 */

namespace Flussu\Flussuserver\NC;
use \stdClass;
use Flussu\General;

class HandlerAppNC extends HandlerBaseNC {

    public function getApp($wfId){
        $SQL="select c01_wf_id as id, c01_logo as logo, c01_name as name, c01_email as email, c01_validfrom as valid_from, c01_validuntil as valid_until from t01_app where c01_wf_id=?";
        $this->execSql($SQL,array($wfId));
        $res=$this->getData();
        if (isset($res[0]["id"]))
            return $res;
        return null;
    }
    public function getAppLang($wfId){
        $SQL="
            select 
                c05_lang as lang, 
                c05_title as title, 
                c05_website as website, 
                c05_whoweare as whoweare, 
                c05_privacy as privacy, 
                c05_startprivacy as btn_privacy, 
                c05_langstart as btn_language, 
                c05_menu as menu, 
                c05_errors as errors, 
                c05_operative as operative, 
                c05_openai as openai 
            from 
                t05_app_lang 
            where 
                c05_wf_id=?";
        $this->execSql($SQL,array($wfId));
        return $this->getData();
    }

    public function recApp($wfId,$logo,$name,$email,$vFrom,$vUntil){
        $SQL="select c01_wf_id as id, c01_logo as logo, c01_name as name, c01_email as email, c01_validfrom as valid_from, c01_validuntil as valid_until from t01_app where c01_wf_id=?";

        $SQL="
            REPLACE INTO t01_app 
            (c01_wf_id,c01_logo,c01_name,c01_email,c01_validfrom,c01_validuntil)
            values 
            (?,?,?,?,?,?)";
        return  $this->execSql($SQL,array($wfId,$logo,$name,$email,$vFrom,$vUntil));
    }
    public function recAppLang($wfId,$lang,$title,$uri,$whowa,$priv,$stPriv,$stLng,$menu,$err,$oper,$openai){
        $ret=false;
        try{
            $SQL="
                REPLACE INTO t05_app_lang 
                (c05_wf_id,c05_lang,c05_title,c05_website,c05_whoweare,c05_privacy,c05_startprivacy,
                c05_langstart,c05_menu,c05_errors,c05_operative,c05_openai)
                values 
                (?,?,?,?,?,?,?,?,?,?,?,?)";
            return  $this->execSql($SQL,array($wfId,$lang,$title,$uri,$whowa,$priv,$stPriv,$stLng,$menu,$err,$oper,$openai));
        } catch (\Throwable $e){
            $ret=$e->getMessage();
        }
        return $ret;
    }

    private function _handleSvcChanges($which, $was, $is, $wid){
        $tgHost=$_ENV['telegramhost'];
        if ($was==$is)
            return;
        $toDo="UPD";
        if ($was!="" && $is=="")
            $toDo="DEL";
        if ($was=="" && $is!="")
            $toDo="INS";

        $id=$this->WID2Wofoid($wid);
        if (General::isCurtatoned($is))
            $is=General::montanara($is,$id);

        switch ($which){
            case 1:
                // TELEGRAM BOT
                switch ($toDo){
                    case "UPD":
                    case "INS":
                        $theData=new stdClass();
                        $theData->wid=$this->Wofoid2WID($id);
                        $IS=json_decode($is);
                        $theData->botname=$IS->usr;
                        $theData->botkey=$IS->key;
                        $this->callAPI("POST","https://".$tgHost."/botRegister.php?",json_encode($theData));
                }
                break;
        }
    }

    public function getTimedCalls($forceAll=false){
        $SQL="
            select 
                c100_seq as seq, 
                c100_sess_id as sid, 
                c100_wid as wid,
                c100_block_id as bid,
                c100_start_date as s_date, 
                c100_minutes as e_min, 
                c100_send_data as e_data
            from 
                t100_timed_call 
            where 
                c100_enabled=1
        ";
        if ($forceAll)
            $SQL2=$SQL;
        else
            $SQL2=$SQL." and DATE_ADD(c100_start_date, INTERVAL c100_minutes MINUTE) > DATE_ADD(current_timestamp, INTERVAL -30 MINUTE)";
        $ret=false;
        if ($this->execSql($SQL2)){
            $ret=$this->getData();
            if (is_null($ret) || !is_array($ret) || (is_array($ret) && count($ret)<1)){
                if ($this->execSql($SQL))
                    return $this->getData();
            }
        }
        return $ret;
    }
    public function disableTimedCall($seq,$result=""){
        $SQL="update t100_timed_call set c100_enabled=0, c100_call_result=?, c100_call_date=? where c100_seq=?";
        return $this->execSql($SQL,[$result,date("Y/m/d H:i:s"),$seq]);
    }
    public function updateTimedCall($seq,$result){
        return $this->disableTimedCall($seq,$result);
    }
    public function createTimedCall($intWid,$sess_id,$block_id,$data,$minutes){
        if (is_null($block_id))
            $block_id="";
        if (is_null($data))
            $data="";
        if ($minutes>0 && $intWid>0){
            $SQL="
                insert into t100_timed_call 
                    (c100_sess_id, c100_send_data, c100_minutes, c100_wid, c100_block_id, c100_enabled) 
                    values (?,?,?,?,?,?)";
            return $this->execSql($SQL,[$sess_id,$data,$minutes,$intWid,$block_id,1]);
        }
        return false;
    }

    function callAPI($method, $url, $data){
        $method=strtoupper($method);
        $curl = curl_init();
        switch ($method){
           case "POST":
              curl_setopt($curl, CURLOPT_POST, 1);
              if ($data)
                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              break;
           case "PUT":
              curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
              if ($data)
                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
              break;
           default:
              if ($data)
                 $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
           'APIKEY: A7D0-FEDE-'.date('Ymd'),
           'Content-Type: application/flussu',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // EXECUTE:
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
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