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
 * CLASS-NAME:       Flussu Zapier API Controller
 * UPDATED DATE:     04.11.2022 - Aldus-Claude - Flussu v5.0
 * VERSION REL.:     5.0.20251115
 * UPDATES DATE:     15.11:2025
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
//use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;

/**
 * Zapier Webhook Controller
 * Handles API calls from Zapier automation platform
 */
class ZapierController extends AbsWebhookProvider
{
    /**
     * Constructor - sets Zapier-specific configuration
     */
    public function __construct()
    {
        parent::__construct();
        $this->providerName = 'ZAPIER';
        $this->varPrefix = 'zap_';
        $this->expectedUserAgent = 'Zapier';
    }

    /**
     * Main API call handler for Zapier requests
     *
     * @param Request $request The incoming request object
     * @param string $apiPage The API endpoint being called
     * @return void
     */
    public function apiCall(/*Request $request,*/ $apiPage): void
    {
        // Set CORS headers
        $this->setCorsHeaders();

        // Extract credentials
        list($usrName, $usrPass) = $this->extractCredentials();

        // Extract workflow ID
        list($wid, $SentWID) = $this->extractWorkflowId(/*$request*/);

        // Get payload data
        $rawdata = file_get_contents('php://input');
        $theData = json_decode($rawdata, true);

        // Extract data from nested structure if present
        if (isset($theData) && is_array($theData) && array_key_exists("data", $theData)) {
            $theData = $this->extractData($theData["data"]);
        }

        // Authenticate user
        $uid = $this->authenticate($usrName, $usrPass);

        if ($uid < 1) {
            $this->reportErrorAndDie("403", "Unauthenticated");
        }

        // Handle authentication test endpoint
        if (strpos($apiPage, "zap?auth") !== false) {
            $this->reportErrorAndDie("200", "OK authenticated");
        }

        // Handle workflow list endpoint
        if (strpos($apiPage, "zap?list") !== false) {
            $this->handleListWorkflows($uid);
        }

        // Handle test endpoint
        if ($SentWID == "[__wzaptest__]") {
            $this->reportErrorAndDie("200", "Hi Zapier, I'm Alive :), how are u?");
        }

        // Validate workflow ID
        if ($wid < 1) {
            $this->reportErrorAndDie("406", "Wrong Flussu WID");
        }

        // Validate data
        if (is_null($theData) || empty($theData)) {
            $this->reportErrorAndDie("406", "No Data Received");
        }

        // Validate credentials
        if (is_null($usrName) || empty($usrName)) {
            $this->reportErrorAndDie("406", "No Username received");
        }

        if (is_null($usrPass) || empty($usrPass)) {
            $this->reportErrorAndDie("406", "No Password received");
        }

        error_reporting(0);

        switch ($apiPage) {
            case "zap":
                // Execute workflow
                $res = $this->executeWorkflow($wid, $SentWID, $uid, $theData);
                $sid = $res[0];
                $vars = json_encode($res[1]);
                $vars = "{" . str_replace(["{", "}", "[", "]"], "", $vars) . "}";
                $vvv = json_encode(["result" => "started", "res" => "", "WID" => $SentWID, "SID" => $sid]);
                die(str_replace("\"res\":\"\"", "\"res\":" . $vars, $vvv));
                break;

            default:
                $this->reportErrorAndDie("403", "Forbidden");
        }

        error_reporting(E_ALL);
    }

    /**
     * Handle workflow list request
     *
     * @param int $userId User ID
     * @return never
     */
    private function handleListWorkflows(int $userId): never
    {
        $db = new HandlerNC();
        $res = $db->getFlussu(false, $userId);
        $retArr = [];
        $i = 1;

        foreach ($res as $wf) {
            $wc = new \stdClass();
            $wc->id = $i++;
            $wc->wid = $wf["wid"];
            $wc->title = $wf["name"];
            array_push($retArr, $wc);
        }

        die(json_encode($retArr));
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
