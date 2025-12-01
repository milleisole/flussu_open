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
 *
 *      This is the main entrance to the Flussu Server, a PHP script
 *      to handle all the requests to this server. 
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.09.2025
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../vendor/autoload.php';

use Flussu\Controllers\FlussuController;
use Flussu\Controllers\ZapierController;
use Flussu\Controllers\VersionController;
use Flussu\Flussuserver\Request;
use Flussu\General;
use Flussu\Config;

// VERSION
//$FlussuVersion="0.0.unknown!";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (!function_exists('config')) {
    /**
     * Helper per accedere ai valori di configurazione tramite
     * dot notation. Es.: config('services.google.private_key').
     *
     * @param string $key
     * @return mixed
     */
    function config(string $key,$default=null)
    {
        // Ritorna il valore chiamando la classe Singleton
        return Config::init()->get($key,$default);
    }
}
if (!function_exists('flussuVersion')) {
    /**
     * Ritorna la versione corrente di Flussu Open Server
     *
     * @return string
     */
    function flussuVersion()
    {
        return config("flussu.version").".".config("flussu.release");
    }
}

$FVP=explode(".",flussuVersion());
$v=$FVP[0];
$m=$FVP[1];
$r=$FVP[2];

if (isset($argv) && is_array($argv)){
    echo ("Flussu Server v".$_ENV['major'].".".$_ENV['minor'].".".$_ENV['release']."\n");
    if (count($argv)>2) {
        switch($argv[1]){
            case "-curt":
                die(General::curtatone(999,$argv[2])."\n");
            case "-mont":
                die(General::montanara($argv[2],999)."\n");
            case "-iscu":
                die(General::isCurtatoned($argv[2])?"yes\n":"no\n");
            default:
                die ("Error:unknown command ".$argv[1]."\nuse: -curt | -iscu\n");
        }
        //die(json_encode($argv));
    } else {
        die ("Error:unknown command\nUse: php api.php -[cmd] [params]\n");
    }
}

