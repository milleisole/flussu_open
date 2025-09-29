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
 
 La classe Worker contiene il codice che elabora le 
 operazioni del processo:
    - Startup
    - Esecuzione di uno step

 E' un componente FONDAMENTALE del sistema e le modifiche
 vanno fatte con MOLTA attenzione

 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Flussuserver
 * CLASS NAME:       Worker
 * CLASS-INTENT:     Work Dispatcher/executor
 * USE ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     04.07:2020 - Aldus - Flussu v1.3
 * VERSION REL.:     4.5.1 20250820 
 * UPDATED DATE:     20.08:2025 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/
/*
    In pratica la classe WORKER è il cuore dell'elaborazione di Flussu, in questa classe infatti
    si inizia e si conclude ogni elaborazione di uno step. 
    A causa dell'atomicità di esecuzione di uno step, a volte è necessario eseguire una elaborazione
    per poi procedere a quella successiva aspettando che lo step precedente riempa di dati le variabili
    necessarie nello step successivo, sicchè se gli step sono disegnati per essere eseguiti in sequenza
    (senza uno stop), Worker eseguirà gli step successivi contigui fino a quando non finisce il processo
    o non c'è uno stop (Es. richiesta di eseguire una scelta, come la visualizzazione di un button).
*/
/**
 * The Worker class is responsible for managing and executing various operations within the Flussu server.
 * 
 * This class handles the startup process and the execution of individual steps in the workflow. It acts as
 * a dispatcher and executor for different tasks, ensuring that each operation is performed correctly and
 * efficiently. The Worker class is a fundamental component of the system, and modifications to it should be
 * made with great care.
 * 
 * Key responsibilities of the Worker class include:
 * - Managing the startup process of the Flussu server.
 * - Executing individual steps in the workflow.
 * - Dispatching tasks to the appropriate handlers and components.
 * - Handling exceptions and errors during the execution of tasks.
 * - Integrating with external APIs and controllers such as MultiWfController, OpenAi, PdfController, and OpenAiController.
 * - Ensuring the smooth and efficient operation of the server by coordinating various components and processes.
 * 
 * The class is designed to be robust and reliable, providing a central point for managing the execution flow
 * within the Flussu server.
 * 
 */

