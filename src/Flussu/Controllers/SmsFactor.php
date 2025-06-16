<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3 - Mille Isole SRL - Released under Apache License 2.0
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
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * --------------------------------------------------------------------*
 * CLASS-NAME:       SMS sender provider for SmsFactor API
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * --------------------------------------------------------------------*/
namespace Flussu\Controllers;
/**
 * Provides functionality to send SMS messages using the SmsFactor API.
 */
class SmsFactor extends AbsSmsSender
{
    /**
     * Sends an SMS message to a specified recipient.
     * 
     * @param string $senderName The name of the sender to display on the recipient's device.
     * @param string $toNumber The phone number of the recipient.
     * @param string $textMessage The message text to send.
     * @return bool|string Returns true on success, or an error message on failure.
     */
    function sendSms($senderName,$toNumber,$textMessage){
        $opts=[
            "token"=>$this->_apiKey,
            "to"=>$toNumber,
            "sender"=>trim($senderName),
            "text"=>trim($textMessage),
        ];
        return $this->sendRequest("https://api.smsfactor.com/send", "GET", $opts);
    }
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