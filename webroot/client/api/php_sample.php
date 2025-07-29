<?php
require_once 'FlussuApiClient.php';
try {
    $flussu = new FlussuApiClient('srvdev4.flu.lt', '[wd3c6749d79118099]', 'it');
    
    // Start the workflow
    $invio=json_encode(["$".'testo' => "Questo è il testo che ti sto inviando",]);
    $response = $flussu->startWorkflow($invio);
    $result = $response["elms"] ;
    //echo "Workflow avviato:\n";
    //print_r($response);
    echo "ERROR:".$result["L$1"][0];
    echo "<hr>";
    echo "TEXT:".implode("<br>",array_slice(explode("<br>",$result["L$2"][0]),1));

} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}