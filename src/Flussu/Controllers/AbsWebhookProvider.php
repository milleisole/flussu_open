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
 * CLASS-NAME:       Abstract Webhook Provider Base Class
 * CREATE DATE:      15.11:2025
 * VERSION REL.:     5.0.20251115
 * UPDATES DATE:     15.11:2025
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\Contracts\IWebhookProvider;
use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\Flussuserver\Session;
use Flussu\Flussuserver\Worker;
use Flussu\General;

/**
 * Abstract base class for webhook providers (Zapier, IFTTT, Make, n8n, etc.)
 * Provides common functionality for handling webhook requests and workflow execution
 */
abstract class AbsWebhookProvider implements IWebhookProvider
{
    /**
     * The name of the webhook provider (e.g., 'ZAPIER', 'IFTTT')
     */
    protected string $providerName = 'WEBHOOK';

    /**
     * The variable prefix used in workflows (e.g., 'zap_', 'ifttt_')
     */
    protected string $varPrefix = 'wh_';

    /**
     * Expected User-Agent string for this provider
     */
    protected string $expectedUserAgent = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Authenticate user using database credentials instead of hardcoded values
     *
     * @param string $username Username from request
     * @param string $password Password from request
     * @return int User ID if authenticated, 0 otherwise
     */
    public function authenticate(string $username, string $password): int
    {
        if (empty($username) || empty($password)) {
            return 0;
        }

        $theFlussuUser = new \Flussu\Persons\User();

        // Load user credentials from config/database
        $credentials = config("webhooks.{$this->getProviderConfigKey()}.credentials", []);

        foreach ($credentials as $cred) {
            if (isset($cred['username']) && $cred['username'] === $username) {
                // Verify password (support both plain and hashed)
                if (isset($cred['password']) && $this->verifyPassword($password, $cred['password'])) {
                    if (isset($cred['user_id'])) {
                        $theFlussuUser->load($cred['user_id']);
                        if ($theFlussuUser->getId() > 0) {
                            return $theFlussuUser->getId();
                        }
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Verify password (supports both plain text and hashed passwords)
     *
     * @param string $provided Provided password
     * @param string $stored Stored password (plain or hashed)
     * @return bool True if password matches
     */
    protected function verifyPassword(string $provided, string $stored): bool
    {
        // Check if stored password is hashed (bcrypt starts with $2y$)
        if (substr($stored, 0, 4) === '$2y$') {
            return password_verify($provided, $stored);
        }

        // Plain text comparison (for backward compatibility)
        return $provided === $stored;
    }

    /**
     * Extract and normalize data from webhook payload
     * Default implementation - can be overridden by specific providers
     *
     * @param mixed $data Raw data from webhook
     * @return array Normalized key-value array
     */
    public function extractData($data): array
    {
        $values = "";
        $res = [];

        if (is_array($data)) {
            for ($i = 0; $i < count($data); $i++) {
                foreach ($data[$i] as $key => $val) {
                    if (strpos($values, $val) === false) {
                        $values .= "," . $val;
                    }
                }
            }
        } else {
            foreach ($data as $key => $val) {
                if (strpos($values, $val) === false) {
                    $values .= "," . $val;
                }
            }
        }

        $vll = explode(",", substr($values, 1));
        foreach ($vll as $vl) {
            $vl = explode(":", $vl);
            if (count($vl) > 0) {
                for ($i = 2; $i < count($vl); $i++) {
                    $vl[1] = $vl[1] . ":" . $vl[$i];
                }
            }
            $vl[1] = preg_replace('~^"?(.*?)"?$~', '$1', $vl[1]);
            if (array_key_exists($vl[0], $res) && $res[$vl[0]] != $vl[1]) {
                $res[$vl[0]] = $res[$vl[0]] . "," . $vl[1];
            } else {
                $res = array_merge($res, [trim($vl[0]) => trim($vl[1])]);
            }
        }

        return $res;
    }

    /**
     * Execute workflow with webhook data
     *
     * @param int $wid Workflow ID
     * @param string $origWid Original workflow identifier
     * @param int $userId User ID
     * @param array $data Webhook data
     * @return array [sessionId, variables]
     */
    public function executeWorkflow(int $wid, string $origWid, int $userId, array $data): array
    {
        $ret = [];
        $sid = "";
        $handl = new HandlerNC();
        $res = $handl->getFirstBlock($wid);

        if (isset($res[0]["exec"])) {
            $LNG = "IT";
            $wSess = new Session(null);
            $IP = General::getCallerIPAddress();
            $UA = General::getCallerUserAgent();
            $wSess->createNew($wid, $IP, $LNG, $UA, $userId, $this->providerName, $origWid);
            $sid = $wSess->getId();

            $wwork = new Worker($wSess);
            $frmBid = $wSess->getBlockId();

            // Extract variables that match the provider's prefix
            $rows = explode("\n", $res[0]["exec"]);
            foreach ($rows as $row) {
                $row = trim($row);
                $varPattern = "$" . $this->varPrefix;
                if (substr($row, 0, strlen($varPattern)) == $varPattern) {
                    $extName = substr($row, strlen($varPattern), strpos($row, "=") - strlen($varPattern));
                    $intName = $varPattern . $extName;
                    $wSess->assignVars($intName, isset($data[$extName]) ? $data[$extName] : "");
                    array_push($ret, [$intName => isset($data[$extName]) ? $data[$extName] : "---"]);
                }
            }

            $hres = $wwork->execNextBlock($frmBid, "", false);
        }

        return [$sid, $ret];
    }

    /**
     * Set CORS headers for webhook responses
     */
    protected function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
    }

    /**
     * Report error and terminate execution
     *
     * @param string $httpErr HTTP error code
     * @param string $errMsg Error message
     * @return never
     */
    protected function reportErrorAndDie(string $httpErr, string $errMsg): never
    {
        die(json_encode(["error" => $httpErr, "message" => $errMsg]));
    }

    /**
     * Extract workflow ID from request
     *
     * @param Request $request The request object
     * @return array [workflowId, originalWid]
     */
    protected function extractWorkflowId(Request $request): array
    {
        $SentWID = isset($_SERVER["HTTP_WID"]) ? $_SERVER["HTTP_WID"] : "";
        $rawdata = file_get_contents('php://input');
        $theData = json_decode($rawdata, true);

        if (isset($theData["WID"]) && !empty($theData["WID"])) {
            $SentWID = $theData["WID"];
        } else {
            if ($SentWID == "" && isset($theData["WID"])) {
                $SentWID = $theData["WID"];
            }
        }

        $wid = HandlerNC::WID2Wofoid($SentWID);

        return [$wid, $SentWID];
    }

    /**
     * Extract credentials from request
     *
     * @return array [username, password]
     */
    protected function extractCredentials(): array
    {
        $usrName = isset($_SERVER["PHP_AUTH_USER"]) ? $_SERVER["PHP_AUTH_USER"] : "";
        $usrPass = isset($_SERVER["PHP_AUTH_PW"]) ? $_SERVER["PHP_AUTH_PW"] : "";

        return [$usrName, $usrPass];
    }

    /**
     * Get provider configuration key for config lookups
     *
     * @return string Configuration key (lowercase provider name)
     */
    protected function getProviderConfigKey(): string
    {
        return strtolower($this->providerName);
    }

    /**
     * Verify User-Agent matches expected value
     *
     * @return bool True if User-Agent matches
     */
    protected function verifyUserAgent(): bool
    {
        if (empty($this->expectedUserAgent)) {
            return true; // No verification if not set
        }

        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
        return $userAgent === $this->expectedUserAgent;
    }

    /**
     * Check if request method is POST
     *
     * @return bool True if POST request
     */
    protected function isPostRequest(): bool
    {
        return isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST";
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
