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
 * CLASS-NAME:       OAUTH Controller
 * CREATED DATE:     01.01.2025 - EXPERIMENTAL - TBD
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/

namespace Flussu\Controllers;
use GuzzleHttp\Client;
use \FlussuSession;
use Flussu\Flussuserver\Session;

class OauthController
{
    private string $serviceAccountFile;
    private string $tokenUri = 'https://oauth2.googleapis.com/token';
    private array $scopes = ['https://www.googleapis.com/auth/drive'];

    public function __construct(string $serviceAccountFile = null)
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
    public function getToken(Session $sess)
    {
        return $this->getAccessTokenInternal($sess);
    }

    /**
     * Ottiene un token di accesso, rinnovandolo se scaduto.
     * Ritorna un array con i dati del token: ['access_token' => '...', 'expires_in' => ...]
     */
    protected function getAccessTokenInternal(Session $sess): array
    {
        //$tokenData = FlussuSession::get('google_access_token');
        $tokenData =$sess->getVarValue("$"."google_access_token");

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
        //FlussuSession::set('google_access_token', $newTokenData);
        $sess->assignVars("$"."google_access_token",$newTokenData);

        return $newTokenData;
    }

    /**
     * Esegue il flow di OAuth2 per ottenere un nuovo access token.
     * Usa un JWT assertion basato su service account.
     */
    private function fetchNewAccessToken(): array
    {
        // Carica le credenziali del service account
        $creds = json_decode(file_get_contents($this->serviceAccountFile), true);
        if (!$creds || !isset($creds['google'], $creds["google"]['service_account'])) {
            throw new \Exception("Credenziali del service account non valide o non trovate.");
        }

        $creds = $creds["google"]['service_account'];

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
        openssl_sign($unsignedJwt, $signature, openssl_pkey_get_private($privateKey), OPENSSL_ALGO_SHA256);
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $assertion = $unsignedJwt . '.' . $signatureEncoded;

        // Richiedi il token
        $client = new Client();
        $response = $client->post($this->tokenUri, [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion
            ]
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        // body esempio: {"access_token":"...","expires_in":3600,"token_type":"Bearer"}

        if (!isset($body['access_token'])) {
            throw new \Exception("Impossibile ottenere un token da Google.");
        }

        // Calcoliamo l'explicit expiration time
        $body['expires_at'] = time() + $body['expires_in'];

        return $body;
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