<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5- Mille Isole SRL - Released under Apache License 2.0
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
 * --------------------------------------------------------------------*/
namespace Flussu\Controllers;

enum Platform: int {
    case INIT = -1;
    case CHATGPT = 0;
    case GROK = 1;
    case GEMINI = 2;
    case DEEPSEEK = 3;
    case CLAUDE = 4;
    case HUGGINGFACE = 5;
    case ZEROONE = 6;
    case KIMI = 7;
    case QWEN = 8;
    case STABILITY = 9;  // v4.5.2 - Stability AI (Stable Diffusion) - image generation only
    case MISTRAL = 10;
    case ZAI_GLM = 11;
    case TOGETHER = 12;  // v5.0 - Together.ai - chat (Llama, Mixtral, ...) + image generation (FLUX.1)

    /**
     * v5.0 — Some chat providers cannot (or no longer) generate images themselves.
     * This helper redirects image-gen requests to a capable provider while keeping
     * the chat experience on the original platform.
     *
     * All chat-only providers default to TOGETHER (FLUX.1) so the operator only has
     * to configure one fallback backend. xAI's own guidance for Grok is to use a
     * different model for image generation; we apply the same logic uniformly.
     *
     * Provider redirected → TOGETHER:
     *   GROK, DEEPSEEK, CLAUDE, HUGGINGFACE, ZEROONE, KIMI, MISTRAL
     *
     * Image-capable providers (passthrough — generate their own images):
     *   CHATGPT, GEMINI, STABILITY, QWEN, ZAI_GLM, TOGETHER
     */
    public function resolveImageProvider(): self
    {
        return match ($this) {
            self::GROK,
            self::DEEPSEEK,
            self::CLAUDE,
            self::HUGGINGFACE,
            self::ZEROONE,
            self::KIMI,
            self::MISTRAL    => self::TOGETHER,
            default          => $this,
        };
    }

    /**
     * v5.x — Web research counterpart of resolveImageProvider().
     *
     * Unlike image generation (which delegates to TOGETHER for chat-only providers),
     * web research uses Flussu's local hybrid pipeline as the shared agent:
     * WebSearchController + WebScraperController + a synthesis call back to the
     * same chat provider. So this method is a passthrough by default.
     *
     * It exists for symmetry with resolveImageProvider() and as an extension point:
     * an operator wanting to force every web request through a single AI vendor with
     * a native browsing tool can override this match table to redirect to that
     * provider (e.g. CHATGPT via its Responses-API web_search tool).
     */
    public function resolveWebProvider(): self
    {
        return $this;
    }
}