namespace Flussu\Flussuserver;
use \Throwable;
use Flussu\Flussuserver\Handler;
use Flussu\General;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class Worker {
  
  private $_WofoS;
  private $_WofoD;
  private $_ExecR;
  private $_xcBid;
  private $_xcelm=array();
  private $_exitNum=-1;
  private $_en=0;
  private $_secureALooper=0;
  private $_envCount = 0;
  private $_sendRequestUserInfo=false;
  private $_sendRequestUserInfoVarName="";
/*-------------------------------------------------------------------------------------------------------
    CLASS
  -------------------------------------------------------------------------------------------------------*/

    /**
     * > This function is the constructor of the class. It takes a Session object as a parameter and
     * sets it to the private variable _WofoS. It also creates a new Handler object and sets it to the
     * private variable _WofoD. It also creates a new FlussuCommand object and sets it to the private
     * variable _WofoC. It also sets the private variable _envCount to the current time in
     * microseconds. Finally, if the Session object is starting, it calls the private function
     * _execStartBlock
     */
    public function __construct (Session $Session){
        $this->_WofoS = $Session;
        $this->_WofoD = new Handler();
        //$this->_WofoC = new FlussuCommand();
        $this->_envCount=round(microtime(true) * 100);
        if ($this->_WofoS->isStarting()){
            $this->_execStartBlock();
        }
    }

   /**
    * > The function is a clone function that clones the object and its properties
    */
    function __clone(){
        $this->_WofoS = clone $this->_WofoS;
        $this->_WofoD = clone $this->_WofoD;
        //$this->_WofoC = clone $this->_WofoC;
    }

    /**
     * A destructor. It is called when the object is destroyed.
     */
    public function __destruct(){
        //if (\General::$Debug) echo "[Distr Databroker ]<br>";
    }
    

/*-------------------------------------------------------------------------------------------------------
    DATA/VARS/PREP
  -------------------------------------------------------------------------------------------------------*/
  /**
   * It assigns a value to a variable, and if the variable is an exit, it sets the exit number.
   * 
   * Args:
   *   key: The name of the variable to assign.
   *   value: The value to be pushed.
   *   execBlock: The block that is currently being executed.
   */
    public function pushValue($key,$value,$execBlock=null){
        if (substr($key,0,4)=="\$ex!"){
            // SET EXIT --> JUST IF A VARNAME IS SET.
            $this->_WofoS->assignHistory($execBlock,array("B",$value));
            $exitNum=intval(substr($key,4));
            $this->_exitNum=$exitNum;
            /*if ($execBlock!=null){
                $vname=$this->_WofoD->getElemVarNameForExitNum($execBlock,$exitNum,$this->_WofoS->getLang());
                if (is_array($vname)){
                    $this->_WofoS->assignVars($vname[0],$vname[1]);
                }
            }*/
        } else {
            $this->_WofoS->assignVars($key,$value);
            $this->_WofoS->assignHistory($execBlock,array("R",$value));
        }
    }

    public function getTitle(){
        
    }

    public function choosedExit(){     return $this->_exitNum; }
    public function getBlockId(){      return $this->_xcBid;   }
    /**
     * It takes the history of the last  rows of the spreadsheet and adds them to the current
     * spreadsheet
     * 
     * Args:
     *   isRestart: if true, the history rows are added to the beginning of the array.
     *   restRows: the number of rows to be restarted.
     */
    public function getExecElements($isRestart=false, $restRows=0){ 
        if ($isRestart){
            $nXlm=[];
            $hRows=$this->_WofoS->getHistoryRows($restRows,$this->_xcBid);
            $a="";
            $p="";
            $i=0;
            $prow="";
            foreach ($hRows as $row){
                $a=array($row[0]."\$h".($i++) => array($row[1],""));
                if ($row!=$prow)
                    $nXlm=array_merge($nXlm, $a);
                $prow=$row;
            }
            $nXlm=array_merge($nXlm, $this->_xcelm);
            return $nXlm;
        }
        else
            return $this->_xcelm;   
    }


    /**
     * It replaces all the variables in the string with their values
     * 
     * Args:
     *   labelText: the text to be parsed
     *   usaVirgolette: if true, the  will be replaced with the value of the variable, if
     * false, the  will be replaced with the jValue of the variable.
     * 
     * Returns:
     *   The value of the variable.
     */
    private function _strReplace($labelText,$usaVirgolette=false){
        $num=preg_match_all( "#\\$(\w+)#", $labelText, $match );
        //echo json_encode($match[0][1]);
        for ($i=0;$i<$num;$i++){
            $theVar=$this->_WofoS->getVar($match[0][$i]);
            if ($theVar!==false){
                if ($usaVirgolette)
                    $toSubst=$theVar->jValue;
                else
                    $toSubst=$theVar->value;

                if (!is_array($toSubst) && !is_object($toSubst)){
                    $labelText = str_replace($match[0][$i],$toSubst,$labelText);
                } else {
                    $arrText="{";
                    foreach ($toSubst as $key => $value){
                        try{
                            if (is_array($value))
                                $value=\json_encode($value);
                            $arrText.="\"$key\":\"$value\",";
                        } catch (Throwable $e){
                            $e->getMessage();
                        }
                    }
                    $arrText=substr($arrText, 0, -1)."}";
                    $labelText = str_replace($match[0][$i],$arrText,$labelText);
                }
            }
        }
        return $labelText;
    }
    
/*-------------------------------------------------------------------------------------------------------
    WORK
  -------------------------------------------------------------------------------------------------------*/

 /* -------------------------------------------------
      Esegue le istruzioni del blocco di START
    ------------------------------------------------- */
    /**
     * > Execute the start block of the current workflow
     */
    private function _execStartBlock(){
        $functions="";
        $this->_WofoS->recLog("Exec start block.");
        $blk=$this->_WofoS->getBlockId();
        $theBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$blk,"");
        $fncBlk=$this->_WofoD->getBlockUuidFromDescription($this->_WofoS->getWid(),"[[FUNCTIONS]]");
        if($fncBlk){
            $functions=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$fncBlk,"")["exec"];
            $this->_WofoS->setFunctions($functions);
        }
        $this->_WofoS->setExecBid($theBlk["block_id"]);
        $xcRes=$this->_doBlockExec($theBlk);
        $nextBlk=$theBlk["exits"][0]["exit_dir"];
        $this->_WofoS->setBlockId($nextBlk);
    }


   /**
    * It takes a string, and returns a string
    * 
    * Args:
    *   frmXctdBid: the block ID of the block to execute
    *   extData: an array of data that will be used to populate the variables in the workflow.
    *   isRestart: true if the workflow is being restarted, false otherwise
    * 
    * Returns:
    *   The return value of the last statement executed in the function.
    */
    function execNextBlock($frmXctdBid,$extData=null,$isRestart=false) {
        $res="";
        $lng=$this->_WofoS->getLang();
        if (is_null($this->_WofoS)){    
            $res="ERR:01 - Worker without session";

            // STATO DI ERRORE?
            $this->_WofoS->statusError(true);

        } else {
            //acquire exernal data
            //Get external data and build req/res element list
            // to rebuild session functionality and/or history
            // needed to rebuild and interruped client session
            if (!is_null($extData)){
                if (is_array($extData) && count($extData)>0){
                    if (isset($extData["arbitrary"]))
                        $ext2Data=$extData["arbitrary"];
                    else
                        $ext2Data=$extData;
                    foreach($ext2Data as $key => $value){
                        if (substr($key,0,1)=="$"){
                            if ($key==$value){
                                // Button da Telegram!
                                $eln=explode("!",$value);
                                if (is_array($eln) && count($eln)==2){
                                    $tmpBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$frmXctdBid, $this->_WofoS->getLang());
                                    foreach($tmpBlk["elements"] as $elm){
                                        if ($elm["c_type"]==2){
                                            try{
                                                if ($elm["exit_num"]==$eln[1]){
                                                    $value=$elm["langs"][$this->_WofoS->getLang()]["label"];
                                                    break;
                                                }
                                            } catch (Throwable $e) {}
                                        }
                                    }
                                }
                            }
                            try{
                                $vvalue=is_array($value)?$value[array_keys($value)[0]]:$value;
                                if (substr($vvalue,0,4)=="@OPT"){
                                    // INTERPRETAZIONE DI OPT!!!
                                    $resArr2=json_decode(substr($vvalue,4),true);
                                    //$resArr2=json_decode(str_replace("@OPT","",$vvalue),true);
                                    $resArr=[];
                                    $j=0;
                                    for ($i=0;$i<count($resArr2);$i+=2){
                                        $resArr[$j++]=explode(",",$resArr2[$i])[0];
                                        $resArr[$j++]=$resArr2[$i+1];
                                    }
                                    // forza l'assegnazione di dati arbitrari anche in fase di START
                                    $this->_WofoS->removeVars($key);
                                    $this->pushValue($key,$resArr,$frmXctdBid);
                                } else {
                                    //$value='"'.str_replace('"','\"',$value).'"';
                                    // forza l'assegnazione di dati arbitrari anche in fase di START
                                    if ($this->_WofoS->isStarting())
                                        $this->_WofoS->removeVars($key);
                                    if (substr($key,0,4)=="$"."ex!" && (strpos($key,";")!==false)){
                                        $parts=explode(";",$key);
                                        $key=$parts[0];
                                        $valValue=General::montanara($parts[1],340);
                                        $parts2=explode(";",$valValue);
                                        $valValue=$parts2[0];
                                        $varName="$".$parts2[1];
                                        $valValue=str_replace(["[SP]","[PV]"],[" ",";"],$valValue);
                                        $this->pushValue($varName, trim($valValue),$frmXctdBid);
                                        //$vvalue="_";
                                    }
                                    $this->pushValue($key, trim($vvalue),$frmXctdBid);
                                }
                            } catch(\Exception $e){
                                $res.="\r\nERROR:".$e->getMessage();
                                $this->_WofoS->recLog($res." - Execution stopped!!!");
                                $this->_WofoS->statusError(true);
                                $this->_WofoS->statusRender(false);
                                return $res;
                            }
                        }
                    }
                    $this->_WofoS->loadWflowVars();
                } 
            } 

            $exit=-1;
            $blkExit=-1;
            if (!isset($frmXctdBid) || empty(trim($frmXctdBid))){
                $frmXctdBid=$this->_WofoS->getBlockId();
            } else {
                if ($this->_exitNum>-1){
                    $res.="\r\n$frmXctdBid -"."> exit:".$this->_exitNum;
                    $theBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$frmXctdBid, $this->_WofoS->getLang());
                    if (is_null($theBlk)){
                        $frmXctdBid=$this->_WofoS->getBlockid();
                        $theBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$frmXctdBid, $this->_WofoS->getLang());
                    }
/*
                    if (!isset($theBlk["exits"][$this->_exitNum]["exit_dir"])){
                        $res.="\r\nNo more blocks or last block...";
                        $this->_xcelm = array_merge($this->_xcelm, array("END$" => array("finiu","stop")));
                        $hasExit=false;
                    }
                    else
*/
                        $frmXctdBid=$theBlk["exits"][$this->_exitNum]["exit_dir"];
                } else {
                    // FORSE ULTIMO BLOCCO?
                    $hasExit=false;
                    if (substr($frmXctdBid,0,3)=="NMB"){
                        // call esterna setting state value a blocco con NOME passato
                        $parts=explode("NMB",$frmXctdBid);
                        $newFrmXctdBid=$this->_WofoD->getBlockUuidFromDescription($this->_WofoS->getWid(),$parts[1]);
                        if ($newFrmXctdBid!=null && !empty($newFrmXctdBid))
                            $frmXctdBid=$newFrmXctdBid;
                        else {
                            try{
                                $WID2=\Flussu\Flussuserver\NC\HandlerNC::WID2Wofoid($this->_WofoS->getVarValue("$"."_MemSeStat")->Wwid);
                                $newFrmXctdBid=$this->_WofoD->getBlockUuidFromDescription($WID2,$parts[1]);
                            } catch (\Throwable $e){
                                $this->_WofoS->recLog($e->getMessage());
                            }
                            if ($newFrmXctdBid!=null && !empty($newFrmXctdBid))
                                $frmXctdBid=$newFrmXctdBid;
                            else 
                                $frmXctdBid=$this->_WofoS->getBlockId();
                        }
                    }
                    $lBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$frmXctdBid,$this->_WofoS->getLang());
                    if ($lBlk){
                        for($i=0;$i<count($lBlk["exits"]);$i++){
                            if ($lBlk["exits"][$i]["exit_dir"]!="0" && $lBlk["exits"][$i]["exit_dir"]!=""){
                                $hasExit=true;
                                break;
                            }
                            //$bExit= $lBlk->exits[$i];
                        }
                    } else {
                        General::log("ERROR: Block not found: ".$frmXctdBid." (Wid:".$this->_WofoS->getWid()." - called from ".$this->_WofoS->getBlockId().")");
                    }
                    if (!$hasExit){
                        $res.="\r\nNo more blocks or last block...";
                        $this->_xcelm = array_merge($this->_xcelm, array("END$" => array("finiu","stop")));
                        // $this->_WofoS->setDurationZero();
                    }
                }
            }

            $arbitrary=null;
            if ($extData!=null && !empty($extData) && is_array($extData) && isset($extData["arbitrary"])){
                if (is_array($extData["arbitrary"])){
                    $arbitrary=$extData["arbitrary"];
                } else
                    $arbitrary=json_decode(str_replace("'","\"",$extData["arbitrary"]),true);
                unset($extData["arbitrary"]);
            }

            $i=0;
            do {
                $this->_WofoS->statusRender(true);
                $theBlk=$this->_WofoD->buildFlussuBlock($this->_WofoS->getWid(),$frmXctdBid, $this->_WofoS->getLang());
                if (!isset($theBlk))
                    break;
                else
                    $this->_WofoS->setExecBid($theBlk["block_id"]);
                $this->_WofoS->recUseStat($this->_WofoD->getBlockIdFromUUID($frmXctdBid),$extData);
                $extData="";

//////////////////////////////////////////////////////////////////////////////////////////                
                
                // ===========================================
                // INSERIMENTO DATI ARBITRARI PRIMA DELL'EXEC
                // ===========================================
                // Viene eseguito dopo l'esecuzione del blocco
                // per il quale i dati arbitrari sono inviati
                // ===========================================
                $arbArray=array();
                if ($arbitrary!=null){
                    $this->_WofoS->recLog("START ACQUIRE ARBITRARY DATA");
                    $this->_WofoS->assignHistory("<ARBD_START>","");
                    try{
                        foreach($arbitrary as $key => $value){
                            if (substr($key,0,1)!="$")
                                $key="$".$key;

                            $key=str_replace("_AL2905","_outerCallerUri",$key);
                            $key=str_replace("_FD0508","_scriptCallerUri",$key);

                            if ($key=="$"."_outerCallerUri" || $key=="$"."_scriptCallerUri"){
                                // Potrebbe essere una pagina esterna con una form interna che 
                                // riporta il valore WID, che non si troverebbe quindi QUI.
                                if (!is_null($value) && !empty($value) && trim($value)!="null"){
                                    if (strpos($value,"WID=")===false){
                                        $parts=explode("?",$value);
                                        if (count($parts)<2)
                                            $parts=explode("&",$value);
                                        $url=$parts[0]."?WID=".$this->_WofoS->getStarterWID();
                                        if (count($parts)>1){
                                            for($i=1;$i<count($parts);$i++)
                                                $url.="&".$parts[$i];
                                        } /*else {
                                            $url=$value."?WID=".$this->_WofoS->getStarterWID();
                                        }*/
                                        $value=$url;
                                    }
                                } else {$value="";}
                                $isStarting=true;
                            }

                            //$value='"'.str_replace('"','\"',$value).'"';
                            $value=$this->sanitizeExec($value,$frmXctdBid);

                            $this->pushValue($key, trim($value),$frmXctdBid);
                            array_push($arbArray,$key);

                        }
                    } catch(\Exception $e){
                        $res.="\r\nERROR:".$e->getMessage();
                        $this->_WofoS->recLog($res." - Execution stopped!!!");
                        $this->_WofoS->statusError(true);
                        $this->_WofoS->statusRender(false);
                        return $res;
                    }
                    $this->_WofoS->assignHistory("<SESS_START>","");
                    $this->_WofoS->recLog("END ACQUIRE ARBITRARY DATA");
                    $arbitrary=null;
                }

//////////////////////////////////////////////////////////////////////////////////////////


                $res.="\r\n$frmXctdBid [".$theBlk["description"]."]";
                // Verificare presenza/valorizzazione variabili
                $this->_xcBid=$theBlk["block_id"];
                $xcRes=$this->_doBlockExec($theBlk,$arbArray);
                
                if (count($theBlk["elements"])==0){
                    // Blocco di sola esecuzione
                    // Se non è quello finale, si dovrà elaborare e poi scegliere quello successivo.
                    $blkExit=0;
                    if (is_array($xcRes)){
                        if (!empty($xcRes[0]) && is_array($xcRes[0])){
                            if ($xcRes[0][0]=="exit")
                                $blkExit=intval($xcRes[0][1]);
                        } 
                    }
                    $exit=-1;
                    // edit da considerare per scegliere il blocco successivo
                } else {
                    // CI SONO ELEMENTI
                    // Blocco con visualizzazione
                    //NON BLOCKING '0'=MESSAGE,, '3'=MEDIA(URL), '4'=LINK(URL), '5'=TXT_ASSIGN
                    //BLOCKING '1'=INPUT, '2'=BUTTON

                    // ------------------------------2.2.1
                    // Uscita forzata?
                    $frcBlkExit=false;
                    $blkExit=-1;
                    if (is_array($xcRes)){
                        if (!empty($xcRes[0]) && is_array($xcRes[0])){
                            if ($xcRes[0][0]=="exit"){
                                $blkExit=intval($xcRes[0][1]);
                                //$exit=1;
                                $frcBlkExit=true;
                            }
                        } 
                    }
                    // ------------------------------2.2.1

                    if (!$frcBlkExit){
                        $this->_WofoS->cleanLastHistoryBid($this->_xcBid);
                        $lng=$this->_WofoS->getLang();
                        $this->_WofoS->assignVars("$"."lastLabel","");
                        $elements=$theBlk["elements"];
                        if (isset($xcRes["addElements"])){
                            // Verifico button esistenti
                            $to_move=[];
                            foreach ($theBlk["elements"] as $key=>$theElem){
                                if ($theElem["c_type"]==2)
                                    $to_move[$key]=$theElem;
                            }
                            // Aggiungo gli elementi aggiuntivi
                            $order=count($elements)+1;
                            foreach ($xcRes["addElements"] as $newElem){
                                $newElemGen["elem_id"]=str_replace(".","-",uniqid("a7D0-",true))."-FEDE";
                                $newElemGen["e_order"]=$order++;
                                $newElemGen["langs"][$lng]["label"]=$newElem["text"];
                                
                                if (isset($newElem["css"]))
                                    $newElemGen["css"]["class"]=$newElem["css"];
                                else
                                    $newElemGen["css"]["class"]="";
                                
                                $newElemGen["css"]["display_info"]=[];
                                $newElemGen["note"]="generated";
                                $newElemGen["exit_num"]="0";
                                switch ($newElem["type"]){
                                    case "IE":
                                    case "IS":
                                    case "IM":
                                        if ($newElem["mandatory"])
                                            $newElemGen["css"]["display_info"]["mandatory"]=true;
                                        if ($newElem["type"]=="IE")
                                            $newElemGen["css"]["display_info"]["subtype"]="e-mail";
                                        else if ($newElem["type"]=="IM")
                                            $newElemGen["css"]["display_info"]["subtype"]="textarea";
                                        $newElemGen["langs"][$lng]["uri"]="";                                        
                                        $newElemGen["value"]=$newElem["value"];
                                        $newElemGen["var_name"]="$".$newElem["varname"];
                                        $newElemGen["c_type"]="1";
                                        $newElemGen["d_type"]="INPUT";
                                        break;
                                    case "SS":
                                    case "SE":
                                    case "SM":
                                        $newElemGen["c_type"]="6";
                                        $newElemGen["d_type"]="SELECTION";
                                        if ($newElem["mandatory"])
                                            $newElemGen["css"]["display_info"]["mandatory"]=true;
                                        if ($newElem["type"]=="SE")
                                            $newElemGen["css"]["display_info"]["subtype"]="exclusive";
                                        if ($newElem["type"]=="SM")
                                            $newElemGen["css"]["display_info"]["subtype"]="multiple";
                                        $newElemGen["var_name"]="$".$newElem["varname"];
                                        if ($newElem["mandatory"])
                                            $newElemGen["css"]["display_info"]["mandatory"]=true;
                                            foreach($newElem["values"] as $value){
                                                $newElemGen["langs"][$lng]["label"][$value[0].",0"]=$value[1];
                                            }
                                        break;
                                    case "B":
                                        //add Button
                                        $newElemGen["value"]=$newElem["value"];
                                        $newElemGen["var_name"]="$".$newElem["varname"];
                                        $newElemGen["c_type"]="2";
                                        $newElemGen["d_type"]="BUTTON";
                                        $elmValue=str_replace([" ",";"],["[SP]","[PV]"],$newElem["value"]).";".$newElem["varname"];
                                        $valValue=General::curtatone(340,$elmValue);
                                        $newElemGen["exit_num"]=$newElem["exit"].";".$valValue;
                                        if ($newElemGen["skipValid"]) {
                                            $newElemGen["css"]["display_info"]["subtype"]="skip-validation";
                                        }
                                        break;
                                    default:
                                        //add Label
                                        $newElemGen["var_name"]="";
                                        $newElemGen["c_type"]="0";
                                        $newElemGen["d_type"]="LABEL";
                                        break;
                                }
                                $elements=array_merge($elements,[$newElemGen]);
                            } 
                            $moved = [];
                            foreach ($to_move as $val) {
                                $key = array_search($val, $elements);
                                if ($key !== false) {
                                    $moved[] = $elements[$key];
                                    unset($elements[$key]);
                                }
                            }
                            $elements = array_values($elements);
                            $elements = array_merge($elements, $moved);
                        }
                        foreach ($elements as $elem){
                            $lbl=$elem["langs"][$lng]["label"];
                            if (is_array($lbl)){
                                $origLbl=json_encode($lbl);
                            } else {
                                $origLbl=$lbl;
                                if (!empty($lbl!="") && !(strpos($lbl,"$")===false)){
                                    $lbl=$this->_strReplace($lbl);
                                }
                            }
                            if (isset($elem["langs"][$lng]["uri"]))
                                $uri=$elem["langs"][$lng]["uri"];
                            $origUri=$uri;
                            if (!empty($uri) && !(strpos($uri,"$")===false)){
                                $uri=$this->_strReplace($uri);
                            }
                            if ($elem["c_type"]==2)
                                $exit=1;
                            if (is_numeric($elem["exit_num"]))
                            $this->_en++;
                            switch($elem["c_type"]){
                                case 0:
                                    $res.="\r\n    Show (text) \"$origLbl\"";
                                    $this->_xcelm = array_merge($this->_xcelm, array("L$".$this->_en => array($lbl,$elem["css"])));
                                    $this->_WofoS->assignVars("$"."lastLabel",$lbl);
                                    $this->_WofoS->assignHistory($this->_xcBid,["L",$lbl]);
                                    break;
                                case 3:
                                    $res.="\r\n    Show (media) \"$origUri\"";
                                    $this->_xcelm = array_merge($this->_xcelm, array("M$".$this->_en => array($uri,$elem["css"])));
                                    $this->_WofoS->assignHistory($this->_xcBid,["M",$uri]);
                                    break;
                                case 4:
                                    $res.="\r\n    Link '$origUri'";
                                    if ($lbl!="")
                                        $this->_xcelm = array_merge($this->_xcelm, array("A$".$this->_en => array($lbl."!|!".$uri,$elem["css"])));
                                    else
                                        $this->_xcelm = array_merge($this->_xcelm, array("A$".$this->_en => array($uri,$elem["css"])));

                                    $this->_WofoS->assignHistory($this->_xcBid,["A",$uri]);
                                    break;
                                case 5:
                                    $res.="\r\n    Assign text";
                                    $this->_WofoS->assignVars($elem["var_name"],$lbl);
                                    break;
                                case 6:
                                    $res.="\r\n    Select \"$origLbl\"";
                                    /*if (is_null(json_decode($lbl))){
                                        $lba=\json_encode(eval("return ".str_replace("\\\"","\"",$lbl).";"));
                                        $this->_xcelm = array_merge($this->_xcelm, array("ITS".$elem["var_name"] => array($lba,$elem["css"],"[val]:".json_encode($this->_WofoS->getVarValue($elem["var_name"])))));
                                    } else {*/
                                        $lbl2=[];
                                        foreach($lbl as $key => $value){
                                            $key=explode(",",$key)[0];
                                            $lbl2[$key]=$value;
                                        }
                                        if (count($lbl2)>0 && $lbl2[array_keys($lbl2)[0]]!=""){
                                            $this->_WofoS->assignVars("$"."AR_".substr($elem["var_name"],1),$lbl2);
                                        } else {
                                            // E' possibile creare l'array dei valori nel codice usando "AR_" + nome Variabile Select
                                            // e lasciando la select vuota da qualsiasi valore. 
                                            $lbl3=[];
                                            $lbl=$this->_WofoS->getVarValue("$"."AR_".substr($elem["var_name"],1));
                                            foreach($lbl as $key => $value){
                                                $key=explode(",",$key)[0];
                                                $lbl3[$key.",0"]=$value;
                                            }
                                            if (count($lbl3)>0 && $lbl3[array_keys($lbl3)[0]]!=""){
                                                $lbl2=$lbl;
                                                $lbl=$lbl3;
                                                $elem["langs"][$lng]["label"]=$lbl;
                                                //$this->_WofoS->assignVars("$"."AR_".substr($elem["var_name"],1),$lbl2);
                                            }
                                        }
                                        $ev=is_null($this->_WofoS->getVarValue($elem["var_name"]))?[]:$this->_WofoS->getVarValue($elem["var_name"]);
                                        $this->_xcelm = array_merge($this->_xcelm, array("ITS".$elem["var_name"] => array(json_encode($lbl),$elem["css"],"[val]:".json_encode($ev))));
                                    //}
                                    break;
                                case 7:
                                    $res.="\r\n    Upload file \"$origLbl\"";
                                    $this->_xcelm = array_merge($this->_xcelm, array("ITM".$elem["var_name"] => array($lbl,$elem["css"])));
                                    break;
                                case 1:
                                    $res.="\r\n    Ask \"$origLbl\"";
                                    $_elmValue=$this->_WofoS->getVarValue($elem["var_name"]);
                                    if (is_null($_elmValue)) 
                                        $_elmValue="";
                                    $this->_xcelm = array_merge($this->_xcelm, array("ITT".$elem["var_name"] => array($lbl,$elem["css"],"[val]:".$_elmValue)));
                                    $this->_WofoS->assignHistory($this->_xcBid,["S",$lbl]);
                                    break;
                                case 2:
                                    $res.="\r\n    Button [$origLbl]";
                                    $this->_xcelm = array_merge($this->_xcelm, array("ITB$".$elem["exit_num"] => array($lbl,$elem["css"])));
                                    // BLKEXIT?
                                    $exit=1;
                                    break;
                            }
                        }
                        if ($this->_sendRequestUserInfo===true){
                            $this->_xcelm = array_merge($this->_xcelm, array("GUI$".$this->_sendRequestUserInfoVarName => array('','',"[val]:")));
                        }
                    }
                    if ($blkExit<=0){
                        if (is_array($xcRes)){
                            if (count($xcRes)>0 && !empty($xcRes[0]) && is_array($xcRes[0])){
                                if ($xcRes[0][0]=="exit")
                                    $blkExit=intval($xcRes[0][1]);
                            } else
                                $blkExit=0;
                        } else
                            $blkExit=0;
                    }
                }

                if (count($theBlk["exits"])>0)
                    $nextBlk=$theBlk["exits"][$blkExit]["exit_dir"];
                else{
                    // E' un blocco di RETURN
                    $nextBlk="";
                    $exit=0;
                }
                
                if ($exit>0) {
                    $res.="\r\nDONE.";   
                    break;
                }
                $blkExit=0;

                // ==========================================
                //  SUB-WORKFLOW HANDLERS
                // ==========================================

                if (is_array($xcRes) && count($xcRes)>0 && array_key_exists(0,$xcRes)){
                    if ($xcRes[0][0]=="WID"){
                        // sub wid call
                        $res.="\r\nGo to WID ".$xcRes[0][1];
                        $retBlockId=$nextBlk;
                        $nextBlk=$this->_WofoS->moveTo($xcRes[0][1],$retBlockId);
                        $this->_WofoS->setBlockId($nextBlk);
                        $frmXctdBid=$nextBlk;
                    } elseif ($xcRes[0][0]=="BACK"){
                        // ritorno dopo call
                        $res.="\r\nBack to WID ".$xcRes[0][1];
                        $nextBlk=$this->_WofoS->moveBack($xcRes[0][1]); 
                        //$theBlk=$this->_WofoD->buildFlofoBlock($this->_WofoS->getWid(),$frmXctdBid, $this->_WofoS->getLang());
                        //$nextBlk=$theBlk["exits"][0]["exit_dir"];
                    }
                }
                // ==========================================

                if (is_null($nextBlk) || empty($nextBlk) || strlen($nextBlk)<10){
                    // BLOCCO SCONNESSO???
                    // STATO DI ERRORE?
                    $res.="\r\nNo more blocks or last block...";
                    $this->_xcelm = array_merge($this->_xcelm, array("END$" => array("finiu","stop")));
                    $this->_WofoS->statusRunning(false);
                    $this->_WofoS->statusEnd(true);
                    $this->_WofoS->setSessionEnd($this->_xcBid);
                    $this->_WofoS->setDurationZero();
                    break;  
                } else {
                    $this->_WofoS->setBlockId($nextBlk);
                    $frmXctdBid=$nextBlk;
                }

                $this->_WofoS->statusRunning(true);
                // infinity loop safer
                if ($i++>256){
                    // STATO DI ERRORE?
                    $this->_WofoS->statusError(true);
                    $this->_WofoS->recLog("INTERNAL ERROR: Forced exit from infinite loop!!!");
                    $res.="\r\nINTERNAL ERROR: Forced exit from infinite loop!!!";
                    break;
                } 
                $res.="\r\nDONE. "; //(next: ".$frmXctdBid.")";
            } while(true);
        }
        $this->_WofoS->statusRender(false);
        $this->_WofoS->recLog($res);
        return $res;
    }

    
 /* ---------------------------------------------------
      Esecuzione del codice all'interno di un blocco
    --------------------------------------------------- */
   
   /**
    * It replaces the contents of a string with the contents of another string, starting at a given
    * position and replacing a given number of characters
    * 
    * Args:
    *   errMsg: The error message.
    */
    private function _sanitizeErrMsg($errMsg){
        $i=0;
        do{
            $i3=strpos($errMsg, "array (");
            if ($i3!==false){
                $j=strpos($errMsg, "\t)",$i3);
                if ($j!==false)
                    $errMsg=substr_replace($errMsg, '(', $i3,($j-$i3)-5);
                else
                    $errMsg=substr($errMsg,$i3+7,-1);
            }

            $i0=strpos($errMsg, "'file' =>");
            if ($i0!==false){
                $j=strpos($errMsg, "\n",$i0);
                $errMsg=substr_replace($errMsg, '\'file\' ** *************', $i0,$j-$i0);
            }
            $i1=strpos($errMsg, "'class' =>");
            if ($i1!==false){
                $j=strpos($errMsg, "\n",$i1);
                $errMsg=substr_replace($errMsg, '\'class\' ** *************', $i1,$j-$i1);
            }
            $i2=strpos($errMsg, "'function' =>");
            if ($i0===$i1 && $i2===$i1 && $i3===false) break;
            $j=strpos($errMsg, "\n",$i2);
            $errMsg=substr_replace($errMsg, '\'function\' ** *************', $i2,$j-$i2);
        } while ( $i++ < 1000 );
        return $errMsg;
    }


    /**
     * It removes comments from a PHP code
     * 
     * Args:
     *   code: The code to be executed.
     * 
     * Returns:
     *   the value of the variable .
     */
    private function removeComments($code){
        $pcode=$code;
        $vars=[];
        if (!empty($code)){
            try{
                $pcode = preg_replace('!/\*.*?\*/!s', '', $code);
                $pcode = preg_replace('/\n\s*\n/', "\n", $pcode);
                $pcode = preg_replace('/^[ \t]*[\r\n]+/m', '', $pcode);
                $rows=explode("\n",$pcode);
                $rcode="";
                foreach ($rows as $row) {
                    if (strpos(trim($row),"//")===0){
                        // do nothing
                    } else {
                        $rcode .=$row."\n";
                    }
                }
                $pcode=$rcode;
            } catch(Throwable $e){
                // Fai qualcosa o niente
            }
        }
        return $pcode;
    }

