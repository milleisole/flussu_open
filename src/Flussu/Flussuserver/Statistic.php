<?php
/* --------------------------------------------------------------------*
 * Flussu v4.2 - Mille Isole SRL - Released under Apache License 2.0
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

    La classe Statistic serve a leggere le tabelle di stato
    generate dalle chiamate ai processi e a generare utte le
    informazioni che poi vengono visualizzare dal client

    E' un componente FONDAMENTALE del sistema e le modifiche
    vanno fatte con MOLTA attenzione

    [EN]
    The `Statistic` class in the Flussu system is responsible for generating and processing statistical
    data related to workflow objectives and user interactions, with methods for retrieving various types
    of data based on specified parameters.

 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Flussuserver
 * CLASS NAME:       Statistic
 * CLASS-INTENT:     Statistics producer
 * USE ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     22.02.2021 - Aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/
namespace Flussu\Flussuserver;
use Flussu\Languages;
use Flussu\Flussuserver\NC\HandlerNC;


class Statistic {
    private $_WofoD;
    private $wid=0;

    public function __construct ($wid){
        $this->_WofoD = new HandlerNC();
        $this->wid=$wid;
    }
    public function __clone(){
        $this->_WofoD = clone $this->_WofoD;
    }

    function getDaysTotalData($startDate,$daysInterval){
        $data= $this->_getData($startDate,$daysInterval);
        $res= $this->_prepareData1(1,$data,$startDate,$daysInterval);
        $arrRes=[];
        for ($i=0;$i<count($res);$i++)
            $arrRes[$res[$i]->label]=$res[$i]->value;
        return $arrRes;
    }

    function getDaysWebOnlyData($startDate,$daysInterval,$forExtraction=false){
        $data= $this->_getData($startDate,$daysInterval,true);
        $res= $this->_prepareData1(1,$data,$startDate,$daysInterval,$forExtraction);
        $arrRes=[];
        for ($i=0;$i<count($res);$i++)
            $arrRes[$res[$i]->label]=$res[$i]->value;
        return $arrRes;
    }

    function getDaysChatOnlyData($startDate,$daysInterval,$forExtraction=false){
        $data= $this->_getData($startDate,$daysInterval,false,true);
        $res= $this->_prepareData1(1,$data,$startDate,$daysInterval,$forExtraction);
        $arrRes=[];
        for ($i=0;$i<count($res);$i++)
            $arrRes[$res[$i]->label]=$res[$i]->value;
        return $arrRes;
    }

    function getObjectives($startDate,$daysInterval,$forExtraction=false){
        $data= $this->_getData($startDate,$daysInterval); // Numero sessioni nel periodo
        $objs=$this->_getWorkflowObjectives();            // Numero STEP di tipo OBJ
        if (is_null($objs) || (is_array($objs) && count($objs)<1))
            $objs=$this->_getLastWorkflowBlock();

        $data= $this->_prepareData1(1,$data,$startDate,$daysInterval,$forExtraction);

        $labels=["exec"];
        $bidSel=[];
        $zero=[];
        for ($i=0;$i<count($objs);$i++){
            $des=$objs[$i]["b_desc"];
            if (strpos($des,"#OBJ_")!==false)
                $des=substr($des,strpos($des,"#OBJ_")+5);
            array_push($labels,"% ".$des);
            array_push($bidSel,$objs[$i]["b_id"]);    
            array_push($zero,"0");
        }
        $arrRes=[];
        for ($i=0;$i<count($data);$i++){
            $stD=$data[$i]->date;
            //$odt=array($data[$i]->value);
            $vals=[];
            if($data[$i]->value>0){
                $recs=$this->_extractWfUse($stD,$bidSel);
                $tot=$data[$i]->value;
                for ($j=0;$j<count($recs);$j++){
                    $use=$recs[$j]["b_use"];
                    $pct=0;
                    if ($use>0)
                        $pct=intval(($use/$tot)*100);
                    array_push($vals,$pct);
                }
            } else {
                $vals=$zero;
            }
            //$data[$i]->objsv=json_encode($vals);
            array_push ($arrRes,[$data[$i]->label,$data[$i]->value,json_encode($vals)]);
        }
        return ["labels"=>$labels,"values"=>$arrRes];
    }

    private function _prepareData1(int $type,$data,$startFrom,$interval,$forExtraction=false){
        $arrRes=[];
        switch ($type){
            case 1:
                $stD=date_modify(date_create($startFrom),"-$interval days");
                //$stD=date_modify($stD,"+1 days");
                for ($i=0;$i<$interval;$i++){
                    $tmp = new \StdClass;
                    $Lang=new Languages();
                    if (!$forExtraction)
                        $tmp->label=$Lang->txt4weekday(date_format($stD,'D d'),$Lang->txt4Lang()).",".$Lang->txt4month(date_format($stD,'M'));
                    else
                        $tmp->label=date_format($stD,'Y-m-d');
                    //$tmp->label=$Lang->txt4weekday(date_format($stD,'D d')).",".date_format($stD,'M');
                    $tmp->value=0;
                    $tmp->date=date_format($stD,'Y/m/d');
                    $SRC=date_format($stD,'Y-m-d');
                    foreach ($data as $rec){
                        if(date_format(date_create($rec["rkey"]),'Y-m-d')==$SRC){
                            $tmp->value=$rec["rval"];
                            break;
                        }
                    }
                    array_push($arrRes,$tmp);
                    $stD=date_modify($stD,"+1 days");
                }
                break;
        }
        return $arrRes;
    }

/* ==================================================================================

     DATABASE OPERATIONS

 ================================================================================== */

    private function _getWorkflowObjectives(){
        $SQL="select c20_desc as b_desc, c20_id as b_id from t20_block where c20_flofoid=? and c20_desc like '%#OBJ_%'";
        $this->_WofoD->execSql($SQL,array($this->wid));
        return $this->_WofoD->getData(); 
    }

    private function _getLastWorkflowBlock(){
        $SQL="SELECT B.c20_desc as b_desc, X.c25_blockid as b_id, X.c25_nexit as bexitn, X.c25_direction as exitdir
        FROM t25_blockexit X INNER JOIN t20_block B ON X.c25_blockid=B.c20_id WHERE B.c20_flofoid=?
        GROUP BY (X.c25_blockid) HAVING X.C25_direction=0 and X.C25_nexit=0";
        $this->_WofoD->execSql($SQL,array($this->wid));
        return $this->_WofoD->getData(); 
    }

    private function _extractWfUse($date,$bidSel){
        //$SQL="SELECT {0} as b_id, count(c70_bid) as b_use FROM `t70_stat` WHERE c70_wid={2} and c70_bid={0} and c70_timestamp between '{1}T00:00:00' and '{1}T23:59:59'";
        $SQL="SELECT {0} as b_id, count(distinct c70_sid) as b_use FROM `t70_stat` WHERE c70_bid={0} and c70_timestamp between '{1}T00:00:00' and '{1}T23:59:59'";
        $genSql="";
        for ($i=0;$i<count($bidSel);$i++){
            $sql=str_replace("{0}",$bidSel[$i],$SQL);
            //$sql=str_replace("{1}",$date,str_replace("{2}",$this->wid,$sql));
            $sql=str_replace("{1}",$date,$sql);
            if ($i<count($bidSel)-1)
                $sql.=" UNION ALL ";
            $genSql.=$sql;
        }
        
        //echo ("\r\n".$genSql);

        $this->_WofoD->execSql($genSql); //,array($this->wid));
	 $extdt=$this->_WofoD->getData(); 

	 //echo ("\r\n".print_r($extdt));

        return $extdt; 
    }

    private function _getData($startDate,int $days,$justWeb=false,$justChat=false){
        $sessObj=null;
        
        $E=date_create($startDate." 00:00:00");
        //echo date_format($S,"Y/m/d");

        $E=date_format($E,"Y/m/d");
        $S=date('Y/m/d',strtotime($E . '-'.$days.' days'));
        $E.="T23:59:59";
        $S.="T00:00:00";

        $SQL="select DATE(c71_date) as rkey, count(distinct c71_sid) as rval from t71_access where c71_wid=? ";
        $SQL.="and c71_date between '$S' and '$E' ";
        if ($justWeb===true)
            $SQL.="and c71_chan=0 ";
        else if ($justChat===true)
            $SQL.="and c71_chan=1 ";
        $SQL.="group by DATE(c71_date)";
        
        // ESTRARRE

        $this->_WofoD->execSql($SQL,array($this->wid));
        return $this->_WofoD->getData(); 
    }

    // CLASS DESTRUCTION
    //----------------------
    public function __destruct(){
        //if (\General::$Debug) echo "[Distr Databroker ]<br>";
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