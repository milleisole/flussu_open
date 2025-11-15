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
 * CLASS-NAME:       Flussu Hugging Face interface - v1.0
 * CREATED DATE:     02.11.2025 - Aldus - Flussu v5.0
 * VERSION REL.:     5.0.0 20251113 
 * UPDATE DATE:      13.11.2025 - Aldus
 * -------------------------------------------------------
 * MODELLI CONSIGLIATI
 * Per Traduzione:
 *   Italiano â†’ Inglese
 *     "Helsinki-NLP/opus-mt-it-en"
 *   Italiano â†’ Spagnolo
 *     "Helsinki-NLP/opus-mt-it-es"
 *   Italiano â†’ Francese
 *     "Helsinki-NLP/opus-mt-it-fr"
 *   Italiano â†’ Tedesco
 *     "Helsinki-NLP/opus-mt-it-de"
 * 
 * Per Text Generation:
 *   Buono, veloce
 *     mistralai/Mistral-7B-Instruct-v0.1  
 *   Richiede autorizzazione
 *     meta-llama/Llama-2-7b-chat-hf       
 *   Alternativa open 
 *     tiiuae/falcon-7b-instruct           
 * 
 * Per Sentiment Analysis:
 *   distilbert-base-uncased-finetuned-sst-2-english
 *   cardiffnlp/twitter-roberta-base-sentiment
 * -------------------------------------------------------
*/
namespace Flussu\Api\Ai;

use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Log;
use Exception;

class FlussuHuggingFaceAi implements IAiProvider
{
    private $_aiErrorState = false;
    private $_hf_token = "";
    private $_hf_model = "";
    private $_api_url = "https://api-inference.huggingface.co/models/";
    
    public function canBrowseWeb(){
        return false;
    }
    
    public function __construct($model = ""){
        // Leggi token da config
        $this->_hf_token = config("services.ai_provider.huggingface.auth_token");
        
        // Modello di default per traduzione ITâ†’EN
        if ($model)
            $this->_hf_model = $model;
        else {
            if (!empty(config("services.ai_provider.huggingface.model")))
                $this->_hf_model = config("services.ai_provider.huggingface.model");
            else
                $this->_hf_model = "Helsinki-NLP/opus-mt-it-en"; // Default
        }
    }

    /**
     * Chat principale - invia messaggio e riceve risposta
     * 
     * @param array $preChat Storico conversazione
     * @param string $sendText Testo da inviare
     * @param string $role Ruolo (user/assistant)
     * @return array [storico_aggiornato, risposta_testo]
     */
    public function chat($preChat, $sendText, $role = "user"){
        // Aggiungi messaggio utente allo storico
        $preChat[] = [
            'role' => $role,
            'content' => $sendText,
        ];
        
        return $this->_chatContinue($preChat);
    }

    /**
     * Continua conversazione con storico
     * 
     * @param array $sendArray Array messaggi
     * @return array [storico, risposta, token_in, token_out]
     */
    private function _chatContinue($sendArray){
        $tokenIn = 0;
        $tokenOut = 0;
        $responseText = "";
        
        try {
            // Estrai ultimo messaggio utente
            $lastMessage = end($sendArray);
            $textToProcess = $lastMessage['content'];
            
            // Determina il tipo di task in base al modello
            $taskType = $this->_getTaskType($this->_hf_model);
            
            // Esegui richiesta API
            $response = $this->_callHuggingFaceAPI($textToProcess, $taskType);
            
            // Formatta risposta
            $responseText = $this->_formatResponse($response, $taskType);
            
            // Note: HuggingFace Inference API typically does not return token usage
            // Token counts remain 0 unless specific models provide this info
            if (isset($response['usage'])) {
                $tokenIn = $response['usage']['prompt_tokens'] ?? 0;
                $tokenOut = $response['usage']['completion_tokens'] ?? 0;
            }
            
        } catch (\Throwable $e) {
            //Log::error("Hugging Face API Error: " . $e->getMessage());
            $responseText = "Error: " . $e->getMessage();
        }
        
        // Return standardized structure with retrocompatibility
        return [
            0 => $sendArray,               // retrocompatibility: conversation history
            1 => $responseText,            // retrocompatibility: response text
            'conversation' => $sendArray,
            'response' => $responseText,
            'token_in' => $tokenIn,
            'token_out' => $tokenOut
        ];
    }

