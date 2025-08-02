<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       MultiWfController.class
 * CREATED DATE:     1.0 28.12:2020 - Aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * CLASS PATH:       \Flussuserver
 * -------------------------------------------------------
 * Controller per il record Multi-Workflow.
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * 
 * ------------------------------------------------------- */
namespace Flussu\Controllers;
use Auth;
use Session;

use Flussu\General;
use Flussu\Flussuserver\NC\HandlerMultNC;

use Log;

class MultiWfController 
{
    public function registerNew($wf_id,$user_id,$user_email,$data_array){
        // $userId can be $SMAPEID
        if (!is_numeric($user_id)){
            $theFlussuUser=new \Flussu\Persons\User();
            $ret=$theFlussuUser->emailExist($user_email);
            if ($ret[0]){
                // esiste
                $user_id=$ret[1];
                $theFlussuUser->load($user_id);
            } else {
                $theFlussuUser->authenticate($user_email, $user_id);
                if ($theFlussuUser->getId()<1){
                    $theFlussuUser->registerNew($user_email, $user_id, $user_email);
                }
                $user_id=$theFlussuUser->getId();
            }
            if ($theFlussuUser->getId()<1){
                throw new \Exception("Cannot Identify/Create User");
            }
        }
        $wf_id=$this->_extrWfId($wf_id);
        $data=json_encode($data_array,JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
        $hnd=new HandlerMultNC();
        $assigned_Server="this.server";
        $recs=$hnd->getMultiWf2($wf_id,$user_id);
        $res=false;
        $mwid="";
        if (isset($recs["mwf_id"])){
            $mwid=$recs["mwf_id"];
            $res=$hnd->updateMultiWf($mwid,$wf_id,$user_id,$user_email,$data,$assigned_Server);
        } else {
            $WID="[".General::camouf($wf_id)."]";
            $orig=$wf_id.$WID.$user_email;
            $checksum = crc32($orig);
            $md5 = md5($orig);
            $mwid=substr(substr($md5,2,3).dechex($checksum).substr($md5,7,5),1,15);
            $res=$hnd->newMultiWf($mwid,$wf_id,$user_id,$user_email,$data,$assigned_Server);
        }
        if (!$res){
            $rres=$hnd->getMultiWf2($wf_id,$user_id);
            if (isset($rres))
                $mwid=$rres["mwf_id"];
        }
        $newMWFid="_M.".$mwid."_";;
        return $newMWFid;
    }

    private function _extrWfId($WID){
        $WofoId=$WID;
        if (strlen($WID)<30 && strpos($WID, '[w') === 0 && strpos($WID, ']')===strlen($WID)-1) {
            $WID=substr_replace(substr_replace($WID,"_",strlen($WID)-1,1),"_",0,2);
            $WofoId=General::demouf($WID);
        } 
        return $WofoId;
    }
    public function getData($mwf_cam_id){
        $res=[];
        $mwf_cam_id=strtoupper($mwf_cam_id);
        if (substr($mwf_cam_id,1,2)=="M."){
            $MWFid=str_replace("M.","",substr($mwf_cam_id,1,-1));
            $hnd=new HandlerMultNC();
            $res=$hnd->getMultiWf($MWFid);
        }
        return $res;
    }

    public function addUseCount($mwf_cam_id){
        if (!is_numeric($mwf_cam_id)){
            // trasformare in numero

        }
    }
    public function addOpenCount($mwf_cam_id){
        if (!is_numeric($mwf_cam_id)){
            // trasformare in numero

        }
    }
    public function addEmailCount($mwf_cam_id){
        if (!is_numeric($mwf_cam_id)){
            // trasformare in numero

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