/**
 * It takes a string of PHP code and returns an array of all the variables used in that code
 * 
 * Args:
 *   code: the code to be parsed
 * 
 * Returns:
 *   array(
 *         '',
 *         '',
 *         '',
 *         '',
 *         '',
 *         '',
 *         '',
 *         '',
 *         '',
 *         '
 */
    function getCodeVars($code){
        $vars = array();
        if (!empty($code)){
            try{
                $tokens = token_get_all('<?php '.$code);
                $last_token = false;
                foreach ($tokens as $token) {
                    if (is_array($token)) {
                        if ($token[0] == T_VARIABLE) {
                            if (!in_array($token[1], $vars))
                                $vars[] = $token[1];
                        }
                    }
                }
            } catch(Throwable $e){
                // Fai qualcosa o niente
            }
        }
        return $vars;
    }

    function wrapFunctionsWithExistsCheck($code) {
        // Espressione regolare per trovare le dichiarazioni di funzione
        $pattern = '/function\s+(\w+)\s*\(/';
    
        // Trova tutte le corrispondenze
        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
            $functions = $matches[1]; // nomi delle funzioni con le loro posizioni
    
            // Processa le funzioni in ordine inverso per mantenere corretti gli offset delle stringhe
            $functions = array_reverse($functions);
    
            foreach ($functions as $func) {
                $funcName = $func[0];
                $namePos = $func[1];
    
                // Trova la posizione iniziale della parola chiave 'function'
                $functionPos = strrpos(substr($code, 0, $namePos), 'function');
    
                // Trova la posizione della parentesi graffa '{' dopo la dichiarazione della funzione
                $openBracePos = strpos($code, '{', $namePos);
                if ($openBracePos === false) {
                    continue; // Nessun corpo della funzione trovato
                }
    
                // Trova la parentesi graffa di chiusura '}'
                $closeBracePos = $this->findMatchingBrace($code, $openBracePos);
                if ($closeBracePos === false) {
                    continue; // Parentesi non corrispondenti
                }
    
                // Estrae l'intera definizione della funzione
                $functionCode = substr($code, $functionPos, $closeBracePos - $functionPos + 1);
    
                // Costruisce il codice sostitutivo
                $wrappedFunctionCode = "if (!function_exists('$funcName')) {\n" . $functionCode . "\n}\n";
    
                // Sostituisce il codice originale della funzione con il codice avvolto
                $code = substr_replace($code, $wrappedFunctionCode, $functionPos, $closeBracePos - $functionPos + 1);
            }
        }
    
        return $code;
    }
    
    // Funzione di supporto per trovare la parentesi graffa di chiusura corrispondente
    function findMatchingBrace($code, $startPos) {
        $len = strlen($code);
        $depth = 0;
        for ($i = $startPos; $i < $len; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } elseif ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false; // Nessuna parentesi di chiusura trovata
    }

