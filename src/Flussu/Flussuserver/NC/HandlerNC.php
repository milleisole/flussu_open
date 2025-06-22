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

 La classe handler si prende carico di eseguire la maggior
 parte delle operazioni dei dati dei processi nel database:
    - inserimento
    - estrazione
    - cancellazione

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS-NAME:       FlussuHandlerNonCached.class
 * CLASS PATH:       /Flussu/Flussuserver
 * FOR ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     (04.11.2020) 25.01:2021 - Aldus
 * VERSION REL.:     4.4.20250621
 * UPDATES DATE:     22.06:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * Added error handling for exec scripts
 * -------------------------------------------------------*/

/**
 * The Handler class is responsible for managing various operations and interactions within the Flussu server.
 * 
 * This class serves as a central point for handling different types of requests and operations, including
 * database interactions, API calls, and other server-side tasks. It provides methods for executing commands,
 * managing sessions, and processing data.
 * 
 * Key responsibilities of the Handler class include:
 * - Managing database connections and executing queries.
 * - Handling API requests and responses.
 * - Managing user sessions and authentication.
 * - Processing and validating input data.
 * - Executing commands and managing their lifecycle.
 * - Logging activities and errors for monitoring and debugging purposes.
 * 
 * The class is designed to be flexible and extendable, allowing for the addition of new functionalities and
 * operations as needed. It ensures that all operations are executed in a controlled and efficient manner,
 * maintaining the stability and reliability of the Flussu server.
 * 
 */

namespace Flussu\Flussuserver\NC;
use Flussu\General;
use Flussu\Persons;
use Flussu\Flussuserver\NC\HandlerBaseNC;

use stdClass;

class HandlerNC extends HandlerBaseNC {

    // Flofo GET name and first block id
    //----------------------
    function getFlussuName($WID){
        if (!is_numeric($WID))
            $WID=self::WID2Wofoid($WID);
        $this->execSql("select c10_name name from t10_workflow where c10_id=?",array($WID));
        return $this->getData()[0]["name"];
    }
    function getFlussuNameFirstBlock($wofoId){
        $this->execSql("select c10_name name, c20_uuid as start_blk, c20_id as bid, c10_active as active from t10_workflow inner join t20_block on c20_flofoid=c10_id where c10_id=? and c20_start=1",array($wofoId));
        return $this->getData();
    }
    function getFlussuNameDefLangs($wofoId){
        $this->execSql("select c10_name name, c10_supp_langs as supp_langs, c10_def_lang as def_lang from t10_workflow where c10_id=?",array($wofoId));
        return $this->getData();
    }
    function getSuppLang($wofoId){
        $this->execSql("select c10_supp_langs as supp_langs from t10_workflow where c10_id=?",array($wofoId));
        return $this->getData();
    }
    /**
     * It updates the `c10_supp_langs` column of the `t10_workflow` table with the value of
     * `` where the `c10_id` column is equal to ``
     * 
     * Args:
     *   wofoId: The workflow id
     *   suppLangs: a comma-separated list of language codes
     * 
     * Returns:
     *   The data from the query.
     */
    function updateSuppLang($wofoId,$suppLangs){
        $this->execSql("update t10_workflow set c10_supp_langs=? where c10_id=?",array($suppLangs,$wofoId));
        return $this->getData();
    }
    

    // Flofo GET
    //----------------------
    function getOwnFlussuList($forUserId){
        $rSet=$this->getUserFlussus($forUserId,1);
        $elems=[];
        foreach ($rSet as $row => $value)
            $elems[$rSet[$row]["wid"]]=$rSet[$row]["name"];
        return $elems;
    }

    /*
    The primary purpose of this function is to retrieve and process a list of workflows for a specific user, 
    ensuring that the workflow IDs are camouflaged and formatted appropriately before being returned.*/
    function getOwnProjFlofoList($forUserId){
        $SQL="SELECT distinct  wf_id, prj_name, wf_name,W.c10_active as prj_is_active FROM v20_prj_wf_all left join t10_workflow W on wf_id=W.c10_id where wf_id is not null and wf_user=? order by prj_name,wf_name";
        $this->execSql($SQL,$forUserId);
        $rSet=$this->getData();
        $elems=[];
        if (is_array($rSet)){
            foreach ($rSet as $row => $value){
                $var=General::camouf($value["wf_id"]);
                $var=substr_replace(substr_replace($var,"]",strlen($var)-1,1),"[w",0,1);
                $rSet[$row]["wf_id"]=$var;
            }
        }
        
        return $rSet;
    }

    /*
    The primary purpose of this function is to provide a flexible way to retrieve and process workflow 
    information for a specific user, with different levels of detail based on the whichUse parameter.
    E=for Editing
    */
    function getUserFlussus($forUserid,$whichUse=0){
        $from="
        FROM 
            v10_wf_prj WFP join t10_workflow WF on WFP.wf_id=WF.c10_id
            left outer join t83_project PR on WFP.prj_id=PR.c83_id
            left outer join t87_prj_user PU on PR.c83_id=PU.c87_prj_id
        WHERE
            WF.c10_userid=? OR PU.c87_usr_id=?
        ";
        $params=[$forUserid,$forUserid]; 
        switch ($whichUse){
            case "0":

                $Fields="v2.wf_id as wid, 
                t1.c10_wf_auid as wfauid,
                v2.wf_user as user_id, 
                v2.prj_name as proj, 
                v2.wf_name as name, 
                t1.c10_description as description, 
                t1.c10_active as is_active, 
                t1.c10_supp_langs as supp_lang, 
                t1.c10_def_lang as def_lang,
                t1.c10_validfrom as valid_from,  
                t1.c10_validuntil as valid_until, 
                t1.c10_modified as last_mod 
                ";
                $from="FROM v20_prj_wf_all v2 
                inner join t10_workflow t1 
                ON t1.c10_id=v2.wf_id  
                WHERE v2.wf_user=? /* AND t1.c10_active>0 */";
                $Order="order by prj_name,wf_name";
                $params=[$forUserid]; 
                break;
            case "E":
                $Fields="WF.c10_id as wid, WF.c10_name as name";
                $Order="order by WF.c10_name";
                break;
            default:
                $Fields="WF.c10_id as id";
                $Order="order by WF.c10_id";
                break;
        }
        
        $SQL="
        SELECT DISTINCT
        $Fields
        $from
        $Order
        ";

        $this->execSql($SQL,$params);
        $rSet=$this->getData();
        if (is_array($rSet)){
            foreach ($rSet as $row => $value){
                if (array_key_exists("wid",$value) && $value["wid"]>0){
                    $var=General::camouf($value["wid"]);
                    $var=substr_replace(substr_replace($var,"]",strlen($var)-1,1),"[w",0,1);
                    $rSet[$row]["wid"]=$var;
                }
            }
        }
        return $rSet;
    }

    function getFlussuWID($wid_identifier_any){
        $wid=0;
        $WID="UNKNOWN ERROR! ".$wid_identifier_any." malformed!";
        if (!is_numeric($wid_identifier_any)){
            // bug solved
            if (strlen($wid_identifier_any)<20 && (stripos($wid_identifier_any,"_")===0 || stripos($wid_identifier_any,"[w")===0))
            {
                // è un WID
                $check=self::WID2Wofoid($wid_identifier_any);
                if (is_numeric($check)){
                    $wid=$check;
                    $WID=$wid_identifier_any;
                } else {
                    // ERRORE!
                    $wid=-1;
                    $WID="ERROR! ".$wid_identifier_any." is not a valid WID or not found!";
                }
            } else {
                // è un UUID
                $wauid=str_replace(["{","}"],["",""],$wid_identifier_any);
                $SQL="select c10_id from t10_workflow where c10_wf_auid=?";
                $chk=$this->execSql($SQL,array($wauid));
                $dres=$this->getData();
                if (count($dres)>0){
                    $wid= $dres[0]["c10_id"];
                    $WID=self::Wofoid2WID($wid);
                } else {
                    // ERRORE!
                    $wid=-1;
                    $WID="ERROR! ".$wid_identifier_any." id not a valid WFAUID or not found!";
                }
            }
        } else {
            $wid=$wid_identifier_any;
            $WID=self::Wofoid2WID($wid);
        }
        return ["wid"=>$wid,"WID"=>$WID];
    }
    function getFlussu($getJustFlowExec, $forUserid, $wofoId=0, $allElements=false){
        return $this->_getFlussu($getJustFlowExec, $forUserid, $wofoId,"", $allElements);
    }

    function getFlussuByUuid($getJustFlowExec, $forUserid, $wfAUId="", $allElements=false){
        return $this->_getFlussu($getJustFlowExec, $forUserid, -1,$wfAUId, $allElements);
    }

