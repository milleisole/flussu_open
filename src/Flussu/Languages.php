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
 * CLASS PATH:       App\Flussu
 * CLASS-NAME:       Languages
 * -------------------------------------------------------*
 * RELEASED DATE:    07.01:2022 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/
namespace Flussu;
class Languages
{
    function txt4Lang(){
        if (!isset($_SESSION["lng"]))
            if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
                $_SESSION["lng"]=strtolower(substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2));
            else
                $_SESSION["lng"]="en";
        return $_SESSION["lng"];
    }

    function txt4weekday($weekday,$_Lang=null){
        if(empty($weekday))
            return "No weekday provided";
        if (is_null($_Lang))
            $_Lang=$this->txt4Lang();
        switch($_Lang){
            case "it":
                $weekday=str_ireplace(["Mon ","Tue ","Wed ","Thu ","Fri ","Sat ","Sun "],["Lun ","Mar ","Mer ","Gio ","Ven ","Sab ","Dom "],$weekday);
                break;
            case "fr":
                $weekday=str_ireplace(["Mon ","Tue ","Wed ","Thu ","Fri ","Sat ","Sun "],["Lun ","Mar ","Mer ","Jeu ","Ven ","Sam ","Dim "],$weekday);
                break;
            }
        return $weekday;
    }
    function txt4month($month,$_Lang=null){
        if(empty($month))
            return "No month provided";
        if (is_null($_Lang))
            $_Lang=$this->txt4Lang();
        $month=trim($month)." ";
        switch($_Lang){
            case "it":
                $month=str_replace("Jan ","Gen ",$month);
                $month=str_replace("Jan. ","Gen ",$month);
                $month=str_replace("May ","Mag ",$month);
                $month=str_replace("Jun. ","Giu ",$month);
                $month=str_replace("Jun ","Giu ",$month);
                $month=str_replace("Jul. ","Lug ",$month);
                $month=str_replace("Jul ","Lug ",$month);
                $month=str_replace("Aug. ","Ago ",$month);
                $month=str_replace("Aug ","Ago ",$month);
                $month=str_replace("Sep. ","Set ",$month);
                $month=str_replace("Sep ","Set ",$month);
                $month=str_replace("Oct. ","Ott ",$month);
                $month=str_replace("Oct ","Ott ",$month);
                $month=str_replace("Dec. ","Dic ",$month);
                $month=str_replace("Dec ","Dic ",$month);
                break;
            case "fr":
                $month=str_replace("Feb ","Fév ",$month);
                $month=str_replace("Feb. ","Fév ",$month);
                $month=str_replace("Apr ","Avr ",$month);
                $month=str_replace("Apr. ","Avr ",$month);
                $month=str_replace("May ","Mai ",$month);
                $month=str_replace("Jun. ","Jui ",$month);
                $month=str_replace("Jun ","Jui ",$month);
                $month=str_replace("Jul. ","Jiu ",$month);
                $month=str_replace("Jul ","Jiu ",$month);
                $month=str_replace("Aug. ","Aoù ",$month);
                $month=str_replace("Aug ","Aoù ",$month);
                $month=str_replace("Dec. ","Déc ",$month);
                $month=str_replace("Dec ","Déc ",$month);
                break;
            }
        return $month;
    }

    function txt4time($date,$_Lang=null)
    {
        if(empty($date))
            return "No date provided";

        if (is_null($_Lang))
            $_Lang=$this->txt4Lang();

        if ($_Lang=="it")
            $periods = array("secondo", "minuto", "ora", "giorno", "settimana", "mese", "anno", "decennio");
        else if ($_Lang=="fr")
            $periods = array("seconde", "minute", "heure", "jour", "semaine", "mois", "an", "décennie");
        else
            $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");

        $lengths         = array("60","60","24","7","4.35","12","10");
        $now             = time();
        $unix_date       = strtotime($date);

        // check validity of date
        if(empty($unix_date)) 
            return "Bad date";

        // is it future date or past date
        if($now > $unix_date) {    
            $difference     = $now - $unix_date;
            if ($_Lang=="it")
                $tense = "fa";
            elseif ($_Lang=="fr")
                $tense = "depuis";
            else
                $tense = "ago";
        } else {
            $difference     = $unix_date - $now;
            if ($_Lang=="it")
                $tense = "da adesso";
            else if ($_Lang=="fr")
                $tense = "à partir de maintenant";
            else
                $tense = "from now";
        }

        for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++)
            $difference /= $lengths[$j];

        $difference = round($difference);

        if($difference != 1){
            if ($_Lang=="it"){
                $voc=substr($periods[$j], strlen($periods[$j])-1,1);
                if ($voc=="a")
                    $periods[$j]=substr($periods[$j], 0,strlen($periods[$j])-1)."e";
                else
                    $periods[$j]=substr($periods[$j], 0,strlen($periods[$j])-1)."i";
            }
            else
                $periods[$j].= "s";
        }

        return "$difference $periods[$j] {$tense}";
    }
}