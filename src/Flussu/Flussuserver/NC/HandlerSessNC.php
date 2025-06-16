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

 La classe handler si prende carico di eseguire la maggior
 parte delle operazioni dei dati dei processi nel database:
    - inserimento
    - estrazione
    - cancellazione

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS-NAME:       FlussuHandlerSession.class
 *    EXTENDS:       FlussuHandlerBase.class
 * CLASS PATH:       /Flussu/Flussuserver/NC
 * FOR ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     (04.11.2020) 30.11:2023 - Aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
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

class HandlerSessNC extends HandlerBaseNC {

    public function getActiveSess($sessId){
        $SQL="SELECT W.c10_active as wactive, W.c10_wf_auid as wfauid, S.* FROM t200_worker S inner join t10_workflow W ON W.c10_id=S.c200_wid where S.c200_sess_id=?";
        $this->execSql($SQL,array($sessId));
        return $this->getData();
    }
    public function startSession($WID,$Lang,$stblk,$userId,$hduration,$uu2Bin){
        $SQL="insert into t200_worker (c200_sess_id,c200_wid,c200_lang,c200_thisblock,c200_user,c200_hduration) values (?,?,?,?,?,?)";
        $p_res=$this->execSql($SQL,array($uu2Bin,$WID,$Lang,$stblk,$userId,$hduration));
        $SQL="insert into t205_work_var (c205_sess_id,c205_elm_id) values (?,?)";
        $p_res1=$this->execSql($SQL,array($uu2Bin,'allValues'));
        return $p_res && $p_res1;
    }

    public function addNotify ($dataType,$dataName,$dataValue,$channel,$wid,$sessid,$bidid){
        // Aggiungere una notifica allo stack su database
        $parms=[
            $wid,
            $sessid,
            $bidid,
            $channel,
            json_encode(["type"=>$dataType,"name"=>$dataName,"value"=>$dataValue])
        ];
        $res= $this->execSql("insert into t70_stat (c70_wid,c70_sid,c70_bid,c70_channel,c70_data) values (?,?,?,?,?)",$parms);        
        $parms=[
            $sessid,
            $dataType,$dataName,$dataValue
        ];
        $res= $this->execSql("insert into t203_notifications (c203_sess_id,c203_n_type,c203_n_name,c203_n_value) values (?,?,?,?)",$parms);
        return $res;        
    }   

    public function getNotify($sessId){
        $notyf=[];
        if (!empty($sessId)){
            $SQL="select * from t203_notifications where c203_sess_id=? order by c203_recdate desc";
            $res=$this->execSql($SQL,array($sessId));
            $rows=$this->getData();
            if (is_array($rows) && isset($rows[0])){
                $deleId="";
                $cnt=0;
                foreach ($rows as $row){
                    $notyf[$cnt++]=["id"=>General::getUuidv4(),"type"=>$row["c203_n_type"],"name"=>$row["c203_n_name"],"value"=>$row["c203_n_value"]];
                    $deleId.=$row["c203_notify_id"].",";
                }
                if (!empty($deleId)){
                    $SQL="delete from t203_notifications where c203_notify_id in (".substr($deleId,0,-1).")";
                    $res=$this->execSql($SQL);
                }
            }
        }                           
        return $notyf;
    }

    public function setDurationHours($hours,$sessid){
        // Se $hours==0 azzera la durata.
        if (!empty($hours) && is_numeric($hours) && $hours>=0){
            $SQL="UPDATE t200_worker SET c200_hduration=? where c200_sess_id=?";
            return $this->execSql($SQL,[$hours,$sessid]);
        }
        return false;
    }