    /*
    The purpose of the getFlofo function is to retrieve workflow information from a database based on various parameters such as user ID, 
    workflow ID, and whether to include all elements or just active ones. 
    It constructs and executes an SQL query, processes the result set, and returns the processed data.
    */
    private function _getFlussu($getJustFlowExec, $forUserid, $wofoId=0, $wfAUId="",$allElements=false){
        $params=null;
        $SQL="select c10_id as wid, c10_wf_auid as wfauid, c10_name as name, c10_description as description, c10_userid as userId ";
        if (!$allElements) {
            $params=array($forUserid); 
            //$SQL.=",c10_userid as userId";
        }
        if (isset($getJustFlowExec) && !$getJustFlowExec){
            $SQL.=",c10_active as is_active, c10_supp_langs as supp_langs, c10_def_lang as lang, c10_validfrom as valid_from, c10_validuntil as valid_until, c10_modified as last_mod
                  ,c10_svc1 as svc1, c10_svc2 as svc2, c10_svc3 as svc3, ifnull(AP.c01_wf_id,0) as app_id,
                  WP.prj_id, ifnull(P.c83_desc,'') as prj_desc, '' as usr_list
              FROM 
                  t10_workflow right join v10_wf_prj WP on t10_workflow.c10_id=WP.wf_id
                  left outer join t83_project P on P.c83_id=WP.prj_id 
                  left join t01_app AP on AP.c01_wf_id=WP.wf_id
              where 1=1  ";
        } else 
            $SQL.=" from t10_workflow where 1=1 ";

        if ($wofoId>0){
            $params=array($wofoId); 
            $SQL=str_replace("where 1=1"," where c10_id=? ",$SQL);
        } else if ($wfAUId!=""){
            $params=array($wfAUId); 
            $SQL=str_replace("where 1=1"," where c10_wf_auid=? ",$SQL);
        } else {
            $params=array($forUserid); 
            $SQL=str_replace("where 1=1"," where (c10_userid=0 OR c10_userid=?) ",$SQL);
        }
        if ($getJustFlowExec)
            $SQL.=" and c10_active>0 and (UTC_TIMESTAMP() <= c10_validuntil and UTC_TIMESTAMP() >= c10_validfrom) ";

        $SQL.=" order by c10_name";

        if ($params!=null)
            $chk=$this->execSql($SQL,$params);
        else
            $chk=$this->execSql($SQL);

        $rSet=$this->getData();

        if ($getJustFlowExec) {
            if (is_array($rSet)){
                foreach ($rSet as $row => $value){
                    if (isset($value["userId"]) && $value["userId"]>0)
                        $rSet[$row]["userId"]=General::camouf($value["userId"]);
                }
            }
        }
        if (is_array($rSet)){
            if (count($rSet)==1)
                $rSet[0]["usr_list"]=$this->getFlofoProjectUsersList($rSet[0]["userId"],$rSet[0]["wid"]);
            foreach ($rSet as $row => $value){
                if (array_key_exists("wid",$value) && $value["wid"]>0){
                    $var=General::camouf($value["wid"]);
                    $var=substr_replace(substr_replace($var,"]",strlen($var)-1,1),"[w",0,1);
                    $rSet[$row]["wid"]=$var;
                }
                $wid=intval($value["wid"]);
                if (array_key_exists("svc1",$value))
                    $rSet[$row]["svc1"]=General::montanara($rSet[$row]["svc1"],$wid);
                if (array_key_exists("svc2",$value))
                    $rSet[$row]["svc2"]=General::montanara($rSet[$row]["svc2"],$wid);
                if (array_key_exists("svc3",$value))
                    $rSet[$row]["svc3"]=General::montanara($rSet[$row]["svc3"],$wid);
            }
        }
        return $rSet;
    }

/*
    Retrieves a specific workflow block by its UUID. If the workflow ID is not provided, it first fetches 
    the workflow ID associated with the block UUID, then calls getFlofoBlocks to get the block details.
*/
    function getFlussuBlock($getJustFlowExec,$wofoId,$blockUuid){
        if (empty($wofoId) || intval($wofoId)<1){
            $SQL="select c20_flofoid from t20_block where c20_uuid=?";
            $this->execSql($SQL,array($blockUuid));
            $wofoId= intval($this->getData()[0]["c20_flofoid"]);
        }
        return $this->getFlussuBlocks($getJustFlowExec,$wofoId,false,false,$blockUuid);
    }

/*
    Retrieves the first block of a workflow by its ID. It checks if the first block is active and then calls
    getFlofoBlock to get the block details.
*/
    function getFirstBlock($wofoId){
        $res=$this->getFlussuNameFirstBlock($wofoId);
        if ($res!= null && is_array($res) && count($res)==1 && $res[0]["active"]==true){
            $wname=$res[0]["name"];
            $stblk=$res[0]["start_blk"];
            $bid=intval($res[0]["bid"]);
            return $this->getFlussuBlock(true,$wofoId,$stblk);
        }
        return null;
    }

/*
    Retrieves a list of blocks for a given workflow ID. It constructs an SQL query based on various parameters and returns the block details.
*/
    function getFlussuBlocks($getJustFlowExec,$wofoId,$withoutStartValue=false,$forEditingPurp=false,$blockUuid=""){
        $SQL2="";
        $SQL="select c20_uuid as block_id, ifnull(c20_type,'') as type, c20_exec as exec, ";
        if ($blockUuid<>""){
            $SQL2=" c20_uuid=? and ";
            $params=array($blockUuid,$wofoId); 
        } else 
            $params=array($wofoId); 
        if (!$getJustFlowExec){
            if (!$withoutStartValue)
                $SQL.="c20_start as is_start,";
            if ($forEditingPurp)
                $SQL.="c20_note as note,";
            $SQL.="ifnull(c20_desc,'') as description, c20_xpos as x_pos, c20_ypos as y_pos, c20_modified as last_mod ". 
                "from t20_block where ".$SQL2.
                "c20_flofoid=? order by c20_start desc";
        } else {
            $SQL.="ifnull(c20_desc,'') as description, c20_start as is_start ". 
                "from t20_block where ".$SQL2.
                "c20_flofoid=? order by c20_start desc ";
        }
        $this->execSql($SQL,$params);
        return $this->getData();
    }

/*
    Retrieves the exits for a specific block by its UUID. It constructs an SQL query to get the exit 
    directions and other details, then returns the result.
*/
    function getFlofoBlockExits($block_uuid,$allData){
        $params=array($block_uuid); 
        $SQL="SELECT ";
        if ($allData)
            $SQL.="b.c20_id as block_id, b.c20_uuid as block_uuid,a.c25_nexit as exit_num,";
        $SQL.="ifnull((select c.c20_uuid from t20_block c where c.c20_id=a.c25_direction),0) as exit_dir ".
             "FROM t25_blockexit a join t20_block b on a.c25_blockid=b.c20_id where b.c20_uuid=? order by a.c25_nexit";
        $this->execSql($SQL,$params);
        return $this->getData();
    }
/*
    Retrieves a list of elements within a specific block by its UUID. It constructs an SQL query based on 
    various parameters and processes the result set to include additional details like CSS and element types.
*/

    private function _getFlofoElementSql($getJustFlowExec,$witoutBlockId=false,$forEditingPurp=false,$calledJoin=false){
        $SELECT="be.c30_uuid as elem_id, ";
        if (!$witoutBlockId)
            $SELECT.="b1.c20_uuid as block_id, ";
        
        //if (!$getJustFlowExec){
            $SELECT.="be.c30_varname as var_name, ".
            "be.c30_order as e_order, ifnull(be.c30_note,'') as note,'' as d_type, be.c30_css as css ";
       // } else {
       //     $SELECT.="be.c30_varname as var_name, be.c30_order as e_order ";
       // }
        if ($forEditingPurp)
            $SELECT.=",be.c30_note as note ";
        $SELECT.=",be.c30_type as c_type, ifnull(be.c30_exit_num,'') as exit_num ";
        $FROM=($calledJoin?"":" t20_block b1 inner join ")." t30_blk_elm be on b1.c20_id=be.c30_blockid ";
        $WHERE="b1.c20_uuid=".($calledJoin?"[]joinHere[]":"?"); 
        $OB="be.c30_order";
        return [$SELECT,$FROM,$WHERE,$OB];
    }

    function getFlofoElementList($getJustFlowExec,$blkId,$witoutBlockId=false,$forEditingPurp=false){
        $params=array($blkId); 
        $PSQL=$this->_getFlofoElementSql($getJustFlowExec,$witoutBlockId,$forEditingPurp);
        $SQL="select ".$PSQL[0]." from ".$PSQL[1]." where ".$PSQL[2]." order by ".$PSQL[3];
        $this->execSql($SQL,$params);
        $rSet=$this->getData();
        if (!$getJustFlowExec){
            if (is_array($rSet)){
                foreach ($rSet as $row => $value){
                    //$rSet[$row]["_var_name"]=empty($value["var_name"])?"":substr($value["var_name"],1);
                    $css=json_decode($value["css"],true);
                    $vcs=trim(str_replace("\"","",$value["css"]));
                    if (empty($css) || !is_array($css)){
                        $css=[];
                        $css["display_info"]=["mandatory"=>false,"subtype"=>""];
                        if (strtolower(trim($vcs))=="textarea"){
                            $css["display_info"]["subtype"]="Textarea";
                            $css["class"]="";
                        }
                        else
                            $css["class"]=$vcs;
                    }
                    $ctp=$value["c_type"];
                    $value["d_type"]=$this->_elmTypeDesc($ctp);
                }
            }
        }
        return $rSet;
    }

    function getElemVarNameForExitNum($blockUuid,$exitNum,$lang)
    {
        $SQL="SELECT c30_varname as res, c30_elemid as elid, ifnull(c40_text,'') as txt FROM t30_blk_elm inner join t20_block on c20_id=c30_blockid right join t40_element on c40_id=c30_elemid and c40_lang=? WHERE c20_uuid=? and c30_exit_num=? and c30_type=2";
        //$SQL="SELECT c30_varname as res, c30_elemid as elid FROM `t30_blk_elm` inner join t20_block on c20_id=c30_blockid WHERE c20_uuid=? and c30_exit_num=? and c30_type=2"; 
        $this->execSql($SQL,[$lang,$blockUuid,$exitNum]);
        $res=$this->getData();
/*
        if (is_array($res) && isset($res[0]["res"])){
            $ret=trim($res[0]["res"]);
            if (!empty($ret)){
                $elid=trim($res[0]["elid"]);
                $SQL="SELECT c40_text as txt FROM t40_element WHERE c40_id=? and c40_lang=?"; 
                $this->execSql($SQL,[$elid,$lang]);
                $res=$this->getData();
                if (is_array($res) && isset($res[0]["txt"]))
                    return [$ret,($res[0]["txt"])];
            }
        }
*/
        return null;
    }
    function getElemVarValueForExitNum($blockUuid,$exitNum){
        $SQL="SELECT c30_varname as res FROM `t30_blk_elm` inner join t20_block on c20_id=c30_blockid WHERE c20_uuid=? and c30_exit_num=? and c30_type=2"; 
        $this->execSql($SQL,[$blockUuid,$exitNum]);
        $res=$this->getData();
        if (is_array($res) && isset($res[0]["res"])){
            return $res[0]["res"];
        }
        return "";
    }

    function getFlofoProjectUsersList($userId,$wid){
        if ($this->_hasUserARoleOnWorkflow($wid,$userId)){
            $SQL="
                SELECT 
                    concat(U1.c80_name,' ',U1.c80_surname) as user_name, 'prop' as user_role
                FROM
                    t80_user U1 right join t10_workflow W1 on W1.c10_userid = U1.c80_id
                WHERE
                    W1.c10_id=?
                UNION ALL
                SELECT 
                    concat(U.c80_name,' ',U.c80_surname) as user_name, 'user' as user_role
                FROM 
                    v10_wf_prj WP right join t87_prj_user PU on WP.prj_id=PU.c87_prj_id
                    left outer join t80_user U on PU.c87_usr_id=U.c80_id
                WHERE 
                    WP.wf_id=? and U.c80_id<>(
                        SELECT U2.c80_id
                        FROM t80_user U2 right join t10_workflow W2 on W2.c10_userid = U2.c80_id
                        WHERE W2.c10_id=?
                    )
            ";
            $parm=array($wid,$wid,$wid); 
            $this->execSql($SQL,$parm);
            return $this->getData();
        }
        return null;
    }
    
    function getFlofoProjectSGD($userId,$wid){
        if ($this->_hasUserARoleOnWorkflow($wid,$userId)){
            

        }
        return "function TBD";
    }

    private function _hasUserARoleOnWorkflow($wid,$uid){
        $SQL="SELECT distinct wf_user FROM v20_prj_wf_all where wf_id=? and wf_user=?";
        $this->execSql($SQL,[$wid,$uid]);
        return (is_array($this->getData()[0]));
    }

    function getFlofoBackupList($userId,$wid){
        if ($this->_hasUserARoleOnWorkflow($wid,$userId)){
            $SQL="SELECT c15_rec_date as bak_Date,c15_backup_id as bak_Id 
                  FROM t15_workflow_backup
                  WHERE c15_workflow_id=? ORDER BY c15_rec_date DESC";
            $this->execSql($SQL,[$wid]);
            return $this->getData();
        }
        return null;
    }

