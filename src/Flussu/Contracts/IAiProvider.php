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
 * CLASS-NAME:     Contracts - Interface for Pay providers classes
 * CREATE DATE:    14.01:2025 
 * VERSION REL.:   5.0.20251113
 * UPDATES DATE:   13.11:2025 
 * -------------------------------------------------------*/
namespace Flussu\Contracts;

interface IAiProvider
{
    public function canBrowseWeb();
    
    /**
     * Execute a chat completion request
     * 
     * @param array $preChat Previous conversation history
     * @param string $text User message text
     * @param string $role User role (default: "user")
     * @return array Response structure:
     *   [0] => conversation history (retrocompatibility)
     *   [1] => response text (retrocompatibility)
     *   ['conversation'] => conversation history array
     *   ['response'] => response text string
     *   ['token_in'] => input tokens consumed (int, 0 if unavailable)
     *   ['token_out'] => output tokens consumed (int, 0 if unavailable)
     */
    public function chat($preChat,$text,$role);
    
    //public function chatContinue($arrayText);
    public function chat_WebPreview($text,$session,$maxTokens,$temperature); 
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