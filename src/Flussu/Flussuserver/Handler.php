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
 * CLASS-NAME:       FlussuHandler.class
 * CLASS PATH:       /Flussu/Flussuserver
 * FOR ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     (28.11.2024) - Aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/

namespace Flussu\Flussuserver;
use Flussu\General;
use Flussu\Beans\Databroker;
use Flussu\Flussuserver\NC\HandlerNC;

class Handler {
    private $_UBean;
    private $_HNC;
    // CLASS CONSTRUCTION
    //----------------------
    public function __construct (){
        $this->_HNC=new HandlerNC();
        $this->_UBean = new Databroker(General::$DEBUG);
    }
    // CLASS DESTRUCTION
    //----------------------
    public function __destruct(){
        //if (General::$Debug) echo "[Distr Databroker ]<br>";
    }
    function __clone(){$this->_UBean = clone $this->_UBean;}
    function getFlussuName($WID):mixed                              {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getFlussuName($WID);
    }
    function getFlussuNameFirstBlock($wofoId):array                  {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        $id="GFNFB".$wofoId;
        $res=General::GetCache($id,"wid",$wofoId);
        if (is_null($res)){
            $res= $this->_HNC->getFlussuNameFirstBlock($wofoId);
            if (!empty($res)){
                General::PutCache($id,$res,"wid",$wofoId);
                $this->getFlussuBlock(true,$wofoId,$res[0]["start_blk"]);
            }
        }
        return $res;
    }
    function getFlussuNameDefLangs($wofoId):array                    {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getFlussuNameDefLangs($wofoId);}
    function getSuppLang($wofoId):array                             {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getSuppLang($wofoId);
    }
    function getFlussuWID($wid_identifier_any):array                 {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getFlussuWID($wid_identifier_any);
    }
    function getFlussu($getJustFlowExec, $forUserid, $wofoId=0, $allElements=false): mixed{
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getFlussu($getJustFlowExec, $forUserid, $wofoId, $allElements);
    }
    function getFlussuBlock($getJustFlowExec,$wofoId,$blockUuid): mixed {
        $id="GFLBK".$blockUuid;
        $res=General::GetCache($id,"blk",$blockUuid);
        if (is_null($res)){
            $res=$this->_HNC->getFlussuBlock($getJustFlowExec,$wofoId,$blockUuid);
            General::PutCache($id,$res,"blk",$blockUuid);
        }
        return $res;
    }
    function getFirstBlock($wofoId):array{
        //$id="GFIB".$wofoId;
        //$res=General::GetCache($id,"wid",$wofoId);
        //if (is_null($res)){
            $res=$this->_HNC->getFirstBlock($wofoId);
        //    General::PutCache($id,$res,"wid",$wofoId);
        //}
        return $res;
    }
    function getElemVarNameForExitNum($blockUuid,$exitNum,$lang):array|null
    {
        $id="GEVNFEN".$blockUuid.$exitNum.$lang;
        $res=General::GetCache($id,"blk",$blockUuid);
        if (is_null($res)) {
            $res= $this->_HNC->getElemVarNameForExitNum($blockUuid,$exitNum,$lang);
            General::PutCache($id,$res,"blk",$blockUuid);
        }
        return $res;
    }
    function getBlockIdFromUUID($uuid):mixed {
        $id="GBIFU".$uuid;
        $res=General::GetCache($id,"blk",$uuid);
        if (is_null($res)){
            $res= $this->_HNC->getBlockIdFromUUID($uuid);
            General::PutCache($id,$res,"blk",$uuid);
        }
        return $res;
    }
    function getBlockUuidFromDescription($WoFoId,$desc):mixed {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getBlockUuidFromDescription($WoFoId,$desc);
    }
    function getWorkflowByUUID($WofoId, $WID, $wfAUId, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false):array {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getWorkflowByUUID($WofoId, $WID, $wfAUId, $LNG, $getJustFlowExec, $forEditingPurpose);
    }
    function getWorkflow($WofoId, $WID, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false):array {
        //General::Log(debug_backtrace()[1]["function"]." > ".__FUNCTION__." ".json_encode(func_get_args()));
        return $this->_HNC->getWorkflow($WofoId, $WID, $LNG, $getJustFlowExec, $forEditingPurpose);
    }
    function buildFlussuBlock($WoFoId, $BlkUuid, $LNG="", $getJustFlowExec=false, $forEditingPurpose=false):array|null {
        $id="BFB".$WoFoId.$BlkUuid.$LNG.($getJustFlowExec?"T":"F").($forEditingPurpose?"T":"F");
        $res=General::GetCache($id,"blk",$BlkUuid);
        if (is_null($res)){
            $res= $this->_HNC->buildFlussuBlock($WoFoId, $BlkUuid, $LNG, $getJustFlowExec, $forEditingPurpose);
            General::PutCache($id,$res,"blk",$BlkUuid);
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