    function getFlofoBackup($userId,$wid,$bkId){
        if ($this->_hasUserARoleOnWorkflow($wid,$userId)){
            $SQL="SELECT c15_workflow_json as backup 
                  FROM t15_workflow_backup
                  WHERE c15_workflow_id=? and c15_backup_id=?";
            $this->execSql($SQL,[$wid,$bkId]);
            return $this->getData()[0]["backup"];
        }
        return "{}";
    }

    function getUserProjects($userId,$prjId=null){
        $prjSel="";
        if (!is_null($prjId))
            $prjSel=" AND P.c83_id=?";
        $SQL="
            SELECT 
                P.c83_id as prj_id,
                P.c83_desc as prj_desc
            FROM 
                t87_prj_user PU right join t83_project P on PU.c87_prj_id=P.c83_id
            WHERE
                PU.c87_usr_id=? $prjSel
            Order by 2
        ";
        if (!is_null($prjId))
            $this->execSql($SQL,[$userId,$prjId]);
        else
            $this->execSql($SQL,[$userId]);
        return $this->getData();
    }

    function getProjects(){
        $SQL="
            SELECT 
                c83_id as prj_id,
                c83_desc as prj_desc,
                c83_note as prj_note
            FROM 
                t83_project
            Order by 2
        ";
        $this->execSql($SQL);
        return $this->getData();
    }

    function isProjectOnUser($prjId,$userId){
        $chk=$this->getUserProjects($userId,$prjId);
        return (is_array($chk) && count($chk)>0);
    }

    function updateProject($pid,$desc,$note=null){
        $noteUpd="";
        if (!is_null($note))
            $noteUpd=", c83_note=?";
        $SQL="
            UPDATE t83_project
            SET c83_desc=? $noteUpd
            WHERE c83_id=?
        ";
        if (!is_null($note))
            $this->execSql($SQL,[$desc,$note,$pid]);
        else
            $this->execSql($SQL,[$desc,$pid]);
    }

    function createProject($desc,$note,$wid){
        // create a new project and put inside the wid and the wid proprietor
        $SQL="INSERT INTO t83_project (c83_desc,c83_note) VALUES (?,?)";
        $pid=$this->execSqlGetId($SQL,[$desc,$note]);
        if ($pid>0){
            $SQL="INSERT INTO t85_prj_wflow (c85_prj_id,c85_flofoid) VALUES (?,?)";
            if ($this->execSql($SQL,[$pid,$wid])){
                $SQL="INSERT INTO t87_prj_user (c87_prj_id,c87_usr_id) VALUES (?,(select c10_userid from t10_workflow where c10_id=?))";
                return $this->execSql($SQL,[$pid,$wid]);
            }
        }
        return false;
    }
    
    function getWorkflowSProjectId($wid){
        $SQL="select prj_id from v10_wf_prj where wf_id=?";
        $this->execSql($SQL,[$wid]);
        $res=$this->getData();
        if (is_array($res) && count($res)==1 && intval($res[0]["prj_id"])>0)
            return intval($res[0]["prj_id"]);
        return 0;
    }

    function changeWorkflowProprietor($userEmail,$wid){
        if (General::isEmailAddress($userEmail) && intval($wid)>0){
            $usrId=$this->_getUseridFromEmail($userEmail);
            if($usrId>0){
                $pid=$this->getWorkflowSProjectId($wid);
                if ($pid>0){
                    if ($this->delUserByIdFromProject($usrId,$pid)){
                        $SQL="UPDATE t10_workflow set c10_userid=? where c10_id=?";
                        return $this->execSql($SQL,[$usrId,$wid]);
                    }
                    return false;
                }
            }
        }
        return false;
    }

    function moveWorkflowToProject($toPID, $wid){
        $singleRes=false;
        // cancella workflow da qualsiasi altro progetto
        $SQL="DELETE FROM t85_prj_wflow WHERE c85_flofoid=?";
        $this->execSql($SQL,[$wid]);
        $singleRes=true;
        if ($toPID>0) {
            // oppure inseriscilo
            $SQL="INSERT INTO t85_prj_wflow (c85_prj_id,c85_flofoid) values (?,?)";
            $this->execSql($SQL,[$toPID,$wid]);
            $singleRes=true;
        }
        // verifica se il progetto originale e' rimasto vedovo
        $this->checkEmptyProject();
        return $singleRes;
    }

    function checkEmptyProject(){
        // verifico se un  progetto e' rimasto senza workflow
        // in questo caso va eliminato il progetto e gli utenti assegnati
        $SQL="SELECT c83_id as pid FROM t83_project WHERE c83_id not in (select c85_prj_id from t85_prj_wflow)"; 
        $this->execSql($SQL);
        $res=$this->getData();
        if (is_array($res) && is_array($res[0]) && intval($res[0][0])!=0){
            $prid=intval($res[0][0]);
            $SQL="DELETE FROM t87_prj_user WHERE c87_prj_id=?";
            $this->execSql($SQL,[$prid]);
            $SQL="DELETE FROM t83_project WHERE c83_id=?";
            $this->execSql($SQL,[$prid]);
        }
    }

    function isUserTheWorkflowMaster($uid,$wid){
        $SQL="select c10_userid from t10_workflow where c10_id=?";
        $this->execSql($SQL,[$wid]);
        $res=$this->getData();
        return (is_array($res) && count($res)==1 && intval($res[0]["c10_userid"])==intval($uid));
    }

    private function _getUseridFromEmail($userEmail){
        $Usr= new Persons\User();
        $ret=$Usr->emailExist($userEmail);
        $usrId=0;
        if ($ret[0])
            $usrId=intval($ret[1]);
        return $usrId;
    }

    function addUserByEmailToProject($userEmail,$projId){
        //QUI ADESSO
        if (General::isEmailAddress($userEmail) && intval($projId)>0){
            $usrId=$this->_getUseridFromEmail($userEmail);
            if($usrId>0 && !$this->isProjectOnUser($projId,$usrId)){
                $SQL="INSERT INTO t87_prj_user (c87_prj_id,c87_usr_id) VALUES (?,?)";
                return $this->execSql($SQL,[$projId,$usrId]);
            }
        }
        return false;
    }

    function delUserByIdFromProject($usrId,$projId){
        $SQL2="DELETE FROM t87_prj_user WHERE c87_prj_id=? AND c87_usr_id=?";
        return $this->execSql($SQL2,[$projId,$usrId]);
    }

    function delUserByNameFromProject($userName,$projId){
        //QUI ADESSO
        if (intval($projId)>0){
            $SQL="
                SELECT 
                    U.c80_id as uid, concat(U.c80_name,' ',U.c80_surname) usrname 
                FROM 
                    t87_prj_user P left join t80_user U on P.c87_usr_id=U.c80_id 
                WHERE 
                    P.c87_prj_id=?
            ";
            $this->execSql($SQL,[$projId]);
            $res=$this->getData();
            if (is_array($res)){
                foreach ($res as $row){
                    if ($row["usrname"] == $userName){
                        $ret=$this->delUserByIdFromProject($row["uid"],$projId);
                        if (!$ret) return $ret;
                    }
                }
                return true;
            }
        }
        return false;
    }

    function translateLabels($from, $tolng, $toLang, $wofoid, $sessTransId){
        $this->_open_ai = new \Flussu\Controllers\OpenAiController();
        //$trCmd="Translate to ".$toLang.":";

        //Check existing languages
        // START LANG c'è? -> ok partenza
        // END LANG c'è? ->No=add lang (meglio cancellare l'esistente?)
        $from_Exist=false;
        $to_Exist=false;
        $params=array($wofoid); 
        $SQL0="distinct el.c40_lang as LNG";
        $SQL1="from t40_element el inner join t30_blk_elm be on el.c40_id=be.c30_elemid inner join t20_block bl on bl.c20_id=be.c30_blockid where c20_flofoid=?";
        $SQL2="select count (*) ".$SQL1;
        $SQL3="select ".$SQL0." ".$SQL1;

/*
        $this->execSql($SQL2,$params);
        $cnt= $this->getData();
        \General::setSession($sessTransId,[$cnt,0]);
*/

        $this->execSql($SQL3,$params);
        $res= $this->getData();
        foreach ($res as $rec){
            if (trim(strtoupper($rec["LNG"]))==trim(strtoupper($from))){
                $from_Exist=true;
                if ($to_Exist) break;
            }
            if (trim(strtoupper($rec["LNG"]))==trim(strtoupper($tolng))){
                $to_Exist=true;
                if ($from_Exist) break;
            }
        }
        if ($from_Exist){
            // Seleziona etichette della lingua prescelta (di partenza)
            $SQL0="be.*, el.*";
            $SQL1="from t40_element el inner join t30_blk_elm be on el.c40_id=be.c30_elemid inner join t20_block bl on bl.c20_id=be.c30_blockid where c20_flofoid=? and c40_lang=?";
            $SQL2="select count(*) as CNT ".$SQL1;
            $SQL3="select ".$SQL0." ".$SQL1;
            $params=array($wofoid,$from); 

            $i=$this->execSql($SQL2,$params);
            $cnt= $this->getData();
            $cnt=$cnt[0]["CNT"];
            General::setGlobal(str_replace("-","",$sessTransId),[$cnt,0]);

            $this->execSql($SQL3,$params);
            $res= $this->getData();
            $i=0;
            $ar=array();
            $totchar=0;
            $this->_cntDone=0;
            foreach ($res as $rec){
                $r=new stdClass();
                $r->tx=$rec["c40_text"];
                if(!empty($r->tx)){
                    $r->elmid=$rec["c40_id"];
                    $r->id=$rec["c30_blockid"]."-".$rec["c40_id"];
                    $r->tp=$rec["c30_type"];
                    $r->uri=$rec["c40_url"];
                    if ($r->tp==6){
                        // traduci struttura
                        $jt=json_decode($r->tx,true);

                    } else {
                        array_push($ar,$r);
                        $totchar+=strlen($r->tx);
                        if ($totchar>100){
                            $totchar=0;
                            $this->_translateLabels($tolng,$toLang,$ar,$cnt,$sessTransId);
                            $ar=array();
                        }
                    }
                } else{
                    //$_cntDone++;
                    General::setGlobal(str_replace("-","",$sessTransId),[$cnt,$this->_cntDone++]);
                }
            }
            if (count($ar)>0){
                $this->_translateLabels($tolng,$toLang,$ar,$cnt,$sessTransId);
            }
            General::setGlobal(str_replace("-","",$sessTransId),[$cnt,$cnt]);

            // add the new language to the supported ones
            $tl=$this->getSuppLang($wofoid)[0]["supp_langs"];
            $tll=explode(",",$tl);
            $addL = true;
            foreach ($tll as $lng){
                if ($lng == $tolng)
                    $addL = false;
            }
            if ($addL){
                $tl.=",".$tolng;
                $this->updateSuppLang($wofoid,$tl);
            }

            return true;
        }
        return false;
    }
    
