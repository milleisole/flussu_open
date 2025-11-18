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
 * CLASS-NAME:       Google Drive Controller
 * CREATED DATE:     11.01.2025 - EXPERIMENTAL - TBD
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11:2025
 * -------------------------------------------------------*/

namespace Flussu\Controllers;

use Flussu\Contracts\ICloudStorageProvider;
use Flussu\Controllers\OauthController;
use Flussu\Controllers\LocalStorageManager;
use Flussu\General;
use Flussu\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Google\Service\Sheets;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\ValueRange;

class GoogleDriveController extends LocalStorageManager implements ICloudStorageProvider
{
    private OauthController $oauth;
    private Client $httpClient;
    private string $apiBaseUrl = 'https://www.googleapis.com/drive/v3';
    private ?string $cloudFolderId = null;
    private string $cloudFolderName = 'flussu_server_folder';
    private ?Sheets $sheetsService = null;
    public function __construct()
    {
        // Inizializza la gestione file locali
        parent::__construct();
        
        $this->oauth = new OauthController();
        $this->httpClient = new Client();
        
        // MODIFICATO: Aggiunti gli scope per Drive E per Sheets
        $this->oauth->addScope('https://www.googleapis.com/auth/drive');
        $this->oauth->addScope('https://www.googleapis.com/auth/spreadsheets');
        
        try {
            // L'inizializzazione della cartella Drive rimane invariata
            $this->initializeCloudFolder();
        } catch (\Exception $e) {
            General::addRowLog("AVVISO: Inizializzazione cartella fallita: " . $e->getMessage());
            $driveConfig = Config::init()->get('services.google.drive_config', []);
            if (isset($driveConfig['shared_folder_id'])) {
                $this->cloudFolderId = $driveConfig['shared_folder_id'];
                $this->cloudFolderName = 'Configured Folder';
                General::addRowLog("Usando direttamente folder ID dal config: " . $this->cloudFolderId);
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Inizializza la cartella flussu_server_folder su Google Drive
     */
    private function initializeCloudFolder(): void
    {
        try {
            $driveConfig = Config::init()->get('services.google.drive_config', []);
            $mode = $driveConfig['mode'] ?? 'shared_folder';
            
            General::addRowLog("Drive mode: " . $mode);
            
            if ($mode === 'shared_folder') {
                // Modalità 1: Usa una cartella condivisa esistente
                $this->initializeSharedFolder($driveConfig);
            } else {
                // Modalità 2: Usa cartella del service account e condividila
                $this->initializeServiceAccountFolder($driveConfig);
            }
            
        } catch (\Exception $e) {
            General::addRowLog("Errore inizializzazione cartella cloud: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modalità 1: Usa cartella condivisa da un altro utente
     */
    private function initializeSharedFolder(array $config): void
    {
        $sharedFolderId = $config['shared_folder_id'] ?? null;
        
        if (!$sharedFolderId) {
            throw new \Exception("shared_folder_id non configurato per modalità shared_folder");
        }
        
        try {
            // Ottieni metadati completi con supporto per drive condivisi
            $folderMetadata = $this->makeRequest('GET', "/files/{$sharedFolderId}", [
                'query' => [
                    'fields' => 'id,name,mimeType,capabilities,owners,shared,permissions',
                    'supportsAllDrives' => true,
                    'supportsTeamDrives' => true  // Retrocompatibilità
                ]
            ]);
            
            if ($folderMetadata['mimeType'] !== 'application/vnd.google-apps.folder') {
                throw new \Exception("L'ID specificato non corrisponde a una cartella");
            }
            
            $this->cloudFolderId = $sharedFolderId;
            $this->cloudFolderName = $folderMetadata['name'];
            
            General::addRowLog("Cartella trovata: {$this->cloudFolderName} (ID: {$this->cloudFolderId})");
            General::addRowLog("Owner: " . ($folderMetadata['owners'][0]['emailAddress'] ?? 'unknown'));
            General::addRowLog("Shared: " . ($folderMetadata['shared'] ? 'YES' : 'NO'));
            
            // Log dettagliato delle capabilities
            $caps = $folderMetadata['capabilities'] ?? [];
            General::addRowLog("Capabilities: " . json_encode($caps));
            
            // Verifica permessi più permissiva
            $canWrite = false;
            
            // Controlla varie capabilities
            if (isset($caps['canAddChildren']) && $caps['canAddChildren']) {
                $canWrite = true;
                General::addRowLog("✓ canAddChildren = true");
            }
            if (isset($caps['canEdit']) && $caps['canEdit']) {
                $canWrite = true;
                General::addRowLog("✓ canEdit = true");
            }
            if (isset($caps['canCreateFolders']) && $caps['canCreateFolders']) {
                $canWrite = true;
                General::addRowLog("✓ canCreateFolders = true");
            }
            
            // Se non abbiamo trovato permessi di scrittura ma la cartella è condivisa,
            // proviamo comunque (il problema potrebbe essere nella risposta dell'API)
            if (!$canWrite && $folderMetadata['shared']) {
                General::addRowLog("AVVISO: Nessun permesso di scrittura rilevato, ma la cartella è condivisa. Procedo comunque.");
                $canWrite = true;
            }
            
            if (!$canWrite) {
                throw new \Exception(
                    "Il service account non ha permessi di scrittura sulla cartella. " .
                    "Verifica che il service account (" . Config::init()->get('services.google.client_email') . ") " .
                    "abbia il ruolo 'Editor' sulla cartella."
                );
            }
            
            General::addRowLog("Cartella condivisa configurata con successo: {$this->cloudFolderName}");
            
        } catch (\Exception $e) {
            General::addRowLog("ERRORE in initializeSharedFolder: " . $e->getMessage());
            throw new \Exception("Impossibile accedere alla cartella condivisa: " . $e->getMessage());
        }
    }

    /**
     * Modalità 2: Crea/usa cartella del service account e condividila
     */
    private function initializeServiceAccountFolder(array $config): void
    {
        // Cerca o crea la cartella del service account
        $query = [
            "name = '{$this->cloudFolderName}'",
            "mimeType = 'application/vnd.google-apps.folder'",
            "trashed = false",
            "'me' in owners"  // Solo cartelle di proprietà del service account
        ];
        
        $params = [
            'q' => implode(' and ', $query),
            'fields' => 'files(id,name)',
            'pageSize' => 1
        ];
        
        $result = $this->makeRequest('GET', '/files', ['query' => $params]);
        
        if (!empty($result['files'])) {
            $this->cloudFolderId = $result['files'][0]['id'];
            General::addRowLog("Usando cartella esistente del service account: " . $this->cloudFolderId);
        } else {
            // Crea la cartella
            $metadata = [
                'name' => $this->cloudFolderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ];
            
            $result = $this->makeRequest('POST', '/files', [
                'json' => $metadata,
                'query' => ['fields' => 'id,name']
            ]);
            
            $this->cloudFolderId = $result['id'];
            General::addRowLog("Creata nuova cartella del service account: " . $this->cloudFolderId);
        }
        
        // Condividi con gli utenti configurati
        $shareWith = $config['share_with'] ?? [];
        foreach ($shareWith as $share) {
            if (isset($share['email']) && isset($share['role'])) {
                $this->shareFolder($this->cloudFolderId, $share['email'], $share['role']);
            }
        }
    }

    /**
     * Condivide una cartella con un utente
     */
    private function shareFolder(string $folderId, string $email, string $role = 'writer'): bool
    {
        try {
            // Verifica se già condivisa
            $perms = $this->makeRequest('GET', "/files/{$folderId}/permissions", [
                'query' => ['fields' => 'permissions(emailAddress)']
            ]);
            
            $alreadyShared = false;
            foreach ($perms['permissions'] ?? [] as $perm) {
                if (($perm['emailAddress'] ?? '') === $email) {
                    $alreadyShared = true;
                    break;
                }
            }
            
            if (!$alreadyShared) {
                $permission = [
                    'type' => 'user',
                    'role' => $role,
                    'emailAddress' => $email
                ];
                
                $this->makeRequest('POST', "/files/{$folderId}/permissions", [
                    'json' => $permission,
                    'query' => ['sendNotificationEmail' => false]
                ]);
                
                General::addRowLog("Cartella {$folderId} condivisa con {$email} (ruolo: {$role})");
            } else {
                General::addRowLog("Cartella {$folderId} già condivisa con {$email}");
            }
            
            return true;
            
        } catch (\Exception $e) {
            General::addRowLog("Errore condivisione cartella con {$email}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload di un file locale su Google Drive
     * 
     * @param string $localFilePath Path del file locale
     * @param string|null $remoteName Nome del file su Drive (se null usa il nome locale)
     * @param string|null $folderId ID cartella di destinazione (se null usa flussu_server_folder)
     * @return array Metadati del file caricato
     */
    public function uploadLocalFile(string $localFilePath, ?string $remoteName = null, ?string $folderId = null): array
    {
        if (!file_exists($localFilePath)) {
            throw new \Exception("File locale non trovato: " . $localFilePath);
        }
        
        $content = $this->readLocalFile($localFilePath);
        $fileName = $remoteName ?? basename($localFilePath);
        $mimeType = mime_content_type($localFilePath) ?: 'application/octet-stream';
        
        // Se non specificata una cartella, usa quella di default
        if ($folderId === null) {
            $folderId = $this->cloudFolderId;
        }
        
        // QUI: Verifichiamo che cloudFolderId sia impostato
        General::addRowLog("uploadLocalFile: usando folder ID: " . ($folderId ?? 'NULL'));
        
        return $this->uploadFile($fileName, $content, $folderId, $mimeType);
    }
    
    /**
     * {@inheritdoc}
     */
    public function uploadFile(string $fileName, string $content, ?string $folderId = null, ?string $mimeType = null): array
    {
        if ($folderId === null) {
            if ($this->cloudFolderId === null) {
                throw new \Exception("Nessuna cartella cloud configurata.");
            }
            $folderId = $this->cloudFolderId;
        }
        
        General::addRowLog("Upload file '{$fileName}' nella cartella: {$folderId}");
        
        // Determina la modalità dal config
        $driveConfig = Config::init()->get('services.google.drive_config', []);
        $mode = $driveConfig['mode'] ?? 'shared_folder';
        
        try {
            // Se siamo in modalità service_account_folder, l'upload è semplice
            if ($mode === 'service_account_folder') {
                return $this->uploadFileSimple($fileName, $content, $folderId, $mimeType);
            }
            
            // Altrimenti, proviamo con supporto per cartelle condivise
            return $this->uploadFileToSharedFolder($fileName, $content, $folderId, $mimeType);
            
        } catch (ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            $statusCode = $e->getResponse()->getStatusCode();
            
            General::addRowLog("Errore upload (status {$statusCode}): " . $errorBody);
            
            if ($statusCode === 403 && strpos($errorBody, 'storage quota') !== false) {
                throw new \Exception(
                    "Errore quota storage. Possibili soluzioni:\n" .
                    "1. Cambia modalità in 'service_account_folder' nel config\n" .
                    "2. Verifica che la cartella sia condivisa correttamente\n" .
                    "3. Usa Google Workspace con Shared Drives"
                );
            }
            
            throw new \Exception("Errore upload: " . $errorBody);
        }
    }

    /**
     * Upload semplice per cartelle del service account
     */
    private function uploadFileSimple(string $fileName, string $content, string $folderId, ?string $mimeType): array
    {
        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId]
        ];
        
        // Upload in multipart
        $multipart = [
            [
                'name' => 'metadata',
                'contents' => json_encode($metadata),
                'headers' => ['Content-Type' => 'application/json']
            ],
            [
                'name' => 'file',
                'contents' => $content,
                'headers' => ['Content-Type' => $mimeType ?? 'application/octet-stream']
            ]
        ];
        
        $response = $this->httpClient->post(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
            [
                'multipart' => $multipart,
                'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()]
            ]
        );
        
        $result = json_decode($response->getBody()->getContents(), true);
        General::addRowLog("Upload completato. File ID: " . $result['id']);
        return $this->standardizeFileMetadata($result);
    }

    /**
     * Upload per cartelle condivise (più complesso)
     */
    private function uploadFileToSharedFolder(string $fileName, string $content, string $folderId, ?string $mimeType): array
    {
        // Prima crea il file metadata
        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId]
        ];
        
        // Prova il resumable upload
        $initResponse = $this->httpClient->post(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'X-Upload-Content-Type' => $mimeType ?? 'application/octet-stream',
                    'X-Upload-Content-Length' => strlen($content)
                ],
                'body' => json_encode($metadata)
            ]
        );
        
        // Ottieni l'URL di upload
        $uploadUrl = $initResponse->getHeader('Location')[0];
        
        // Carica il contenuto
        $uploadResponse = $this->httpClient->put(
            $uploadUrl,
            [
                'headers' => [
                    'Content-Type' => $mimeType ?? 'application/octet-stream'
                ],
                'body' => $content
            ]
        );
        
        $result = json_decode($uploadResponse->getBody()->getContents(), true);
        General::addRowLog("Upload resumable completato. File ID: " . $result['id']);
        return $this->standardizeFileMetadata($result);
    }



    /**
     * Download di un file da Google Drive in locale
     * 
     * @param string $fileId ID del file su Drive
     * @param string|null $localPath Path locale dove salvare (se null genera automaticamente)
     * @param bool $toTemp Se true salva in temp, altrimenti in Uploads
     * @return string Path del file salvato
     */
    public function downloadToLocal(string $fileId, ?string $localPath = null, bool $toTemp = false): string
    {
        // Ottieni metadata per il nome del file
        $metadata = $this->getFileMetadata($fileId);
        
        // Download contenuto
        $content = $this->downloadFile($fileId);
        
        // Determina il path locale
        if ($localPath === null) {
            $localPath = $metadata['name'];
        }
        
        // Salva localmente
        return $this->saveLocalFile($content, $localPath, $toTemp);
    }
    
    /**
     * Sincronizza un file locale con Google Drive
     * Se il file esiste già su Drive, lo aggiorna
     * 
     * @param string $localFilePath Path del file locale
     * @param string|null $remoteName Nome remoto (se diverso)
     * @return array Metadati del file su Drive
     */
    public function syncLocalFile(string $localFilePath, ?string $remoteName = null): array
    {
        $fileName = $remoteName ?? basename($localFilePath);
        
        // Cerca se il file esiste già
        $searchResult = $this->searchFiles($fileName, $this->cloudFolderId);
        
        if (!empty($searchResult['files'])) {
            // File esiste, aggiorna
            $fileId = $searchResult['files'][0]['id'];
            $content = $this->readLocalFile($localFilePath);
            return $this->updateFile($fileId, $content);
        } else {
            // File non esiste, carica
            return $this->uploadLocalFile($localFilePath, $fileName);
        }
    }
    
    /**
     * Lista tutti i file nella cartella flussu_server_folder
     */
    public function listCloudFiles(array $options = []): array
    {
        return $this->listFiles($this->cloudFolderId, $options);
    }
    
    // ===== METODI DELL'INTERFACCIA ICloudStorageProvider =====
    
    /**
     * {@inheritdoc}
     */
    public function listFiles(?string $folderId = null, array $options = []): array
    {
        // Se non specificata, usa la cartella cloud di default
        if ($folderId === null) {
            $folderId = $this->cloudFolderId;
        }
        
        $query = ["'{$folderId}' in parents", "trashed = false"];
        
        $params = [
            'q' => implode(' and ', $query),
            'pageSize' => $options['pageSize'] ?? 100,
            'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink),nextPageToken',
        ];
        
        if (isset($options['orderBy'])) {
            $params['orderBy'] = $options['orderBy'];
        }
        
        if (isset($options['pageToken'])) {
            $params['pageToken'] = $options['pageToken'];
        }
        
        $result = $this->makeRequest('GET', '/files', ['query' => $params]);
        
        // Standardizza i risultati
        $standardizedFiles = [];
        if (isset($result['files'])) {
            foreach ($result['files'] as $file) {
                $standardizedFiles[] = $this->standardizeFileMetadata($file);
            }
        }
        
        return [
            'files' => $standardizedFiles,
            'nextPageToken' => $result['nextPageToken'] ?? null
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function searchFiles(string $query, ?string $folderId = null, array $options = []): array
    {
        // Se non specificata, cerca nella cartella cloud di default
        if ($folderId === null) {
            $folderId = $this->cloudFolderId;
        }
        
        $googleQuery = [];
        
        // Supporta ricerca per nome e full-text
        if (isset($options['searchType']) && $options['searchType'] === 'fulltext') {
            $googleQuery[] = "fullText contains '{$query}'";
        } else {
            $googleQuery[] = "name contains '{$query}'";
        }
        
        $googleQuery[] = "'{$folderId}' in parents";
        $googleQuery[] = "trashed = false";
        
        $params = [
            'q' => implode(' and ', $googleQuery),
            'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink)',
        ];
        
        $result = $this->makeRequest('GET', '/files', ['query' => $params]);
        
        // Standardizza i risultati
        $standardizedFiles = [];
        if (isset($result['files'])) {
            foreach ($result['files'] as $file) {
                $standardizedFiles[] = $this->standardizeFileMetadata($file);
            }
        }
        
        return [
            'files' => $standardizedFiles,
            'nextPageToken' => $result['nextPageToken'] ?? null
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFileMetadata(string $fileId): array
    {
        $params = [
            'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink,description,starred,capabilities',
            'supportsAllDrives' => true
        ];
        
        $result = $this->makeRequest('GET', "/files/{$fileId}", ['query' => $params]);
        return $this->standardizeFileMetadata($result);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fileExists(string $fileId): bool
    {
        try {
            $this->getFileMetadata($fileId);
            return true;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'non trovata') !== false) {
                return false;
            }
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): array
    {
        // Se non specificato il parent, usa la cartella cloud di default
        if ($parentFolderId === null) {
            $parentFolderId = $this->cloudFolderId;
        }
        
        $metadata = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId]
        ];
        
        $result = $this->makeRequest('POST', '/files', [
            'json' => $metadata,
            'query' => ['fields' => 'id,name,mimeType,webViewLink,parents']
        ]);
        
        return $this->standardizeFileMetadata($result);
    }
    
    /**
     * {@inheritdoc}
     */
    public function downloadFile(string $fileId): string
    {
        // Per Google Docs/Sheets/Slides bisogna esportare
        $metadata = $this->getFileMetadata($fileId);
        
        if (strpos($metadata['mimeType'], 'vnd.google-apps') !== false) {
            // È un documento Google, dobbiamo esportarlo
            $exportMimeType = $this->getExportMimeType($metadata['mimeType']);
            
            $response = $this->httpClient->get(
                "{$this->apiBaseUrl}/files/{$fileId}/export",
                [
                    'query' => ['mimeType' => $exportMimeType],
                    'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()]
                ]
            );
            
            return $response->getBody()->getContents();
        } else {
            // File normale, download diretto
            $response = $this->httpClient->get(
                "{$this->apiBaseUrl}/files/{$fileId}",
                [
                    'query' => ['alt' => 'media'],
                    'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()]
                ]
            );
            
            return $response->getBody()->getContents();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function shareMyFolderWith(string $email, string $role = 'writer'): bool
    {
        try {
            // Usa la cartella del service account
            $myFolderId = '1pIp9DXKF851KcpiFg8UchsM8l_LxkN8T'; // flussu_server_folder owned by service account
            
            $permission = [
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $email
            ];
            
            $this->makeRequest('POST', "/files/{$myFolderId}/permissions", [
                'json' => $permission,
                'query' => ['sendNotificationEmail' => false]
            ]);
            
            General::addRowLog("Cartella {$myFolderId} condivisa con {$email}");
            return true;
            
        } catch (\Exception $e) {
            General::addRowLog("Errore condivisione: " . $e->getMessage());
            return false;
        }
    }
    public function debugPermissions(): array
    {
        $debug = [
            'service_account' => Config::init()->get('services.google.client_email'),
            'configured_folder_id' => Config::init()->get('services.google.shared_folder_id'),
            'active_folder_id' => $this->cloudFolderId,
            'folder_name' => $this->cloudFolderName,
            'can_access' => false,
            'permissions' => []
        ];
        
        try {
            // Prova a ottenere i metadati della cartella
            $folderMeta = $this->getFileMetadata($this->cloudFolderId);
            $debug['can_access'] = true;
            
            // Prova a ottenere i permessi
            $perms = $this->makeRequest('GET', "/files/{$this->cloudFolderId}/permissions", [
                'query' => ['fields' => 'permissions(emailAddress,role,type)']
            ]);
            
            $debug['permissions'] = $perms['permissions'] ?? [];
            
        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
        }
        
        return $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(string $fileId, string $content): array
    {
        // Per Google Drive, l'update del contenuto usa un endpoint diverso
        $response = $this->httpClient->patch(
            "https://www.googleapis.com/upload/drive/v3/files/{$fileId}?uploadType=media",
            [
                'body' => $content,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/octet-stream'
                ]
            ]
        );
        
        $result = json_decode($response->getBody()->getContents(), true);
        return $this->standardizeFileMetadata($result);
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->makeRequest('DELETE', "/files/{$fileId}");
            return true;
        } catch (\Exception $e) {
            General::addRowLog("Errore eliminazione file {$fileId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function moveFile(string $fileId, string $newParentId): array
    {
        // Prima ottieni i parent attuali
        $file = $this->makeRequest('GET', "/files/{$fileId}", [
            'query' => ['fields' => 'parents']
        ]);
        
        $previousParents = implode(',', $file['parents'] ?? []);
        
        // Sposta il file
        $result = $this->makeRequest('PATCH', "/files/{$fileId}", [
            'query' => [
                'addParents' => $newParentId,
                'removeParents' => $previousParents,
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink'
            ]
        ]);
        
        return $this->standardizeFileMetadata($result);
    }
    
    /**
     * {@inheritdoc}
     */
    public function copyFile(string $fileId, ?string $newName = null, ?string $folderId = null): array
    {
        $copyMetadata = [];
        
        if ($newName !== null) {
            $copyMetadata['name'] = $newName;
        }
        
        // Se non specificata, copia nella cartella cloud di default
        if ($folderId === null) {
            $folderId = $this->cloudFolderId;
        }
        $copyMetadata['parents'] = [$folderId];
        
        $result = $this->makeRequest('POST', "/files/{$fileId}/copy", [
            'json' => $copyMetadata,
            'query' => ['fields' => 'id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink']
        ]);
        
        return $this->standardizeFileMetadata($result);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getShareableLink(string $fileId, array $options = []): string
    {
        // Imposta i permessi di condivisione se richiesto
        if (isset($options['public']) && $options['public'] === true) {
            try {
                $this->makeRequest('POST', "/files/{$fileId}/permissions", [
                    'json' => [
                        'type' => 'anyone',
                        'role' => 'reader'
                    ]
                ]);
            } catch (\Exception $e) {
                General::addRowLog("Errore impostazione permessi pubblici: " . $e->getMessage());
            }
        }
        
        // Ottieni il link
        $file = $this->getFileMetadata($fileId);
        return $file['webUrl'] ?? '';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStorageInfo(): array
    {
        try {
            $result = $this->makeRequest('GET', '/about', [
                'query' => ['fields' => 'storageQuota']
            ]);
            
            return [
                'used' => intval($result['storageQuota']['usage'] ?? 0),
                'total' => intval($result['storageQuota']['limit'] ?? 0),
                'free' => intval(($result['storageQuota']['limit'] ?? 0) - ($result['storageQuota']['usage'] ?? 0))
            ];
        } catch (\Exception $e) {
            // Service account potrebbero non avere quota
            General::addRowLog("Impossibile ottenere info storage (normale per service account): " . $e->getMessage());
            return [
                'used' => 0,
                'total' => 0,
                'free' => 0
            ];
        }
    }
    
    // ===== METODI HELPER PRIVATI =====
    
    /**
     * Ottiene il token di accesso
     */
    private function getAccessToken(): string
    {
        $tokenData = $this->oauth->getAccessToken();
        return $tokenData['access_token'];
    }
    
    /**
     * Esegue una richiesta autenticata all'API di Google Drive
     */
    private function makeRequest(string $method, string $endpoint, array $options = []): array
    {
        try {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                ['Authorization' => 'Bearer ' . $this->getAccessToken()]
            );
            
            $response = $this->httpClient->request(
                $method,
                $this->apiBaseUrl . $endpoint,
                $options
            );
            
            $body = $response->getBody()->getContents();
            return $body ? json_decode($body, true) : [];
            
        } catch (ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            General::addRowLog("Errore Google Drive API: " . $errorBody);
            
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new \Exception("Risorsa non trovata");
            }
            
            throw new \Exception("Errore API Google Drive: " . $errorBody);
        }
    }
    
    /**
     * Converte i metadati di Google Drive nel formato standard
     */
    private function standardizeFileMetadata(array $googleFile): array
    {
        return [
            'id' => $googleFile['id'],
            'name' => $googleFile['name'],
            'type' => isset($googleFile['mimeType']) && $googleFile['mimeType'] === 'application/vnd.google-apps.folder' ? 'folder' : 'file',
            'mimeType' => $googleFile['mimeType'] ?? null,
            'size' => $googleFile['size'] ?? 0,
            'createdTime' => $googleFile['createdTime'] ?? null,
            'modifiedTime' => $googleFile['modifiedTime'] ?? null,
            'parents' => $googleFile['parents'] ?? [],
            'webUrl' => $googleFile['webViewLink'] ?? null,
            'downloadUrl' => $googleFile['webContentLink'] ?? null,
            'provider' => 'google_drive'
        ];
    }
    
    /**
     * Determina il MIME type di esportazione per i documenti Google
     */
    private function getExportMimeType(string $googleMimeType): string
    {
        $exportMap = [
            'application/vnd.google-apps.document' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.google-apps.spreadsheet' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.google-apps.presentation' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.google-apps.drawing' => 'image/png',
        ];
        
        return $exportMap[$googleMimeType] ?? 'application/pdf';
    }

    // FUNZIONI DI ISPEZIONE E INFORMAZIONI ACCOUNT

    /**
     * Ottiene informazioni sull'account corrente
     */
    public function getAccountInfo(): array
    {
        try {
            $about = $this->makeRequest('GET', '/about', [
                'query' => ['fields' => 'user(displayName,emailAddress,permissionId),storageQuota']
            ]);
            
            return [
                'success' => true,
                'user' => $about['user'] ?? [],
                'storageQuota' => $about['storageQuota'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Lista TUTTE le cartelle accessibili (root e condivise)
     */
    public function listAllAccessibleFolders(): array
    {
        try {
            // Lista tutte le cartelle, incluse quelle condivise
            $params = [
                'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'fields' => 'files(id,name,owners,shared,sharingUser,permissions)',
                'pageSize' => 100,
                'includeItemsFromAllDrives' => true,
                'supportsAllDrives' => true
            ];
            
            $result = $this->makeRequest('GET', '/files', ['query' => $params]);
            
            $folders = [];
            foreach ($result['files'] ?? [] as $folder) {
                $ownerEmail = $folder['owners'][0]['emailAddress'] ?? 'sconosciuto';
                $folders[] = [
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'owner' => $ownerEmail,
                    'shared' => $folder['shared'] ?? false,
                    'sharedBy' => $folder['sharingUser']['emailAddress'] ?? null
                ];
            }
            
            return $folders;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Verifica dettagli di una specifica cartella
     */
    public function inspectFolder(string $folderId): array
    {
        try {
            // Ottieni metadati completi
            $metadata = $this->makeRequest('GET', "/files/{$folderId}", [
                'query' => [
                    'fields' => 'id,name,mimeType,owners,shared,sharingUser,capabilities,permissions',
                    'supportsAllDrives' => true
                ]
            ]);
            
            // Prova a ottenere i permessi dettagliati
            $permissions = [];
            try {
                $permsResponse = $this->makeRequest('GET', "/files/{$folderId}/permissions", [
                    'query' => [
                        'fields' => 'permissions(id,type,role,emailAddress,displayName)',
                        'supportsAllDrives' => true
                    ]
                ]);
                $permissions = $permsResponse['permissions'] ?? [];
            } catch (\Exception $e) {
                $permissions = ['error' => $e->getMessage()];
            }
            
            return [
                'metadata' => $metadata,
                'permissions' => $permissions,
                'capabilities' => $metadata['capabilities'] ?? []
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Getter per cloudFolderId (per debug)
     */
    public function getCloudFolderId(): ?string
    {
        return $this->cloudFolderId;
    }

    /**
     * Test di scrittura nella cartella
     */
    public function testFolderWrite(string $folderId = null): array
    {
        if ($folderId === null) {
            $folderId = $this->cloudFolderId;
        }
        
        $testFileName = 'test_write_' . uniqid() . '.txt';
        
        try {
            // Prova a creare un file di test
            $metadata = [
                'name' => $testFileName,
                'parents' => [$folderId],
                'mimeType' => 'text/plain'
            ];
            
            $result = $this->makeRequest('POST', '/files', [
                'json' => $metadata,
                'query' => [
                    'supportsAllDrives' => true,
                    'fields' => 'id,name'
                ]
            ]);
            
            // Se siamo arrivati qui, la scrittura funziona
            // Elimina il file di test
            $this->deleteFile($result['id']);
            
            return [
                'success' => true,
                'message' => 'Test di scrittura riuscito'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    // ===================================================================
    // NUOVO: METODI PER L'INTEGRAZIONE CON GOOGLE SHEETS
    // ===================================================================

    /**
     * Scrive o sostituisce i dati nella prima riga di un foglio di calcolo, ideale per i titoli.
     *
     * @param string      $spreadsheetId L'ID del foglio di calcolo.
     * @param array       $titles        Array associativo dei titoli [Colonna => Titolo].
     * @param string|null $sheetName     Nome del foglio. Se null, usa il primo. Se non esiste, lo crea.
     * @return bool True in caso di successo, false altrimenti.
     */
    public function spreadsheetLoadTitles(string $spreadsheetId, array $titles, ?string $sheetName = null): bool
    {
        try {
            $this->ensureSheetExists($spreadsheetId, $sheetName); // Assicura che lo sheet esista e aggiorna $sheetName se era null
            
            $row = $this->buildRowFromData($titles);
            if (empty($row)) return false;

            $range = "'$sheetName'!A1"; // Range esplicito per la prima riga
            
            $body = new ValueRange(['values' => [$row]]);
            $params = ['valueInputOption' => 'USER_ENTERED'];

            $this->getSheetsService()->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
            
            General::addRowLog("Titoli aggiornati con successo nel foglio '$sheetName'.");
            return true;

        } catch (\Exception $e) {
            General::addRowLog("Errore aggiornamento titoli: " . $e->getMessage());
            return false;
        }
    }

/**
 * Accoda una nuova riga di dati alla fine di un foglio di calcolo.
 *
 * @param string      $spreadsheetId L'ID del foglio di calcolo.
 * @param array       $values        Array associativo dei valori [Colonna => Valore].
 * @param string|null $sheetName     Nome del foglio. Se null, usa il primo. Se non esiste, lo crea.
 * @param array       $formulas      Array associativo delle formule [Colonna => Formula].
 * @return bool True in caso di successo, false altrimenti.
 */
public function spreadsheetLoadValues(string $spreadsheetId, array $values, ?string $sheetName = null, array $formulas = []): bool
{
    try {
        $this->ensureSheetExists($spreadsheetId, $sheetName);

        // --- PASSO 1: Aggiungi i valori statici ---
        $staticRow = $this->buildRowFromData($values);
        if (empty($staticRow)) {
            // Se la riga è vuota ma ci sono formule, crea una riga con il giusto numero di celle vuote
            if (!empty($formulas)) {
                $maxCol = max(array_keys($formulas));
                for ($i=0; $i < (ord($maxCol) - ord('A') + 1); $i++) {
                    $staticRow[] = '';
                }
            } else {
                return false;
            }
        }

        $range = $sheetName;
        $body = new ValueRange(['values' => [$staticRow]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        
        $appendResult = $this->getSheetsService()->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

        // --- PASSO 2: Se ci sono formule, aggiorna la riga appena creata ---
        if (!empty($formulas) && $appendResult->getUpdates()->getUpdatedRange()) {
            
            $updatedRange = $appendResult->getUpdates()->getUpdatedRange();
            
            preg_match('/!(\w+)(\d+):/', $updatedRange, $matches);
            if (!isset($matches[2])) {
                 // Fallback per range singoli come A5
                preg_match('/!(\w+)(\d+)/', $updatedRange, $matches);
                if (!isset($matches[2])) throw new \Exception("Impossibile determinare la riga aggiornata.");
            }
            $rowNumber = (int)$matches[2];

            // Aggiorna ogni formula individualmente per non sovrascrivere altre celle
            foreach ($formulas as $column => $formula) {
                $finalFormula = str_replace('{row}', $rowNumber, $formula);
                $updateRange = "'$sheetName'!{$column}{$rowNumber}";
                $updateBody = new ValueRange(['values' => [[$finalFormula]]]);
                $this->getSheetsService()->spreadsheets_values->update($spreadsheetId, $updateRange, $updateBody, $params);
            }
            
            General::addRowLog("Formule aggiunte con successo alla riga $rowNumber.");
        }

        General::addRowLog("Valori aggiunti con successo al foglio '$sheetName'.");
        return true;

    } catch (\Exception $e) {
        General::addRowLog("Errore aggiunta valori/formule: " . $e->getMessage());
        return false;
    }
}
    // ===================================================================
    // NUOVO: METODI PRIVATI DI SUPPORTO PER SHEETS
    // ===================================================================

    /**
     * Inizializza e/o restituisce il servizio Google Sheets autenticato.
     */
    private function getSheetsService(): Sheets
    {
        if ($this->sheetsService === null) {
            $client = new \Google\Client();
            $client->setAccessToken($this->oauth->getAccessToken()['access_token']);
            $this->sheetsService = new Sheets($client);
        }
        return $this->sheetsService;
    }
    
    /**
     * Verifica se uno sheet esiste per nome e, se non esiste, lo crea.
     * Aggiorna la variabile $sheetName se era null.
     */
    private function ensureSheetExists(string $spreadsheetId, ?string &$sheetName): void
    {
        $service = $this->getSheetsService();
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheets = $spreadsheet->getSheets();

        // Se non è specificato un nome, usa il primo foglio disponibile
        if (empty($sheetName)) {
            $sheetName = $sheets[0]->getProperties()->getTitle();
            return;
        }

        // Cerca se il foglio esiste già
        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->getTitle() === $sheetName) {
                return; // Trovato, non fare nulla
            }
        }

        // Se siamo qui, il foglio non esiste e va creato
        $addSheetRequest = new AddSheetRequest([
            'properties' => new SheetProperties(['title' => $sheetName])
        ]);

        $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => [new Request(['addSheet' => $addSheetRequest])]
        ]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        General::addRowLog("Creato nuovo foglio: '$sheetName'");
    }

    /**
     * Converte un array associativo [Colonna => Valore] in un array sequenziale.
     */
    private function buildRowFromData(array $associativeData): array
    {
        if (empty($associativeData)) return [];
        $maxColumn = max(array_keys($associativeData));
        $sequentialRow = [];
        foreach (range('A', $maxColumn) as $column) {
            $sequentialRow[] = $associativeData[$column] ?? '';
        }
        return $sequentialRow;
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