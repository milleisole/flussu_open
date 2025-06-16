<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3 - Mille Isole SRL - Released under Apache License 2.0
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
 * CREATED DATE:     01.01.2025 - EXPERIMENTAL - TBD
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use GuzzleHttp\Client;
use Flussu\Session;

class GDriveController
{
    private $authController;
    private $uploadEndpoint = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
    private $downloadEndpoint = 'https://www.googleapis.com/drive/v3/files/'; // aggiungere {fileId}?alt=media per download
    private $metadataEndpoint = 'https://www.googleapis.com/drive/v3/files/'; // per metadata

    public function __construct($authController = null)
    {
        $this->authController = $authController ?: new OauthController();
    }

    /**
     * POST /drive/upload
     * Parametri (via POST):
     * - filename (string) Obbligatorio
     * - content_base64 (string) Opzionale
     * - local_path (string) Opzionale
     */
    public function upload()
    {
        header('Content-Type: application/json');

        $filename = $_POST['filename'] ?? null;
        if (!$filename) {
            http_response_code(400);
            echo json_encode(['error' => 'filename is required']);
            return;
        }

        // Verifica che filename non contenga slash per sicurezza
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid filename']);
            return;
        }

        $contentBase64 = $_POST['content_base64'] ?? null;
        $localPath = $_POST['local_path'] ?? null;

        if (!$contentBase64 && !$localPath) {
            http_response_code(400);
            echo json_encode(['error' => 'Either content_base64 or local_path must be provided']);
            return;
        }

        // Ottenimento del token di accesso
        $tokenData = $this->authController->getAccessTokenInternal();
        $accessToken = $tokenData['access_token'];

        $fileContent = null;
        $mimeType = 'application/octet-stream';

        // Se base64, decodifica
        if ($contentBase64) {
            $decoded = base64_decode($contentBase64, true);
            if ($decoded === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid base64 content']);
                return;
            }
            $fileContent = $decoded;
        } elseif ($localPath) {
            // Verifica se il file esiste localmente
            if (!file_exists($localPath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Local file not found']);
                return;
            }
            // Verifica dimensione (max 50MB)
            if (filesize($localPath) > 50 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'File too large (>50MB)']);
                return;
            }
            $fileContent = file_get_contents($localPath);
            // Possiamo cercare di dedurre il mimeType
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $localPath);
            finfo_close($finfo);
        }

        // Creiamo la richiesta multiparte per Drive:
        // Parte 1: metadata in JSON
        // Parte 2: contenuto file
        $metadata = [
            'name' => $filename
        ];

        $client = new Client();
        try {
            $response = $client->post($this->uploadEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'multipart' => [
                    [
                        'name' => 'metadata',
                        'contents' => json_encode($metadata),
                        'headers' => ['Content-Type' => 'application/json; charset=UTF-8']
                    ],
                    [
                        'name' => 'file',
                        'contents' => $fileContent,
                        'headers' => ['Content-Type' => $mimeType]
                    ]
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            // body dovrebbe contenere l'ID del file caricato
            echo json_encode($body);

        } catch (\Exception $e) {
            http_response_code(500);
            error_log('Drive upload error: ' . $e->getMessage());
            echo json_encode(['error' => 'Upload failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /drive/download?id=FILE_ID
     * Scarica il file da Drive e lo restituisce binario.
     * Il download non restituisce json ma il contenuto binario del file.
     */
    public function download()
    {
        $fileId = $_GET['id'] ?? null;
        if (!$fileId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'File ID not provided']);
            return;
        }

        $tokenData = $this->authController->getAccessTokenInternal();
        $accessToken = $tokenData['access_token'];

        $client = new Client();

        try {
            // Prima otteniamo i metadata per conoscere mimeType e name
            $metaResponse = $client->get($this->metadataEndpoint . $fileId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'query' => ['fields' => 'name,mimeType']
            ]);

            $metaBody = json_decode($metaResponse->getBody()->getContents(), true);
            if (!isset($metaBody['name'], $metaBody['mimeType'])) {
                throw new \Exception('Unable to retrieve file metadata');
            }

            $fileName = $metaBody['name'];
            $fileMime = $metaBody['mimeType'];

            // Ora scarichiamo il file
            $downloadResponse = $client->get($this->downloadEndpoint . $fileId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'query' => ['alt' => 'media']
            ]);

            header('Content-Type: ' . $fileMime);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            echo $downloadResponse->getBody()->getContents();

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            error_log('Drive download error: ' . $e->getMessage());
            echo json_encode(['error' => 'Download failed', 'message' => $e->getMessage()]);
        }
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