    private function _translateLabels($tolng,$toLang,$ar,$cnt,$sessTransId){
        // traduci
        foreach ($ar as $tt){
            General::setGlobal(str_replace("-","",$sessTransId),[$cnt,$this->_cntDone++]);
            if (!empty($tt->tx)){
                $cmd="translate to ".$toLang.":\r\n".str_replace([":","\n","\r"],["[$1-3]","[$2-4]","[$3-5]"],$tt->tx)."\r\n";
                $trans=$this->_doTranslate($cmd."§");
                if (!empty($trans)){
                    try{
                        $trans=strpos($trans,"\n§ END OF DOC")!==false?explode("\n§ END OF DOC",$trans)[0]:$trans;
                        $trans=strpos($trans,"§ END OF DOC")!==false?explode("§ END OF DOC",$trans)[0]:$trans;
                        $trans=strpos($trans,"Code\n\n")===0?explode("\n",$trans)[2]:$trans;
                        $trans=strpos($trans,"Code\n")===0?explode("\n",$trans)[1]:$trans;
                        $trans=strpos($trans,"Output\n\n>")===0?trim(str_replace(["[","]","\"","'",],["","","",""],explode(">",$trans)[1])):$trans;
                    } catch(\Throwable $e){}
                    $tt->tx=str_replace(["[$1-3]","[$2-4]","[$3-5]"],[":","\n","\r"],$trans);
                    $tt->tx=str_replace(["[1-3]","[2-4]","[3-5]"],[":","\n","\r"],$tt->tx);
                    $tt->tx=str_replace(["$1-3","$2-4","$3-5"],[":","\n","\r"],$tt->tx);
                }
            }
            $this->_updateCreateElemLang($tt,$tolng);
        }
    }

    private function _updateCreateElemLang($arRec,$newLang){
        $elmId=$arRec->elmid;
        $SQL="select c40_id as id from t40_element where c40_id=? and c40_lang=?";
        $exist=$this->execSql($SQL,array($elmId,$newLang));
        $data=$this->getData();
        $p_res=false;
        if (count($data)>0 && isset($data[0]["id"]) && $data[0]["id"]==$elmId){
            $SQL="update t40_element set c40_text=? where c40_id=? and c40_lang=?";
            $params=array($arRec->tx,$elmId,$newLang); 
            $p_res=$this->execSql($SQL,$params);
        } else {
            $SQL="insert into t40_element (c40_text,c40_url,c40_id,c40_lang) values (?,?,?,?)";
            $params=array($arRec->tx,$arRec->uri,$elmId,$newLang); 
            $p_res=$this->execSql($SQL,$params);
        }
        return $p_res;
    }
    private function _doTranslate($transText){
        try{
            $rsp=$this->_open_ai->genQueryOpenAi($transText);
            $rspTxt="";
            if (empty($rsp["err"]))
                $rspTxt=$rsp["resp"];
            return trim($rspTxt);
        } catch (\Throwable $e){
            var_dump($e);
        }
        return null;
    }
    
    function getFlofoElementText($elmUuid,$lang){
        $params=array($elmUuid); 
            $SQL="SELECT c40_lang lang, c40_text label, ifnull(c40_url,'') uri
            FROM 
                t40_element E inner join t30_blk_elm on c30_elemid=E.c40_id
            where c30_uuid=? ";
        if (isset($lang) && $lang!=""){
            $SQL.="and c40_lang=? ";
            array_push($params, $lang);
        }
        $SQL.="order by 1 asc";
        $this->execSql($SQL,$params);
        return $this->getData();
    }



    // Flofo CREATE
    //----------------------
    function createFlofo($newWorkflowData,$userId=0){
        $res=array("result"=>"ERR","message"=>"Can't create this workflow");
        $langs="";  //lingue supportate
        $lang="";   //lingua di default
        $alng=$newWorkflowData->supp_langs;
        if (!is_array($newWorkflowData->supp_langs))
            $alng=explode(",",$newWorkflowData->supp_langs);
        foreach ($alng as $lang)
            $langs.=$lang.",";
        if ($langs=="")
            $langs="IT,";
        $langs=trim($langs);
        if (substr($langs,-1)==",");
            $langs=substr($langs,0,strlen($langs)-1);
        //La lingua di default e' la prima delle lingue supportate
        $lang=substr($langs,0,2);  
        $SQL="INSERT INTO t10_workflow (c10_name,c10_wf_auid,c10_description,c10_supp_langs,c10_def_lang,c10_userid,c10_validfrom,c10_validuntil,c10_active,c10_svc1,c10_svc2,c10_svc3) VALUES (?,?,?,?,?,?,?,?,?,'','','')";
        $newAauid=General::getUuidv4();

        $this->execSql("select c10_id from t10_workflow where c10_name=? and c10_description=?",array($newWorkflowData->name,$newWorkflowData->description));
        $extWfId=$this->getData();
        if (count($extWfId)>0){
            $wid=General::camouf($extWfId[0]["c10_id"]);
            $res=array("result"=>"EXIST ","WID"=>$wid);
        } else {
            $newWfId=$this->execSqlGetId($SQL,array($newWorkflowData->name,$newAauid,$newWorkflowData->description,$langs,$lang,$userId,'1899/12/31 23:59:59','2100/01/01 00:00:00',1));
            if ($newWfId>0){
                $svc1="";
                $svc2="";
                $svc3="";
                if (isset($newWorkflowData->svc1) && !is_null($newWorkflowData->svc1))
                    $svc1=$this->_beCurtatoned($newWfId, $newWorkflowData->svc1);
                if (isset($newWorkflowData->svc2) && !is_null($newWorkflowData->svc2))
                    $svc2=$this->_beCurtatoned($newWfId, $newWorkflowData->svc2);
                if (isset($newWorkflowData->svc3) && !is_null($newWorkflowData->svc3))
                    $svc3=$this->_beCurtatoned($newWfId, $newWorkflowData->svc3);
                $SQL="update t10_workflow set c10_svc1=?,c10_svc2=?,c10_svc3=? where c10_id=?";
                $this->execSql($SQL,array($svc1,$svc2,$svc3,$newWfId));

                $uuid=General::getUuidv4();
                $SQL="INSERT INTO t20_block (c20_flofoid,c20_uuid,c20_start,c20_desc,c20_exec,c20_type) values (?,?,?,?,?,?)";
                $newBlockId=$this->execSqlGetId($SQL,array($newWfId,$uuid,1,"START BLOCK","wofoEnv->init();\r\n",""));
                $wid="";
                if ($newBlockId>0){
                    $SQL="insert into t25_blockexit (c25_direction,c25_blockid,c25_nexit) values (?,?,?)";
                    $this->execSql($SQL,array(0,$newBlockId,0));
                    $this->execSql($SQL,array(0,$newBlockId,1));
                    $wid=General::camouf($newWfId);
                    $res=array("result"=>"OK","WID"=>$wid);
                    if ($res){
                        $this->_handleSvcChanges(1,"",$svc1,$wid);
                        $this->_handleSvcChanges(2,"",$svc2,$wid);
                        $this->_handleSvcChanges(3,"",$svc3,$wid);
                    }
                }
            }
        }
        return $res;
    }

    // Flofo DELETE
    //----------------------
    function deleteFlofo($WID,$doesNotDeleteBackup=false){
        // DELETE WORKFLOW
        // 1 recupera tutti i blockId da t310
        // 2 elimina i blocks usando l'apposita funzione
        // 3 elimina il workflow
        $res=array("result"=>"ERR","message"=>"Workflow ".$WID." not deleted.");
        $wfid=General::demouf($WID);
        $SQL="select c20_uuid as id from t20_block where c20_flofoid=?";
        if ($this->execSql($SQL,array($wfid))){
            $recs=$this->getData();
            for ($i=0;$i<count($recs);$i++)
                $this->deleteFlofoBlock($recs[$i]["id"]);
            
            $SQL="select c10_svc1 as svc1, c10_svc2 as svc2, c10_svc3 as svc3 from t10_workflow where c10_id=?";
            $this->execSql($SQL,array($wfid));
            $rSet=$this->getData();
            $svc1=$rSet[0]["svc1"];
            $svc2=$rSet[0]["svc2"];
            $svc3=$rSet[0]["svc3"];
            
            $SQL="delete from t10_workflow where c10_id=?";
            if ($this->execSql($SQL,array($wfid))){
                $ress=true;
                if (!$doesNotDeleteBackup){
                    $SQL="delete from t15_workflow_backup where c15_workflow_id=?";
                    $ress=$this->execSql($SQL,array($wfid));
                }
                if ($ress){
                    $res=array("result"=>"OK","message"=>"Workflow ".$WID." has been deleted.");

                    $this->_handleSvcChanges(1,$svc1,"",$WID);
                    $this->_handleSvcChanges(2,$svc2,"",$WID);
                    $this->_handleSvcChanges(3,$svc3,"",$WID);
        
                }
            }
        }
        return $res;
    }
    function deleteFlofoBlock($blockUuid){
        General::DelCache("blk",$blockUuid);

        $res=array("result"=>"ERR","message"=>"block ".$blockUuid." not deleted.");
        $blid=$this->getBlockIdFromUUID($blockUuid);
        // DELETE ALL EXITS CONNECTIONS
        $SQL="update t25_blockexit set c25_direction=0 where c25_direction=?";
        $res0=$this->execSql($SQL,array($blid));
        // DELETE ALL EXITS
        $SQL="delete from t25_blockexit where c25_blockid=?";
        $res1=$this->execSql($SQL,array($blid));
        // DELETE ALL ELEMENTS
        $SQL="SELECT c30_uuid as id FROM t30_blk_elm where c30_blockid=?";
        if ($this->execSql($SQL,array($blid))){
            $recs=$this->getData();
            for ($i=0;$i<count($recs);$i++)
                $this->deleteFlofoElement($recs[$i]["id"],"",$blid);
            // DELETE THE BLOCK ITSELF
            $SQL="delete from t20_block where c20_id=?";
            $res2=$this->execSql($SQL,array($blid));

            $res=array("result"=>"OK","message"=>"block ".$blockUuid." has deleted.");
        }
        return $res;
    }
    function deleteFlofoElement($elementUuid,$blockUuid,$blockId=0){
        General::DelCache("blk",$blockUuid);
        if ($blockId>0)
            $blid=$blockId;
        else
            $blid=$this->getBlockIdFromUUID($blockUuid);
        $SQL="SELECT c30_elemid as id FROM t30_blk_elm where c30_uuid=? and c30_blockid=?";
        $this->execSql($SQL,array($elementUuid,$blid));
        if (isset($this->getData()[0]["id"])){
            $elid=$this->getData()[0]["id"];
            $SQL="delete from t40_element where c40_id=?";
            $this->execSql($SQL,array($elid));
            $SQL="delete from t30_blk_elm where c30_elemid=? and c30_blockid=?";
            $this->execSql($SQL,array($elid,$blid));
            $res=array("result"=>"OK","message"=>"element ".$elementUuid." from block ".$blockUuid." has deleted.");
        } else
            $res=array("result"=>"ERR","message"=>"Element ".$elementUuid." not found");
        return $res;
    }

