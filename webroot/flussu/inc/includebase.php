<?php

define('PROJECT_ROOT', dirname(__DIR__, 2)."/../");

require_once PROJECT_ROOT . 'vendor/autoload.php';

use Flussu\Config;
use Flussu\Controllers\VersionController;

// VERSION
$FlussuVersion="0.0.unknown!";

$dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
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

if (!function_exists('redirect')) {
    function redirect(string $url) {
        header("Location: " . $url);
        exit;
    }
}

$FVP=explode(".", config("flussu.version").".".config("flussu.release"));
$v=$FVP[0];
$m=$FVP[1];
$r=$FVP[2];

if (!isset($_SESSION)) {
    session_start();
}
$SID= session_id();
$SDATA= print_r($_SESSION, true);
$user_id=$_SESSION["user_id"] ?? 0;
// QUI NON C'E' L'UTENTE ALLA RIPARTENZA
$user=new \Flussu\Persons\User();
if ($user_id>0) {
    $user->load($user_id);
    $_SESSION["username"] = $user->getEmail();
    $auk=\Flussu\General::getDateTimedApiKeyFromUser($user_id,60);
    $_SESSION["auk"] = $auk;
} 
$fc=new VersionController();
$dbv="v".$fc->getDbVersion();

function isUserLoggedIn() {
    global $user;
    return isset($user) && $user->getId()>0;
}

// Ottieni l'utente corrente
function getCurrentUser() {
    global $user;
    if (!isUserLoggedIn()) {
        return null;
    }
    return $user;
}

// Reindirizza al login se non autenticato
function requireLogin() {
    if (!isUserLoggedIn()) {
        header('Location: /flussu/login.php');
        exit;
    }
}

// Logout
function doLogout() {
    session_unset();
    session_destroy();
    header('Location: /flussu/login.php');
    exit;
}

// Gestione logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    doLogout();
}

if (!(isset($user) && $user->getId()>0)){
    $su=end(explode("/", $_SERVER["SCRIPT_URL"]));
    switch($su){
        //case "dashboard.php":   
        case "login.php":   
        case "forgot-password.php":
        case "reset-password.php":
        case "register.php":
            break;
        default:
        if (!$user->isActive()) {
            redirect("login.php");
        }
        break;
    }
}

$test="a123";

