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
 * CLASS-NAME:       Flussu AI Media Controller - v1.0
 * CREATED DATE:     22.02.2026 - Aldus - Flussu v4.5.2
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
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
use Flussu\Api\Ai\FlussuMistralAi;
use Flussu\Api\Ai\FlussuZaiGlmAi;
use Flussu\Api\Ai\FlussuTogetherAi;

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
            case Platform::MISTRAL:
                $this->_aiClient = new FlussuMistralAi($model, $chat_model);
                break;
            case Platform::ZAI_GLM:
                $this->_aiClient = new FlussuZaiGlmAi($model, $chat_model);
                break;
            case Platform::TOGETHER:
                $this->_aiClient = new FlussuTogetherAi($model, $chat_model);
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


            // v5.0 - URL passthrough: providers (e.g. Z.ai/mfile) can return a CDN URL
            // already hosting the image. Skip local download/storage entirely.
            if (!empty($result['url']) && empty($result['b64_data'])) {
                General::addRowLog("AI image (URL passthrough): " . $result['url'] . " for session " . $sessId);
                $usage = self::_refineImageTokenUsage($result['tokenUsage'] ?? null, null);
                return ["Ok", $result['url'], $usage];
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
                // Accept "host", "host:port" or full "scheme://host[:port]" in env.
                if (strpos($filehost, '://') !== false) {
                    $fileUrl = rtrim($filehost, '/') . $relativePath;
                } else {
                    // Prefer explicit env override; otherwise detect HTTPS via $_SERVER (also
                    // honour reverse-proxy header HTTP_X_FORWARDED_PROTO); default to https.
                    $protocol = $_ENV['protocol'] ?? null;
                    if (empty($protocol)) {
                        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                            $protocol = 'https';
                        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
                            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
                        else
                            $protocol = 'https';
                    }
                    $fileUrl = $protocol . '://' . $filehost . $relativePath;
                }
            } else {
                $fileUrl = $relativePath;
            }

            General::addRowLog("AI image generated: " . $filePath . " for session " . $sessId);

            $usage = self::_refineImageTokenUsage($result['tokenUsage'] ?? null, strlen($imageData));
            return ["Ok", $fileUrl, $usage];

        } catch (\Throwable $e) {
            return ["Error", $e->getMessage(), null];
        }
    }

    /**
     * v5.0 — Refine the synthetic tokenUsage emitted by image-gen providers.
     * Most image APIs don't bill in tokens, so providers return output=1 as a placeholder.
     * Here we replace that with a meaningful proxy:
     *   - if we know the produced bytes (b64 path): tokens = bytes / 4
     *   - if we don't (URL passthrough): random 30000-45000
     * Real token usage from providers like gpt-image-1 (output > 1) is preserved.
     */
    private static function _refineImageTokenUsage(?array $usage, ?int $imageBytes): ?array
    {
        if (!is_array($usage)) return null;
        $current = (int)($usage['output'] ?? 0);
        if ($current > 1) return $usage; // real usage from API, leave alone
        if ($imageBytes !== null && $imageBytes > 0) {
            $usage['output'] = (int)floor($imageBytes / 4);
        } else {
            $usage['output'] = random_int(30000, 45000);
        }
        return $usage;
    }

    /**
     * v5.0 — Translate a raw provider/HTTP error into a user-friendly English message.
     * Used for chat-facing replies; workflow logs keep the raw message for debugging.
     */
    public static function humanizeImageError(string $rawError): string
    {
        $low = strtolower($rawError);
        if (strpos($low, 'nsfw') !== false
            || strpos($low, 'safety') !== false
            || strpos($low, 'moderation') !== false
            || strpos($low, 'content policy') !== false
            || strpos($low, 'content_policy') !== false
            || strpos($low, 'content filter') !== false)
            return "The system cannot generate this image because the request may violate the content policy. Please try with a different subject.";
        if (strpos($low, 'copyright') !== false || strpos($low, 'trademark') !== false || strpos($low, 'celebrity') !== false || strpos($low, 'public figure') !== false)
            return "The system cannot generate this image because it could infringe copyright or trademark. Please describe a generic subject instead.";
        if (strpos($low, 'rate') !== false && strpos($low, 'limit') !== false)
            return "Image generation rate limit reached. Please try again in a few moments.";
        if (strpos($low, 'insufficient') !== false || strpos($low, 'billing') !== false || strpos($low, 'credits') !== false || strpos($low, 'quota') !== false)
            return "Image generation is temporarily unavailable (billing or quota issue). Please contact the administrator.";
        if (strpos($low, 'unauthorized') !== false || strpos($low, ' 401') !== false || strpos($low, ' 403') !== false || strpos($low, 'forbidden') !== false)
            return "Image generation authentication failed. Please contact the administrator.";
        if (strpos($low, 'not_available') !== false
            || strpos($low, 'model_notavailable') !== false
            || strpos($low, 'not available') !== false
            || strpos($low, 'unknown model') !== false
            || strpos($low, 'model not found') !== false
            || strpos($low, 'deprecated') !== false)
            return "The configured image model is not available. Please contact the administrator.";
        if (strpos($low, 'timeout') !== false || strpos($low, 'timed out') !== false)
            return "Image generation timed out. Please try again.";
        return "Image generation failed. Please try again, possibly with a different prompt.";
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
        return self::cleanupAiMediaDir('analysis', $hours);
    }

    /**
     * Cleanup old generated images (older than $hours).
     * Static so it can be invoked from CLI/cron without provider instantiation.
     * Default 72h: long enough that a chat user re-opening a conversation still sees
     * the image inline, short enough to avoid unbounded disk growth.
     */
    public static function cleanupGeneratedFiles(int $hours = 72): int
    {
        return self::cleanupAiMediaDir('generated', $hours);
    }

    /**
     * Internal: prune files older than $hours from /Uploads/ai_media/{$subdir}/.
     * Resolves the path the same way the constructor does so it works in HTTP and CLI.
     */
    public static function cleanupAiMediaDir(string $subdir, int $hours): int
    {
        $base = ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3)) . '/Uploads/ai_media/' . $subdir;
        if (!is_dir($base)) return 0;
        $deleted = 0;
        $cutoff = time() - ($hours * 3600);
        $files = glob($base . '/*');
        if (!is_array($files)) return 0;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (@unlink($file)) $deleted++;
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
