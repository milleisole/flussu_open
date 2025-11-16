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
 *
 * Handler for User, ApiKey, and Roles management
 *
 * This class handles database operations for:
 * - User authentication and management
 * - Temporary API keys generation and validation
 * - User roles and permissions
 *
 * -------------------------------------------------------*
 * CLASS-NAME:       HandlerUserNC
 * CLASS PATH:       /Flussu/Flussuserver/NC
 * -------------------------------------------------------*
 * CREATED DATE:     16.02.2025
 * VERSION REL.:     4.5.20250216
 * UPDATE DATE:      16.02:2025
 * -------------------------------------------------------*/

namespace Flussu\Flussuserver\NC;
use Flussu\General;
use Flussu\Flussuserver\NC\HandlerBaseNC;

class HandlerUserNC extends HandlerBaseNC {

    // ========================================
    // API KEY MANAGEMENT
    // ========================================

    /**
     * Generate a new temporary API key for a user
     *
     * @param int $userId The user ID
     * @param int $minutesValid Number of minutes the key should be valid
     * @return string|false The generated API key or false on failure
     */
    public function generateApiKey($userId, $minutesValid = 30) {
        General::addRowLog("[Handler: Gen API Key for User {$userId}]");

        if ($userId <= 0 || $minutesValid <= 0) {
            General::addRowLog("[Handler: Invalid parameters]");
            return false;
        }

        try {
            // Generate a secure random key
            $randomBytes = random_bytes(64);
            $apiKey = bin2hex($randomBytes);

            // Calculate expiration datetime
            $expiresDateTime = date('Y-m-d H:i:s', strtotime("+{$minutesValid} minutes"));

            // Insert into database
            $sql = "INSERT INTO t82_api_key (c82_user_id, c82_key, c82_expires) VALUES (?, ?, ?)";
            $params = array($userId, $apiKey, $expiresDateTime);

            if ($this->execSql($sql, $params)) {
                General::addRowLog("[Handler: API Key generated - Expires: {$expiresDateTime}]");
                return $apiKey;
            } else {
                General::addRowLog("[Handler: Failed to insert API key]");
                return false;
            }

        } catch(\Exception $e) {
            General::addRowLog("[Handler: Exception generating API key: ".$e->getMessage()."]");
            return false;
        }
    }

    /**
     * Validate an API key and return user data if valid
     *
     * @param string $apiKey The API key to validate
     * @return array|false Array with user_id, expires, used or false if invalid
     */
    public function validateApiKey($apiKey) {
        General::addRowLog("[Handler: Validate API Key]");

        if (empty($apiKey) || strlen($apiKey) != 128) {
            General::addRowLog("[Handler: Invalid API key format]");
            return false;
        }

        try {
            // Find the API key in database
            $sql = "SELECT c82_id, c82_user_id, c82_expires, c82_used FROM t82_api_key WHERE c82_key = ? LIMIT 1";
            $params = array($apiKey);

            if ($this->execSql($sql, $params)) {
                $result = $this->getData();
                if (is_array($result) && count($result) > 0) {
                    $keyData = $result[0];

                    // Check if key has already been used
                    if (!is_null($keyData['c82_used'])) {
                        General::addRowLog("[Handler: API Key already used]");
                        return false;
                    }

                    // Check if key has expired
                    $now = date('Y-m-d H:i:s');
                    if ($now > $keyData['c82_expires']) {
                        General::addRowLog("[Handler: API Key expired]");
                        return false;
                    }

                    General::addRowLog("[Handler: API Key valid for user {$keyData['c82_user_id']}]");
                    return array(
                        'id' => $keyData['c82_id'],
                        'user_id' => $keyData['c82_user_id'],
                        'expires' => $keyData['c82_expires'],
                        'used' => $keyData['c82_used']
                    );
                }
            }

            General::addRowLog("[Handler: API Key not found]");
            return false;

        } catch(\Exception $e) {
            General::addRowLog("[Handler: Exception validating API key: ".$e->getMessage()."]");
            return false;
        }
    }

    /**
     * Mark an API key as used
     *
     * @param int $keyId The API key ID
     * @return bool True if successful, false otherwise
     */
    public function markApiKeyAsUsed($keyId) {
        General::addRowLog("[Handler: Mark API Key {$keyId} as used]");

        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE t82_api_key SET c82_used = ? WHERE c82_id = ?";
        $params = array($now, $keyId);

        if ($this->execSql($sql, $params)) {
            General::addRowLog("[Handler: API Key marked as used]");
            return true;
        }

        General::addRowLog("[Handler: Failed to mark API Key as used]");
        return false;
    }

