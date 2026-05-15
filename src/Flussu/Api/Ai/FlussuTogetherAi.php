<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * CLASS-NAME:       Flussu Together.ai interface - v1.0
 * CREATED DATE:     26.04.2026 - Aldus - Flussu v5.0
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * Together.ai exposes an OpenAI-compatible REST surface:
 *   - chat:   POST https://api.together.xyz/v1/chat/completions
 *   - image:  POST https://api.together.xyz/v1/images/generations
 * Image generation is used primarily with black-forest-labs/FLUX.1-* models.
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;

class FlussuTogetherAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_together;
    private $_together_key="";
    private $_together_model="";
    private $_together_chat_model="";
    private $client;

    public function canBrowseWeb(){
        return false;
    }

    public function __construct($model="", $chat_model=""){
        if (!isset($this->_together)){
            $this->_together_key = config("services.ai_provider.together.auth_key");
            if (empty($this->_together_key))
                throw new Exception("Together.ai API key not configured. Set 'auth_key' in config services.ai_provider.together");
            if ($model)
                $this->_together_model = $model;
            else {
                if (!empty(config("services.ai_provider.together.model")))
                    $this->_together_model = config("services.ai_provider.together.model");
            }
            if ($chat_model)
                $this->_together_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.together.chat-model")))
                    $this->_together_chat_model = config("services.ai_provider.together.chat-model");
                else
                    $this->_together_chat_model = $this->_together_model;
            }
            $this->client = new Client([
                'base_uri' => 'https://api.together.xyz/v1/',
                'timeout'  => 10.0,
            ]);
            $this->_together = true;
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
        $preChat[] = [
            'role' => $role,
            'content' => $sendText,
        ];
        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        $payload = [
            'model'    => $this->_together_chat_model,
            'messages' => $arrayText,
            'max_tokens' => 2000,
            'temperature' => (float) Config::init()->aiTemperature('together'),
        ];
        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_together_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json' => $payload,
            ]);
            $data = json_decode($response->getBody(), true);
            if ($response->getStatusCode() !== 200)
                return [$arrayText, "Error: HTTP " . $response->getStatusCode() . ". Body: " . json_encode($data), null];

            $tokenUsage = null;
            if (isset($data['usage'])) {
                $tokenUsage = [
                    'model'  => $this->_together_chat_model,
                    'input'  => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0,
                ];
            }
            if (isset($data['choices'][0]['message']['content']))
                return [$arrayText, $data['choices'][0]['message']['content'], $tokenUsage];
            return [$arrayText, "Error: no Together.ai response. Details: " . print_r($data, true), null];
        } catch (Exception $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
    }

    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        return [[], "Error: media analysis not supported by Together.ai (use chat-model with vision-capable model directly)", null];
    }

    // v5.0 - Image generation via FLUX.1 (and any other Together image model)
    public function canGenerateImages(): bool { return true; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        $imageModel = config("services.ai_provider.together.image-model");
        if (empty($imageModel))
            $imageModel = "black-forest-labs/FLUX.1-schnell"; // serverless, paid (~$0.003/img). FLUX.1-schnell-Free now requires a dedicated endpoint.

        // Together.ai image API uses width/height (not "size") and steps/n.
        $w = 1024; $h = 1024;
        if (preg_match('/^(\d+)\s*[x*]\s*(\d+)$/i', $size, $m)) {
            $w = (int)$m[1]; $h = (int)$m[2];
        }

        // Steps: schnell models are tuned for 1-4 steps; pro/dev for 20-50. "quality" hint.
        $isSchnell = stripos($imageModel, 'schnell') !== false;
        $steps = $isSchnell ? 4 : (($quality === 'hd') ? 50 : 28);

        $payload = [
            'model'  => $imageModel,
            'prompt' => $prompt,
            'width'  => $w,
            'height' => $h,
            'steps'  => $steps,
            'n'      => 1,
            'response_format' => 'b64_json',
        ];
        try {
            $response = $this->client->post('images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_together_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,
                'connect_timeout' => 20,
                'json' => $payload,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $raw  = (string)$response->getBody();
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            General::addRowLog("Together image transport error: " . $e->getMessage());
            return ["error" => "Together.ai image generation failed: " . $e->getMessage()];
        }

        if ($code !== 200) {
            General::addRowLog("Together image HTTP $code body=" . substr($raw, 0, 500));
            return ["error" => "Together.ai HTTP $code: " . substr($raw, 0, 500)];
        }

        $first = $data['data'][0] ?? null;
        if (!$first) {
            General::addRowLog("Together image empty data: " . substr($raw, 0, 500));
            return ["error" => "No image data returned by Together.ai. Body: " . substr($raw, 0, 500)];
        }

        // Synthetic usage record: image-gen APIs don't bill in tokens, so we report
        // 1 image as one "output unit" so downstream logging/$INFO stays consistent.
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
            $bin = $this->_fetchUrl($first['url']);
            if ($bin === null) {
                General::addRowLog("Together URL fetch failed: " . $first['url']);
                return ["error" => "Together.ai URL fetch failed: " . $first['url']];
            }
            return [
                "b64_data" => base64_encode($bin),
                "revised_prompt" => $prompt,
                "tokenUsage" => $tokenUsage,
            ];
        }
        General::addRowLog("Together image no url/b64: " . substr($raw, 0, 500));
        return ["error" => "No image URL/b64 in Together.ai response"];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=0.7){
        return [];
    }

    private function _fetchUrl(string $url): ?string {
        try {
            $http = new Client(['timeout' => 60, 'connect_timeout' => 20]);
            $resp = $http->get($url, ['http_errors' => false]);
            if ($resp->getStatusCode() !== 200) {
                General::addRowLog("URL fetch HTTP " . $resp->getStatusCode() . " for " . $url);
                return null;
            }
            return (string)$resp->getBody();
        } catch (\Throwable $e) {
            General::addRowLog("URL fetch exception (" . $e->getMessage() . ") for " . $url);
            return null;
        }
    }
}
