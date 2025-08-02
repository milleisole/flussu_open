<?php
/* --------------------------------------------------------------------*
 * Test completo per Google Drive Integration
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Controllers\OauthController;
use Flussu\Controllers\GoogleDriveController;
use Flussu\Config;

// Colori per output console
class Console {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const RESET = "\033[0m";
    
    public static function success($msg) {
        echo self::GREEN . "✓ " . $msg . self::RESET . "\n";
    }
    
    public static function error($msg) {
        echo self::RED . "✗ " . $msg . self::RESET . "\n";
    }
    
    public static function info($msg) {
        echo self::BLUE . "ℹ " . $msg . self::RESET . "\n";
    }
    
    public static function warning($msg) {
        echo self::YELLOW . "⚠ " . $msg . self::RESET . "\n";
    }
    
    public static function section($title) {
        echo "\n" . self::BLUE . "=== " . $title . " ===" . self::RESET . "\n\n";
    }
}

// Test principale
try {
    Console::section("TEST GOOGLE DRIVE INTEGRATION");
    
    // ===== TEST 0: Configurazione =====
    Console::section("Test 0: Verifica Configurazione");
    
    try {
        $config = Config::init();
        Console::success("Configurazione caricata correttamente");
        
        // Verifica presenza credenziali Google
        $clientEmail = $config->get('services.google.client_email');
        if ($clientEmail) {
            Console::success("Client email trovato: " . $clientEmail);
        } else {
            Console::error("Client email non trovato nella configurazione!");
            exit(1);
        }
    } catch (\Exception $e) {
        Console::error("Errore caricamento configurazione: " . $e->getMessage());
        exit(1);
    }
    
    // ===== TEST 1: OAuth Controller =====
    Console::section("Test 1: OAuth Controller");
    
    $oauth = new OauthController();
    Console::info("OauthController inizializzato");
    
    // Test connessione
    $testResult = $oauth->testConnection();
    
    if ($testResult['success']) {
        Console::success("Autenticazione OAuth riuscita!");
        Console::info("Account: " . $testResult['email']);
        Console::info("Token valido per: " . $testResult['token_expires_in'] . " secondi");
    } else {
        Console::error("Autenticazione OAuth fallita: " . $testResult['error']);
        exit(1);
    }
    
    // ===== TEST 2: Google Drive Controller - Inizializzazione =====
    Console::section("Test 2: Google Drive Controller - Inizializzazione");
    
    $drive = new GoogleDriveController();
    Console::success("GoogleDriveController inizializzato");
    
    // Verifica cartelle locali
    Console::info("Verifica cartelle locali:");
    Console::info("  - Upload dir: " . $drive->getUploadDir());
    Console::info("  - Temp dir: " . $drive->getTempDir());
    
    if (is_dir($drive->getUploadDir())) {
        Console::success("  ✓ Directory /Uploads esiste");
    } else {
        Console::error("  ✗ Directory /Uploads NON esiste!");
    }
    
    if (is_dir($drive->getTempDir())) {
        Console::success("  ✓ Directory /Uploads/temp esiste");
    } else {
        Console::error("  ✗ Directory /Uploads/temp NON esiste!");
    }
    
    Console::info("\nCartella su Google Drive: flussu_server_folder (creata automaticamente)");
    
    // ===== TEST 3: Operazioni con file locali =====
    Console::section("Test 3: Operazioni con file locali");
    
    // Test 3.1: Crea file locale temporaneo
    Console::info("Test 3.1: Creazione file locale temporaneo...");
    $tempContent = "File temporaneo locale di test\n";
    $tempContent .= "Creato: " . date('Y-m-d H:i:s') . "\n";
    $tempContent .= "Questo file sarà caricato su Google Drive";
    
    $tempFilePath = $drive->saveLocalFile($tempContent, "local_temp_" . date('YmdHis') . ".txt", true);
    Console::success("File temporaneo salvato localmente: " . basename($tempFilePath));
    
    // Test 3.2: Crea file locale persistente
    Console::info("\nTest 3.2: Creazione file locale persistente...");
    $persistentContent = "File persistente locale di test Flussu\n";
    $persistentContent .= "Data creazione: " . date('Y-m-d H:i:s') . "\n";
    $persistentContent .= "Test di integrazione locale -> cloud";
    
    $persistentFilePath = $drive->saveLocalFile($persistentContent, "local_persistent_" . date('Ymd') . ".txt", false);
    Console::success("File persistente salvato localmente: " . basename($persistentFilePath));
    
    // Test 3.3: Lista file locali
    Console::info("\nTest 3.3: Lista file locali...");
    $localFiles = $drive->listLocalFiles(false); // Lista da Uploads
    Console::success("Trovati " . count($localFiles) . " file in /Uploads:");
    foreach ($localFiles as $file) {
        Console::info("  - " . $file['name'] . " (" . formatBytes($file['size']) . ")");
    }
    
    $tempFiles = $drive->listLocalFiles(true); // Lista da temp
    Console::success("Trovati " . count($tempFiles) . " file in /Uploads/temp:");
    foreach ($tempFiles as $file) {
        Console::info("  - " . $file['name'] . " (" . formatBytes($file['size']) . ")");
    }
    

    // ===== DEBUG SECTION =====
Console::section("DEBUG: Verifica modalità Drive");

$driveConfig = Config::init()->get('services.google.drive_config', []);
$mode = $driveConfig['mode'] ?? 'shared_folder';

Console::info("Modalità configurata: " . $mode);

if ($mode === 'shared_folder') {
    Console::warning("Stai usando una cartella condivisa. Se ricevi errori di quota, considera di cambiare a 'service_account_folder'");
    
    // Suggerisci la configurazione alternativa
    Console::info("\nPer cambiare modalità, modifica il config così:");
    Console::info('"drive_config": {');
    Console::info('    "mode": "service_account_folder",');
    Console::info('    "share_with": [');
    Console::info('        {"email": "aldo@milleisole.com", "role": "writer"}');
    Console::info('    ]');
    Console::info('}');
}



Console::section("DEBUG: Analisi ambiente Google Drive");

// Info account
Console::info("1. Informazioni account service:");
$accountInfo = $drive->getAccountInfo();
if ($accountInfo['success']) {
    Console::success("Account: " . ($accountInfo['user']['emailAddress'] ?? 'N/A'));
    Console::info("Display Name: " . ($accountInfo['user']['displayName'] ?? 'N/A'));
    Console::info("Permission ID: " . ($accountInfo['user']['permissionId'] ?? 'N/A'));
    Console::info("Storage Quota: " . json_encode($accountInfo['storageQuota']));
} else {
    Console::error("Errore recupero info account: " . $accountInfo['error']);
}

// Cloud folder ID
Console::info("\n2. Cloud Folder ID configurato: " . ($drive->getCloudFolderId() ?? 'NULL'));

// Lista tutte le cartelle accessibili
Console::info("\n3. Cartelle accessibili:");
$allFolders = $drive->listAllAccessibleFolders();
if (isset($allFolders['error'])) {
    Console::error("Errore: " . $allFolders['error']);
} else {
    Console::success("Trovate " . count($allFolders) . " cartelle:");
    foreach ($allFolders as $folder) {
        $shared = $folder['shared'] ? " [CONDIVISA" . ($folder['sharedBy'] ? " da " . $folder['sharedBy'] : "") . "]" : "";
        Console::info("  - {$folder['name']} (ID: {$folder['id']}) - Owner: {$folder['owner']}{$shared}");
    }
}

// Se abbiamo un folder ID configurato, analizziamolo
$configuredFolderId = Config::init()->get('services.google.shared_folder_id');
if ($configuredFolderId) {
    Console::info("\n4. Analisi cartella configurata (ID: $configuredFolderId):");
    $folderDetails = $drive->inspectFolder($configuredFolderId);
    
    if (isset($folderDetails['error'])) {
        Console::error("ERRORE accesso cartella: " . $folderDetails['error']);
    } else {
        Console::success("Cartella: " . $folderDetails['metadata']['name']);
        Console::info("Owner: " . ($folderDetails['metadata']['owners'][0]['emailAddress'] ?? 'N/A'));
        Console::info("È condivisa: " . ($folderDetails['metadata']['shared'] ? 'SI' : 'NO'));
        
        Console::info("\nPermessi:");
        if (is_array($folderDetails['permissions'])) {
            foreach ($folderDetails['permissions'] as $perm) {
                Console::info("  - " . ($perm['emailAddress'] ?? $perm['type'] ?? 'N/A') . 
                             " => Ruolo: " . $perm['role']);
            }
        }
        
        Console::info("\nCapabilities (cosa può fare il service account):");
        $caps = $folderDetails['capabilities'];
        Console::info("  - canAddChildren: " . ($caps['canAddChildren'] ?? 'N/A'));
        Console::info("  - canEdit: " . ($caps['canEdit'] ?? 'N/A'));
        Console::info("  - canShare: " . ($caps['canShare'] ?? 'N/A'));
    }
}

Console::warning("\nContinuare con i test? (s/n): ");
$answer = trim(fgets(STDIN));
if (strtolower($answer) !== 's') {
    exit(0);
}

    // ===== TEST 4: Upload da locale a cloud =====
    Console::section("Test 4: Upload da locale a Google Drive");

    // DEBUG: Verifica stato del controller
    Console::info("DEBUG - Cloud Folder ID nel controller: " . 
        (method_exists($drive, 'getCloudFolderId') ? $drive->getCloudFolderId() : 'metodo non disponibile'));

    // Test 4.1: Upload file temporaneo
    Console::info("Test 4.1: Upload file temporaneo su Google Drive...");
    try {
        $uploadedTemp = $drive->uploadLocalFile($tempFilePath, "cloud_" . basename($tempFilePath));
        Console::success("File temporaneo caricato su cloud con ID: " . $uploadedTemp['id']);
        $cloudTempId = $uploadedTemp['id'];
    } catch (\Exception $e) {
        Console::error("Errore upload: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'quota storage') !== false) {
            Console::warning("\nSUGGERIMENTO: Cambia la modalità in 'service_account_folder' nel file di configurazione");
            Console::warning("Questo permetterà al service account di creare la propria cartella e condividerla");
        }
        
        Console::warning("\nVuoi continuare con gli altri test? (s/n): ");
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) !== 's') {
            exit(1);
        }
    }
    

    try {
        Console::info("Test 4.1.1: Modifica SpreadSheet su Google Drive...");
        // 1. Inizializza il controller come facevi prima
        $driveController = new GoogleDriveController();
        
        // L'ID del tuo foglio di calcolo
        $mySpreadsheetId = '19ugI8ybFY6HTKBW2yn0P_Q1V7Lx165FVBJszl2ebXLQ';

        // --- ESEMPIO 1: Caricare i titoli (sostituisce sempre la riga 1) ---
        Console::info( "Caricamento titoli: ");
        $titoli = [
            'A' => 'Data',
            'B' => 'Tipo Utente',
            'C' => 'Punteggio 1',
            'D' => 'Punteggio 2',
            'E' => 'Punteggio 3',
            'F' => 'Punteggio 4',
            'G' => 'totale punti',
            'H' => 'perc.Pippo',
        ];
        $done=$driveController->spreadsheetLoadTitles($mySpreadsheetId, $titoli, 'Quest1'); // Specifica il nome 'Vendite'
        $result="Error!\n";
        if ($done)
            $result="Ok.\n";
        Console::info($result);
        // --- ESEMPIO 2: Caricare una riga di valori nel foglio 'Vendite' ---
        Console::info("Caricamento prima riga di valori: ");
        $questionario = [
            'A' => date('Y-m-d'),
            'B' => "UC",
            'C' => 20,
            'D' => 15,
            'E' => 20,
            'F' => 30,
            'H' => "PCT"
        ];

        $formulePerRiga = [
            'G' => '=C{row}+D{row}+E{row}+F{row}'   // Calcola il totale punti
        ];
        $done=$driveController->spreadsheetLoadValues(
            $mySpreadsheetId, 
            $questionario, 
            'Quest1',      // Nome del foglio
            $formulePerRiga // Nuovo parametro con le formule
        );
        $result="Error!\n";
        if ($done)
            $result="Ok.\n";
        Console::info($result);



        // --- ESEMPIO 3: Caricare un'altra riga con colonne non consecutive ---
        Console::info("Caricamento seconda riga: ");
        $questionario = [
            'D' => 25,
            'A' => date('Y-m-d'),
            'B' => "UN",
            'H' => "PCT",
            'E' => 10,
            'C' => 18,
            'F' => 20
        ];

        $formulePerRiga = [
            'G' => '=C{row}+D{row}+E{row}+F{row}'   // Calcola il totale punti
        ];
        $done=$driveController->spreadsheetLoadValues($mySpreadsheetId, $questionario, 'Quest1', $formulePerRiga);
        $result="Error!\n";
        if ($done)
            $result="Ok.\n";
        Console::info($result);



        // --- ESEMPIO 4: Creare un nuovo foglio al volo e aggiungerci dati ---
        echo "Caricamento dati in un foglio che non esiste: ";
        $logData = [
            'A' => date('Y-m-d H:i:s'),
            'B' => 'INFO',
            'C' => 'Test di creazione automatica del foglio'
        ];
        // Poiché 'Log 2025' probabilmente non esiste, verrà creato automaticamente
        $done=$driveController->spreadsheetLoadValues($mySpreadsheetId, $logData, 'Log 2025'); 
        $result="Error!\n";
        if ($done)
            $result="Ok.\n";
        Console::info($result);


    } catch (\Exception $e) {
        echo "ERRORE CRITICO: " . $e->getMessage() . "\n";
    }












    // Test 4.2: Upload file persistente
    Console::info("\nTest 4.2: Upload file persistente su Google Drive...");
    $uploadedPersistent = $drive->uploadLocalFile($persistentFilePath);
    Console::success("File persistente caricato su cloud con ID: " . $uploadedPersistent['id']);
    $cloudPersistentId = $uploadedPersistent['id'];
    
    // Test 4.3: Lista file su cloud
    Console::info("\nTest 4.3: Lista file in flussu_server_folder...");
    $cloudFiles = $drive->listCloudFiles();
    Console::success("Trovati " . count($cloudFiles['files']) . " file su Google Drive:");
    foreach ($cloudFiles['files'] as $file) {
        Console::info("  - {$file['name']} (Tipo: {$file['type']}, ID: {$file['id']})");
    }
    
    // ===== TEST 5: Download da cloud a locale =====
    Console::section("Test 5: Download da Google Drive a locale");
    
    // Test 5.1: Download in Uploads
    Console::info("Test 5.1: Download file da cloud a /Uploads...");
    $downloadedPath = $drive->downloadToLocal($cloudPersistentId, "downloaded_" . date('YmdHis') . ".txt", false);
    Console::success("File scaricato in: " . $downloadedPath);
    
    // Verifica contenuto
    $downloadedContent = $drive->readLocalFile($downloadedPath);
    if ($downloadedContent === $persistentContent) {
        Console::success("Contenuto verificato correttamente!");
    } else {
        Console::error("Contenuto non corrisponde!");
    }
    
    // Test 5.2: Download in temp
    Console::info("\nTest 5.2: Download file da cloud a /Uploads/temp...");
    $downloadedTempPath = $drive->downloadToLocal($cloudTempId, null, true);
    Console::success("File scaricato in temp: " . $downloadedTempPath);
    
    // ===== TEST 6: Operazioni avanzate =====
    Console::section("Test 6: Operazioni avanzate");
    
    // Test 6.1: Sincronizzazione file
    Console::info("Test 6.1: Test sincronizzazione file...");
    
    // Modifica il file locale
    $modifiedContent = $persistentContent . "\n\n[MODIFICATO LOCALMENTE] " . date('Y-m-d H:i:s');
    $drive->saveLocalFile($modifiedContent, basename($persistentFilePath), false);
    
    // Sincronizza
    $syncResult = $drive->syncLocalFile($persistentFilePath);
    Console::success("File sincronizzato con successo");
    
    // Test 6.2: Crea sottocartella su cloud
    $testSubfolderName = "Test_Subfolder_" . date('YmdHis');
    Console::info("\nTest 6.2: Creazione sottocartella '$testSubfolderName' su cloud...");
    $subfolder = $drive->createFolder($testSubfolderName);
    Console::success("Sottocartella creata con ID: " . $subfolder['id']);
    
    // Test 6.3: Copia file nel cloud
    Console::info("\nTest 6.3: Copia file su cloud...");
    $copiedFile = $drive->copyFile(
        $cloudPersistentId, 
        "copia_cloud_" . date('YmdHis') . ".txt",
        $subfolder['id']
    );
    Console::success("File copiato con nuovo ID: " . $copiedFile['id']);
    
    // Test 6.4: Ricerca file su cloud
    Console::info("\nTest 6.4: Ricerca file su cloud con pattern 'cloud'...");
    $searchResults = $drive->searchFiles("cloud");
    Console::success("Trovati " . count($searchResults['files']) . " file corrispondenti:");
    foreach ($searchResults['files'] as $file) {
        Console::info("  - " . $file['name']);
    }
    
    // Test 6.5: Link condivisibile
    Console::info("\nTest 6.5: Generazione link condivisibile...");
    $shareLink = $drive->getShareableLink($cloudPersistentId, ['public' => true]);
    Console::success("Link condivisibile generato:");
    Console::info("  " . $shareLink);
    
    // ===== TEST 7: Pulizia file locali temporanei =====
    Console::section("Test 7: Pulizia file temporanei locali");
    
    Console::info("File in /Uploads/temp prima della pulizia:");
    $tempFilesBefore = $drive->listLocalFiles(true);
    Console::info("  Trovati " . count($tempFilesBefore) . " file");
    
    Console::info("\nPulizia file temporanei più vecchi di 0 giorni...");
    $deletedCount = $drive->cleanupTempFiles(0); // 0 giorni = elimina tutto
    Console::success("Eliminati $deletedCount file temporanei locali");
    
    // ===== TEST 8: Pulizia finale =====
    Console::section("Test 8: Pulizia file di test");
    
    Console::warning("Vuoi eliminare TUTTI i file di test? (s/n): ");
    $answer = trim(fgets(STDIN));
    
    if (strtolower($answer) === 's') {
        // Elimina file locali
        Console::info("\n--- Pulizia file locali ---");
        $localTestFiles = $drive->listLocalFiles(false, "local_*.txt");
        foreach ($localTestFiles as $file) {
            if ($drive->deleteLocalFile($file['path'])) {
                Console::success("Eliminato locale: " . $file['name']);
            }
        }
        
        $localTestFiles = $drive->listLocalFiles(false, "downloaded_*.txt");
        foreach ($localTestFiles as $file) {
            if ($drive->deleteLocalFile($file['path'])) {
                Console::success("Eliminato locale: " . $file['name']);
            }
        }
        
        // Elimina file su cloud
        Console::info("\n--- Pulizia file su cloud ---");
        $cloudFilesToDelete = [
            $cloudTempId => "File temporaneo cloud",
            $cloudPersistentId => "File persistente cloud",
            $copiedFile['id'] => "File copiato",
            $subfolder['id'] => "Sottocartella di test"
        ];
        
        foreach ($cloudFilesToDelete as $fileId => $description) {
            Console::info("Eliminazione cloud: $description...");
            if ($drive->deleteFile($fileId)) {
                Console::success("$description eliminato");
            } else {
                Console::warning("Impossibile eliminare: $description");
            }
        }
    } else {
        Console::warning("File di test mantenuti");
        Console::info("File locali in: " . $drive->getUploadDir());
        Console::info("File cloud in: flussu_server_folder");
    }
    
    // ===== RIEPILOGO =====
    Console::section("RIEPILOGO TEST");
    Console::success("Tutti i test completati!");
    Console::info("\nArchitettura utilizzata:");
    Console::info("  SERVER LOCALE (Flussu):");
    Console::info("    /Uploads           - File persistenti locali");
    Console::info("    /Uploads/temp      - File temporanei locali");
    Console::info("");
    Console::info("  GOOGLE DRIVE (Cloud):");
    Console::info("    /flussu_server_folder - Tutti i file caricati da Flussu");
    
    // Test info storage
    Console::section("Info Storage Cloud (opzionale)");
    try {
        $storageInfo = $drive->getStorageInfo();
        if ($storageInfo['total'] > 0) {
            Console::info("Storage utilizzato: " . formatBytes($storageInfo['used']));
            Console::info("Storage totale: " . formatBytes($storageInfo['total']));
            Console::info("Storage libero: " . formatBytes($storageInfo['free']));
        } else {
            Console::info("Info storage non disponibili (normale per service account)");
        }
    } catch (\Exception $e) {
        Console::info("Info storage non disponibili");
    }
    
} catch (\Exception $e) {
    Console::error("ERRORE CRITICO: " . $e->getMessage());
    Console::error("Stack trace:");
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Funzione helper per formattare i byte
 */
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

// Esegui lo script
Console::info("Avvio test Google Drive Integration...\n");