if (strpos($_SERVER["REQUEST_URI"],"license") || strpos($_SERVER["QUERY_STRING"],"license")!==false){
    $license = file_get_contents('../LICENSE.md');
    die("<html><head><title>Flussu Server License</title></head><body><p>".str_replace("<br><br>","</p><p>",str_replace("\n","<br>",htmlentities($license)))."</body></html>");
} else if ($_SERVER["REQUEST_URI"]=="/favicon.ico"){
    die (file_get_contents(
        "favicon.ico"     
    ));
} else if ($_SERVER["REQUEST_URI"]=="/checkversion" || $_SERVER["REQUEST_URI"]=="/update" || $_SERVER["REQUEST_URI"]=="/refreshviews"|| $_SERVER["REQUEST_URI"]=="/views"){
    $fc=new VersionController();
    $res=$fc->execCheck();
    if ($_SERVER["REQUEST_URI"]=="/views" || $_SERVER["REQUEST_URI"]=="/refreshviews"){
        $res.="<hr><h4>Refresh views:</h4>";
        $res.=$fc->refreshViews();
    }
    die($res);
} else if ($_SERVER["REQUEST_URI"]=="/"){
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Methods: *');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Max-Age: 10');
    header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
    header('Content-Type: application/json; charset=UTF-8');
    $V=$v.".".$m.".".$r;
    $hostname = gethostname();
    $fc=new VersionController();
    $dbv="v".$fc->getDbVersion();
    $srv=$_ENV["server"];
    die(json_encode(["host"=>$hostname,"server"=>$srv,"Flussu Open"=>$v.".".$m,"v"=>$v,"m"=>$m,"r"=>$r,"db"=>$dbv,"pv"=>phpversion()]));
} else if ($_SERVER["REQUEST_URI"]=="/notify"){
    /* 
        PHP Session is blocking asyncrhonous calls if you use the same session_id, so the
        notifications mechanism must be session-agnostic.
        The solution is to handle it here BEFORE we start the PHP Session.
        "notify.php"scrit, will handle the whole process and send back the notifications if any. 
    */
    if (isset($_GET["SID"])){
        include 'notify.php';
    } else {
        header('HTTP/1.0 403 Forbidden');
        die(\json_encode(["error"=>"403","message"=>"Unauthorized action"]));
    }
} else if (stripos($_SERVER["REQUEST_URI"],"/wh/")===0){
    /*
    It's a specificed DIRECT WEB HOOK call, so we need to handle it here.
    The first part must be a Workflow-id
    If there is a second part, it must be a block id
    */
    try{
        $fc=new FlussuController();
        General::log("Webhook call: ".$_SERVER["REQUEST_URI"]);
        $res=$fc->webhook($_SERVER["REQUEST_URI"]);
        die($res);
    } catch(\Throwable $e){ 
        header('HTTP/1.0 500 Error');
        General::log("Webhook call error: ".json_encode( $e->getMessage()),true);
        die(\json_encode(["error"=>"500","message"=>"Webhook call error"]));
    }
} else {
    $apiPage=basename($_SERVER['REQUEST_URI']);
    $req=new Request();
    /*
    It's a WEB HOOK call, if can be Flussu or other system, so we need to check
    */
    // Se la call non viene da servizi riconosciuti, allor passala a flussu!

    if ($apiPage=="flussu"){
        // user login handling
        header('Location: /flussu/index.php', true, 301);
        die();
    }
    if (!checkUnattendedWebHookCall($req,$apiPage)){
        General::log("Extcall Flussu Controller: ".$apiPage." - ".$_SERVER["REQUEST_URI"]." from ".($_SERVER["REMOTE_ADDR"]??"(no address)")." - ".($_SERVER["HTTP_ORIGIN"]??"(no origin)")." - ".($_SERVER['HTTP_USER_AGENT'])??"(no user agent)");

        //Troll the websites dork hackers
        if (stripos(strtolower(trim($apiPage)),"phpinfo")!==false){
            header("Content-Type: text/plain");
            die("[gd] => Array{ [GD Support] => enabled\n\t[GD Version] => bundled (800.A compatible)\n\t[FreeType Support] => enabled\n\t\t[FreeType Linkage] => with freetype\n\t[FreeType Version] => 52.16.4\n[GIF Read Support] => enabled\n\n\t\t[GIF Create Support] => enabl\ned\n[hacked_by] => a_very_good\n\t_hacker_bro");
        }
        if (stripos(strtolower(trim($apiPage)),"wp-")!==false || stripos(strtolower(trim($apiPage)),"secret")!==false){
            header("Content-Type: text/plain");
            die("<?php\n/*================\n WordPress v800A\n Wow! what a hacker u r!\n Impressed by your hacking skills, here's a secret code for you:\n E-MAIL: hack@youscared.me\n PASSWORD: 0x32778_is_800A\n Use it wisely, bro!\n==============*/\necho 'We was hacked by a very good hacker!';");
        }
        if (stripos(strtolower(trim($apiPage)),".zip")!==false){
            header("Pragma: public"); // required
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=800A.zip");
            header("Content-Length: 1");
            header("Content-Transfer-Encoding: binary");
            die("HELP HELP! WE WAS HACKED!!! ... Trust me, this bro is a very good hacker!");
        }
        switch (strtolower(trim($apiPage))){
            case ".env":
            case "i.php":
            case "p.php":
            case "tiny.php":
            case "info.php":
                die('impressed{"code":"0x32778","very_secret_number":"#800A","whoa, you are a very good hacker bro!"}');
            case "robots.txt":
                header("Content-Type: text/plain");
                die("#no robots allowed here!\nUser-agent: *\nDisallow: /");
            default:
                // Continua con Flussu
                break;
        }
        $fc=new FlussuController();
        $fc->apiCall($apiPage);
    }
}

//EXTERNAL CALLS - CONFIGURED ON config/.services.json
function checkUnattendedWebHookCall($req,$apiPage){
    $callSign=$_SERVER['HTTP_USER_AGENT']." ".$_SERVER['HTTP_ORIGIN'];
    $wh=config("webhooks");
    $iwh="";
    $idf="";
    foreach ($wh as $service => $sign) {
        $idf=$service;
        foreach ($sign['sign'] as $p_sign) {
            if (strpos($callSign, $p_sign) !== false) {
                $iwh=$sign['call'];
                break;
            }
        }
        if ($iwh) break;    
    }
    if ($iwh){
        // Richiama Caller
        $iwh=explode("@",$iwh);
        $providerClass = 'Flussu\\Controllers\\' . $iwh[0];
        if (class_exists($providerClass)) {
            $handlerCall=new $providerClass();
            General::log("Extcall from $idf - Called controller: ".$iwh[0]."->".$iwh[1]." - "."$"."callSign='".$callSign."'");
            $handlerCall->{$iwh[1]}($req,$apiPage);
            return true;
        }
    }
    return false;
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