    function deleteFlofoExit($exitNum,$blockUuid){
        General::DelCache("blk",$blockUuid);
        $blid=$this->getBlockIdFromUUID($blockUuid);
        $SQL="delete from t25_blockexit where c25_blockid=? and c25_nexit=?";
        if (!$this->execSql($SQL,array($blid,$exitNum))){
            $res=array("result"=>"ERR","message"=>"Cannot delete exit ".$exitNum." from block ".$blockUuid);
        } else {
            $SQL="select * from t25_blockexit where c25_blockid=? order by c25_nexit";
            $this->execSql($SQL,array($blid));
            $recs=$this->getData();
            $j=0;
            for ($i=0;$i<count($recs);$i++){
                $SQL="update t25_blockexit set c25_nexit=?, c25_direction=? where c25_id=?";
                $dir=$recs[$i]["c25_direction"];
                $id=$recs[$i]["c25_id"];
                $ret=$this->execSql($SQL,array($j++,$dir,$id));
            }
            $res=array("result"=>"OK","message"=>"exit ".$exitNum." from block ".$blockUuid." has deleted.");
        }
        return $res;
    }

    // Flofo UPDATE
    //----------------------
    function updateFlofo($workflowData,$OptionalWID=null,$doNotBackup=false){
        $id=0;
        $res=array("result"=>"OK");
        if (empty($workflowData)){
            $res=array("result"=>"ERR","message"=>"No workflow data received");
        } else {
            if (isset($workflowData->workflow) && is_array($workflowData->workflow))
                $workflowData=$workflowData->workflow[0];
            if (isset($workflowData->wid))
                $WID=$workflowData->wid;
            else
                $WID=$OptionalWID;
            if ($WID!="" && $WID!="0" && strlen($WID)>10){
                $id=self::WID2Wofoid($WID);
                if ($id>0){
                    $errPos="P";
                    $SQL="select c10_id as id, c10_svc1 as svc1, c10_svc2 as svc2, c10_svc3 as svc3 from t10_workflow where c10_id=?";
                    $exist=$this->execSql($SQL,array($id));
                    $res=$this->getData();
                    if (is_array($res) && isset($res[0]["id"]) && $res[0]["id"]==$id){
                        $errPos="Q";
                        // ESISTE, SI DEVE UPDATARE
                        $pre_svc1=$res[0]["svc1"];
                        $pre_svc2=$res[0]["svc2"];
                        $pre_svc3=$res[0]["svc3"];

                        // ------------------------------------------------------------
                        // Prima il backup (max ultimi 10 save)
                        // il backup viene fermato in caso di import, perche' gia' effettuato in fase di import
                        // ------------------------------------------------------------
                        if (!$doNotBackup)
                            $p_res=$this->_backupFlofo($id);
                        
                        // Rimuovi cache "WID"                       
                        General::DelCache("wid",$id);
                        
                        // ------------------------------------------------------------
                        // Poi l'update dei dati del workflow
                        // ------------------------------------------------------------
                        $workflowData->lang=\strtoupper($workflowData->lang);
                        $workflowData->supp_langs=\strtoupper($workflowData->supp_langs);
                        if (!strpos($workflowData->lang,$workflowData->supp_langs)===false){
                            $langs=explode(",",$workflowData->supp_langs);
                            $workflowData->lang=$langs[0];
                        }
                        $svc1="";
                        $svc2="";
                        $svc3="";
                        if (isset($workflowData->svc1))
                            $svc1=$this->_beCurtatoned($id, $workflowData->svc1);
                        if (isset($workflowData->svc2))
                            $svc2=$this->_beCurtatoned($id, $workflowData->svc2);
                        if (isset($workflowData->svc3))
                            $svc3=$this->_beCurtatoned($id, $workflowData->svc3);
                        $SQL="update t10_workflow set c10_description=?,c10_name=?,c10_supp_langs=?,c10_def_lang=?,c10_active=?,c10_svc1=?,c10_svc2=?,c10_svc3=?,c10_modified=CURRENT_TIMESTAMP where c10_id=?";
                        $params=array($workflowData->description,$workflowData->name,$workflowData->supp_langs,$workflowData->lang,$workflowData->is_active,$svc1,$svc2,$svc3,$id);
                        $p_res=$this->execSql($SQL,$params);
                        
                        // il workflow potrebbe avere decine di blocchi
                        // e alcuni potrebbero essere stati cancellati
                        // quindi PRIMA recupero la lista di tutti i BLOCK-ID del workflow
                        $this->execSql("select c20_uuid as bid from t20_block where c20_flofoid=?",array($id));
                        $b_list=$this->getData();
                        if (isset($workflowData->blocks)){
                            foreach($workflowData->blocks as $blockData){
                                for($i=0;$i<count($b_list);$i++){
                                    if ($b_list[$i]["bid"]!="" && $b_list[$i]["bid"]==$blockData->block_id){
                                        $b_list[$i]["bid"]="";
                                        break;
                                    }
                                }
                                $this->updateFlofoBlock($blockData,$id,$workflowData->name);
                            }
                            for($i=0;$i<count($b_list);$i++){
                                if ($b_list[$i]["bid"]!=""){
                                    $this->deleteFlofoBlock($b_list[$i]["bid"]);
                                }
                            }
                            // Alcune exit potrebebro essere stati collegati a blocchi non ancora esistenti
                            // nel momento in cui registro il block
                            // sicchè serve, alla fine, updatare tutte le exit
                            foreach($workflowData->blocks as $blockData)
                                $this->updateFlofoExit($blockData);
                        }

                        $this->_handleSvcChanges(1,$pre_svc1,$svc1,$WID);
                        $this->_handleSvcChanges(2,$pre_svc2,$svc2,$WID);
                        $this->_handleSvcChanges(3,$pre_svc3,$svc3,$WID);

                        $returnFlofo=$this->getWorkflow($id,$WID, "", false, true);

                        $res=array("result"=>"OK","message"=>"Workflow ".$WID." has been updated.","workflow"=>($returnFlofo["workflow"][0]));

                    } else {
                        $res=array("result"=>"ERR","message"=>"If you want to persist a new workflow you must use a 'Create Workflow' command");
                    }
                } else {
                    $res=array("result"=>"ERR","message"=>"If you want to persist a new workflow you must use a 'Create Workflow' command");
                }
            } elseif ($WID=="0") {
                $errPos="Q";
                // NON ESISTE, SI DEVE CREARE
            } else {
                $res=array("result"=>"ERR","message"=>"To persist a new workflow must use a 'create new workflow' command");
            }
        }
        return $res;
    }

    // workflow Absolute Unique Identifier change
    //----------------------
    // v3.0.5 - il WfAUId è un campo UUID univoco.
    // Se è necessario il WfAUId può essere cambiato per contenere i dati del producer/versione/release/ecc.
    // Per poterlo cambiare bisogna verificare se su quetso DB c'è già lo stesso workflow ma con id diverso.
    function change_wfAUId($wid,$newWfAUId){
        $res=array("result"=>"OK","message"=>"Workflow AUId changed");
        $SQL="select c10_id,c10_wf_auid,c10_description,c10_name from t10_workflow where c10_wf_auid=?";
        $chk=$this->execSql($SQL,array($newWfAUId));
        $dres=$this->getData();
        if (count($dres)>0 && $dres["c10_id"]!=$wid){
            $res=array("result"=>"ERR","message"=>"You can't assign this WfAUId because it's already assigned to another workflow. Name:'".$dres[0]["c10_name"]." Description:(".$dres[0]["c10_description"].")'.");
        } else { 
            $SQL="update t10_workflow set c10_wf_auid=? where c10_wf_auid=?";
            $chk=$this->execSql($SQL,array($wid,$newWfAUId));
            if (!$chk){
                $dbErr=$this->getError();
                $res=array("result"=>"ERR","message"=>"Error changing WfAUId:".json_encode($dbErr));
            }
        }
        return $res;
    }

    // Flofo IMPORT
    //----------------------
    // v3.0.5 - aggiornamento import conservando l'UUID originale o, se non esiste, generandone uno nuovo,
    //          e controllo inesistenza dello stesso workflow su questo server con id diverso.
    function importFlofo($workflowData,$WID){
        $id=0;
        $res=array("result"=>"OK");
        if (empty($workflowData)){
            $res=array("result"=>"ERR","message"=>"No workflow data received");
        } else {
            if (!empty($WID) && $WID!="" && $WID!="0" && strlen($WID)>10){
                if (isset($workflowData->workflow) && is_array($workflowData->workflow))
                    $workflowData=$workflowData->workflow[0];
                $id=self::WID2Wofoid($WID);
                if ($id>0){
                    $errPos="P";
                    $SQL="select c10_id as id, c10_userid as userid, c10_name as name, c10_description as description, c10_svc1 as svc1, c10_svc2 as svc2, c10_svc3 as svc3 from t10_workflow where c10_id=?";
                    $exist=$this->execSql($SQL,array($id));
                    $res=$this->getData();
                    if (is_array($res) && isset($res[0]["id"]) && $res[0]["id"]==$id){
                        // OK, ESISTE, SI PUO' SOSTITUIRE
                        $userid=$res[0]["userid"];
                        if (isset ([0]["wf_auid"]))
                            $uuid=$res[0]["wf_auid"];
                        else 
                            $uuid=General::getUuidv4();
                        $workflowData->svc1=$res[0]["svc1"];
                        $workflowData->svc2=$res[0]["svc2"];
                        $workflowData->svc3=$res[0]["svc3"];
                        $workflowData->name=$res[0]["name"];
                        $workflowData->description=$res[0]["description"]." \r\n(imported from ".$workflowData->description.")";
                        $workflowData->lang=\strtoupper($workflowData->lang);
                        $workflowData->supp_langs=\strtoupper($workflowData->supp_langs);
                        if (!strpos($workflowData->lang,$workflowData->supp_langs)===false){
                            $langs=explode(",",$workflowData->supp_langs);
                            $workflowData->lang=$langs[0];
                        }
                        if (isset($workflowData->svc1))
                            $svc1=$this->_beCurtatoned($id, $workflowData->svc1);
                        if (isset($workflowData->svc2))
                            $svc2=$this->_beCurtatoned($id, $workflowData->svc2);
                        if (isset($workflowData->svc3))
                            $svc3=$this->_beCurtatoned($id, $workflowData->svc3);
                        $workflowData->svc1=$svc1;
                        $workflowData->svc2=$svc2;
                        $workflowData->svc3=$svc3;
                        // verifica se l'UUID è già presente in un altro workflow
                        //$uuid="dcb13393-9f47-11ef-a70a-005056035b78";

                        $SQL="select c10_id,c10_wf_auid,c10_description,c10_name from t10_workflow where c10_wf_auid=?";
                        $chk=$this->execSql($SQL,array($uuid));
                        $dres=$this->getData();
                        if (count($dres)>0 && $dres["c10_id"]!=$id){
                            $res=array("result"=>"ERR","message"=>"This workflow is already stored on this server. Name:'".$dres[0]["c10_name"]." Description:(".$dres[0]["c10_description"].")'. Before importing this workflow, you must delete the existing one.");
                        } else { 
                            // ------------------------------------------------------------
                            // Prima il backup (max ultimi 10 save)
                            // ------------------------------------------------------------
                            $p_res=$this->_backupFlofo($id);
                            // ------------------------------------------------------------
                            // Poi si elimina l'esistente
                            // ------------------------------------------------------------
                            $res=$this->deleteFlofo($WID,true);

                            $SQL="insert into t10_workflow (c10_id,c10_wf_auid,c10_userid,c10_description,c10_name,c10_supp_langs,c10_def_lang,c10_active,c10_svc1,c10_svc2,c10_svc3,c10_validfrom, c10_validuntil,c10_modified) values (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)";
                            $params=array($id,$uuid,$userid,$workflowData->description,$workflowData->name,$workflowData->supp_langs,$workflowData->lang,$workflowData->is_active,$svc1,$svc2,$svc3,'1899/12/31 23:59:59','2100/01/01 00:00:00');
                            $p_res=$this->execSql($SQL,$params);
                            if (!$p_res){
                                $err=$this->getError();
                                $res=array("result"=>"ERR","message"=>$err->getMessage());
                            } else {
                                $workflowData->wid=str_replace("_","]","[w".substr("$WID", 1));
                                $workflowData=$this->_replaceAllUUIDs($workflowData);
                                $res=$this->updateFlofo($workflowData,$WID,true);
                            }
                        }
                    } else {
                        $res=array("result"=>"ERR","message"=>"Cannot import a Workflow on a database where the original one does not exists.");
                    }
                } else {
                    $res=array("result"=>"ERR","message"=>"If you want to persist a new workflow you must use a 'Create Workflow' command");
                }
            } else {
                $res=array("result"=>"ERR","message"=>"To substitute a Workflow, you must provide a Workflow ID.");
            }
        }
        return $res;
    }



