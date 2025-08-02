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
 * CLASS-NAME:       OAUTH Controller
 * CREATED DATE:     29.07.2025 - Refactored to be stateless
 * VERSION REL.:     4.5.20250729
 * -------------------------------------------------------*/

namespace Flussu\Controllers;

use GuzzleHttp\Client;
use Flussu\General;
use Flussu\Config;

class OauthController
{
    private string $tokenUri = 'https://oauth2.googleapis.com/token';
    private array $scopes = ['https://www.googleapis.com/auth/drive'];
    private static ?array $cachedToken = null;
    private ?Config $config = null;

    public function __construct()
    {
        $this->config = Config::init();
    }

    /**
     * Ottiene un token di accesso valido
     * @return array ['access_token' => '...', 'expires_in' => ..., 'expires_at' => ...]
     */
    public function getAccessToken(): array
    {
        // Cache in memoria per evitare richieste multiple durante la stessa esecuzione
        if (self::$cachedToken !== null && isset(self::$cachedToken['expires_at'])) {
            if (time() < self::$cachedToken['expires_at']) {
                return self::$cachedToken;
            }
        }

        // Genera nuovo token
        self::$cachedToken = $this->fetchNewAccessToken();
        return self::$cachedToken;
    }

    /**
     * Esegue il flow di OAuth2 per ottenere un nuovo access token.
     */
    private function fetchNewAccessToken(): array
    {
        // Ottieni le credenziali usando la classe Config
        $clientEmail = $this->config->get('services.google.client_email');
        $privateKey = $this->config->get('services.google.private_key');
        
        // Valida che le credenziali esistano
        if (!$clientEmail || !$privateKey) {
            throw new \Exception("Credenziali Google mancanti nella configurazione. Verificare services.google.client_email e services.google.private_key");
        }
        
        // Crea il JWT per l'assertion
        $now = time();
        $jwtHeader = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        $jwtClaimSet = [
            'iss' => $clientEmail,
            'scope' => implode(' ', $this->scopes),
            'aud' => $this->tokenUri,
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $jwtHeaderEncoded = rtrim(strtr(base64_encode(json_encode($jwtHeader)), '+/', '-_'), '=');
        $jwtClaimSetEncoded = rtrim(strtr(base64_encode(json_encode($jwtClaimSet)), '+/', '-_'), '=');

        $unsignedJwt = $jwtHeaderEncoded . '.' . $jwtClaimSetEncoded;

        // Firma il JWT con la private key (RS256)
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        if (!$privateKeyResource) {
            throw new \Exception("Private key non valida nella configurazione");
        }
        
        $signature = '';
        $signResult = openssl_sign($unsignedJwt, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        
        if (!$signResult) {
            throw new \Exception("Errore nella firma del JWT: " . openssl_error_string());
        }
        
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $assertion = $unsignedJwt . '.' . $signatureEncoded;

        // Richiedi il token
        try {
            $client = new Client();
            $response = $client->post($this->tokenUri, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion
                ],
                'http_errors' => true
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($body['access_token'])) {
                throw new \Exception("Risposta da Google non contiene access_token");
            }

            // Calcoliamo l'explicit expiration time
            $body['expires_at'] = time() + $body['expires_in'];

            return $body;
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            General::addRowLog("Errore OAuth Google: " . $errorBody);
            throw new \Exception("Errore nell'ottenere il token da Google: " . $errorBody);
        }
    }
    
    /**
     * Aggiunge gli scope necessari (se vogliamo supportare scope multipli)
     */
    public function addScope(string $scope): void
    {
        if (!in_array($scope, $this->scopes)) {
            $this->scopes[] = $scope;
            // Invalida la cache per forzare un nuovo token con i nuovi scope
            self::$cachedToken = null;
        }
    }
    
    /**
     * Test di validità del token
     */
    public function testConnection(): array
    {
        try {
            $tokenData = $this->getAccessToken();
            
            // Test con una chiamata a Google Drive
            $client = new Client();
            $response = $client->get('https://www.googleapis.com/drive/v3/about?fields=user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData['access_token']
                ]
            ]);
            
            $about = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'email' => $about['user']['emailAddress'],
                'token_expires_in' => $tokenData['expires_at'] - time()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}