    /**
     * Clean up expired API keys from database
     *
     * @return int Number of deleted keys
     */
    public function cleanExpiredApiKeys() {
        General::addRowLog("[Handler: Clean Expired API Keys]");

        try {
            $now = date('Y-m-d H:i:s');
            $sql = "DELETE FROM t82_api_key WHERE c82_expires < ?";
            $params = array($now);

            if ($this->execSql($sql, $params)) {
                // Get affected rows count - we need to check before getData()
                $result = $this->getData();
                $count = is_array($result) ? count($result) : 0;
                General::addRowLog("[Handler: Deleted {$count} expired API keys]");
                return $count;
            }

            return 0;

        } catch(\Exception $e) {
            General::addRowLog("[Handler: Exception cleaning expired API keys: ".$e->getMessage()."]");
            return 0;
        }
    }

    /**
     * Delete all API keys for a specific user
     *
     * @param int $userId The user ID
     * @return bool True if successful, false otherwise
     */
    public function deleteUserApiKeys($userId) {
        General::addRowLog("[Handler: Delete API Keys for User {$userId}]");

        $sql = "DELETE FROM t82_api_key WHERE c82_user_id = ?";
        $params = array($userId);

        if ($this->execSql($sql, $params)) {
            General::addRowLog("[Handler: User API Keys deleted]");
            return true;
        }

        General::addRowLog("[Handler: Failed to delete User API Keys]");
        return false;
    }

    // ========================================
    // USER MANAGEMENT
    // ========================================

    /**
     * Get user by ID
     *
     * @param int $userId The user ID
     * @return array|false User data or false if not found
     */
    public function getUserById($userId) {
        General::addRowLog("[Handler: Get User by ID {$userId}]");

        $sql = "SELECT * FROM t80_user WHERE c80_id = ?";
        $params = array($userId);

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Get user by username
     *
     * @param string $username The username
     * @return array|false User data or false if not found
     */
    public function getUserByUsername($username) {
        General::addRowLog("[Handler: Get User by Username {$username}]");

        $sql = "SELECT * FROM t80_user WHERE c80_username = ?";
        $params = array($username);

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Get user by email
     *
     * @param string $email The email address
     * @return array|false User data or false if not found
     */
    public function getUserByEmail($email) {
        General::addRowLog("[Handler: Get User by Email {$email}]");

        $sql = "SELECT * FROM t80_user WHERE c80_email = ?";
        $params = array($email);

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Check if user is active (not deleted)
     *
     * @param int $userId The user ID
     * @return bool True if user is active, false otherwise
     */
    public function isUserActive($userId) {
        $userData = $this->getUserById($userId);

        if ($userData && isset($userData['c80_deleted'])) {
            $deletedDate = $userData['c80_deleted'];
            return ($deletedDate == '1899-12-31 23:59:59' || strtotime($deletedDate) < strtotime('1900-01-01'));
        }

        return false;
    }

    /**
     * Get all active users
     *
     * @return array|false Array of users or false on failure
     */
    public function getAllActiveUsers() {
        General::addRowLog("[Handler: Get All Active Users]");

        $sql = "SELECT * FROM t80_user WHERE c80_deleted = '1899-12-31 23:59:59' ORDER BY c80_username";

        if ($this->execSql($sql)) {
            return $this->getData();
        }

        return false;
    }

    // ========================================
    // ROLE MANAGEMENT
    // ========================================

    /**
     * Check if user has required role level
     *
     * @param int $userId The user ID
     * @param int $requiredLevel The required role level
     * @return bool True if user has required level, false otherwise
     */
    public function checkUserRoleLevel($userId, $requiredLevel) {
        $userData = $this->getUserById($userId);

        if ($userData && isset($userData['c80_role'])) {
            return ($userData['c80_role'] >= $requiredLevel);
        }

        return false;
    }

    /**
     * Get role information
     *
     * @param int $roleId The role ID
     * @return array|false Role data or false if not found
     */
    public function getRoleById($roleId) {
        General::addRowLog("[Handler: Get Role by ID {$roleId}]");

        $sql = "SELECT * FROM t90_role WHERE c90_id = ?";
        $params = array($roleId);

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Get all roles
     *
     * @return array|false Array of roles or false on failure
     */
    public function getAllRoles() {
        General::addRowLog("[Handler: Get All Roles]");

        $sql = "SELECT * FROM t90_role ORDER BY c90_id";

        if ($this->execSql($sql)) {
            return $this->getData();
        }

        return false;
    }

    // CLASS DESTRUCTION
    //----------------------
    public function __destruct(){
        // Parent destructor will be called automatically
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
