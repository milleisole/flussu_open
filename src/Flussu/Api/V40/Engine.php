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
 * CLASS-NAME:    Flussu API Engine
 * CLASS PATH:    Flussu\Api\V40\FlussuHandler
 * -------------------------------------------------------*
 * CREATED:  25.01.2021 - Aldus - Flussu v2.0
 * VERSION REL.:     4.5.20250802
 * UPDATES DATE:     02.08:2025 
 * -------------------------------------------------------
 * Now supports the language change
 * Language BUG solved: 2025-08-02
 * -------------------------------------------------------*/
/**
 * The Engine class is responsible for handling the core execution flow of the Flussu API within the Flussu server.
 * 
 * This class manages the main execution process, including handling HTTP requests, managing sessions, and
 * coordinating with other components such as Workers, Handlers, and Commands. It ensures that the API requests
 * are processed correctly and efficiently.
 * 
 * Key responsibilities of the Engine class include:
 * - Handling incoming HTTP requests and setting appropriate headers for CORS (Cross-Origin Resource Sharing).
 * - Managing the execution flow by coordinating with the Worker, Handler, and Session classes.
 * - Processing raw data files if provided and integrating them into the execution flow.
 * - Ensuring that sessions are correctly initialized and managed throughout the request lifecycle.
 * - Handling errors and exceptions that occur during the execution process.
 * 
 * The class is designed to be the central point for managing the execution of API requests, ensuring that all
 * components work together seamlessly to provide a reliable and efficient API service.
 * 
 * @package App\Flussu\Api\V40
 * @version 4.1.20250205
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

namespace Flussu\Api\V40;

use Flussu\Flussuserver\Request;

use Flussu\General;
use Flussu\Flussuserver\Worker;
use Flussu\Flussuserver\Command;
use Flussu\Flussuserver\Handler;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Flussuserver\Session;

use OpenApi\Annotations as OA;

 /** 
 * @OA\Post(
 *     path="/flussueng",
 *     summary="Executes a workflow based on provided parameters",
 *     description="This endpoint handles the execution of a workflow. The WID parameter (workflowID) is obviosly mandatory. The SID parameter is optional, if not provided a new workflow session is created. Otherwise, it continues the existing session with the given parameters.",
 *     tags={"Workflow"},
 *     @OA\Parameter(
 *         name="WID",
 *         in="query",
 *         description="Workflow identifier. Can be numeric or a string. If numeric, it is internally converted to a valid WID.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="SID",
 *         in="query",
 *         description="The current session ID. If empty, a new workflow session is created.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="CMD",
 *         in="query",
 *         description="Command to execute (e.g., 'info', 'set').",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="TRM",
 *         in="query",
 *         description="Additional parameters, often in JSON format, to customize the workflow execution.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="BID",
 *         in="query",
 *         description="Identifier of the workflow block to execute. If not provided, the current block is used.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="LNG",
 *         in="query",
 *         description="Workflow language (e.g., 'IT', 'EN').",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="APP",
 *         in="query",
 *         description="Identifier of the requesting application.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="SET",
 *         in="query",
 *         description="Additional settings in JSON format.",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         description="Optional data, including files. If present, send as multipart/form-data.",
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="file_rawdata",
 *                     type="string",
 *                     format="binary",
 *                     description="Optional file to upload with the request."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Execution completed successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="sid", type="string", description="Current session ID."),
 *             @OA\Property(property="bid", type="string", description="Current block ID."),
 *             @OA\Property(property="elms", type="object", description="Flow elements (UI or data) to be presented.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request or parameters."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error during execution."
 *     )
 * )
 */

class Engine {

    public function exec(Request $Req, $file_rawdata=null){
        $wSess=null;
        $terms=null;
        $widd=null;
        $bid="";

        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');

        $wid=General::getGetOrPost("WID");
        $sid=General::getGetOrPost("SID");
        if ($sid=="null") $sid="";

        if (is_numeric($wid)){
            $wid=HandlerNC::Wofoid2WID($wid);
        }

        if (!empty($sid)){
            $wid2=General::montanara($wid,substr(str_replace("-","",$sid),5,5));
            if (!empty($wid2))
                $wid=$wid2; 
        }

        $cmd=General::getGetOrPost("CMD");
        $frmBid="No #BlockID";

        $res=json_encode([
            "sid"=>"err",
            "bid"=>"err",
            "elms"=>"err"
        ]);

        if ($cmd=="info" && $wid!=""){
            $w_id=HandlerNC::WID2Wofoid($wid);
            $wB=new Handler();

            $rec=$wB->getFlussuNameDefLangs($w_id);
            if (isset($rec) && is_array($rec)){
                $res=[
                    "tit"=>$rec[0]["name"],
                    "langs"=>$rec[0]["supp_langs"],
                    "def_lang"=>$rec[0]["def_lang"]
                ];
            }
        } else {
            $TRM=General::getGetOrPost("TRM");
            $ARB="";
            if (strpos($TRM,"arbitrary\":")!==false){
                $ARB=substr($TRM,strpos($TRM,"arbitrary\":")-2);
                $TRM=str_replace($ARB,"",$TRM);
            }  
            $terms=json_decode($TRM,true);
            if ($terms==null & substr($TRM,0,2)=="R:"){
                // è un RESTART
                $restart=true;
                $restRows=str_replace(",","",substr($TRM,2));
            }
            if ($ARB!="")
                $terms=json_decode($ARB,true);
            if (!is_array($terms))
                $terms=[];
            
            foreach ($_GET as $key => $value){
                if (strpos($key,"$")===0)
                    $terms[$key]=$value;
                if (strpos($key,"£")===0){
                    $terms["$".substr($key,2)]= preg_replace('~^[\'"]?(.*?)[\'"]?$~', '$1', $value); 
                }
            }
            $frmBid=General::getGetOrPost("BID");
            $res=$this->execWorker($wid,$sid,$frmBid,$terms,$cmd);
        }
        return $res; 
    }

