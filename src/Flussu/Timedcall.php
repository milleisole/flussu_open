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

La classe Timedcall contiene il codice che gestisce le 
chiamate temporizzate del sistema.

E' un componente NON FONDAMENTALE del sistema e per essere
usato va aggiunto un comando dentro il timer di Linux:

* -------------------------------------------------------*
* CLASS PATH:       App\Flussu\Flussuserver
* CLASS NAME:       TimedCall
* CLASS-INTENT:     Workflow independet caller/executor
* USE ALDUS BEAN:   Databroker.bean
* -------------------------------------------------------*
* CREATED DATE:     21.02:2024 - Aldus - Flussu v3.0
 * VERSION REL.:     4.4.20250621
 * UPDATES DATE:     21.06:2025 
* - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
* Releases/Updates:
* -------------------------------------------------------*/
namespace Flussu;

require_once __DIR__ . '/../../vendor/autoload.php';

use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Flussuserver\Session;
use Flussu\Config;
use Flussu\General;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

if (!function_exists('config')) {
/**
 * Helper per accedere ai valori di configurazione tramite
 * dot notation. Es.: config('services.google.private_key').
 *
 * @param string $key
 * @return mixed
 */
function config(string $key, $default = null)
{
    // Ritorna il valore chiamando la classe Singleton
    return Config::init()->get($key, $default);
}
}
/* -----------------------------------------------------------------------------------

USO (anche da terminale):
php /[path..]/[..to..]/[..flussuserverdir]/src/Flussu/Timedcall.php

Questa classe è richiamata ogni minuto dal sistema 
PERTANTO UNA PARTE DI CODICE E' OPEN, CIOE' DIRETTA 
========================================
    Impostare il cron job
========================================
da terminale:
crontab -e    (editing)

inserire la seguente riga:
+---- mettere la '/' qui, se lo si fa qui si elimina il remark
\|/
*_5 * * * * sh /home/[user]/cronjobs.5m.sh &> cronjobs.5m.log
e salvare

Creare il file cronjobs.5m.sh
nano /home/[user]/cronjobs.5m.sh
e scrivere (per ogni flussuserver presente nel server)
php /home/[user]/[flussuserverdir]/src/Flussu/Timedcall.php
e salvare

rendere eseguibile il file
chmod +x /home/[user]/cronjobs.5m.sh

-------------------------------------------------------------------------------------- */

$tcall = new Timedcall();
$tcall->exec();

/* DA QUI IN POI INIZIA LA CLASSE */

class Timedcall
{
    private $_WofoD;
    private $_logDir=""; 
    public function __construct()
    {
        $this->_logDir = $_ENV["syslogdir"];
        $this->_WofoD = new HandlerNC();
        $vc = new \Flussu\Controllers\VersionController();
        $vcbv = $vc->getDbVersion();
        echo "\033[01;42m\033[01;97m       Flussu Server       \033[0m\r\n";
        echo " sv:\033[01;32mv" . $_ENV["major"] . "." . $_ENV["minor"] . "." . $_ENV["release"] . "  \033[0m\r\n";
        echo " sn:\033[01;32m" . $_ENV["server"] . "\033[0m\r\n";
        echo " db:" . $_ENV["db_name"] . " [\033[01;32mv" . $vcbv . "\033[0m]\r\n";
        if (!empty($this->_logDir)) {
            echo " lg:".$this->_logDir;
        }
        if ($vcbv < 7) {
            echo "\033[01;31mERROR: Database version < 7 !\033[0m\r\nYou must update your DB, please use \033[01;32mhttps://" . $_ENV["server"] . "/checkversion\033[0m\r\n---------------------------\r\n";
            die();
        }
        echo "- - - - - - - - - - - - - -\r\nTimedcall routine\r\n";
    }
    public function exec()
    {
        General::Log2("Timedcall: exec start",$this->_logDir);
        echo "\033[01;32m" . date("Y-m-d H:i:s") . "\033[0m - start\r\n";
        $rows = $this->_WofoD->getTimedCalls(true);
        $srvCall = "https://" . $_ENV["server"] . "/flussueng.php?TCV=1&WID={{wid}}&SID={{sid}}&BID={{bid}}&£is_timed_call=1{{data}}";
        foreach ($rows as $row) {
            // Verifica se la data è corretta!
            $now = new \DateTime();
            $minutes_to_add = $row["e_min"];
            $m_time = new \DateTime($row["s_date"]);
            $m_time->add(new \DateInterval('PT' . $minutes_to_add . 'M'));
            if ($m_time <= $now) {
                //$wid=General::camouf($row["wid"]);
                $WID="";
                $SID="";
                if ($row["sid"]) {
                    $SID=$row["sid"];
                    echo $m_time->format("Y-m-d H:i:s") . " - accepted. SID:" . $SID . "\r\n";
                } else {
                    $WID = "[" . General::camouf($row["wid"]) . "]";
                    echo $m_time->format("Y-m-d H:i:s") . " - accepted. WID:" . $WID . "\r\n";
                }
                $this->_WofoD->disableTimedCall($row["seq"]);

                if ($SID){
                    $sess = new Session($SID);
                    if (!$sess->isExpired()) {
                        $uri = str_replace(["&WID={{wid}}","{{sid}}","{{bid}}"], ["",$SID, $row["bid"]], $srvCall);
                        $theData = "";
                        if (!is_null($row["e_data"]) && !empty($row["e_data"])) {
                            $theData = "&" . str_replace("$", "£", $row["e_data"]);
                        }
                        $uri = str_replace("{{data}}", $theData, $uri);
                        echo "                     - " . $uri . "\r\n";
                        $result = $this->_sendRequest($uri);
                        echo "                     - " . $result . "\r\n";
                        General::Log2("\t\t\t\t\t\t\t\t\t\t\t\t".$uri." -> ".$result,$this->_logDir);
                    } else {
                        $result = "ERROR:[0]:Session expired";
                        echo "                     - SESSION EXPIRED!\r\n";
                    }
                } else {
                    // WID EXECUTION...
                    $uri = str_replace(["{{wid}}","&SID={{sid}}","&BID={{bid}}"], [$WID,"", ""], $srvCall);
                    $theData = "";
                    if (!is_null($row["e_data"]) && !empty($row["e_data"])) {
                        $theData = "&" . str_replace("$", "£", $row["e_data"]);
                    }
                    $uri = str_replace("{{data}}", $theData, $uri);
                    echo "                     - " . $uri . "\r\n";
                    $result = $this->_sendRequest($uri);
                    echo "                     - " . $result . "\r\n";
                    General::Log2("\t\t\t\t\t\t\t\t\t\t\t\t".$uri." -> ".$result,$this->_logDir);
                }
                $this->_WofoD->updateTimedCall($row["seq"], $result);
            } else {
                echo $m_time->format("Y-m-d H:i:s") . " is in the future.\r\n";
            }
        }
        General::Log2("Timedcall: end",$this->_logDir);
        echo "\r\n\033[01;32m" . date("Y-m-d H:i:s") . "\033[0m - end\r\n---------------------------\r\n";
    }


