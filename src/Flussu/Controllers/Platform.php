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
    // --- CHATGPT model variants ---
    case CHATGPT_O4 = 101;
    //----------------------------------
    case GROK = 1;
    // --- GROK model variants ---
    case GROK_42 = 11;
    //----------------------------------
    case GEMINI = 2;
    // --- GEMINI model variants ---
    case GEMINI_35 = 21;
    //----------------------------------
    case DEEPSEEK = 3;
    case CLAUDE = 4;
    // --- CLAUDE model variants ---
    case CLAUDE_OP46 = 41;
    case CLAUDE_SO46 = 42;
    //----------------------------------
    case HUGGINGFACE = 5;
    case ZEROONE = 6;
    case KIMI = 7;
    case QWEN = 8;
    case STABILITY = 9;  // v4.5.2 - Stability AI (Stable Diffusion) - image generation only
}