/**
 * It executes the code in the block.
 * 
 * Args:
 *   block: the block to be executed
 *   arbArray: array of variables to be ignored
 * 
 * Returns:
 *   The return value of the function is the return value of the last statement executed in the
 * function body.
 */
    private function _doBlockExec($block,$arbArray=null){
        $blockexec=$this->removeComments($block["exec"]);
        $exec=$blockexec;
        if (intval($block["is_start"])>0)
            $this->_WofoS->assignVars("\$reminder_to","");

        $this->_WofoS->setExecBid($block["block_id"]);

        $_rres=array();
        $referer="";
        
        if (!is_null($_SERVER) && isset($_SERVER["HTTP_REFERER"]))
            $referer=$_SERVER["HTTP_REFERER"];

        if (trim($exec)!=""){
            $path    = $_SERVER["DOCUMENT_ROOT"];
            $theExec=$this->sanitizeExec($exec,$block["block_id"]);
            $toBeExec=$theExec;

            $theReferer=$referer;
            if (strpos($theReferer,"&")!==false);
                $theReferer=explode("&",$theReferer)[0];

            $theCode='
// init external code
use Flussu\Flussuserver\Environment;
$wofoEnv=new Environment($this->_WofoS);
$Flussu = new \stdClass; 
$Flussu->Wid="'.$this->_WofoS->getWholeWID().'";
$Flussu->wid="'.$block["flussu_id"].'";
$Flussu->WfAuid="'.$this->_WofoS->getWfAuid().'";
$Flussu->Sid="'.$this->_WofoS->getId().'";
$Flussu->Bid="'.$this->_WofoS->getBlockId().'";
$Flussu->BlockTitle="'.($block["is_start"]?"START BLOCK":$block["description"]).'";
$Flussu->Referer=urldecode("'.$theReferer.'");

// workflow vars

if (isset($_outerCallerUri)){
    if ((is_null($_outerCallerUri) || empty($_outerCallerUri)) && $_scriptCallerUri!="")
        $Flussu->Referer=urldecode($_scriptCallerUri);
    elseif (!is_null($_outerCallerUri) && !empty($_outerCallerUri))
        $Flussu->Referer=urldecode($_outerCallerUri);
}
try {
    // exec theBlock code
    return $wofoEnv->endScript();
} catch (\Throwable $e){
    $wofoEnv->log("INTERNAL ERROR! Wid:".$Flussu->Wid." - Bid:".$Flussu->Bid." (".$Flussu->BlockTitle.") - Sid:".$Flussu->Sid."\n - - ".json_encode($e->getMessage()));
    return "Internal exec exception: [1] - ".var_export($e,true);
} catch (\ParseError $p){
    $wofoEnv->log("INTERNAL PARSER ERROR! Wid:".$Flussu->Wid." - Bid:".$Flussu->Bid." (".$Flussu->BlockTitle.") - Sid:".$Flussu->Sid."\n - - ".json_encode($p->getMessage()));
    return "Internal exec exception: [2] - ".var_export($p,true);
};
//Gen_WF[[FUNCTIONS]]
            ';

            $additionalFunctions=$this->_WofoS->getFunctions(); 
            if (!empty($additionalFunctions)){
                $wrappedCode = $this->wrapFunctionsWithExistsCheck($this->removeComments($additionalFunctions));
                $theCode=str_replace("//Gen_WF[[FUNCTIONS]]"," \n ".$wrappedCode." \n ",$theCode);
            }
            setlocale(LC_ALL, 'it_IT');        
            date_default_timezone_set("Europe/Rome");
            $this->_WofoS->assignVars("$"."_dateNow",date('D d M, Y - H:i:s', time()));

            $wfv=str_replace("`","§#§",$this->_WofoS->getWorkflowVars(true));
            //$wfv=$this->_WofoS->getWorkflowVars();
            $theCode=str_replace("// workflow vars",$wfv,$theCode);
            $theCode=str_replace("// exec theBlock code",$theExec,$theCode);

            $this->_secureALooper+=$this->_secureALooper;
            if ($this->_secureALooper>500){
                $this->_WofoS->recLog("Loop of death stopped: BID=".$block["block_id"]);
                General::Log("MORTAL LOOP ERROR: BID=".$block["block_id"]);
                die("stopped on loop");
            }

            $this->_WofoS->recLog("  - code EXEC ");
            $this->_WofoS->recLog($toBeExec);
            $evalRet="";
            $findErr=false;
            $this->_WofoS->statusExec(true);
            $chk= new Command();
            $err=$chk->php_error_test($theCode);
            if (empty($err)){
                $old = ini_set('display_errors', 1);
                try {
                    $evalRet= @eval(" \n ".$theCode." \n ");
                } catch(\ParseError $e){
                    if (strpos($e->getFile(),"eval()")!==false)
                        $errMsg=$this->getErrMessage($theCode,$block["description"],$e);
                    else 
                        $errMsg=$this->_sanitizeErrMsg($this->arr_print($e));
                    $this->_WofoS->recLog("  - execution EXCEPTION:".$errMsg);
                    $findErr=true;
                    General::Log("Block code exec PARSE-ERROR #1:".$this->arr_print($e));
                    $this->_WofoS->statusError(true);
                } catch(\Throwable $e){
                    if (strpos($e->getFile(),"eval()")!==false)
                        $errMsg=$this->getErrMessage($theCode,$block["description"],$e);
                    else 
                        $errMsg=$this->_sanitizeErrMsg($this->arr_print($e));
                    $this->_WofoS->recLog("  - exec EXCEPTION:".$errMsg);
                    $findErr=true;
                    General::Log("Block code exec ERROR #2:".$errMsg);
                    $this->_WofoS->statusError(true);
                }
                ini_set('display_errors', $old);
                /*
                if ($findErr==false && error_get_last()){
                    $errMsg=$this->getErrMessage($theCode,"(before) ".$block["description"],null);
                    if ($errMsg!=""){
                        $this->_WofoS->recLog("  - exec EXCEPTION:".$errMsg);
                        error_clear_last();
                        $this->_WofoS->statusError(booVal: true);
                        General::Log("Block code exec ERROR #3:".$errMsg);
                    }
                }*/
            } else {
                $this->_WofoS->statusError(true);
                $this->_WofoS->recLog("  - block code EXCEPTION:".$this->_sanitizeErrMsg($err));
                General::Log("Block code exec ERROR #4:".$err);

            }

            $this->_WofoS->statusExec(false);
            //$this->_WofoS->recLog("  - exec block code - END");

            $varDone=["wofoEnv","if","else","elseif","for","null","empty"];
            $vars=$this->getCodeVars($blockexec);
            if (!isset($vars) || count($vars)<0)
                $vars=explode("$",$blockexec);
            foreach ($vars as $var){
                if (!empty($var)){
                    $vname=$var;
                    if (strpos($vname,"=")){
                        $vname=trim(substr($vname,0,strpos($vname,"=")));
                    }
                    if (!Command::canBeVariableName($vname)){
                        do{                        
                            if (!Command::canBeVariableName($vname)){
                                $i=Command::strposArray($vname,array("!","'","-","[","]","*","\"","=","."," ",";",")","(",",","\n","\r","\\","/","->",">","+","<"));
                                if ($i>=0)
                                    $vname=trim(substr($vname,0,$i));
                                else
                                    break;
                                if (strlen($vname)<2)
                                    break;
                            } else
                                break;
                        } while (true);
                    }
                    if (strlen($vname)>1){
                        if (!in_array($vname,$varDone) && strpos($theCode,$vname)!==false){
                            array_push($varDone,$vname);
                            if (!(strpos($vname,"$")===0))
                                $vname="$".$vname;
                            $exec=true;
                            if (isset($arbArray) && count($arbArray)>0 && array_search($vname,$arbArray)!==false)
                                $exec=false;
                            if ($exec){
                                try{
                                    $vval=@eval("try{return $vname;} catch (\Throwable "."$"."e){return "."$"."e;}");
                                    if ($vval instanceof Throwable)
                                    {
                                        //FORSE è un errore
                                        $this->_WofoS->recLog("NOT really an error...".$vval->getMessage());
                                        $this->_WofoS->statusError(true);
                                    } else {
                                        if (!is_null($vval)){
                                            if ($vval===true)
                                                $vval="true";
                                            else if ($vval===false)
                                                $vval="false";
                                            if (!is_null($vval)){
                                                if(is_string($vval) && $vval!=""){
                                                    if (!(strpos('"$"."',$vval)===false))
                                                        $vval=str_replace('"',"",str_replace('"$"."',"$",$vval));
                                                    if (!(strpos("'$'.'",$vval)===false))
                                                        $vval=str_replace("'","",str_replace("'$'.'","$",$vval));
                                                    $vval=htmlspecialchars_decode($vval);
                                                    $vval=str_replace('\r\n','\n' ,$vval);
                                                    $vval=str_replace('\n','\r\n' ,$vval);
                                                }
                                                $this->_WofoS->assignVars($vname,$vval);
                                            } else {
                                                // Verifica se la VAR esiste prima di metterla a NULL
                                                if (in_array($vname, $this->_WofoS->arVarKeys))
                                                    $this->_WofoS->assignVars($vname,null);
                                            }
                                        }
                                    }
                                } catch (\Throwable $e){
                                    $this->_WofoS->recLog($e->getMessage());
                                    $this->_WofoS->statusError(true);
                                    //echo $e;
                                }
                            }
                        }
                    }
                } 
            }
            $this->_WofoS->loadWflowVars();
            if ($evalRet!=null && is_array($evalRet)){
                if (!isset($this->_ExecR))
                    $this->_ExecR = new Executor();
                for ($i=0;$i<count($evalRet);$i++){
                    $retArrCmd=$evalRet[$i];
                    foreach ($retArrCmd as $innerCmd => $innerParams){
                        if ($innerCmd=="requestUserInfo"){
                            if (count($innerParams)>0){
                                $this->_sendRequestUserInfo=true;
                                $this->_sendRequestUserInfoVarName=$innerParams[0];
                            }
                        }
                    }
                }
                //                                       ($Sess,            $Handl,  $evalRet, $sentData="",$block="",$WID="")
                try{
                    $_rres=$this->_ExecR->outputProcess($this->_WofoS,$this->_WofoD,$evalRet,$_rres,$block,$this->_WofoS->getWid());

                } catch (\Throwable $e){
                    General::Log("Worker error: ".$e->getMessage());
                    $this->_WofoS->recLog($e->getMessage());
                    $this->_WofoS->statusError(true);
                    $msg=$e->getMessage();
                }
            }
        }
        return $_rres;
    }

 /**
 * It takes an array and returns a string with each key and value on a separate line
 * 
 * Args:
 *   arr: The array to print
 */

    function arr_print($arr){
        $retStr = "";
        if (is_array($arr)){
            foreach ($arr as $key=>$val){
                $retStr .=  $key . '=';
                if (is_array($val))
                    $retStr .= $this->arr_print($val) . '\r\n';
                else
                    $retStr .= $val . '\r\n';
            }
        } else
            $retStr.=$arr;
        return $retStr;
    }