    public function completeExec()
    {
        General::Log2("Timedcall: exec start",$this->_logDir);
        echo "\033[01;32m" . date("Y-m-d H:i:s") . "\033[0m - start\r\n";
        $rows = $this->_WofoD->getTimedCalls(true);
        $srvCall = "https://" . $_ENV["server"] . "/flussueng.php?TCV=1&WID={{wid}}&SID={{sid}}&BID={{bid}}&£is_timed_call=1{{data}}";
        foreach ($rows as $row) {
            // Verifica se la data è corretta!
            $now = new \DateTime();
            $minutes_to_add = $row["e_min"];
            $m_time = new \DateTime($row["s_date"]);
            $m_time->add(new \DateInterval('PT' . $minutes_to_add . 'M'));
            if ($m_time <= $now) {
                //$wid=General::camouf($row["wid"]);
                $WID = "[" . General::camouf($row["wid"]) . "]";
                echo $m_time->format("Y-m-d H:i:s") . " - accepted. WID:" . $WID . " - SID:" . $row["sid"] . "\r\n";

                $this->_WofoD->disableTimedCall($row["seq"]);

                $sess = new Session($row["sid"]);
                if (!$sess->isExpired()) {
                    $uri = str_replace(["{{sid}}", "{{wid}}", "{{bid}}"], [$sess->getId(), $WID, $row["bid"]], $srvCall);
                    $theData = "";
                    if (!is_null($row["e_data"]) && !empty($row["e_data"])) {
                        $theData = "&" . str_replace("$", "£", $row["e_data"]);
                    }
                    $uri = str_replace("{{data}}", $theData, $uri);
                    echo "                     - " . $uri . "\r\n";
                    $result = $this->_sendRequest($uri);
                    echo "                     - " . $result . "\r\n";
                    General::Log2("\t\t\t\t\t\t\t\t\t\t\t\t".$uri." -> ".$result,$this->_logDir);
                } else {
                    $result = "ERROR:[0]:Session expired";
                    echo "                     - SESSION EXPIRED!\r\n";
                }
                $this->_WofoD->updateTimedCall($row["seq"], $result);
            } else {
                echo $m_time->format("Y-m-d H:i:s") . " is in the future.\r\n";
            }
        }
        General::Log2("Timedcall: end",$this->_logDir);
        echo "\r\n\033[01;32m" . date("Y-m-d H:i:s") . "\033[0m - end\r\n---------------------------\r\n";
    }


    private function _sendRequest(string $url)
    {
        General::Log("Timedcall: " . $url);
        $hc = new HttpCaller();
        return $hc->exec($url, "GET");
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