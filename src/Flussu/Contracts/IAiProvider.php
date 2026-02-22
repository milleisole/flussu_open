<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:     Contracts - Interface for AI providers classes
 * CREATE DATE:    14.01:2025
 * VERSION REL.:   4.5.2.20260222
 * UPDATES DATE:   22.02:2026
 * -------------------------------------------------------*/
namespace Flussu\Contracts;

interface IAiProvider
{
    public function canBrowseWeb();
    public function chat($preChat,$text,$role);
    public function chat_WebPreview($text,$session,$maxTokens,$temperature);

    // v4.5.2 - AI Media Exchange
    public function canAnalyzeMedia(): bool;
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array;
    public function canGenerateImages(): bool;
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array;
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