    // Flofo IMPORT
    //----------------------
    function addToFlofo($workflowData,$WID){
        $id=0;
        $res=array("result"=>"OK");
        if (empty($workflowData)){
            $res=array("result"=>"ERR","message"=>"No workflow data received");
        } else {
            if (!empty($WID) && $WID!="" && $WID!="0" && strlen($WID)>10){
                if (isset($workflowData->workflow) && is_array($workflowData->workflow))
                    $workflowData=$workflowData->workflow[0];
                $id=self::WID2Wofoid($WID);
                if ($id>0){
                    $errPos="P";
                    $SQL="select c10_id as id, c10_userid as userid, c10_name as name, c10_description as description, c10_svc1 as svc1, c10_svc2 as svc2, c10_svc3 as svc3 from t10_workflow where c10_id=?";
                    $exist=$this->execSql($SQL,array($id));
                    $res=$this->getData();
                    if (is_array($res) && isset($res[0]["id"]) && $res[0]["id"]==$id){
                        // OK, ESISTE, SI PUO' IMPORTARE
                        $desc=$res[0]["description"]." \r\n(imported new workflow-blocks from ".$workflowData->description.")";
                        // Si fa il backup (max ultimi 10 save)
                        $p_res=$this->_backupFlofo($id);

                        $SQL="update t10_workflow set c10_description=? where c10_id=?";
                        $p_res=$this->execSql($SQL,array($desc,$id));

                        $workflowData=$this->_replaceAllUUIDs($workflowData);
                        foreach($workflowData->blocks as $blockData){
                            if ($blockData->is_start!=0){
                                $blockData->is_start=0;
                                $blockData->description="Imported blocks [START BLOCK]";
                            }
                            $blockData->y_pos-=1000;
                            $this->updateFlofoBlock($blockData,$id);
                        }
                        // Alcune exit potrebebro essere stati collegati a blocchi non ancora esistenti al momento in cui ho registrato il block
                        // sicché serve, alla fine, updatare tutte le exit
                        foreach($workflowData->blocks as $blockData)
                            $this->updateFlofoExit($blockData);

                    } else {
                        $res=array("result"=>"ERR","message"=>"Cannot import a Workflow on a database where the original one does not exixt.");
                    }
                } else {
                    $res=array("result"=>"ERR","message"=>"If you want to persist a new workflow you must use a 'Create Workflow' command");
                }
            } else {
                $res=array("result"=>"ERR","message"=>"To import new blocks on a existing Workflow, you must provide the Workflow ID.");
            }
        }
        return $res;
    }

    private function _replaceAllUUIDs($woFo){
        foreach ($woFo->blocks as $block){
            $block->new_id=General::getUuidv4();
            if ($block->is_start>0)
                $woFo->start_block_id=$block->new_id;
    
            foreach ($woFo->blocks as $block2){
                foreach ($block2->exits as $exit){
                    if ($exit->exit_dir==$block->block_id)
                        $exit->exit_dir=$block->new_id;
                }
            }
            foreach ($block->elements as $element){
                $element->elem_id=General::getUuidv4();
            }
        }
        foreach ($woFo->blocks as $block){
            $block->block_id=$block->new_id;
            $block->new_id="";
            unset($block->new_id);
        }
        return $woFo;
    }

    private function _backupFlofo($id){
        //---------------------------------------------------
        // elimina vecchi backup fino a quando sono meno di 10
        $i=0;
        do{
            $this->execSql("select c15_backup_id as bkid from t15_workflow_backup where c15_workflow_id=? order by c15_rec_date asc",array($id));
            $count=$this->getData();
            if (\is_array($count) && isset($count[0]["bkid"])){
                if (count($count)>9){
                    $bkid=$count[0]["bkid"];
                    $this->execSql("delete from t15_workflow_backup where c15_backup_id=?",array($bkid));        
                } else
                    break;
            } else
                break;
        } while($i++<20);

        //$workflowData=$this->getFlofo(false, 0, $id, true);
        $workflowData=$this->getWorkflow($id, "", false, true);
        //------------------------------------------------------
        // poi registra una nuova copia di backup
        $SQL="insert into t15_workflow_backup (c15_workflow_id,c15_workflow_json) values (?,?)";
        $p_res=$this->execSql($SQL,array($id,json_encode($workflowData["workflow"][0])));
        // -------------------------------------------------------------
        return $p_res;
    }


    function updateFlofoBlock($blockData,$wid=0,$wfName=""){
        $res=array("result"=>"ERR:-5","message"=>"Cannot update/create a Block...");
        if (isset($blockData->block_id)){
            //General::DelCache("blk",$blockData->block_id);
            // E' un blocco!
            // Verifico se esiste o se deve essere creato ex novo
            $errPos="A";
            $SQL="select c20_uuid as uuid, c20_id as bid from t20_block where c20_uuid=?";
            $exist=$this->execSql($SQL,array($blockData->block_id));
            $arres=$this->getData();
            $e_list=[];
            $exec=$blockData->exec;
            $error="";
            if (!empty(trim($exec))){
                $ret=General::hasScriptErrors(str_replace("wofoEnv","$"."wofoEnv",$exec), $wfName." -> ".$blockData->description." (id:".$blockData->block_id.")");
                if ($ret["ok"]==false)
                    $error=$ret["output"];
            }

            if (count($arres)>0 && $arres[0]["uuid"]==$blockData->block_id){
                // ESISTE, SI DEVE UPDATARE
                try{General::DelCache("blk",$blockData->block_id);}catch(\Throwable $e){}
                $bid=$arres[0]["bid"];
                $errPos="B";

                // il blocco potrebbe avere decine di elementi
                // e alcuni potrebbero essere stati cancellati
                // quindi PRIMA recupero la lista di tutti gli ELEMENT-ID di questo BLOCK
                $this->execSql("select c30_uuid as eid from t30_blk_elm where c30_blockid=?",array($bid));
                $e_list=$this->getData();
                if (!isset($blockData->type))
                    $blockData->type="";

                $params=array($blockData->description,$exec,$blockData->type,$blockData->x_pos,$blockData->y_pos,$error); 
                $SQL="update t20_block set c20_desc=?,c20_exec=?,c20_type=?,c20_xpos=?,c20_ypos=?,c20_error=?";
                if (isset($blockData->note)){
                    $params[]=$blockData->note;
                    $SQL.=",c20_note=?";
                    //$params=array($blockData->description,$blockData->exec,$blockData->type,$blockData->x_pos,$blockData->y_pos,$blockData->note,$blockData->block_id); 
                } 
                $params[]=$blockData->block_id;
                $SQL.=",c20_modified=CURRENT_TIMESTAMP where c20_uuid=?";
                $p_res=$this->execSql($SQL,$params);
            } else {
                $errPos="C";
                // NON ESISTE, SI DEVE CREARE
                if ($wid>0){
                    // IL NUOVO REC HA EXITS? /////
                    if (!isset($blockData->exits))
                        $blockData->exits=array(0=>(object)["exit_dir"=>"0"],1=>(object)["exit_dir"=>"0"]);
                    // IL NUOVO REC HA ELEMENTS? //
                    if (!isset($blockData->elements))
                        $blockData->elements=[];
                    ///////////////////////////////
                    if (!isset($blockData->is_start))
                        $blockData->is_start=0;
                    if ($blockData->block_id=="" || $blockData->block_id=="0")
                        $blockData->block_id=General::getUuidv4();
                    if (isset($blockData->note)){
                        $SQL="insert into t20_block (c20_flofoid,c20_desc,c20_exec,c20_type,c20_xpos,c20_ypos,c20_error,c20_note,c20_uuid,c20_start) values (?,?,?,?,?,?,?,?,?)";
                        $params=array($wid,$blockData->description,$exec,$blockData->type,$blockData->x_pos,$blockData->y_pos,$error,$blockData->note,$blockData->block_id,$blockData->is_start); 
                    } else {
                        if (!isset($blockData->type))
                            $blockData->type="";
                        $SQL="insert into t20_block (c20_flofoid,c20_desc,c20_exec,c20_type,c20_xpos,c20_ypos,c20_error,c20_uuid,c20_start) values (?,?,?,?,?,?,?,?)";
                        $params=array($wid,$blockData->description,$exec,$blockData->type,$blockData->x_pos,$error,$blockData->y_pos,$blockData->block_id,$blockData->is_start); 
                    }
                    $p_res=$this->execSql($SQL,$params);
                    $errPos="M";
                    $SQL="select c20_id as id from t20_block where c20_uuid=?";
                    $this->execSql($SQL,array($blockData->block_id));
                    $bid=$this->getData()[0]["id"];

                    $res=array("result"=>"OK","message"=>"Added block with UUID:".$blockData->block_id.".");
                } else {
                    $p_res=false;
                    $res=array("result"=>"ERR","message"=>"Cannot add block without a valid workflow id.");
                }
            }
            if ($p_res){
                $elmId=0;
                $blkId=0;
                foreach ($blockData->elements as $Element){
                    if (is_array($e_list)){
                        for($i=0;$i<count($e_list);$i++){
                            if ($e_list[$i]["eid"]==$Element->elem_id){
                                $e_list[$i]["eid"]="";
                                break;
                            }
                        }
                    }
                    $this->updateFlofoElement($blockData,$Element);
                }
                for($i=0;$i<count($e_list);$i++){
                    if ($e_list[$i]["eid"]!=""){
                        $this->deleteFlofoElement($e_list[$i]["eid"],$blockData->block_id);
                    }
                }

            }
            if ($p_res){
                // IMPLEMENTARE EXITS
                $this->updateFlofoExit($blockData);
                $res="";
            }
            else 
                $res=array("result"=>"ERR".$errPos."1","message"=>"NOT executed at BLOCK level");
        } else {
            if ($blockData->blocks ==null || count($blockData->blocks)<1){
                //creare un primo blocco di START
                //e poi andare avanti con l'update.

            }
        }
        return ($res);
    }

