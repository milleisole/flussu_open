<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu API Interface
 * CREATED DATE:     25.01.2021 - Aldus - Flussu v2.0
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11:2025 
 * -------------------------------------------------------*/

 /**
 * The Stat class is responsible for managing and retrieving statistical data within the Flussu server.
 * 
 * This class handles various tasks related to collecting, processing, and providing access to statistical
 * information. It interacts with the database and other components to gather data and generate meaningful
 * statistics that can be used for monitoring and analysis.
 * 
 * Key responsibilities of the Stat class include:
 * - Collecting statistical data from various sources within the Flussu server.
 * - Processing and aggregating data to generate meaningful statistics.
 * - Providing methods to retrieve statistical information for monitoring and analysis.
 * - Ensuring that statistical data is accurate and up-to-date.
 * - Integrating with other components and services to gather necessary data.
 * 
 * The class is designed to be a central point for managing statistical data, ensuring that all statistics
 * are handled correctly and efficiently.
 * 
 * @package App\Flussu\Statistics
 * @version 5.0.20251117
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

namespace Flussu\Api\V40;

//use Flussu\Flussuserver\Request;

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Flussuserver\Statistic;

class Stat {
    public function extCall(/*Request $Req,*/ $rawdata=null){
        //$w3e=new Wofo3Env();
        $theUser=new User();
        $authKey=General::getGetOrPost("auk");
        $uid=General::getUserFromDateTimedApiKey($authKey);
        if ($uid>0){
            $theUser->load($uid);

            $wid=General::getGetOrPost("WID");
            $uid=$theUser->getId();
            $ctyp=General::getGetOrPost("CTY");
            $ival=General::getGetOrPost("IVL");
    
            $w_id= HandlerNC::WID2Wofoid($wid);
            $wStat=new Statistic($w_id);

            $stD=date_modify(date_create("now"),"+1 days");
            $stD=date_format($stD,"Y/m/d");

            $res1=$wStat->getDaysWebOnlyData($stD,366,true);
            $res2=$wStat->getDaysChatOnlyData($stD,366,true);
            $res3=$wStat->getObjectives($stD,366,true);
     
            $base=["date","web","chat","total","- - - - -"];
            for ($i=1;$i<count($res3["labels"]);$i++)
                array_push($base,$res3["labels"][$i]);
            $res=[];
            array_push($res,$base);
            $index=0;
            foreach ($res1 as $key => $value) {
                $arr=[$key,intval($res1[$key]),intval($res2[$key]),intval($res1[$key])+intval($res2[$key]),""];
                $a=[];
                eval("$"."a=".$res3["values"][$index++][2].";");
                $arr=array_merge($arr,$a);
                //array_push($arr,intval($res1[$key])+intval($res2[$key]));
                array_push($res,$arr);
            }
            //$res=[["Data","web","chat"],["2022-01-01","1","0"],["2022-01-02","2","0"]];

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename("FlussuServer_Result.xlsx"));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            $file=sys_get_temp_dir()."/output.xlsx";
            /*
            $books = [
                ['ISBN', 'title', 'author', 'publisher', 'ctry' ],
                [618260307, 'The Hobbit', 'J. R. R. Tolkien', 'Houghton Mifflin', 'USA'],
                [908606664, 'Slinky Malinki', 'Lynley Dodd', 'Mallinson Rendel', 'NZ']
            ];*/
            $xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $res );
            $xlsx->saveAs($file); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            die();
        }
        $res=["error"=>"unhautorized"];
        die(json_encode($res));
    }
    public function exec(/*Request $Req,*/ User $theUser, $funcNum, $file_rawdata=null){
        $wSess=null;
        $terms=null;
        //$w3e=new Wofo3Env();
        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');

        $wid=General::getGetOrPost("WID");
        $uid=$theUser->getId();
        $ctyp=General::getGetOrPost("CTY");
        $ival=General::getGetOrPost("IVL");

        $res=["error"=>"wrong startup data"];

        if ($wid!="" && $uid>0){
            // startup
            $LNG=General::getGetOrPost("LNG");
            if (empty($LNG))
                $LNG="IT";
            $w_id= HandlerNC::WID2Wofoid($wid);
            $wStat=new Statistic($w_id);

            $IP=General::getCallerIPAddress();
            $UA=General::getCallerUserAgent();
            $userId=0;
            $stD=date_modify(date_create("now"),"+1 days");
            $stD=date_format($stD,"Y/m/d");
            switch($ctyp){
                case "1":
                    $res=$wStat->getDaysTotalData($stD,$ival);
                    break;
                case "2":
                    $res=$wStat->getObjectives($stD,$ival);
                    break;
                case "3":
                    $res1=$wStat->getDaysWebOnlyData($stD,$ival);
                    $res2=$wStat->getDaysChatOnlyData($stD,$ival);
                    $res=array("labels"=>["web","chat"]);
                    //$vals=[];
                    //foreach($res1 as $key=>$value){
                    //    array_push($vals,[intval($value),intval($res2[$key])]);
                    //}
                    array_push($res,$res1);
                    array_push($res,$res2);
                    break;
                default:
                    $res=["error"=>"wrong stat code"];
            }
        }

        die(json_encode($res));
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