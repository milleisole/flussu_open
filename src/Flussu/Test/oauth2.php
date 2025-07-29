<?php
// test_oauth_simple.php
use Flussu\Controllers\OauthController;
use Flussu\Flussuserver\Session;

try {
    // Inizializza sessione (adatta secondo il tuo sistema)
    $session = new Session(null);
    
    // Test OAuth
    $oauth = new OauthController();
    $result = $oauth->testToken($session);
    
    if ($result['success']) {
        echo "✅ Autenticazione Google riuscita!\n";
        echo "Account: " . $result['email'] . "\n";
        echo "Token valido per altri: " . $result['token_expires_in'] . " secondi\n";
    } else {
        echo "❌ Errore: " . $result['error'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Errore critico: " . $e->getMessage() . "\n";
}