    /**
     * Determina il tipo di task dal nome del modello
     */
    private function _getTaskType($modelName){
        if (strpos($modelName, 'opus-mt') !== false) {
            return 'translation';
        } elseif (strpos($modelName, 'sentiment') !== false) {
            return 'sentiment-analysis';
        } elseif (strpos($modelName, 'gpt') !== false || strpos($modelName, 'llama') !== false) {
            return 'text-generation';
        } elseif (strpos($modelName, 'bart') !== false || strpos($modelName, 'pegasus') !== false) {
            return 'summarization';
        } else {
            // Default: prova text-generation
            return 'text-generation';
        }
    }

    /**
     * Chiamata API Hugging Face
     * 
     * @param string $text Testo da processare
     * @param string $task Tipo di task
     * @return array Risposta API
     */
    private function _callHuggingFaceAPI($text, $task){
        $url = $this->_api_url . $this->_hf_model;
        
        $headers = [
            'Authorization: Bearer ' . $this->_hf_token,
            'Content-Type: application/json'
        ];
        
        // Payload diverso per ogni task
        $payload = $this->_buildPayload($text, $task);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 secondi timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL Error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            // Gestisci errori API
            $errorResponse = json_decode($response, true);
            $errorMsg = isset($errorResponse['error']) ? $errorResponse['error'] : $response;
            
            // Se modello in loading, ritenta dopo 20 secondi
            if (strpos($errorMsg, 'loading') !== false) {
                //Log::info("Hugging Face: Model is loading, waiting 20 seconds...");
                sleep(20);
                return $this->_callHuggingFaceAPI($text, $task); // Retry
            }
            
            throw new Exception("API Error (HTTP $httpCode): " . $errorMsg);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $result;
    }

    /**
     * Costruisce payload in base al task
     */
    private function _buildPayload($text, $task){
        switch ($task) {
            case 'translation':
            case 'sentiment-analysis':
            case 'summarization':
                return ['inputs' => $text];
                
            case 'text-generation':
                return [
                    'inputs' => $text,
                    'parameters' => [
                        'max_new_tokens' => 250,
                        'temperature' => 0.7,
                        'top_p' => 0.9,
                        'do_sample' => true
                    ]
                ];
                
            default:
                return ['inputs' => $text];
        }
    }

    /**
     * Formatta la risposta in base al task
     */
    private function _formatResponse($response, $task){
        if (!is_array($response)) {
            return "Invalid response format";
        }
        
        switch ($task) {
            case 'translation':
                // Response: [{"translation_text": "..."}]
                if (isset($response[0]['translation_text'])) {
                    return $response[0]['translation_text'];
                }
                break;
                
            case 'sentiment-analysis':
                // Response: [{"label": "POSITIVE", "score": 0.99}]
                if (isset($response[0]['label']) && isset($response[0]['score'])) {
                    $label = $response[0]['label'];
                    $score = round($response[0]['score'] * 100, 2);
                    return "Sentiment: $label ($score%)";
                }
                break;
                
            case 'text-generation':
                // Response: [{"generated_text": "..."}]
                if (isset($response[0]['generated_text'])) {
                    return $response[0]['generated_text'];
                }
                break;
                
            case 'summarization':
                // Response: [{"summary_text": "..."}]
                if (isset($response[0]['summary_text'])) {
                    return $response[0]['summary_text'];
                }
                break;
        }
        
        // Fallback: ritorna JSON
        return json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Chat con preview web (non supportato per Hugging Face)
     */
    public function chat_WebPreview($sendText, $session = "123-231-321", $max_output_tokens = 150, $temperature = 0.7){
        // Hugging Face non supporta web preview nativo
        // Ritorna array vuoto come da interfaccia
        return [];
    }
}
 /*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS |
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/