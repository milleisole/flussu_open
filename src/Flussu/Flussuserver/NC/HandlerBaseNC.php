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
 * CLASS-NAME:       FlussuHandlerBASE.class
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
 * This class serves as a central point for handling data for each other specialized class
 * 
 */

namespace Flussu\Flussuserver\NC;
use Flussu\General;
use Flussu\Beans\Databroker;

class HandlerBaseNC {

    private $_UBean;
    protected $_cntDone;
    protected $_open_ai;
    public function __construct (){
        $this->_UBean = new Databroker(General::$DEBUG);
    }
    function __clone(){$this->_UBean = clone $this->_UBean;}

    function getError(){
        return $this->_UBean->getError();
    }

    // Data EXEC
    //----------------------
    // Transact OP
    public function prepareMultExecs()                      {return $this->_UBean->prepareMultExecs();}
    public function closeMultExecs()                        {return $this->_UBean->closeMultExecs();}
    public function execMultExecs($SqlString,$SqlARRParams) {return $this->_UBean->execMultExecs($SqlString,$SqlARRParams);}

    public function transExecs($sqlArr){
        return $this->_UBean->transExecs($sqlArr);
    }
    function execSql($SqlCommand,$SqlARRParams=null, $Transactional=false) {
        //if (!is_null($SqlARRParams))
            $this->_UBean->setsearchData($SqlARRParams);
        return $this->_UBean->loadData($SqlCommand, $Transactional);
    }
    function execMultSql($SqlCommand,$SqlARRParams) {
        //if (!is_null($SqlARRParams))
        return $this->_UBean->multDataInsert($SqlCommand,$SqlARRParams);
    }
    // Data EXEC and return LastID
    //----------------------
    function execSqlGetId($SqlCommand,$SqlARRParams=null, $Transactional=false) {
        $this->execSql($SqlCommand,$SqlARRParams, $Transactional);
        return $this->_UBean->getLastId();
    }
    // Data GET
    //----------------------
    function getData()	{
        return $this->_UBean->getfoundRows();
    } 

        // Curtatones
    protected function _beCurtatoned($theId, $text){
        if (isset($text) && trim($text)!="") {
            if (!General::isCurtatoned($text))
                return General::curtatone($theId,$text);
        }
        return $text;
    }
    
    protected function _elmTypeDesc($ctp){
        switch ($ctp){
            case "0":
                return "LABEL";
            case "1":
                return "INPUT";
            case "2":
                return "BUTTON";
            case "3":
                return "MEDIA";
            case "4":
                return "LINK";
            case "5":
                return "VAR_ASSIGN";
            case "6":
                return "SELECTION";
                /*
                if(isset($css["display_info"]["subtype"]) && $css["display_info"]["subtype"]=="")
                    $css["display_info"]["subtype"]="default";
                break;*/
            case "7":
                return "GET_MEDIA";
        }
        return "UNKNOWN!!!";
    }

    /* This function takes a WID parameter ([W76976716897]) 
    and an optional "Session" parameter.
    And returns a wofoId (the ID from database)*/
    static function WID2Wofoid($WID,$theSess=null) {
        $WofoId="";
        $WID=\strtoupper($WID);
        if ($WID!=""){
            if (strlen($WID)<30 && strpos($WID, '[W') === 0 && strpos($WID, ']')===strlen($WID)-1) {
                $WID=substr_replace(substr_replace($WID,"_",strlen($WID)-1,1),"_",0,2);
                $WofoId=General::demouf($WID);
            } else if(strlen($WID)<30 && strpos($WID, '[M.') === 0 && strpos($WID, ']')===strlen($WID)-1){
                // MultiWorkflow
                $mwf=new \Flussu\Controllers\MultiWfController();
                //$mid=str_replace("]","",substr($WID,3));
                $dt=$mwf->getData($WID);
                if (isset($dt["wf_id"])){
                    if (isset($theSess) && is_null($theSess->getVarValue("$"."_multWid"))){
                        $bs="$"."_mult_";
                        $mwf->addOpenCount($WID);
                        $theSess->assignVars($bs."mwid",$WID);
                        $theSess->assignVars($bs."wid",$dt["wf_id"]);
                        $theSess->assignVars($bs."userEmail",$dt["user_email"]);
                        $theSess->assignVars($bs."data",$dt["json_data"]);
                    }
                    return $dt["wf_id"];
                }
            }
            else if (substr($WID,0,1)=="_" && substr($WID,-1)=="_")
                $WofoId=General::demouf($WID);
        }
        return $WofoId;
    }
    /* This function takes a Wofoid (integer) parameter 
    and returns a WID ([W9879879]) code*/
    static function Wofoid2WID($Wofoid){
        $wid=General::camouf($Wofoid);
        $wid=substr_replace(substr_replace($wid,"]",strlen($wid)-1,1),"[w",0,1);
        
        return $wid;
    }

    // Global Values
    //----------------------
    function setGlobalData($sid,$key,$data){
        $data=json_encode($data,JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
        $SQL="insert into t205_work_var (c205_sess_id,c205_elm_id,c205_elm_val) values (?,?,?) ON DUPLICATE KEY UPDATE c205_sess_id=?,c205_elm_id=?,c205_elm_val=?";
        return $this->execSql($SQL,array($sid,$key,$data,$sid,$key,$data));
    }
    function getGlobalData($sid,$key){
        $SQL="select c205_elm_val as data from t205_work_var where c205_sess_id=? and c205_elm_id=?";
        if ($this->execSql($SQL,array($sid,$key))){
            $res=$this->getData();
            if (is_array($res) && isset($res[0]["data"])){
                return json_decode($res[0]["data"]);
            }
        }
        return null;
    }
    function delGlobalData($sid,$key){
        $SQL="delete from t205_work_var where c205_sess_id=? and c205_elm_id=?";
        return $this->execSql($SQL,array($sid,$key));
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

    // CLASS DESTRUCTION
    //----------------------
    public function __destruct(){
        //if (General::$Debug) echo "[Distr Databroker ]<br>";
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