<?php
namespace Flussu\Controllers;

use GuzzleHttp\Client;
use Flussu\Flussuserver\Session;
use Flussu\General;
//use Log;

class OauthController
{
    private string $serviceAccountFile;
    private string $tokenUri = 'https://oauth2.googleapis.com/token';
    private array $scopes = ['https://www.googleapis.com/auth/drive'];
    private ?array $credentials = null;

    public function __construct(string $serviceAccountFile="")
    {
        // Percorso del file di credenziali del service account
        // Esempio: project/config/service_account.json
        // Assicurarsi che questo file esista e abbia le chiavi corrette
        $this->serviceAccountFile = $serviceAccountFile ?? __DIR__ . '/../../../config/services.json';
    }

    /**
     * GET /auth/token
     * Ritorna un token JSON valido. Se scaduto, lo rinnova.
     */
    public function getToken(Session $sess): array
    {
        return $this->getAccessTokenInternal($sess);
    }

    /**
     * Ottiene un token di accesso, rinnovandolo se scaduto.
     * Ritorna un array con i dati del token: ['access_token' => '...', 'expires_in' => ...]
     */
    protected function getAccessTokenInternal(Session $sess): array
    {
        $tokenData = $sess->getVarValue("$"."google_access_token");

        if (is_array($tokenData) && isset($tokenData['access_token'], $tokenData['expires_at'])) {
            // Verifichiamo se è scaduto
            if (time() < $tokenData['expires_at']) {
                // Token ancora valido
                return $tokenData;
            }
        }

        // Token scaduto o non presente, creiamo un nuovo token
        $newTokenData = $this->fetchNewAccessToken();
        
        // Memorizziamo in sessione
        $sess->assignVars("$"."google_access_token", $newTokenData);

        return $newTokenData;
    }

    /**
     * Esegue il flow di OAuth2 per ottenere un nuovo access token.
     * Usa un JWT assertion basato su service account.
     */
    private function fetchNewAccessToken(): array
    {
        // Carica le credenziali del service account
        $configContent = file_get_contents($this->serviceAccountFile);
        $config = json_decode($configContent, true);
        
        // Valida la struttura del file
        if (!isset($config['google']['service_account'])) {
            throw new \Exception("Configurazione Google service account non trovata nel file di configurazione");
        }
        
        $creds = $config['google']['service_account'];
        
        // Valida i campi richiesti
        $requiredFields = ['client_email', 'private_key'];
        foreach ($requiredFields as $field) {
            if (!isset($creds[$field]) || empty($creds[$field])) {
                throw new \Exception("Campo obbligatorio mancante o vuoto: google.service_account.$field");
            }
        }

        $clientEmail = $creds['client_email'];
        $privateKey = $creds['private_key'];
        
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
            throw new \Exception("Private key non valida nel file di configurazione");
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
     * Metodo di utilità per testare la validità del token
     */
    public function testToken(Session $sess): array
    {
        try {
            $tokenData = $this->getToken($sess);
            
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