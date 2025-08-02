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
 * CLASS-NAME:       Flussu API Controller
 * CREATE DATE:     04.08.2022 - Aldus - Flussu v2.2
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Flussu\General;
use Flussu\Api\V40\Flow;
use Flussu\Api\V40\Stat;
use Flussu\Api\V40\Sess;
use Flussu\Api\V40\Conn;
use Flussu\Api\V40\Engine;
use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;

use Log;

use function PHPSTORM_META\map;

class FlussuController 
{
    public function apiCall(Request $request, $apiPage){
        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');

        session_start();

        $uid=0;
        $res="";
        $theFlussuUser=new \Flussu\Persons\User();
        if (is_array(explode("?",$apiPage)))
            $apiPage=explode("?",$apiPage)[0];

        error_reporting(0); 

        switch ($apiPage){
            case "vatid":
                $rq=$request["vatid"];
                $pic=new PartitaIvaController();
                $res=$pic->PICheck(substr($rq,0,2),substr($rq,2));
                var_dump($res);
                break;
            case "flussuconn":
            case "flussuconn.php":
                header('Content-Type: application/json; charset=UTF-8');
                $st=new Conn();
                $st->exec($request,$theFlussuUser);
                //return $res;

                break;
            case "flussueng":
            case "flussueng.php":
                header('Content-Type: application/json; charset=UTF-8');
                $st=new Engine();
                $rawdata = file_get_contents('php://input');
                $res=$st->exec($request,$rawdata);
                $RET=json_encode($res, JSON_HEX_QUOT || JSON_HEX_APOS ); 
                //return $res; 
                //$reees=response()->make($res, 200);
                //return $reees; 
                die($RET);
            case "statdata":
            case "statdata.php":
                header('Content-Type: application/json; charset=UTF-8');
                $st=new Stat();
                $rawdata = file_get_contents('php://input');
                $st->extCall($request,$rawdata);
                //$RET=json_encode($res); 
                //return $res; 
                //$reees=response()->make($res, 200);
                //return $reees; 
                //die($RET);
                break;
            case "zap":
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');

                die("DUNNO???");
            default:
                $cwid=$request["CWID"];
                $authKey="";
                if (!empty($cwid)){
                    $parts=explode("|",$cwid);
                    if (count($parts)==2 && strlen($parts[0])<25){
                        $authKey=substr($parts[1],0,-1);
                        $cwid=$parts[0]."]";
                    } else {
                        $cwid="[".$parts[1];
                        $authKey=substr($parts[0],1);
                    }
                } else 
                    $authKey=$request["auk"];

                $uid=General::getUserFromDateTimedApiKey($authKey);

                //echo "UID=".$uid;

                if ($uid>0){
                    $theFlussuUser=new \Flussu\Persons\User();
                    $theFlussuUser->load($uid);
                }

                //echo "User ID=".$theFlussuUser->getId();


                $C=$request["C"];
                if (!empty($C)){
                    $rawdata = file_get_contents('php://input');
                    $fl=new Flow();
                    $fl->exec($request,$theFlussuUser,$rawdata);
                } else {
                    if ($theFlussuUser->getId()>0){
                        switch ($apiPage){
                            case "flow":
                                //echo "FLOW";
                                $rawdata = file_get_contents('php://input');
                                $fl=new Flow();
                                $fl->exec($request,$theFlussuUser,$rawdata);
                                break;
                            case "sess":
                                //echo "SESS";
                                $st=new Sess();
                                $st->exec($request,$theFlussuUser,0);
                                break;
                            case "stat":
                                //echo "FLOW";
                                $st=new Stat();
                                $st->exec($request,$theFlussuUser,0);
                                break;
                            case "stat0":
                            case "stat1":
                            case "stat2":
                                $pNum=substr($apiPage,-1);
                                $st=new stat();
                                $st->exec($request,$theFlussuUser,$pNum);
                                break;
                        }
                    }
                    header('HTTP/1.0 403 Forbidden');
                    die(\json_encode(["error"=>"403","message"=>"Unauthorized action"]));
                }
        }

        error_reporting(E_ALL); 
    }

    public function webhook($theCallString){
        $res=null;
        /* first we need o know if called is just the workflow or workflow/block
            /wh/WorkFlow (can ba e WID or WF_AUID)
            Ex:/wh/w8567576576a OR /wh/123456-123456-123456-123456
            /wh/WorkFlow/Block (can be Block_UUID or NAME)
            Ex:/wh/w8567567746a/12345-12345-12345-12345 OR /wh/123456-123456-123456-123456/BLOCKNAME
            The session id (if any) must be passed as a parameter            
        */
        $is_echo=false;
        $parts=explode("/",$theCallString);
        if ($parts[2]=="show"){
            array_splice($parts, 2, 1);
            $is_echo=true;
        }
        if (str_starts_with($parts[2],"[") && str_ends_with($parts[2],"]")){
            $anyIdent=$parts[2];
        } else {
            $anyIdent="[".$parts[2]."]";
        }
        $hnd=new HandlerNC();
        $wid=$hnd->getFlussuWID($anyIdent);
        $WID=$anyIdent;
        $wid=$wid["wid"];
        $bid="";
        $caller=$_SERVER["HTTP_USER_AGENT"];

        if ($wid>0){
            if (count($parts)>2 && (isset($parts[3]) && $parts[3]!=null)){
                $blockIdent=$parts[3];
                $bid=$hnd->getBlockUuidFromDescription($wid,$blockIdent);
                if (empty($bid)){
                    $bid=$hnd->getBlockIdFromUUID($blockIdent);
                } else {
                    $bid=$hnd->getBlockIdFromUUID($bid);
                }
            }
            $sid="";
            $prefix="";
            $vars=[];
            foreach($_GET as $key => $value)
                $vars[$key] = $value;
            foreach($_POST as $key => $value)
                $vars[$key] = $value;
            if (array_key_exists("SID",$vars)){
                $sid=$vars["SID"];
                unset($vars["SID"]);
            }

            $payload = @file_get_contents('php://input');
            // STRIPE WEBHOOK
            if (stripos($caller,"stripe")!==false){
                $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
                $sc=new StripeController();
                $vars=$sc->getWebHookData($payload);
                $prefix="stripe_";
            }
            $terms=[];
            foreach ($vars as $key => $value){
                if (!is_null($value))
                    $terms["$".$prefix.$key]= preg_replace('~^[\'"]?(.*?)[\'"]?$~', '$1', $value); 
            }
            $terms["$"."web_caller"]=$caller;
            $terms["$"."webhook"]=true;
            if ($is_echo==false){
                $eng=new Engine();
                $res=$eng->execWorker($wid,$sid,$bid,$terms);
            } else {
                echo "<h2>FLUSSU ECHO WEBHOOK</h2><hr>";
                echo "<strong>WID</strong>=$WID&nbsp;($wid)<br>";
                echo "<strong>SID</strong>=$sid<hr><h3>VARS</h3><div style='padding-left:30px'>";
                foreach ($vars as $key => $value){
                    echo "<strong>$key</strong>=".htmlentities($value)."<br>";
                }
                echo "</div><hr><h3>TERMS</h3><div style='padding-left:30px'>";
                foreach ($terms as $key => $value){
                    echo "<strong>$key</strong>=".htmlentities($value)."<br>";
                }
                echo "</div>";
                die();
            }
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