<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0- Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu Z.AI GLM interface - v1.0
 * CREATED DATE:     07.04.2026 - Flussu v4.5
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
class FlussuZaiGlmAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_zai_glm;
    private $_zai_glm_key="";
    private $_zai_glm_model="";
    private $_zai_glm_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return true;
    }
    public function __construct($model="",$chat_model=""){
        if (!isset($this->_zai_glm)){
            $this->_zai_glm_key = config("services.ai_provider.zai_glm.auth_key");
            if (empty($this->_zai_glm_key))
                throw new Exception("Z.AI GLM API key not configured. Set 'auth_key' in config services.ai_provider.zai_glm");
            if ($model)
                $this->_zai_glm_model = $model;
            else {
                if (!empty(config("services.ai_provider.zai_glm.model")))
                    $this->_zai_glm_model=config("services.ai_provider.zai_glm.model");
                else
                    $this->_zai_glm_model = "glm-4.5v";
            }
            if ($chat_model)
                $this->_zai_glm_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.zai_glm.chat-model")))
                    $this->_zai_glm_chat_model=config("services.ai_provider.zai_glm.chat-model");
                else
                    $this->_zai_glm_chat_model = "glm-4.6";
            }
            $this->client = new Client([
                'base_uri' => 'https://api.z.ai/api/paas/v4/',
                'timeout'  => 10.0,
            ]);

        }
    }
    function chat($preChat,$sendText,$role="user"){
        foreach ($preChat as &$message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["message"]);
            }
        }
        unset($message);
        $preChat[]= [
            'role' => $role,
            'content' => $sendText,
        ];
        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        $payload = [
            'model' => $this->_zai_glm_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000,
            'temperature' => (float) Config::init()->aiTemperature('zai_glm')
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json'=>$payload
            ]);
            $data=$response->getBody();

            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP status code " . $response->getStatusCode() . ". Details: " . $data, null];

            $data = json_decode($data, true);
            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_zai_glm_chat_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            else
                return [$arrayText, "Error: no Z.AI GLM response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    // v4.5.2 - AI Media Exchange: Z.AI GLM supports vision via glm-4.5v
    public function canAnalyzeMedia(): bool { return true; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        try {
            $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png', 'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];
            $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';

            $imageData = file_get_contents($mediaPath);
            if ($imageData === false) {
                return [[], "Error: unable to read file " . $mediaPath, null];
            }
            $base64Image = base64_encode($imageData);

            foreach ($preChat as &$message) {
                if (isset($message["message"]) && !isset($message["content"])) {
                    $message["content"] = $message["message"];
                    unset($message["message"]);
                }
            }
            unset($message);

            $preChat[] = [
                'role' => $role,
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:{$mimeType};base64,{$base64Image}"
                    ]]
                ]
            ];

            $payload = [
                'model' => $this->_zai_glm_model,
                'messages' => $preChat,
                'max_tokens' => 2000,
                'temperature' => (float) Config::init()->aiTemperature('zai_glm')
            ];

            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json' => $payload
            ]);

            $data = json_decode($response->getBody(), true);

            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model' => $this->_zai_glm_model,
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0
                ];
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return [$preChat, $data['choices'][0]['message']['content'], $tokenUsage];
            }

            return [[], "Error: no Z.AI GLM vision response. Details: " . print_r($data, true), null];

        } catch (Exception $e) {
            return [[], "Error: Z.AI GLM vision failed. Details: " . $e->getMessage(), null];
        }
    }

    // v4.5.2 - Image Generation via CogView (cogview-4 / cogview-3-plus)
    public function canGenerateImages(): bool { return true; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $imageModel = config("services.ai_provider.zai_glm.image-model");
        if (empty($imageModel))
            $imageModel = "cogView-4-250304"; // Z.ai is case-sensitive: camelCase "cogView" required

        // Override base endpoint via config: cogview-* lives on open.bigmodel.cn,
        // glm-* lives on api.z.ai. Default keeps the existing base.
        $imageBase = config("services.ai_provider.zai_glm.image-base-uri");
        if (empty($imageBase))
            $imageBase = 'https://api.z.ai/api/paas/v4/';

        $payload = [
            'model'  => $imageModel,
            'prompt' => $prompt,
            'size'   => $size,
        ];
        try {
            $http = new Client(['timeout' => 120]);
            $response = $http->post(rtrim($imageBase, '/') . '/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
                    'Content-Type'  => 'application/json',
                ],
                'connect_timeout' => 20,
                'json' => $payload,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $raw  = (string)$response->getBody();
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            General::addRowLog("Z.AI GLM image transport error: " . $e->getMessage());
            return ["error" => "Z.AI GLM image generation failed: " . $e->getMessage()];
        }

        if ($code !== 200) {
            General::addRowLog("Z.AI GLM image HTTP $code body=" . substr($raw, 0, 500));
            return ["error" => "Z.AI GLM HTTP $code: " . substr($raw, 0, 500)];
        }

        $first = $data['data'][0] ?? null;
        if (!$first) {
            General::addRowLog("Z.AI GLM image empty data: " . substr($raw, 0, 500));
            return ["error" => "No image data returned by Z.AI GLM. Body: " . substr($raw, 0, 500)];
        }

        $tokenUsage = [
            'model'  => $imageModel,
            'input'  => 0,
            'output' => 1,
        ];
        if (!empty($first['b64_json']))
            return [
                "b64_data" => $first['b64_json'],
                "revised_prompt" => $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        if (!empty($first['url'])) {
            // Two distinct issues with Z.ai's mfile CDN:
            //   1) The ?ufileattname=...&_watermark query triggers a download-as-attachment
            //      pipeline that returns 404 "file not exist" on direct GET. Strip the query.
            //   2) The bare URL works server-to-server (we fetched it successfully) but the
            //      CDN refuses cross-origin <img> embeds from a third-party page (Referer
            //      hot-link protection), so passthrough breaks in the client. We must
            //      re-host: download here and return base64.
            $cleanUrl = preg_replace('/\?.*$/', '', $first['url']);
            [$bin, $err] = $this->_fetchUrl($cleanUrl);
            if ($bin === null) {
                General::addRowLog("Z.AI GLM URL fetch failed (" . $err . "): " . $cleanUrl);
                return ["error" => "Z.AI GLM URL fetch failed [" . $err . "]: " . $cleanUrl];
            }
            return [
                "b64_data" => base64_encode($bin),
                "revised_prompt" => $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        }
        General::addRowLog("Z.AI GLM image no url/b64: " . substr($raw, 0, 500));
        return ["error" => "No image URL/b64 in Z.AI GLM response"];
    }

    /**
     * v5.x — Native web research via Z.AI GLM `web_search` tool.
     * Returns [history, replyText, tokenUsage] like chat().
     */
    function chat_WebPreview($sendText, $session="", $max_output_tokens=1024, $temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('zai_glm');
        }
        $messages = [['role' => 'user', 'content' => $sendText]];
        $payload = [
            'model'       => $this->_zai_glm_chat_model,
            'messages'    => $messages,
            'max_tokens'  => $max_output_tokens,
            'temperature' => $temperature,
            'tools'       => [[
                'type' => 'web_search',
                'web_search' => [
                    'search_engine' => 'search_pro_jina',
                    'count'         => 5,
                ],
            ]],
        ];
        try {
            $resp = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_zai_glm_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout'         => 120,
                'connect_timeout' => 20,
                'json'            => $payload,
                'http_errors'     => false,
            ]);
            $code = $resp->getStatusCode();
            $raw  = (string)$resp->getBody();
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            return [$messages, "Error: Z.AI GLM web search failed: " . $e->getMessage(), null];
        }
        if ($code !== 200 || !is_array($data)) {
            return [$messages, "Error: Z.AI GLM HTTP $code: " . substr($raw, 0, 500), null];
        }

        $textOut = trim((string)($data['choices'][0]['message']['content'] ?? ''));

        $citations = [];
        $webRefs = $data['web_search'] ?? ($data['choices'][0]['message']['tool_calls'] ?? []);
        if (is_array($webRefs)) {
            foreach ($webRefs as $hit) {
                $url   = $hit['link'] ?? ($hit['url']   ?? null);
                $title = $hit['title'] ?? '';
                if (!empty($url) && !isset($citations[$url])) {
                    $citations[$url] = $title !== '' ? $title : $url;
                }
            }
        }
        if (!empty($citations)) {
            $lines = ["", "---", "> **Fonti:**"];
            $i = 1;
            foreach ($citations as $u => $t) {
                $lines[] = "> [$i] [$t]($u)";
                $i++;
            }
            $textOut .= "\n" . implode("\n", $lines);
        }

        $tokenUsage = [
            'model'  => $this->_zai_glm_chat_model,
            'input'  => $data['usage']['prompt_tokens']     ?? 0,
            'output' => $data['usage']['completion_tokens'] ?? 0,
        ];
        return [$messages, $textOut, $tokenUsage];
    }

    /**
     * Fetch a URL using Guzzle. Tries first with a browser-like User-Agent (some CDNs,
     * notably mfile.z.ai, refuse default Guzzle/PHP UAs). Returns [string|null $bin, string|null $err].
     */
    private function _fetchUrl(string $url): array {
        $browserUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
        try {
            $http = new Client(['timeout' => 60, 'connect_timeout' => 20]);
            $resp = $http->get($url, [
                'http_errors' => false,
                'allow_redirects' => true,
                'headers' => [
                    'User-Agent' => $browserUa,
                    'Accept' => 'image/png,image/*,*/*;q=0.8',
                ],
            ]);
            $code = $resp->getStatusCode();
            if ($code !== 200) {
                $err = "HTTP " . $code . " body=" . substr((string)$resp->getBody(), 0, 200);
                General::addRowLog("URL fetch " . $err . " for " . $url);
                return [null, $err];
            }
            return [(string)$resp->getBody(), null];
        } catch (\Throwable $e) {
            $err = "exception: " . $e->getMessage();
            General::addRowLog("URL fetch " . $err . " for " . $url);
            return [null, $err];
        }
    }
}
