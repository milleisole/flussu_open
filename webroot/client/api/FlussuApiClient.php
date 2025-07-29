<?php
/* --------------------------------------------------------------------*
 * Flussu v4.4 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       SAMPLE API CLASS
 * -------------------------------------------------------*
 * RELEASED DATE:    14.07:2025 - Aldus - Flussu v4.4
 * VERSION REL.:     4.4.20250621
 * UPDATES DATE:     21.06:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 *
 *                 USE THIS FILE IN YOUR PHP PROJECTS 
 *                  TO INTERACT WITH THE FLUSSU API
 * 
 * This file is a sample PHP client for interacting with the Flussu API.
 * It provides methods to start a workflow, send data, send text responses,
 * press buttons, get workflow information, reset the session, change language,
 * and parse elements from the response.
 * -------------------------------------------------------*/

// Sample use:
/* --------------------------------------------------------------
try {
    $flussu = new FlussuClient('srvdev4.flu.lt', '[w73385d6787117396]', 'en');
    
    // Start the workflow
    $response = $flussu->startWorkflow();
    echo "Workflow avviato:\n";
    print_r($response);

    // Analyze the received elements
    $elements = $flussu->parseElements($response);
    foreach ($elements as $element) {
        echo "Elemento: {$element['type']} - {$element['value']}\n";
    }
    
    // send some data
    $response = $flussu->sendText("Ciao, questo è un test");
    print_r($response);

    // Or press a button
    $response = $flussu->pressButton(0, "OK");
    print_r($response);
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
------------------------------------------------------------------*/

class FlussuApiClient {
    private string $server;
    private string $wid;
    private string $sid = '';
    private string $bid = '';
    private string $lng = 'it';
    private array $cookies = [];
    
    public function __construct(string $server, string $wid, string $language = 'IT') {
        if (is_null($server) || empty($server))
            $server="srvdev4.flu.lt";
        $server="https://".$server;
        if (is_null($wid) || empty($wid)) 
            throw new  \Exception('WID non può essere nullo o vuoto');
        $this->server = rtrim($server, '/') . '/api/v2.0/flussueng.php';
        $this->wid = $wid;
        if (is_null($language) || empty($language)) $language="IT";
        $this->lng = $language;
    }
    
    /**
     * Avvia un nuovo workflow
     */
        /**
     * Avvia il workflow con dati iniziali opzionali
     * @param string|null $initialData JSON string con i dati iniziali
     * @return array
     */
    public function startWorkflow(?string $initialData = null): array {
        $termObj = [
            '$isForm' => 'true',
            '$_FD0508' => 'php-client',
            '$_AL2905' => ''
        ];
        
        // Se vengono forniti dati iniziali, li decodifica e li aggiunge
        if ($initialData) {
            $decodedData = json_decode($initialData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                $termObj = array_merge($termObj, $decodedData);
            } else {
                throw new InvalidArgumentException('I dati iniziali devono essere un JSON valido');
            }
        }
        
        return $this->sendStepData($termObj);
    }
    
