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
 * CLASS-NAME:       Flussu Revolut API Controller - TBD - EXPERIMENTAL
 * CREATED DATE:     07.12.2024 - Aldus - Flussu v4.0.0
 * VERSION REL.:     4.1.20250205
 * UPDATE DATE:      12.01:2025 
 * -------------------------------------------------------*/

namespace Flussu\Controllers;


class RevolutController  extends AbsPayProviders
{
    private $clientId;
    private $clientSecret;
    private $apiUrl;
    private $accessToken;

    public function __construct($clientId, $clientSecret, $sandbox = true)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiUrl = $sandbox ? 'https://sandbox-b2b.revolut.com/api/1.0' : 'https://b2b.revolut.com/api/1.0';
        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken()
    {
        $url = $this->apiUrl . '/auth/token';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        $response = $this->sendRequest('POST', $url, $data, false);
        return $response['access_token'];
    }

    private function sendRequest($method, $url, $data = [], $auth = true)
    {
        $headers = [
            'Content-Type: application/json'
        ];

        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method
        ];

        if (!empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if (!$result) {
            throw new \Exception('Errore nella chiamata API: ' . curl_error($ch));
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    function createPayLink($paymentId,$description,$amount,$prodImg,$successUri,$cancelUri){
        return $this->createPaymentLink($description, $amount);
    }
    public function createPaymentLink($description, $amount)
    {
        $url = $this->apiUrl . '/payment-links';
        $data = [
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => $description,
            'type' => 'single_use',
            'capture_mode' => 'automatic'
        ];

        $response = $this->sendRequest('POST', $url, $data);
        // Memorizza $response['id'] per future verifiche
        return $response;
    }

    public function getPaymentStatus($paymentId)
    {
        $url = $this->apiUrl . '/payment-links/' . $paymentId;
        $response = $this->sendRequest('GET', $url);
        return $response;
    }

    /*
    public function registerWebhook($url, $events = ['Payment', 'Refund'])
    {
        $endpoint = $this->apiUrl . '/webhooks';
        $data = [
            'url' => $url,
            'events' => $events
        ];

        $response = $this->sendRequest('POST', $endpoint, $data);
        return $response;
    }

    public function getWebhooks()
    {
        $endpoint = $this->apiUrl . '/webhooks';
        $response = $this->sendRequest('GET', $endpoint);
        return $response;
    }

    public function deleteWebhook($webhookId)
    {
        $endpoint = $this->apiUrl . '/webhooks/' . $webhookId;
        $response = $this->sendRequest('DELETE', $endpoint);
        return $response;
    }*/
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