    public function execWorker($wid, $sid, $bid, $terms,$cmd=""){
        $res=null;

        if ($sid==""){
            // startup
            $LNG=trim(strtoupper(General::getGetOrPost("LNG")));
            $APP=General::getGetOrPost("APP");
            if (empty($LNG))
                $LNG="IT";
            
            $wSess=new Session(null); 
            if (is_numeric($wid)){
                $w_id=$wid;
            } else 
                $w_id=HandlerNC::WID2Wofoid($wid,$wSess);

            $IP=General::getCallerIPAddress();
            $UA=General::getCallerUserAgent();
            $userId=0;
            $wSess->createNew($w_id,$IP,$LNG,$UA,$userId,$APP,$wid);
            $sid=$wSess->getId();

        } else {
            //ripetizone
            $wSess=new Session($sid);
            if ($wSess->isExpired())
                die(json_encode(["error"=>"This session has expired - E89"]));
            else {
                $widd=$wSess->getWid();
                if ($wid=="")
                    $wid=HandlerNC::Wofoid2WID($widd);
                $w_id=HandlerNC::WID2Wofoid($wid,$wSess);

                // muove su altro workflow?
                if (!is_null($w_id) &&!is_null($widd) && $w_id>0 && $widd>0 && $widd!=$w_id) {
                    if (!empty($sid))
                        $frmBid=General::montanara($bid,str_replace("-","",$sid));
                    if (!empty($frmBid)){
                        $wSess->moveTo($wid, "", $frmBid);
                        $bid=$frmBid;
                    }
                    $w_id=$widd;
                }
            } 
        }
        $_nwLNG=General::getGetOrPost("LNG");
        if ($_nwLNG!=""){
            $LNG=strToUpper($_nwLNG);
            $wSess->setLang($LNG);
        }

        if ($cmd=="set"){
            //recupera il JSON con nome/valore
            $settings=json_decode(General::getGetOrPost("SET"),true);
            //$LNG=General::getGetOrPost("LNG");
            $APP=General::getGetOrPost("APP");
        }
        
        if (is_null($wSess))
            die(json_encode(["error"=>"Session is NULL - 800A"]));
        else if ($wSess->isWorkflowInError())
            die(json_encode(["error"=>"Workflow load on error - E00"]));
        else if ($wSess->isExpired())
            die(json_encode(["error"=>"This session has expired - E89"]));
        else if (!$wSess->isWorkflowActive())
            die(json_encode(["error"=>"This workflow is inactive - E99"]));
        else {
            $isTimedCall=General::getGetOrPost("TCV");
            if ($isTimedCall==true){
                $wSess->setTimedCalled(true);
            }

            $wwork= new Worker($wSess);

            // -----------------------------------------
            //  VERIFICA E GESTIONE FILE ATTACH
            //  se c'é un file, viene caricato e salvato
            //  l'oggeto di ritorno contiene i dati
            //  la sessione ha registrato l'URL nella
            //  var [nomevar]_uri 
            // -----------------------------------------
            $wcmd= new Command();
            $attachedFile=$wcmd->fileCheckExtract($wSess,$wwork,$bid,$terms);                                                                                                                                                                                                                                                                                                                                                                                                                                              
            $terms=$attachedFile->Terms;
            // ------------------------------------------

            if (!empty($sid)){
                $frmBid2=General::montanara($bid,substr(str_replace("-","",$sid),5,5));
                if (!empty($frmBid2))
                    $bid=$frmBid2;
            }
            if (empty($bid))
                $bid=$wSess->getBlockId();
            $restart=false;
            $restRows=0;

            $hres=$wwork->execNextBlock($bid,$terms,$restart);
            $frmBid=$wwork->getBlockId();
            // APPLICARE HTMLSANITIZE PRIMA DI INVIARE!
            $frmElms=$wwork->getExecElements($restart,$restRows);
            //$frmElms["0RD_0"]="";
            if ($restart){
                $frmElms=array_merge($frmElms, array("TITLE$0"=>array($wSess->getWfTitle(),"")));
            }
            $rightOrder="";
            try{
                foreach ($frmElms as $Key => $aElm){
                    $key=$Key;
                    $fe=explode("$",$Key);
                    if (is_array($fe) && count($fe)>1)
                        $key=$fe[0];
                    $Elm=$aElm[0];
                    $Css=$aElm[1];
                    if (is_string($Css) && strpos($Css,"{")===1){
                        $Css=json_decode($Css,true);
                    }
                    if (isset($Css) && is_null($Css) || empty($Css)){
                        $Css=[];
                        $Css["display_info"]=["type"=>"unk"]; //["display_info"]["type"]="unk";
                    }
                switch ($key){
                        case "ITB":

                            break;
                        case "L":
                            $frmElms[$Key]=array(Command::htmlSanitize($Elm,$wSess->getVarValue("$"."isTelegram")),$Css);
                            //break;
                        case "ITM":
                        case "ITT":
                            $aVal="[val]:";
                            if (count($aElm)>2)
                                $aVal=$aElm[2];
                            //$Elm=json_encode($Elm, JSON_HEX_QUOT || JSON_HEX_APOS ); 
                            //$aVal=json_encode($aVal, JSON_HEX_QUOT || JSON_HEX_APOS ); 
                            $frmElms[$Key]=array($Elm,$Css,$aVal);
                            $frmElms[$Key]=array(Command::htmlSanitize($Elm,$wSess->getVarValue("$"."isTelegram")),$Css,$aVal);
                            break;
                        case "ITS":
                            $aVal="[val]:";
                            if (count($aElm)>2);
                                $aVal=$aElm[2];
                            break;
                        case "M":
                            if (strpos($Elm,'flussu_qrc')===false){
                                if (isset($Css) && is_array($Css)){
                                    if (is_array($Css["display_info"])) {
                                        $Css["display_info"]["type"]="unk";
                                    } else {
                                        $a=$Css["display_info"];
                                        $Css["display_info"]=[];
                                        $Css["display_info"]["type"] = "unk";      
                                        $Css["display_info"] = $a;      
                                    }
                                }
                                $ext = strtolower(pathinfo($Elm, PATHINFO_EXTENSION));
                                switch ($ext){
                                    case "jpg":
                                    case "jpeg":
                                    case "gif":
                                    case "svg":
                                    case "png":
                                        if (isset($Css) && is_array($Css))
                                        {
                                            if (is_array($Css["display_info"])) {
                                                $Css["display_info"]["type"]="image";
                                            } else {
                                                $a=$Css["display_info"];
                                                $Css["display_info"]=[];
                                                $Css["display_info"]["type"] = "image";      
                                                $Css["display_info"] = $a;      
                                            }
                                        }
                                        //$frmElms[$Key]= "<img $Css src='$Elm'><br>";
                                        break;
                                    case "mp4":
                                    case "avi":
                                    case "mpg":
                                    case "mpeg":
                                        if (isset($Css) && is_array($Css))
                                            $Css["display_info"]["type"]="movie";
                                        //$frmElms[$Key]="<video $Css controls><source src='$Elm' type='video/$ext'>Your browser does not support the video tag.</video>";
                                        break;
                                    default:
                                    if (isset($Css) && is_array($Css))
                                        $Css["display_info"]["type"]="file";
                                    //$frmElms[$Key]="<a $Css target='_blank' href='$Elm'>download</a>";
                                }
                                //$Elm=Command::htmlSanitize($Elm);
                            } else {
                                if (isset($Css) && is_array($Css))
                                    $Css["display_info"]["type"]="qrcode";
                                //$frmElms[$Key]=$Elm;
                            }
                            $frmElms[$Key]=array($Elm,$Css);
                    }
                    //if (strpos($Key,"0RD_")===false && strpos($Key,"TITLE")===false)
                    //    $rightOrder.=$Key.",";
                }
            } catch (\Throwable $e){
                General::Log("INTERNAL ERROR! Wid:".$wid." - Bid:".$frmBid." (".$frmBid.") - Sid:".$sid."\n - - ".json_encode($e->getMessage()),true);
                return "Internal exec exception: [1] - ".var_export($e,true);
            }
            //$frmElms["0RD_0"]=$rightOrder;
            if (!isset($frmBid)){
                // SEMBRA SIA L'ULTIMO BLOCCO!
                $res=[
                    $frmElms["END$"] => array("finiu","stop")
                ];
                return $res;
            }
            $res=[
                "sid"=>$sid,
                "bid"=>$frmBid,
                "elms"=>$frmElms
            ];
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