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
 * CLASS-NAME:       Flussu AI Media Controller - v1.0
 * CREATED DATE:     22.02.2026 - Aldus - Flussu v4.5.2
 * VERSION REL.:     4.5.2 20260222
 * UPDATE DATE:      22.02:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * Orchestrates AI media operations:
 * Scenario A: Analyze media (vision/OCR) via AI providers
 * Scenario B: Generate images via AI providers (DALL-E 3)
 * -------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Contracts\IAiProvider;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Api\Ai\FlussuGeminAi;
use Flussu\Api\Ai\FlussuClaudeAi;
use Flussu\Api\Ai\FlussuDeepSeekAi;
use Flussu\Api\Ai\FlussuHuggingFaceAi;
use Flussu\Api\Ai\FlussuZeroOneAi;
use Flussu\Api\Ai\FlussuKimiAi;
use Flussu\Api\Ai\FlussuQwenAi;
use Flussu\Api\Ai\FlussuStabilityAi;

class AiMediaController
{
    private IAiProvider $_aiClient;
    private string $_aiMediaDir;
    private string $_analysisDir;
    private string $_generatedDir;

    private static array $_allowedAnalysisExt = ['jpg','jpeg','png','gif','webp','pdf'];

    public function __construct(Platform $platform = Platform::CHATGPT, $model = "", $chat_model = ""){
        switch ($platform) {
            case Platform::CHATGPT:
                $this->_aiClient = new FlussuOpenAi($model, $chat_model);
                break;
            case Platform::GROK:
                $this->_aiClient = new FlussuGrokAi($model);
                break;
            case Platform::GEMINI:
                $this->_aiClient = new FlussuGeminAi($model);
                break;
            case Platform::CLAUDE:
                $this->_aiClient = new FlussuClaudeAi($model);
                break;
            case Platform::DEEPSEEK:
                $this->_aiClient = new FlussuDeepSeekAi($model);
                break;
            case Platform::HUGGINGFACE:
                $this->_aiClient = new FlussuHuggingFaceAi($model);
                break;
            case Platform::ZEROONE:
                $this->_aiClient = new FlussuZeroOneAi($model, $chat_model);
                break;
            case Platform::KIMI:
                $this->_aiClient = new FlussuKimiAi($model, $chat_model);
                break;
            case Platform::QWEN:
                $this->_aiClient = new FlussuQwenAi($model, $chat_model);
                break;
            case Platform::STABILITY:
                $this->_aiClient = new FlussuStabilityAi($model);
                break;
            default:
                $this->_aiClient = new FlussuOpenAi($model, $chat_model);
                break;
        }

        $baseDir = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
        $this->_aiMediaDir = $baseDir . '/Uploads/ai_media';
        $this->_analysisDir = $this->_aiMediaDir . '/analysis';
        $this->_generatedDir = $this->_aiMediaDir . '/generated';

        $this->_initDirectories();
    }

    /**
     * Scenario A - Analyze media with AI (Vision/OCR)
     *
     * @param string $sessId       Session ID
     * @param string $mediaPath    Absolute path to the media file
     * @param string $prompt       What to analyze (e.g. "Describe this image", "Extract text from this document")
     * @return array [status, text, tokenUsage]
     */
    public function analyzeMedia(string $sessId, string $mediaPath, string $prompt): array
    {
        if (!$this->_aiClient->canAnalyzeMedia()) {
            return ["Error", "This AI provider does not support media analysis", null];
        }

        if (!file_exists($mediaPath)) {
            return ["Error", "File not found: " . $mediaPath, null];
        }

        $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::$_allowedAnalysisExt)) {
            return ["Error", "File type not supported for analysis: " . $ext, null];
        }

        $preChat = General::ObjRestore("AiCht" . $sessId, true);
        if (is_null($preChat) || empty($preChat))
            $preChat = [];

        try {
            $result = $this->_aiClient->analyzeMedia($preChat, $mediaPath, $prompt);

            if (!is_array($result) || count($result) < 2) {
                return ["Error", "Invalid response from AI provider", null];
            }

            $tokenUsage = $result[2] ?? null;

            // Save chat history with reference to the analyzed file
            if (is_array($result[0]) && !empty($result[0])) {
                $history = $result[0];
                $history[] = [
                    'role' => 'assistant',
                    'content' => $result[1],
                ];
                General::ObjPersist("AiCht" . $sessId, $history);
            }

            return ["Ok", $result[1], $tokenUsage];

        } catch (\Throwable $e) {
            return ["Error", $e->getMessage(), null];
        }
    }

    /**
     * Scenario B - Generate image with AI (DALL-E 3)
     *
     * @param string $sessId   Session ID
     * @param string $prompt   Description of the image to generate
     * @param string $size     Image size ("1024x1024", "1792x1024", "1024x1792")
     * @param string $quality  Image quality ("standard", "hd")
     * @return array [status, file_url|error, tokenUsage]
     */
    public function generateImage(string $sessId, string $prompt, string $size = "1024x1024", string $quality = "standard"): array
    {
        if (!$this->_aiClient->canGenerateImages()) {
            return ["Error", "This AI provider does not support image generation", null];
        }

        try {
            $result = $this->_aiClient->generateImage($prompt, $size, $quality);

            if (isset($result['error'])) {
                return ["Error", $result['error'], null];
            }

            if (!isset($result['b64_data'])) {
                return ["Error", "No image data returned", null];
            }

            // Save generated image to disk
            $imageData = base64_decode($result['b64_data']);
            if ($imageData === false) {
                return ["Error", "Failed to decode image data", null];
            }

            $un = str_replace(".", "-", uniqid("", true));
            $filename = "gen_" . $un . ".png";
            $filePath = $this->_generatedDir . '/' . $filename;

            if (file_put_contents($filePath, $imageData) === false) {
                return ["Error", "Failed to save generated image", null];
            }

            // Build public URL
            $filehost = $_ENV['filehost'] ?? $_ENV['server'] ?? '';
            $relativePath = '/Uploads/ai_media/generated/' . $filename;
            $fileUrl = '';
            if (!empty($filehost)) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $fileUrl = $protocol . '://' . $filehost . $relativePath;
            } else {
                $fileUrl = $relativePath;
            }

            General::addRowLog("AI image generated: " . $filePath . " for session " . $sessId);

            return ["Ok", $fileUrl, null];

        } catch (\Throwable $e) {
            return ["Error", $e->getMessage(), null];
        }
    }

    /**
     * Check if the current provider supports media analysis
     */
    public function canAnalyze(): bool
    {
        return $this->_aiClient->canAnalyzeMedia();
    }

    /**
     * Check if the current provider supports image generation
     */
    public function canGenerate(): bool
    {
        return $this->_aiClient->canGenerateImages();
    }

    /**
     * Cleanup old analysis files (older than $hours)
     */
    public function cleanupAnalysisFiles(int $hours = 24): int
    {
        $deleted = 0;
        $cutoff = time() - ($hours * 3600);
        $files = glob($this->_analysisDir . '/*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file))
                    $deleted++;
            }
        }
        return $deleted;
    }

    private function _initDirectories(): void
    {
        foreach ([$this->_aiMediaDir, $this->_analysisDir, $this->_generatedDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
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