    public function getSessionsList($whereClause){
        $SQL="
            SELECT 
                c200_wid as wid, 
                c200_time_start as t_start, 
                c200_time_end as t_end, 
                c200_sess_id as sess, 
                c207_count as h_qty,
                (SELECT c209_row from t209_work_log where c209_sess_id=c200_sess_id limit 1) as sess_start,
                (SELECT GROUP_CONCAT(substring(WL.c209_row,locate('\"',WL.c209_row),locate(' (1)',WL.c209_row)-locate('\"',WL.c209_row))) FROM t209_work_log WL WHERE WL.c209_sess_id=c200_sess_id AND WL.c209_timestamp > ( SELECT WL2.c209_timestamp FROM t209_work_log WL2 WHERE WL2.c209_sess_id=WL.c209_sess_id AND WL2.c209_row like '%exec block code - END' ORDER BY WL2.c209_timestamp limit 1 ) 
                    AND WL.c209_row like 'var assign%' 
                    AND (WL.c209_row NOT like '%wofoEnv->%' AND WL.c209_row NOT like '%=true%' AND WL.c209_row NOT like '%=false%' AND WL.c209_row NOT like '%=\"Ok\"%' AND WL.c209_row NOT like '%=0 %' AND WL.c209_row NOT like '%=1 %' AND WL.c209_row NOT like '%=2 %' AND WL.c209_row NOT like '%=3 %' AND WL.c209_row NOT like '%=\"\"%' AND WL.c209_row NOT like '%=\" \"%')
                    ORDER BY WL.c209_timestamp ASC LIMIT 14) as vars
            FROM 
                t200_worker inner join t207_history on c207_sess_id=c200_sess_id
            WHERE
                c207_count>0 AND c200_wid $whereClause 
            ORDER BY
                c200_wid ASC, c200_time_end desc
            limit 50
        ";
        $res=null;
        if ($this->execSql($SQL)) {
            $res=$this->getData();
        } else {
            //die("ERROR");
            //echo("<h1>ERROR</h1>");

        }
        return $res;
    }


    public function closeSession($theMemSeStat,$arVars,$stat,$history,$workLogs,$subWid,$usessid){
        $transExec= array();
        $dtarr=[];
        if (isset($theMemSeStat->workflowId)){
            $dtarr=[
            "wid"=>$theMemSeStat->wid,
            "lang"=>$theMemSeStat->lang,
            "thisblock"=>$theMemSeStat->blockid,
            "blk_end"=>isset($theMemSeStat->endblock)?$theMemSeStat->endblock:0,
            "time_end"=>str_replace("-","/",$theMemSeStat->enddate),
            "user"=>$theMemSeStat->userid,
            "state_error"=>$theMemSeStat->err,
            "state_usererr"=>$theMemSeStat->usrerr,
            "state_exterr"=>$theMemSeStat->exterr,
            "subs"=>json_encode($subWid),
            "sid"=>$usessid];
            $SQL="UPDATE t200_worker SET c200_wid=:wid,c200_lang=:lang,c200_thisblock=:thisblock".
                ",c200_blk_end=:blk_end,c200_time_end=:time_end,c200_user=:user,c200_state_error=:state_error".
                ",c200_state_usererr=:state_usererr,c200_state_exterr=:state_exterr,c200_subs=:subs ".
                "WHERE c200_sess_id=:sid";
            
            try{
                array_push($transExec,["SQL"=>$SQL,"PRM"=>$dtarr]);
            } catch (\Throwable $e){
                $res=$e->getMessage();
            }
        }
        if (isset($theMemSeStat)){
            $SQL="UPDATE t205_work_var set c205_elm_val=:val where c205_sess_id=:sid and c205_elm_id='allValues'";
            $this->execMultSql(
                $SQL,
                array(["sid"=>$usessid,"val"=>json_encode($arVars)])
            );
        }
        $aret=$this->updateWorklog($workLogs,$transExec);
        $workLogs=$aret[0];
        $transExec=$aret[1];
        if (count($stat)>0){
            $question_marks=[];
            $insert_values = array();
            foreach($stat as $d){
                $question_marks[] = '(?,?,?,?,?,?)';
                $insert_values = array_merge($insert_values, array_values($d));
            }
            $SQL="insert into t70_stat (c70_wid,c70_sid,c70_bid,c70_start,c70_channel,c70_data) values ".implode(',', $question_marks);
            array_push($transExec,["SQL"=>$SQL,"PRM"=>$insert_values]);
        }
        $hist=json_encode($history);
        $SQL="update t207_history set c207_history=?, c207_count=? where c207_sess_id=?";
        array_push($transExec,["SQL"=>$SQL,"PRM"=>array($hist,count(explode("],[",$hist)),$usessid)]);

        $this->transExecs($transExec);
    }

    function updateWorklog($workLogs,$transExec){
        $insert_values = array();
        $question_marks=[];
        foreach($workLogs as $d){
            $question_marks[] = '(?,?,?)';
            $insert_values = array_merge($insert_values, array_values($d));
        }
        $SQL="insert into t209_work_log (c209_sess_id,c209_tpinfo,c209_row) values ".implode(',', $question_marks);
        if (isset($transExec))
            array_push($transExec,["SQL"=>$SQL,"PRM"=>$insert_values]);
        else {
            $this->execSql($SQL,$insert_values);
          //-------------------------
            $workLogs=[];
          //-------------------------
        }
        return [$workLogs,$transExec];
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