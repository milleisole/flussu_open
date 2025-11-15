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
 * CLASS-NAME:     Contracts - Interface for Webhook providers classes
 * CREATE DATE:    15.11:2025
 * VERSION REL.:   4.5.20251115
 * UPDATES DATE:   15.11:2025
 * -------------------------------------------------------*/
namespace Flussu\Contracts;

use Flussu\Flussuserver\Request;

interface IWebhookProvider
{
    /**
     * Main API call handler for webhook requests
     *
     * @param Request $request The incoming request object
     * @param string $apiPage The API endpoint being called
     * @return void Outputs JSON response and terminates
     */
    public function apiCall(Request $request, string $apiPage): void;

    /**
     * Authenticate the webhook request
     *
     * @param string $username Username from request
     * @param string $password Password from request
     * @return int User ID if authenticated, 0 otherwise
     */
    public function authenticate(string $username, string $password): int;

    /**
     * Extract and normalize data from webhook payload
     *
     * @param mixed $data Raw data from webhook
     * @return array Normalized key-value array
     */
    public function extractData($data): array;

    /**
     * Execute workflow with webhook data
     *
     * @param int $wid Workflow ID
     * @param string $origWid Original workflow identifier
     * @param int $userId User ID
     * @param array $data Webhook data
     * @return array [sessionId, variables]
     */
    public function executeWorkflow(int $wid, string $origWid, int $userId, array $data): array;
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
