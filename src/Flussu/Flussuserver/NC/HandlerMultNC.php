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
 * CLASS-NAME:       FlussuHandlerMultipleWf.class
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
 * This class serves as a central point for handling data for the MultipleWorkflow System
 * 
 */

namespace Flussu\Flussuserver\NC;

class HandlerMultNC extends HandlerBaseNC {

    function newMultiWf($mwid,$wf_id,$user_id,$user_email,$json_data,$assigned_server){
        $SQL="insert into t60_multi_flow(c60_id,c60_workflow_id,c60_user_id,c60_email,c60_json_data,c60_assigned_server) values (?,?,?,?,?,?)";
        return $this->execSql($SQL,array($mwid,$wf_id,$user_id,$user_email,$json_data,$assigned_server));
    }
    function updateMultiWf($mwid,$wf_id,$user_id,$user_email,$json_data,$assigned_server){
        $SQL="update t60_multi_flow set c60_workflow_id=?,c60_user_id=?,c60_email=?,c60_json_data=?,c60_assigned_server=? where c60_id=?";
        return $this->execSql($SQL,array($wf_id,$user_id,$user_email,$json_data,$assigned_server,$mwid));
    }
    function incrementOpenCountMultiWf($mwf_id){
        $mwf_rec=$this->checkUpdateDataMultiWf($mwf_id);
        $cnt=$mwf_rec["open_count"]++;
        $SQL="update t60_multi_flow set c60_open_count=? where c60_id=?";
        return $this->execSql($SQL,array($cnt,$mwf_id));
    }
    function incrementUsedCountMultiWf($mwf_id){
        $mwf_rec=$this->checkUpdateDataMultiWf($mwf_id);
        $cnt=$mwf_rec["used_count"]++;
        $SQL="update t60_multi_flow set c60_used_count=? where c60_id=?";
        return $this->execSql($SQL,array($cnt,$mwf_id));
    }
    function incrementMailCountMultiWf($mwf_id){
        $mwf_rec=$this->checkUpdateDataMultiWf($mwf_id);
        $cnt=$mwf_rec["mail_count"]++;
        $SQL="update t60_multi_flow set c60_mail_count=? where c60_id=?";
        return $this->execSql($SQL,array($cnt,$mwf_id));
    }
    function checkUpdateDataMultiWf($mwf_id){
        $mwf_rec=["mail_count"=>0,"used_count"=>0,"open_count"=>0,"count_summary"=>""];
        $SQL="select * from t60_multi_flow where c60_id=?";
        if ($this->execSql($SQL,array($mwf_id))){
            $res=$this->getData();
            if (is_array($res) && isset($res[0]["c60_id"])){
                $mwf_rec=[
                    "open_count"=>$res[0]["c60_open_count"],
                    "used_count"=>$res[0]["c60_used_count"],
                    "mail_count"=>$res[0]["c60_mail_count"],
                    "count_summary"=>$res[0]["c60_count_summary"]
                ];
            }
        }
        $jcs=json_decode($mwf_rec["count_summary"]);
        $yd=date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")-1, date("Y")));
        if (!isset($jcs[$yd])){
            $jcs[$yd]="o:".$mwf_rec["open_count"].",e:".$mwf_rec["email_count"].",u=:".$mwf_rec["used_count"];
            $SQL="update t60_multi_flow set c60_open_count=0, c60_used_count=0,c60_email_count=0,c60_count_summary=? where c60_id=?";
            $this->execSql($SQL,array(json_encode($jcs,JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG),$mwf_id));
            $mwf_rec=["mail_count"=>0,"used_count"=>0,"open_count"=>0,"count_summary"=>$jcs];
        }
        return $mwf_rec;
    }

    function getMultiWf2($wf_id,$user_id){
        $SQL="select * from t60_multi_flow where c60_workflow_id=? and c60_user_id=?";
        if ($this->execSql($SQL,array($wf_id,$user_id))){
            $res=$this->getData();
            return $this->_getMultiWfData($res);
        }
    }

    function getMultiWf($mwf_id){
        $SQL="select * from t60_multi_flow where c60_id=?";
        if ($this->execSql($SQL,array($mwf_id))){
            $res=$this->getData();
            return $this->_getMultiWfData($res);
        }
        return null;
    }

    private function _getMultiWfData($res){
        if (is_array($res) && isset($res[0]["c60_id"])){
            $ret=[
                "mwf_id"=>$res[0]["c60_id"],
                "wf_id"=>$res[0]["c60_workflow_id"],
                "user_id"=>$res[0]["c60_user_id"],
                "user_email"=>$res[0]["c60_email"],
                "json_data"=>$res[0]["c60_json_data"],
                "assigned_server"=>$res[0]["c60_assigned_server"],
                "date_from"=>$res[0]["c60_date_from"],
                "date_to"=>$res[0]["c60_date_to"],
                "deleted"=>$res[0]["c60_deleted"],
                "open_count"=>$res[0]["c60_open_count"],
                "used_count"=>$res[0]["c60_used_count"],
                "mail_count"=>$res[0]["c60_mail_count"],
                "count_summary"=>$res[0]["c60_count_summary"]
            ];
            return $ret;
        }
        return null;
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