/**************************************************************************
    PRIVATE/UTILS
***************************************************************************/


 /**
  * It takes the error message, the code block description, and the original error, and returns a
  * string containing the error message, the error type, the line number, and the code block
  * 
  * Args:
  *   theCode: the code to be executed
  *   blockDesc: A description of the block of code that is being executed.
  *   origError: The original error that was thrown.
  * 
  * Returns:
  *   The return value is the result of the last expression in the block.
  */
    private function getErrMessage($theCode,$blockDesc,$origError=null){
        $e=error_get_last();
        $msg="Error on block '$blockDesc':";
        $ln=0;
        $tp="N/A";
        $errMsg="";
        if (!is_null($e) || !is_null($origError)){
            if (!is_null($origError)){
                $msg.=$origError->getMessage();
                $ln=$origError->getLine();
                $tp="[php]";
            } else {
                $mmm=json_decode(json_encode(error_get_last()))->message;
                if (stripos($mmm,"file_get_contents")!==false && stripos($mmm,"../Cache")!==false){
                    return "";
                }
                $msg.=$mmm;
                $ln=$e["line"];
                $tp=$e["type"];
            }
            $errMsg=$msg."\r\nType:".$tp." - Line:".$ln.":\r\n";
            $rrr="#NN\t - \t#RR";
            $xxx=explode("\n",$theCode);
            $lll=$ln-1;
            if ($lll>0){
                if ($lll<count($xxx))
                    $errMsg=$errMsg."\r\n".str_Replace("#NN",$lll+1,str_Replace("#RR",$xxx[$lll],$rrr));
                //else
                //    $errMsg=$errMsg.str_Replace("#NN",$lll,str_Replace("#RR",$xxx[$lll-1],$rrr));
            } else {
                if ($lll>=0)
                    $errMsg=$errMsg.str_Replace("#NN",$lll+2,str_Replace("#RR",$xxx[$lll],$rrr));
            }
            if ($lll+1<count($xxx))
                $errMsg=$errMsg."\r\n".str_Replace("#NN",$lll+2,str_Replace("#RR",$xxx[$lll+1],$rrr));

            //$errMsg=$errMsg."\r\nCODE: ".explode("\n",$theCode)[error_get_last()["line"]-2];
            $errMsg=$this->_sanitizeErrMsg($errMsg);
        }
        return $errMsg;
    }


 /**
  * It removes comments, replaces some PHP functions with "DoNotUse", and then replaces "mail" with
  * "Ne"
  * 
  * Args:
  *   exec: the code to be executed
  *   block_id: The ID of the block.
  * 
  * Returns:
  *   The return value is the result of the last statement executed in the function.
  */
    private function sanitizeExec($exec,$block_id=""){
        // eliminatore di commenti
        $a=0;
        $i=0;
        $s=0;
        do{
            $a=strpos($exec,"/*",$i);
            if ($a!==false){
                $b=strpos($exec,"*/",$a);
                if ($b==false)
                    $b=strlen($exec);
                $exc=substr($exec,$a,$b-$a+2);
                $exec=str_replace($exc,"",$exec);
            } else {
                $a=strpos($exec,"//",$i);
                if ($a!==false){
                    if (substr($exec,$a-1,1)!=":"){
                        $b=strpos($exec,"\n",$a);
                        if ($b==false){
                            $b=strpos($exec,"\r",$a);
                            if ($b==false)
                                $b=strlen($exec);
                        }
                        $exc=substr($exec,$a,$b-$a+1);
                        $exec=str_replace($exc,"",$exec);
                    } else $i=$a+2;
                } else
                break;
            }
            // SICUREZZA ANTI-LOOP
            if ($s++>100) break;
            //---------------------
        } while ($a!==false);

        $exec = str_replace("sendEmail","send_Emaaail", str_replace("sendPremiumEmail","sendPremium_Emaaail", $exec));
        //$exec = str_replace(array("`",chr(96)),array("'","'"), $exec);
        $preExec=$exec;

        $search_line=array(
            '<?=',
            '<?php',
            '?>',
            '$_REQUEST',
            '$_POST',
            '$_GET',
            '$_SESSION',
            '$_SERVER',
            'call_user_func_array',
            'DOCUMENT_ROOT',
            'directory',
            'display_errors',
            'escapeshellcmd',
            'eval',
            'echo',
            'file_',
            'fopen',
            'fread',
            'fwrite',
            'include',
            'ini_set',
            'invokefunction',
            'imap_mail',
            'mb_send_mail',
            'passthru',
            'phpinfo',
            'popen',
            'require',
            'rename',
            'shell_exec',
            'symlink',
            'stream',
            'system',
            'set_time_limit',
            'set_magic_quotes_runtime',
            'touch',
            'unlink'
        );

        /*
        foreach ($search_line as $line)
            $exec=str_ireplace($line,"DoNotUse",$exec);
        foreach ($search_line as $line)
            $exec=str_ireplace($line,"BadCommand",$exec);
        */


        $exec = $this->_trovaFunzioniProibite($exec, $search_line);

        //$exec=str_ireplace($search_line,"[DoNotUse]",$exec);
        //$exec = str_replace(array("`",chr(96)),array("'","'"), $exec);

        // eliminazione word "mail"
        $po=-1;
        do{
            $po=strpos($exec,"mail",$po+1);
            if ($po===false)
                break;
            if ($exec[$po+4]!=";" && ($exec[$po+4]!="\r" || $exec[$po+4]!="\n")){
                // potrebbe essere un comando
                if (($po==0) || ($po>=1 && ($exec[$po-1]=="\r" || $exec[$po-1]=="\n") || $exec[$po-1]==" ")){
                    // è un comando
                    $exec[$po]="N";
                    $exec[$po+1]="e";
                }
            }
        } while(true);

        if ($exec!=$preExec){
            $this->_WofoS->recLog("WARNING: EXEC CMD in this block ($block_id), contains forbidden commands!!!");
            $this->_WofoS->statusError(true);
        }

        $exec = str_replace("send_Emaaail","sendEmail", str_replace("sendPremium_Emaaail", "sendPremiumEmail", $exec));
        $exec = str_replace("\$wofoEnv-","wofoEnv-",$exec);
        $exec = str_replace("wofoEnv-","\$wofoEnv-",$exec);
        return $exec;
    }

    private function _trovaFunzioniProibite($code, $proibite = []) {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        try {
            $stmts = $parser->parse($code);
        } catch (\Throwable $e) {
            return ['parse_error' => $e->getMessage(), 'codice_sostituito' => null];
        }

        $trovate = [];

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($proibite, $trovate) extends NodeVisitorAbstract {
            private $proibite;
            public $trovate;
            public function __construct($proibite, &$trovate) {
                $this->proibite = array_map('strtolower', $proibite);
                $this->trovate = &$trovate;
            }
            public function enterNode(Node $node) {
                // Cerca chiamate a funzioni proibite (tipo system(), exec(), ecc)
                if ($node instanceof Node\Expr\FuncCall) {
                    $name = $node->name instanceof Node\Name ? strtolower($node->name->toString()) : '';
                    if (in_array($name, $this->proibite)) {
                        $this->trovate[] = $name;
                        // Sostituisci la chiamata con la stringa 'DoNotUse!'
                        return new Node\Expr\ConstFetch(new Node\Name('DoNotUse!'));
                    }
                }
            }
        });

        $stmtsModificati = $traverser->traverse($stmts);

        // Ricostruisci il codice (pretty print)
        $printer = new PrettyPrinter\Standard();
        $codice_sostituito = $printer->prettyPrintFile($stmtsModificati);

        return $codice_sostituito;


        return [
            'proibite_trovate' => array_unique($traverser->visitors[0]->trovate),
            'codice_sostituito' => $codice_sostituito,
        ];
    }

   /**
     * If the string is valid JSON, it will return true, otherwise it will return false
     * 
     * Arguments:
     * 
     * * `string`: The string to be checked.
     * 
     * Returns:
     * 
     * function isJson() {
     *         json_decode();
     *         return json_last_error() === JSON_ERROR_NONE;
     *     }
     */
    function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
 /*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS |
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/ 