        /**
     * Continua il workflow con nuovi dati
     * @param string $jsonData JSON string con i dati da inviare
     * @return array
     */
    public function continueWorkflow(string $jsonData): array {
        $decodedData = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedData)) {
            throw new InvalidArgumentException('I dati devono essere un JSON valido');
        }
        
        return $this->sendStepData($decodedData);
    }
    
    
    /**
     * Invia dati al workflow e ottiene la risposta
     */
    public function sendData(array $data): array {
        return $this->sendStepData($data);
    }
    
    /**
     * Invia una risposta testuale semplice
     */
    public function sendText(string $text): array {
        return $this->sendStepData(['$' => $text]);
    }
    
    /**
     * Invia la pressione di un bottone
     */
    public function pressButton(int $buttonIndex = 0, string $buttonText = 'OK'): array {
        $key = '$ex!' . $buttonIndex;
        return $this->sendStepData([$key => $buttonText]);
    }
    
    /**
     * Ottieni informazioni sul workflow
     */
    public function getWorkflowInfo(): array {
        $payload = [
            'WID' => $this->wid,
            'CMD' => 'info'
        ];
        return $this->makeRequest($payload);
    }
    
    /**
     * Resetta la sessione
     */
    public function resetSession(): void {
        $this->sid = "";
        $this->bid = "";
        $this->cookies = [];
    }
    
    /**
     * Cambia lingua del workflow
     */
    public function setLanguage(string $language): array {
        $this->lng = $language;
        return $this->sendStepData([]);
    }
    
    /**
     * Metodo principale per inviare dati al server
     */
    private function sendStepData(array $termObj): array {
        // Se non c'è SID, è il primo step
        if (!$this->sid) {
            $termObj['$'.'isForm'] = 'true';
            $termObj['$'.'isAPI'] = 'true';
            $termObj['$'.'_FD0508'] = 'php-client';
            $termObj['$'.'_AL2905'] = '';
        }
        
        $payload = [
            'WID' => $this->wid,
            'SID' => $this->sid ?? '',
            'BID' => $this->bid ?? '',
            'LNG' => $this->lng,
            'TRM' => json_encode($termObj)
        ];
        
        $response = $this->makeRequest($payload);
        
        // Aggiorna i parametri di sessione
        if (isset($response['sid'])) {
            $this->sid = $response['sid'];
        }
        if (isset($response['bid'])) {
            $this->bid = $response['bid'];
        }
        if (isset($response['lng'])) {
            $this->lng = $response['lng'];
        }
        
        return $response;
    }
    
    /**
     * Effettua la richiesta HTTP al server
     */
    private function makeRequest(array $payload): array {
        $postData = http_build_query($payload);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: ' . strlen($postData)
                ],
                'content' => $postData
            ]
        ]);
        
        $response = file_get_contents($this->server, false, $context);
        
        if ($response === false) {
            throw new \Exception('Errore nella comunicazione con il server Flussu');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Risposta JSON non valida dal server');
        }
        
        if (isset($data['error'])) {
            if ($data['error'] === 'This session has expired - E89') {
                $this->resetSession();
                throw new \Exception('Sessione scaduta');
            }
            throw new \Exception('Errore dal server: ' . $data['error']);
        }
        
        return $data;
    }
    
    /**
     * Ottieni il SID corrente
     */
    public function getSessionId(): ?string {
        return $this->sid;
    }
    
    /**
     * Ottieni il BID corrente  
     */
    public function getBid(): ?string {
        return $this->bid;
    }
    
    /**
     * Ottieni la lingua corrente
     */
    public function getLanguage(): string {
        return $this->lng;
    }
    
    /**
     * Verifica se la sessione è attiva
     */
    public function hasActiveSession(): bool {
        return $this->sid !== null;
    }
    
    /**
     * Estrae e formatta gli elementi dal response per facilitare l'uso
     */
    public function parseElements(array $response): array {
        if (!isset($response['elms']) || !is_array($response['elms'])) {
            return [];
        }
        
        $parsed = [];
        
        foreach ($response['elms'] as $key => $element) {
            if (!is_array($element) || count($element) < 2) {
                continue;
            }
            
            $type = '';
            if (preg_match('/^L\$/', $key)) {
                $type = 'label';
            } elseif (preg_match('/^ITT\$/', $key)) {
                $type = 'input';
            } elseif (preg_match('/^ITS\$/', $key)) {
                $type = 'select';
            } elseif (preg_match('/^ITB\$/', $key)) {
                $type = 'button';
            } elseif (preg_match('/^A\$/', $key)) {
                $type = 'link';
            } elseif (preg_match('/^ITM\$/', $key)) {
                $type = 'file';
            }
            
            $parsed[] = [
                'key' => $key,
                'type' => $type,
                'value' => $element[0] ?? '',
                'config' => $element[1] ?? [],
                'raw' => $element
            ];
        }
        
        return $parsed;
    }

    /**
     * Helper per creare JSON per il workflow
     */
    public static function createData(array $data): string {
        return json_encode($data);
    }
    
    /**
     * Helper per creare un messaggio di testo
     */
    public static function createTextMessage(string $text): string {
        return json_encode(['$' => $text]);
    }
    
    /**
     * Helper per creare una risposta a un bottone
     */
    public static function createButtonPress(int $buttonIndex = 0, string $buttonText = 'OK'): string {
        $key = '$ex!' . $buttonIndex;
        return json_encode([$key => $buttonText]);
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
