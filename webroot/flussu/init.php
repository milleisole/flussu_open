<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * File di inizializzazione comune per le pagine della dashboard
 * --------------------------------------------------------------------*/

// Avvia la sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Config;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
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
        return Config::init()->get($key, $default);
    }
}

// Verifica se l'utente è loggato
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Ottieni l'utente corrente
function getCurrentUser() {
    if (!isUserLoggedIn()) {
        return null;
    }

    $user = new User();
    $user->load($_SESSION['user_id']);

    if ($user->getId() > 0) {
        return $user;
    }

    return null;
}

// Reindirizza al login se non autenticato
function requireLogin() {
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Logout
function doLogout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Gestione logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    doLogout();
}
