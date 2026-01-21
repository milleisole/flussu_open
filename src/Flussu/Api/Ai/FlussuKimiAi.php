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
 * CLASS-NAME:       Flussu Moonshot interface - v1.0
 * CREATED DATE:     14.11.2025 - Aldus - Flussu v5.0
 * VERSION REL.:     5.0.0 20251114
 * UPDATE DATE:      14.11:2025 - Aldus
 * DESCRIPTION:      Moonshot AI integration (OpenAI-compatible API)
 * -------------------------------------------------------*/

namespace Flussu\Api\Ai;

use Flussu\Contracts\IAiProvider;
use Flussu\General;
use Log;
use Exception;

//Kimi client – wrapper compatto attorno alle API ufficiali
//https://platform.moonshot.cn/docs/kimi
//
class FlussuKimiAi implements IAiProvider
{
    private bool $_aiErrorState = false;
    private string $_kimi_key = '';
    private string $_kimi_model = ''; // es. "kimi-latest"
    private string $_base_url = 'https://api.moonshot.cn/v1';
    private array $_defaultTools = ['web-search']; // abilitati di default

    public function canBrowseWeb(): bool
    {
        return true; // Kimi può navigare
    }

    public function __construct(string $model = '', string $chat_model = '')
    {
        $this->_kimi_key = config('services.ai_provider.kimi.auth_key');
        if (!$this->_kimi_key) {
            throw new Exception('Kimi API key mancante in config/services.php');
        }

        $this->_kimi_model = $model
            ?: config('services.ai_provider.kimi.model', 'kimi-latest');

        $this->_base_url = rtrim(config('services.ai_provider.kimi.base_url', $this->_base_url), '/');
    }

    /* -----------------------------------------------------------
IAiProvider – metodi obbligatori
----------------------------------------------------------- */

    public function chat($preChat, $text, $role)
    {
        $this->_aiErrorState = false;
        return [];
    }

    public function generate(string $prompt, array $options = []): string
    {
        return $this->kchat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    public function kchat(array $messages, array $options = []): string
    {
        $this->_aiErrorState = false;

        $payload = [
            'model' => $this->_kimi_model,
            'messages' => $messages,
            'stream' => false,
        ];

        // Tool / plugins
        $tools = $options['tools'] ?? $this->_defaultTools;
        if ($tools) {
            $payload['tools'] = $this->_buildTools($tools);
        }

        // Temperature, top_p, max_tokens, …
        foreach (['temperature', 'top_p', 'max_tokens'] as $k) {
            if (isset($options[$k])) {
                $payload[$k] = $options[$k];
            }
        }

        // File già uploadati
        if (!empty($options['file_ids'])) {
            $payload['file_ids'] = $options['file_ids'];
        }

        try {
            $response = $this->_request('POST', '/chat/completions', $payload);
            $reply = $response['choices'][0]['message']['content'] ?? '';
            return $reply;
        } catch (Exception $e) {
            $this->_aiErrorState = true;
            //Log::error('Kimi chat error: ' . $e->getMessage());
            throw $e;
        }
    }

    /* -----------------------------------------------------------
Funzionalità aggiuntive Kimi
----------------------------------------------------------- */

    /*
Upload di un documento (pdf, txt, docx, xlsx, pptx, md, json, …)
Ritorna l’ID da ri-usare nelle chat.
*/
    public function uploadFile(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            throw new Exception("File $absolutePath non trovato");
        }

        $cFile = curl_file_create($absolutePath);
        $post = ['file' => $cFile, 'purpose' => 'file-extract'];

        $resp = $this->_request('POST', '/files', $post, true); // multipart
        return $resp['id'];
    }

    /* 
Elenco file uploadati
*/
    public function listFiles(): array
    {
        return $this->_request('GET', '/files')['data'] ?? [];
    }

    /*
Cancella un file
*/
    public function deleteFile(string $fileId): bool
    {
        $this->_request('DELETE', "/files/$fileId");
        return true;
    }

    /* -----------------------------------------------------------
Internals – HTTP layer
----------------------------------------------------------- */

    /*
        Esecuzione chiamata HTTP
        $isMultipart = true → invia multipart/form-data (upload file)
    */
    private function _request(string $method, string $endpoint, array $payload = [], bool $isMultipart = false): array
    {
        $url = $this->_base_url . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $isMultipart
                ? ["Authorization: Bearer {$this->_kimi_key}"]
                : [
                    "Authorization: Bearer {$this->_kimi_key}",
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $payload : json_encode($payload));
        }

        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error: $err");
        }

        $decoded = json_decode($body, true);
        if ($info['http_code'] >= 400 || isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? "HTTP {$info['http_code']}";
            throw new Exception("Kimi API error: $msg");
        }

        return $decoded;
    }

    /* -----------------------------------------------------------
Tool builder
----------------------------------------------------------- */
    private function _buildTools(array $tools): array
    {
        // Mappa rapida nome → definizione
        $defs = [
            'web-search' => [
                'type' => 'builtin_function',
                'function' => ['name' => 'web-search'],
            ],
            'code-interpreter' => [
                'type' => 'builtin_function',
                'function' => ['name' => 'code-interpreter'],
            ],
        ];

        $out = [];
        foreach ($tools as $t) {
            $out[] = $defs[$t] ?? ['type' => 'builtin_function', 'function' => ['name' => $t]];
        }
        return $out;
    }

    /* -----------------------------------------------------------
Helpers
----------------------------------------------------------- */
    public function getLastError(): bool
    {
        return $this->_aiErrorState;
    }
    function chat_WebPreview($sendText, $session = "123-231-321", $max_output_tokens = 150, $temperature = 0.7)
    {
        // Moonshot/Moonshot doesn't support web preview in the same way as OpenAI
        // Return empty array as per interface
        return [];
    }
}
