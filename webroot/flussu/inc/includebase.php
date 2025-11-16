<?php

define('PROJECT_ROOT', dirname(__DIR__, 2)."/../");

require_once PROJECT_ROOT . 'vendor/autoload.php';

use Flussu\General;
use Flussu\Config;

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

if ($_SESSION===null) {
    session_start();
}
$user=new \Flussu\Persons\User();
if (isset($_SESSION["user"])) {
    $user=$_SESSION["user"];
    if ($user->isActive) {
        redirect("dashboard.php");
    }
} 
if ($_SERVER["SCRIPT_URL"]!="/flussu/login.php" && $_SERVER["SCRIPT_URL"]!="/flussu/forgot-password.php")
    redirect("login.php");

