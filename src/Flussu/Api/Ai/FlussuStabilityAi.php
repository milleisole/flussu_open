<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5.2 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu Stability AI interface - v1.0
 * CREATED DATE:     22.02.2026 - Aldus - Flussu v4.5.2
 * VERSION REL.:     4.5.2 20260222
 * UPDATE DATE:      22.02:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * Stability AI provider for Stable Diffusion image generation.
 * Supports SD3, SD3.5, SDXL models via api.stability.ai
 * This provider is image-generation only (no chat support).
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\General;
use Log;
use Exception;
use GuzzleHttp\Client;
use Flussu\Contracts\IAiProvider;

class FlussuStabilityAi implements IAiProvider
{
    private $_aiErrorState = false;
    private $_stability_key = "";
    private $_stability_model = "";
    private $client;

    private static array $_sizeToAspect = [
        "1024x1024" => "1:1",
        "1792x1024" => "16:9",
        "1024x1792" => "9:16",
        "1536x1024" => "3:2",
        "1024x1536" => "2:3",
        "1280x1024" => "5:4",
        "1024x1280" => "4:5",
    ];

    public function canBrowseWeb(){
        return false;
    }

    public function __construct($model = "", $chat_model = ""){
        $this->_stability_key = config("services.ai_provider.stability_ai.auth_key");
        if (empty($this->_stability_key))
            throw new Exception("Stability AI API key not configured. Set 'auth_key' in config services.ai_provider.stability_ai");

        if ($model)
            $this->_stability_model = $model;
        else {
            if (!empty(config("services.ai_provider.stability_ai.model")))
                $this->_stability_model = config("services.ai_provider.stability_ai.model");
            else
                $this->_stability_model = "sd3.5-large"; // Default model
        }

        $this->client = new Client([
            'base_uri' => 'https://api.stability.ai/',
            'timeout'  => 60.0,
        ]);
    }

    // Chat is not supported - Stability AI is image-generation only
    function chat($preChat, $sendText, $role = "user"){
        return [$preChat, "Error: Stability AI does not support chat. Use it for image generation only.", null];
    }

    function chat_WebPreview($sendText, $session = "123-231-321", $max_output_tokens = 150, $temperature = 0.7){
        return [];
    }

    // Vision not supported
    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role = "user"): array {
        return [[], "Error: media analysis not supported by Stability AI", null];
    }

    // Image generation IS supported
    public function canGenerateImages(): bool {
        return true;
    }

    /**
     * Generate image using Stability AI (Stable Diffusion 3.x)
     *
     * @param string $prompt     Image description
     * @param string $size       Pixel size ("1024x1024") or aspect ratio ("1:1", "16:9")
     * @param string $quality    "standard" or style_preset name
     * @return array ["b64_data" => ..., "seed" => ...] or ["error" => ...]
     */
    public function generateImage($prompt, $size = "1024x1024", $quality = "standard"): array
    {
        // Convert pixel size to aspect ratio if needed
        $aspectRatio = self::$_sizeToAspect[$size] ?? $size;
        // Validate aspect ratio format (N:N)
        if (!preg_match('/^\d+:\d+$/', $aspectRatio))
            $aspectRatio = "1:1";

        try {
            $multipart = [
                ['name' => 'prompt', 'contents' => $prompt],
                ['name' => 'model', 'contents' => $this->_stability_model],
                ['name' => 'output_format', 'contents' => 'png'],
                ['name' => 'aspect_ratio', 'contents' => $aspectRatio],
            ];

            // Add negative prompt for non-standard quality
            if ($quality !== "standard" && $quality !== "hd") {
                $multipart[] = ['name' => 'style_preset', 'contents' => $quality];
            }

            $response = $this->client->post('v2beta/stable-image/generate/sd3', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->_stability_key,
                    'Accept' => 'application/json',
                ],
                'multipart' => $multipart,
                'timeout' => 120,
                'connect_timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $body = $response->getBody()->getContents();
                return ["error" => "Stability AI HTTP $statusCode: " . $body];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['image'])) {
                return [
                    "b64_data" => $data['image'],
                    "revised_prompt" => $prompt,
                    "seed" => $data['seed'] ?? null,
                    "finish_reason" => $data['finish_reason'] ?? 'SUCCESS'
                ];
            }

            return ["error" => "No image data in Stability AI response"];

        } catch (\Throwable $e) {
            return ["error" => "Stability AI error: " . $e->getMessage()];
        }
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