    function updateFlofoElement($blockData,$Element){
        try{General::DelCache("blk",$blockData->block_id);}catch(\Throwable $e){}
        $elmId=0;
        $blkId=0;
        $errPos="D";
        if ($Element->c_type=="2" && $Element->exit_num=="")
            $Element->exit_num="0";
        // Verifico se l'elemento esiste o se deve essere creato ex novo
        $SQL="select c30_uuid as uuid from t30_blk_elm where c30_uuid=?";
        $exist=$this->execSql($SQL,array($Element->elem_id));
        $exit_num="";
        if (isset($Element->exit_num))
            $exit_num=$Element->exit_num;
        if ($exit_num=="")
        $exit_num=null;
        $data=$this->getData();
        if (count($data)>0 && isset($data[0]["uuid"]) && $data[0]["uuid"]==$Element->elem_id){
            // Esiste -> UPDATE
            $errPos="E";
            // ESISTE, SI DEVE UPDATARE
            $SQL="update t30_blk_elm set c30_varname=?,c30_type=?,c30_exit_num=?,c30_css=?,c30_order=?,c30_note=? where c30_uuid=?";
            $params=array($Element->var_name,$Element->c_type,$exit_num,json_encode($Element->css),$Element->e_order,$Element->note,$Element->elem_id); 
            $p_res=$this->execSql($SQL,$params);
        } else {
            $errPos="F";
            // NON ESISTE, SI DEVE CREARE
            $SQL="select c20_id as id from t20_block where c20_uuid=?";
            $this->execSql($SQL,array($blockData->block_id));
            $blkId=$this->getData()[0]["id"];
            if ($Element->e_order==""){
                $SQL="select max(c30_order) as oo from t30_blk_elm where c30_blockid=?";
                $exist=$this->execSql($SQL,array($blkId));
                $Element->e_order=intval($this->getData()[0]["oo"])+1;
            }
            $SQL="insert into t30_blk_elm (c30_blockid,c30_varname,c30_type,c30_exit_num,c30_css,c30_order,c30_note,c30_uuid) values (?,?,?,?,?,?,?,?)";
            $params=array($blkId,$Element->var_name,$Element->c_type,$exit_num,json_encode($Element->css),$Element->e_order,$Element->note,$Element->elem_id); 
            $p_res=$this->execSql($SQL,$params);
        }
        if ($p_res){
            $errPos="G";
            $SQL="select c30_elemid as id from t30_blk_elm where c30_uuid=?";
            $this->execSql($SQL,array($Element->elem_id));
            $elmId=$this->getData()[0]["id"];
        }
        foreach ($Element->langs as $key => $value){
            $errPos="H";
            //$spec=$Element->langs[$key];
            // Verifico se l'elemento esiste o se deve essere creato ex novo
            $SQL="select c40_id as id from t40_element where c40_id=? and c40_lang=?";
            $exist=$this->execSql($SQL,array($elmId,$key));
            $data=$this->getData();
            $arValue=[];
            if ($Element->c_type==6){
                if (is_object($value->label)) $arValue=(array)$value->label;
                if (is_array($arValue) &&count($arValue)>0){
                    $ar2Value=[];
                    foreach ($arValue as $ekey=>$val){
                        $kkey=explode(",",$ekey);
                        if (count($kkey)<2)
                            $ar2Value=array_merge($ar2Value,[$ekey.",0"=>$val]);
                        else
                        $ar2Value=array_merge($ar2Value,[$kkey[0].",".($kkey[1]=="1"?"1":"0")=>$val]);
                    }
                    $value->label=json_encode($ar2Value);
                }
                    
                //else
                //    $value->label="";
            }
            if (count($data)>0 && isset($data[0]["id"]) && $data[0]["id"]==$elmId){
                $errPos="K";
                // Esiste va updatato
                $SQL="update t40_element set c40_text=?,c40_url=? where c40_id=? and c40_lang=?";
                $params=array($value->label,$value->uri,$elmId,$key); 
                $p_res=$this->execSql($SQL,$params);
            } else {
                $errPos="L";
                // Non esiste, va creato
                $SQL="insert into t40_element (c40_text,c40_url,c40_id,c40_lang) values (?,?,?,?)";
                $params=array($value->label,$value->uri,$elmId,$key); 
                $p_res=$this->execSql($SQL,$params);
            }
        }
    }

    function updateFlofoExit($blockData)
    {
        try{General::DelCache("blk",$blockData->block_id);}catch(\Throwable $e){}
        $nx=0;
        $bid=$this->getBlockIdFromUUID($blockData->block_id);
        foreach ($blockData->exits as $Exit){
            $edir="";
            $SQL="select c25_id as id, c25_direction as edir from t25_blockexit where c25_blockid=? and c25_nexit=?";
            $this->execSql($SQL,array($bid,$nx));

            $data=$this->getData();
            if (count($data)>0 && isset($data[0]["id"])){
                // Esiste -> UPDATE
                $data=$data[0];
                $edir=$data["edir"];
                $uuidir="0";
                if ($edir!="" && $edir!="0")
                    $uuidir=$this->getBlockUUIDFromId($edir);
                if ($Exit->exit_dir!=$uuidir){
                    // UPDATE
                    if ($Exit->exit_dir!="" && $Exit->exit_dir!="0")
                        $edir=$this->getBlockIdFromUUID($Exit->exit_dir); 
                    else   
                        $edir="0";
                    $SQL="update t25_blockexit set c25_direction=? where c25_blockid=? and c25_nexit=?";
                    $this->execSql($SQL,array($edir,$bid,$nx));
                }
            }else{
                // Non esiste -> INSERT
                $edir="0";
                if ($Exit->exit_dir!="" && $Exit->exit_dir!="0")
                    $edir=$this->getBlockIdFromUUID($Exit->exit_dir);    
                $SQL="insert into t25_blockexit (c25_direction,c25_blockid,c25_nexit) values (?,?,?)";
                $this->execSql($SQL,array($edir,$bid,$nx));
            }
            $nx++;
        }
        $SQL="delete from t25_blockexit where c25_blockid=? and c25_nexit>=?";
        $te=count($blockData->exits);
        $this->execSql($SQL,array($bid,$te));
    }

    function getFlofoIdFromBlockUUID($uuid){
        $SQL="select c20_flofoid as id from t20_block where c20_uuid=?";
        $this->execSql($SQL,array($uuid));
        if (isset($this->getData()[0]["id"]))
            return $this->getData()[0]["id"];
        return "";
    }

    function getBlockIdFromUUID($uuid){
        $SQL="select c20_id as id from t20_block where c20_uuid=?";
        $this->execSql($SQL,array($uuid));
        if (isset($this->getData()[0]["id"]))
            return $this->getData()[0]["id"];
        return "";
    }

    function getBlockUUIDFromId($id){
        $SQL="select c20_uuid as uuid from t20_block where c20_id=?";
        $this->execSql($SQL,array($id));
        if (isset($this->getData()[0]["uuid"]))
            return $this->getData()[0]["uuid"];
        return "";
    }

    function getBlockUuidFromDescription($WoFoId,$desc){
        if (!is_null($desc)){
            $SQL="select c20_uuid as uuid from t20_block where c20_flofoid=? and c20_desc like ?";
            $this->execSql($SQL,array($WoFoId,"%".$desc."%"));
            if (isset($this->getData()[0]["uuid"]))
                return $this->getData()[0]["uuid"];
        }
        return "";
    }

    function getWorkflowByUUID($WofoId, $WID, $wfAUId, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false){
        $wflo=$this->getFlussuByUuid($getJustFlowExec,0,$wfAUId,false);
        if (is_array($wflo) && count($wflo)>0){
            $res= $this->_getWorkflow($WofoId,$WID,$wflo, $LNG, $getJustFlowExec, $forEditingPurpose);
        } else {
            $res=array("result"=>"ERR","message"=>"Workflow WfAUiD[$wfAUId] does not exist.");
        }
        return $res;
    }

    function getWorkflow($WofoId, $WID, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false){
        $wflo=$this->getFlussu($getJustFlowExec,0,$WofoId,false);
        if (is_array($wflo) && count($wflo)>0){
            $res= $this->_getWorkflow($WofoId, $WID,$wflo, $LNG, $getJustFlowExec, $forEditingPurpose);
        } else {
            $res=array("result"=>"ERR","message"=>"Workflow WID[$WofoId] does not exist.");
        }
        return $res;
    }
    private function _getWorkflow($wid, $WID, $wflo, $LNG, $getJustFlowExec, $forEditingPurpose){
        $res=[];
        $array0=$this->getFlussuBlocks($getJustFlowExec,$wid,true,$forEditingPurpose);
        $wflo[0]+=array("start_block_id"=>$array0[0]["block_id"]);
        if (empty($wflo[0]["wid"]))
            $wflo[0]["wid"]=$WID;
        foreach($array0 as $blk){
            $bldBlk=$this->buildFlussuBlock($wid, $blk["block_id"], $LNG, $getJustFlowExec, $forEditingPurpose);
            array_push($res,$bldBlk);
        }
        $wflo[0]["blocks"]=$res;
        return array("workflow"=>$wflo);
    }

    function duplicateFlussuBlock($BlkUuid, $andRegister=false){
        $wofoId=$this->getFlofoIdFromBlockUUID($BlkUuid);
        $jblk=["error"=>"800A"];
        if ($wofoId>0){
            $newUuid=General::getUuidv4();
            $blk=$this->buildFlussuBlock("", $BlkUuid, "");
            $jblk=json_decode(json_encode($blk));
            $jblk->block_id=$newUuid;
            $jblk->is_start=0;
            $jblk->pos_x=$jblk->pos_x+80;
            $jblk->pos_y=$jblk->pos_y+90;
            $jblk->description="copy of ".$jblk->description;
            foreach ($jblk->elements as $elm){
                $elm->elem_id=General::getUuidv4();
            }
            if ($andRegister===true)
                $this->updateFlofoBlock($jblk,$wofoId);
        }
        return json_encode($jblk);
    }

