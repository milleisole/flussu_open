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
 * CLASS-NAME:       Flussu IFTTT API Controller
 * CREATED DATE:     15.11.2025 - Claude - Flussu v4.5.1
 * VERSION REL.:     4.5.20251115
 * UPDATES DATE:     15.11:2025
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;

/**
 * IFTTT Webhook Controller
 * Handles API calls from IFTTT automation platform
 */
class IftttController extends AbsWebhookProvider
{
    /**
     * Constructor - sets IFTTT-specific configuration
     */
    public function __construct()
    {
        parent::__construct();
        $this->providerName = 'IFTTT';
        $this->varPrefix = 'ifttt_';
        $this->expectedUserAgent = ''; // IFTTT can have various user agents
    }

    /**
     * Main API call handler for IFTTT requests
     *
     * @param Request $request The incoming request object
     * @param string $apiPage The API endpoint being called
     * @return void
     */
    public function apiCall(Request $request, $apiPage): void
    {
        // Set CORS headers
        $this->setCorsHeaders();

        // Extract credentials (IFTTT uses service key or HTTP Basic Auth)
        list($usrName, $usrPass) = $this->extractIftttCredentials();

        // Extract workflow ID
        list($wid, $SentWID) = $this->extractWorkflowId($request);

        // Get payload data
        $rawdata = file_get_contents('php://input');
        $theData = json_decode($rawdata, true);

        // IFTTT sends data in different format - normalize it
        if (isset($theData) && is_array($theData)) {
            $theData = $this->normalizeIftttData($theData);
        }

        // Authenticate user
        $uid = $this->authenticate($usrName, $usrPass);

        if ($uid < 1) {
            $this->reportErrorAndDie("401", "Unauthorized");
        }

        // Handle status/test endpoint (IFTTT uses this to check service availability)
        if (strpos($apiPage, "ifttt/v1/status") !== false) {
            $this->handleStatusCheck();
        }

        // Handle test setup endpoint (IFTTT uses this to validate connection)
        if (strpos($apiPage, "ifttt/v1/test/setup") !== false) {
            $this->handleTestSetup();
        }

        // Handle workflow list endpoint
        if (strpos($apiPage, "ifttt?list") !== false || strpos($apiPage, "ifttt/v1/triggers") !== false) {
            $this->handleListWorkflows($uid);
        }

        // Handle test endpoint
        if ($SentWID == "[__wifttttest__]") {
            $this->reportErrorAndDie("200", "Hi IFTTT, I'm Alive :), how are u?");
        }

        // Validate workflow ID
        if ($wid < 1) {
            $this->reportErrorAndDie("400", "Invalid workflow identifier");
        }

        // Validate data
        if (is_null($theData) || empty($theData)) {
            $this->reportErrorAndDie("400", "No data received");
        }

        // Validate credentials
        if (is_null($usrName) || empty($usrName)) {
            $this->reportErrorAndDie("401", "Missing authentication");
        }

        error_reporting(0);

        // Handle trigger/action execution
        if (strpos($apiPage, "ifttt") !== false || strpos($apiPage, "ifttt/v1/actions") !== false) {
            // Execute workflow
            $res = $this->executeWorkflow($wid, $SentWID, $uid, $theData);
            $sid = $res[0];
            $vars = $res[1];

            // IFTTT expects specific response format
            $response = [
                "data" => [
                    [
                        "id" => $sid,
                        "result" => "success",
                        "workflow_id" => $SentWID,
                        "session_id" => $sid,
                        "variables" => $vars
                    ]
                ]
            ];

            die(json_encode($response));
        }

        $this->reportErrorAndDie("404", "Endpoint not found");

        error_reporting(E_ALL);
    }

    /**
     * Extract credentials from IFTTT request
     * IFTTT can send credentials via:
     * - IFTTT-Service-Key header
     * - HTTP Basic Auth
     * - URL parameter
     *
     * @return array [username, password/key]
     */
    private function extractIftttCredentials(): array
    {
        // Check for IFTTT-Service-Key header
        if (isset($_SERVER['HTTP_IFTTT_SERVICE_KEY'])) {
            $serviceKey = $_SERVER['HTTP_IFTTT_SERVICE_KEY'];
            // Service key format: "username:password" or just "key"
            if (strpos($serviceKey, ':') !== false) {
                return explode(':', $serviceKey, 2);
            } else {
                // Use service key as password with default username
                return ['ifttt', $serviceKey];
            }
        }

        // Check for IFTTT-Channel-Key header (legacy)
        if (isset($_SERVER['HTTP_IFTTT_CHANNEL_KEY'])) {
            return ['ifttt', $_SERVER['HTTP_IFTTT_CHANNEL_KEY']];
        }

        // Fallback to standard HTTP Basic Auth
        return $this->extractCredentials();
    }

    /**
     * Normalize IFTTT data format to standard format
     * IFTTT uses "actionFields" or direct JSON
     *
     * @param array $data Raw IFTTT data
     * @return array Normalized data
     */
    private function normalizeIftttData(array $data): array
    {
        // If data has "actionFields", extract it
        if (isset($data['actionFields']) && is_array($data['actionFields'])) {
            return $data['actionFields'];
        }

        // If data has nested structure, flatten it
        if (isset($data['data']) && is_array($data['data'])) {
            return $this->extractData($data['data']);
        }

        // Return as-is if already in simple key-value format
        return $data;
    }

    /**
     * Handle IFTTT status check endpoint
     * IFTTT calls this to verify service is operational
     *
     * @return never
     */
    private function handleStatusCheck(): never
    {
        die(json_encode([
            "status" => "ok",
            "service" => "Flussu",
            "version" => "4.5"
        ]));
    }

    /**
     * Handle IFTTT test setup endpoint
     * IFTTT calls this to validate authentication
     *
     * @return never
     */
    private function handleTestSetup(): never
    {
        die(json_encode([
            "data" => [
                "samples" => [
                    "triggers" => [],
                    "actions" => [
                        "execute_workflow" => [
                            "WID" => "[__wifttttest__]"
                        ]
                    ]
                ]
            ]
        ]));
    }

    /**
     * Handle workflow list request
     * Returns list of available workflows in IFTTT format
     *
     * @param int $userId User ID
     * @return never
     */
    private function handleListWorkflows(int $userId): never
    {
        $db = new HandlerNC();
        $res = $db->getFlussu(false, $userId);
        $retArr = [];

        foreach ($res as $wf) {
            $item = [
                "id" => $wf["wid"],
                "title" => $wf["name"],
                "description" => "Execute Flussu workflow: " . $wf["name"]
            ];
            array_push($retArr, $item);
        }

        // IFTTT expects data wrapper
        die(json_encode(["data" => $retArr]));
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