    function buildFlussuBlock($WoFoId, $BlkUuid, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false){
        
        // V2.0
        
        //$retta=$this->buildFlofoBlock($WoFoId, $BlkUuid, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false);
        //return $ret;
        
        $eid=0;
        //$blk=$this->getFlofoBlock($getJustFlowExec,$WoFoId,$BlkUuid)[0];

        $ELSQ=$this->_getFlofoElementSql($getJustFlowExec,true,$forEditingPurpose,true);

        $SQL2="";
        $SQL="select b1.c20_uuid as block_id, ifnull(b1.c20_type,'') as type, b1.c20_exec as exec, ifnull(b1.c20_desc,'') as description, ";
        if ($BlkUuid<>""){
            $SQL2=" b1.c20_uuid=? and ";
            $params=array($BlkUuid,$WoFoId); 
        } else 
            $params=array($WoFoId); 
        if (!$getJustFlowExec){
            $SQL.="b1.c20_start as is_start ";
            if ($forEditingPurpose){
                $SQL.=",b1.c20_note as note, b1.c20_xpos as x_pos, b1.c20_ypos as y_pos,b1.c20_error as error, b1.c20_modified as last_mod ";
            }
        } else {
            $SQL.="b1.c20_start as is_start ";
        }
        if (!empty(trim($ELSQ[0])))
            $SQL.=" ,".$ELSQ[0];

        $SQL.="from t20_block b1 left join ".$ELSQ[1];
        $SQL.=" where ".$SQL2." b1.c20_flofoid=?";// and ".$ELSQ[2];
        $SQL.=" order by ".$ELSQ[3].", b1.c20_start desc";
        
        $SQL=str_replace("[]joinHere[]","b1.c20_uuid",$SQL);
        $this->execSql($SQL,$params);
        $blk=$this->getData();

        $retBlk=["block_id"=>"","type"=>"","exec"=>"","is_start"=>false,"description"=>"","x_pos"=>"0","y_pos"=>"0","last_mod"=>"","elements"=>[],"exits"=>[]];
        if (count($blk)>0){
            $retBlk["flussu_id"]=$WoFoId;
            $retBlk["block_id"]=$blk[0]["block_id"];
            $retBlk["type"]=$blk[0]["type"];
            $retBlk["exec"]=$blk[0]["exec"];
            $retBlk["description"]=$blk[0]["description"];
            if (!$getJustFlowExec)
                $retBlk["is_start"]=$blk[0]["is_start"];
            if ($forEditingPurpose){
                $retBlk["error"]=$blk[0]["error"];
                $retBlk["x_pos"]=$blk[0]["x_pos"];
                $retBlk["y_pos"]=$blk[0]["y_pos"];
                $retBlk["last_mod"]=$blk[0]["last_mod"];
            }               
            if (!is_null($blk[0]["elem_id"])){
                foreach($blk as $elm){
                    $theElem=[];
                    $theElem["elem_id"]=strval($elm["elem_id"]);
                    $theElem["var_name"]=strval($elm["var_name"]);
                    $theElem["c_type"]=strval($elm["c_type"]);
                    $theElem["d_type"]=$this->_elmTypeDesc($elm["c_type"]);
                    $theElem["e_order"]=strval($elm["e_order"]);
                    $theElem["exit_num"]=strval($elm["exit_num"]);
                    //$theElem["css"]=strval($elm["css"]);
                    // INVESTIGATE CSS BUG
                    $theElem["css"]=json_decode(str_replace(['}"','"{','\"'],['}','{','"'],str_replace(":{}}",':""}',$elm["css"])),true);
                    //$theElem["css"]=json_decode($theElem["css"],true);
                    $theElem["note"]="";
                    $theElem["langs"]=[];
                    $langs=$this->getFlofoElementText($elm["elem_id"],$LNG);
                    if (count($langs)<1)
                        $langs=$this->getFlofoElementText($elm["elem_id"],"EN");
                    foreach($langs as $la){
                        $lab=$la["label"];
                        if ($theElem["c_type"]==6 && strpos($lab,"{")===0)
                            $lab=json_decode($la["label"],true);
                        $theElem["langs"][$la["lang"]]=["label"=>$lab,"uri"=>$la["uri"]];
                    }
                    array_push($retBlk["elements"],$theElem);
                }
            }
        } else
            return null;
        if (count($retBlk["elements"])<1 && count($blk)<1 && $getJustFlowExec){
            $eid++;
            $seid=str_pad($eid, 4, '0', STR_PAD_LEFT);
            $W=substr($WoFoId,1,strlen($WoFoId)-2);
            $W=substr($W,0,8)."-".$seid."-".substr($W,8,4)."-a7d0-".substr($W,12)."e7ec".$seid;
            $retBlk["elements"]=array("elem_id"=>$W,"var_name"=>"@codExec","e_order"=>"0","c_type"=>"99","label"=>$blk["exec"]);
        } 
        if ($getJustFlowExec)
            unset($retBlk["exec"]);
        $exits=$this->getFlofoBlockExits($retBlk["block_id"],false);
        $retBlk["exits"]=$exits;
        return $retBlk;
    }


    function buildFlofoBlock_old($WoFoId, $BlkUuid, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false){
      
       
        /*
        
        
        $eid=0;
        $blk=$this->getFlofoBlock($getJustFlowExec,$WoFoId,$BlkUuid)[0];
        $elems2=[];
        if (isset($blk) && isset($blk["block_id"]) &&!is_null($blk["block_id"])){
            $elems=$this->getFlofoElementList($getJustFlowExec,$blk["block_id"],true,$forEditingPurpose);
            if (count($elems)>0){
                foreach($elems as $elm){
                    $langs=$this->getFlofoElementText($elm["elem_id"],$LNG);
                    if (count($langs)<1)
                        $langs=$this->getFlofoElementText($elm["elem_id"],"EN");
                    $elang=[];
                    foreach($langs as $la){
                        if ($elm["c_type"]==6){
                            // necessità temporanea dovuta ad errata codifica via v2.8.5
                            $lll=$la["label"];
                            if (strpos($lll,"{")!==0)
                                $lll="{\"00,0\":\"NONE\"}";
                            if (substr($lll,0,1)=="["){
                                $lll=substr($lll,1,-1);
                                $lll=str_replace("},{",",",$lll);
                            } 
                            // fine necessità temporanea
                            $lbl=json_decode($lll,true);
                            //$elang+=array($la["lang"]=>array("label"=>$lbl,"uri"=>str_replace("thumb","",$la["uri"])));
                            $elang+=array($la["lang"]=>array("label"=>$lbl,"uri"=>$la["uri"]));
                        }
                        else
                            $elang+=array($la["lang"]=>array("label"=>$la["label"],"uri"=>$la["uri"]));
                            //$elang+=array($la["lang"]=>array("label"=>$la["label"],"uri"=>str_replace("thumb","",$la["uri"])));
                    }
                    $elm["c_type"]=strval($elm["c_type"]);
                    $elm["e_order"]=strval($elm["e_order"]);
                    array_push($elems2,($elm+array("langs"=>$elang)));
                }
            }
        } else
            return null;
        if (count($elems2)<1 && count($elems)<1 && $getJustFlowExec){
            $eid++;
            $seid=str_pad($eid, 4, '0', STR_PAD_LEFT);
            $W=substr($WoFoId,1,strlen($WoFoId)-2);
            $W=substr($W,0,8)."-".$seid."-".substr($W,8,4)."-a7d0-".substr($W,12)."e7ec".$seid;
            $blk+=array("elements"=>array(array("elem_id"=>$W,"var_name"=>"@codExec","e_order"=>"0","c_type"=>"99","label"=>$blk["exec"])));
        } else {
            if (count($elems2)>0)
                $blk+=array("elements"=>$elems2);
            else
                $blk+=array("elements"=>$elems);
        }
        if ($getJustFlowExec)
            unset($blk["exec"]);
        $exits=$this->getFlofoBlockExits($blk["block_id"],false);
        $blk+=array("exits"=>$exits);
        return $blk;
        */
    }

    function receiveFlofo($workflowData){
        // receive a workflow from an external entity
        date_default_timezone_set('Europe/Rome');
        $id=0;
        $res=array("result"=>"OK");
        $log=date('m/d/Y h:i:s a', time())."- start";
        if (empty($workflowData)){
            $res=array("result"=>"ERR","message"=>"No workflow data received");
            $log=date('m/d/Y h:i:s a', time())."- Error: no workflow data received ";
        } else {
            if (isset($workflowData->workflow) && is_array($workflowData->workflow))
                $workflowData=$workflowData->workflow[0];
            $WID=$workflowData->wid;
            if ($WID!="" && $WID!="0" && strlen($WID)>10){
                $log=date('m/d/Y h:i:s a', time())."- receided WID $WID";
                if (strlen($WID)<30 && strpos($WID, '[w') === 0 && strpos($WID, ']')===strlen($WID)-1) {
                    $WID=substr_replace(substr_replace($WID,"_",strlen($WID)-1,1),"_",0,2);
                    $id=General::demouf($WID);
                }
                if ($id>0){
                    $errPos="P";
                    $SQL="select c10_id as id from t10_workflow where c10_id=?";
                    $exist=$this->execSql($SQL,array($id));
                    $res=$this->getData();
                    if (!is_array($res) || !isset($res[0]["id"]) || $res[0]["id"]==0){
                        $errPos="X";
                        $log=date('m/d/Y h:i:s a', time())."- Create a new Workflow";
                        // NON ESISTE, SI DEVE CREARE
                        $SQL="INSERT INTO t10_workflow (c10_id,c10_name,c10_description,c10_supp_langs,c10_def_lang,c10_userid,c10_validfrom,c10_validuntil,c10_active) VALUES (?,?,?,?,?,?,?,?,?)";
                        $this->execSql($SQL,array($id,$workflowData->name,$workflowData->description,$workflowData->supp_langs,$workflowData->lang,$workflowData->userId,$workflowData->valid_from,$workflowData->valid_until,$workflowData->is_active));
                        $FB=$workflowData->blocks[0];
                        $SQL="INSERT INTO t20_block (c20_flofoid,c20_uuid,c20_start,c20_desc,c20_exec,c20_type) values (?,?,?,?,?,?)";
                        $newBlockId=$this->execSqlGetId($SQL,array($id,$FB->block_id,1,$FB->description,$FB->exec,""));
                        $SQL="insert into t25_blockexit (c25_direction,c25_blockid,c25_nexit) values (?,?,?)";
                        $this->execSql($SQL,array($FB->exits[0]->exit_dir,$newBlockId,0));
                        $this->execSql($SQL,array($FB->exits[1]->exit_dir,$newBlockId,1));
                    } else {
                        $log=date('m/d/Y h:i:s a', time())."- Updating an existing Workflow";
                    }
                    $res=$this->updateFlofo($workflowData);
                    $log=date('m/d/Y h:i:s a', time())."- ".json_encode($res);
                } else {
                    $log=date('m/d/Y h:i:s a', time())."- Workflow ID error.";
                }
            } else {
                $log=date('m/d/Y h:i:s a', time())."- Workflow data error.";
            }
        }
        return [$res,$log];
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