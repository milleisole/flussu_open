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

    // ========================================
    // EXTENDED USER MANAGEMENT
    // ========================================

    /**
     * Get all users with optional inclusion of deleted users
     *
     * @param bool $includeDeleted Whether to include deleted users
     * @return array|false Array of users or false on failure
     */
    public function getAllUsers($includeDeleted = false) {
        General::addRowLog("[Handler: Get All Users]");

        $sql = "SELECT u.*, r.c90_name as role_name,
                (CASE WHEN u.c80_deleted = '1899-12-31 23:59:59' THEN 1 ELSE 0 END) as is_active
                FROM t80_user u
                LEFT JOIN t90_role r ON r.c90_id = u.c80_role";

        if (!$includeDeleted) {
            $sql .= " WHERE u.c80_deleted = '1899-12-31 23:59:59'";
        }
        $sql .= " ORDER BY u.c80_id";

        if ($this->execSql($sql)) {
            return $this->getData();
        }

        return false;
    }

    /**
     * Get user by username or email
     *
     * @param string $identifier Username or email
     * @return array|false User data or false if not found
     */
    public function getUserByUsernameOrEmail($identifier) {
        General::addRowLog("[Handler: Get User by Username or Email]");

        $sql = "SELECT u.*, r.c90_name as role_name,
                (CASE WHEN u.c80_deleted = '1899-12-31 23:59:59' THEN 1 ELSE 0 END) as is_active
                FROM t80_user u
                LEFT JOIN t90_role r ON r.c90_id = u.c80_role
                WHERE (u.c80_username = ? OR u.c80_email = ?) AND u.c80_deleted = '1899-12-31 23:59:59'";

        if ($this->execSql($sql, array($identifier, $identifier))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Create a new user
     *
     * @param array $data User data
     * @return int|false User ID or false on failure
     */
    public function createUser($data) {
        General::addRowLog("[Handler: Create User]");

        $sql = "INSERT INTO t80_user
                (c80_username, c80_email, c80_password, c80_role, c80_name, c80_surname, c80_pwd_chng)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = array(
            $data['username'],
            $data['email'],
            $data['password'] ?? '',
            $data['role'] ?? 0,
            $data['name'] ?? '',
            $data['surname'] ?? '',
            $data['pwd_chng'] ?? date('Y-m-d H:i:s')
        );

        return $this->execSqlGetId($sql, $params);
    }

    /**
     * Update user data
     *
     * @param int $userId User ID
     * @param array $updateFields Array of field=>value to update
     * @return bool True if successful
     */
    public function updateUser($userId, $updateFields) {
        General::addRowLog("[Handler: Update User {$userId}]");

        if (empty($updateFields)) {
            return false;
        }

        $fields = array();
        $params = array();

        foreach ($updateFields as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }

        $params[] = $userId;
        $sql = "UPDATE t80_user SET " . implode(', ', $fields) . " WHERE c80_id = ?";

        return $this->execSql($sql, $params);
    }

    /**
     * Set user status (active/inactive via soft delete)
     *
     * @param int $userId User ID
     * @param string $deletedDate Deleted date or '1899-12-31 23:59:59' for active
     * @param int $deletedBy User ID who performed the action
     * @return bool True if successful
     */
    public function setUserStatus($userId, $deletedDate, $deletedBy = 0) {
        General::addRowLog("[Handler: Set User {$userId} Status]");

        $sql = "UPDATE t80_user SET c80_deleted = ?, c80_deleted_by = ? WHERE c80_id = ?";
        return $this->execSql($sql, array($deletedDate, $deletedBy, $userId));
    }

    /**
     * Change user password
     *
     * @param int $userId User ID
     * @param string $hashedPassword Hashed password
     * @param string $pwdChng Password change date
     * @return bool True if successful
     */
    public function changePassword($userId, $hashedPassword, $pwdChng) {
        General::addRowLog("[Handler: Change Password for User {$userId}]");

        $sql = "UPDATE t80_user SET c80_password = ?, c80_pwd_chng = ? WHERE c80_id = ?";
        return $this->execSql($sql, array($hashedPassword, $pwdChng, $userId));
    }

    /**
     * Check if username exists
     *
     * @param string $username Username to check
     * @param int|null $excludeUserId User ID to exclude from check
     * @return bool True if exists
     */
    public function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM t80_user WHERE c80_username = ?";
        $params = array($username);

        if ($excludeUserId) {
            $sql .= " AND c80_id != ?";
            $params[] = $excludeUserId;
        }

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            return is_array($result) && count($result) > 0 && $result[0]['count'] > 0;
        }

        return false;
    }

    /**
     * Check if email exists
     *
     * @param string $email Email to check
     * @param int|null $excludeUserId User ID to exclude from check
     * @return bool True if exists
     */
    public function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM t80_user WHERE c80_email = ?";
        $params = array($email);

        if ($excludeUserId) {
            $sql .= " AND c80_id != ?";
            $params[] = $excludeUserId;
        }

        if ($this->execSql($sql, $params)) {
            $result = $this->getData();
            return is_array($result) && count($result) > 0 && $result[0]['count'] > 0;
        }

        return false;
    }

    /**
     * Get user statistics
     *
     * @return array|false Array of statistics or false on failure
     */
    public function getUserStats() {
        General::addRowLog("[Handler: Get User Stats]");

        $sql = "SELECT
                    r.c90_name as role_name,
                    COUNT(u.c80_id) as user_count,
                    SUM(CASE WHEN u.c80_deleted = '1899-12-31 23:59:59' THEN 1 ELSE 0 END) as active_count
                FROM t80_user u
                LEFT JOIN t90_role r ON r.c90_id = u.c80_role
                GROUP BY u.c80_role, r.c90_name";

        if ($this->execSql($sql)) {
            return $this->getData();
        }

        return false;
    }

    // ========================================
    // WORKFLOW PERMISSIONS
    // ========================================

    /**
     * Get workflow owner
     *
     * @param int $workflowId Workflow ID
     * @return int|false Owner user ID or false
     */
    public function getWorkflowOwner($workflowId) {
        $sql = "SELECT c10_userid FROM t10_workflow WHERE c10_id = ?";

        if ($this->execSql($sql, array($workflowId))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0]['c10_userid'];
            }
        }

        return false;
    }

    /**
     * Get user's explicit permission on workflow
     *
     * @param int $workflowId Workflow ID
     * @param int $userId User ID
     * @return string|false Permission string or false
     */
    public function getWorkflowPermission($workflowId, $userId) {
        $sql = "SELECT c88_permission FROM t88_wf_permissions WHERE c88_wf_id = ? AND c88_usr_id = ?";

        if ($this->execSql($sql, array($workflowId, $userId))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0]['c88_permission'];
            }
        }

        return false;
    }

    /**
     * Check if user has workflow access through project
     *
     * @param int $workflowId Workflow ID
     * @param int $userId User ID
     * @return bool True if has access
     */
    public function hasWorkflowProjectAccess($workflowId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM t85_prj_wflow pw
                INNER JOIN t87_prj_user pu ON pu.c87_prj_id = pw.c85_prj_id
                WHERE pw.c85_flofoid = ? AND pu.c87_usr_id = ?";

        if ($this->execSql($sql, array($workflowId, $userId))) {
            $result = $this->getData();
            return is_array($result) && count($result) > 0 && $result[0]['count'] > 0;
        }

        return false;
    }

    /**
     * Grant workflow permission to user
     *
     * @param int $workflowId Workflow ID
     * @param int $userId User ID
     * @param string $permission Permission string
     * @param int $grantedBy User ID who granted permission
     * @return bool True if successful
     */
    public function grantWorkflowPermission($workflowId, $userId, $permission, $grantedBy) {
        General::addRowLog("[Handler: Grant Workflow {$workflowId} Permission to User {$userId}]");

        $sql = "INSERT INTO t88_wf_permissions
                (c88_wf_id, c88_usr_id, c88_permission, c88_granted_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                c88_permission = VALUES(c88_permission),
                c88_granted_by = VALUES(c88_granted_by),
                c88_granted_at = CURRENT_TIMESTAMP";

        return $this->execSql($sql, array($workflowId, $userId, $permission, $grantedBy));
    }

    /**
     * Revoke workflow permission from user
     *
     * @param int $workflowId Workflow ID
     * @param int $userId User ID
     * @return bool True if successful
     */
    public function revokeWorkflowPermission($workflowId, $userId) {
        General::addRowLog("[Handler: Revoke Workflow {$workflowId} Permission from User {$userId}]");

        $sql = "DELETE FROM t88_wf_permissions WHERE c88_wf_id = ? AND c88_usr_id = ?";
        return $this->execSql($sql, array($workflowId, $userId));
    }

    /**
     * Get all permissions for a workflow
     *
     * @param int $workflowId Workflow ID
     * @return array|false Array of permissions or false
     */
    public function getWorkflowPermissions($workflowId) {
        $sql = "SELECT
                    p.c88_usr_id as user_id,
                    u.c80_username,
                    u.c80_email,
                    u.c80_name,
                    u.c80_surname,
                    p.c88_permission as permission,
                    p.c88_granted_by as granted_by,
                    gb.c80_username as granted_by_username,
                    p.c88_granted_at as granted_at
                FROM t88_wf_permissions p
                INNER JOIN t80_user u ON u.c80_id = p.c88_usr_id
                LEFT JOIN t80_user gb ON gb.c80_id = p.c88_granted_by
                WHERE p.c88_wf_id = ?
                ORDER BY p.c88_granted_at DESC";

        if ($this->execSql($sql, array($workflowId))) {
            return $this->getData();
        }

        return false;
    }

    /**
     * Get workflows accessible by user
     *
     * @param int $userId User ID
     * @param bool $includeInactive Include inactive workflows
     * @return array|false Array of workflows or false
     */
    public function getUserWorkflows($userId, $includeInactive = false) {
        $activeCond = $includeInactive ? '' : ' AND c10_active = 1';

        $sql = "SELECT DISTINCT
                    w.c10_id as wf_id,
                    w.c10_wf_auid as wf_auid,
                    w.c10_name as wf_name,
                    w.c10_description,
                    w.c10_active as is_active,
                    w.c10_userid as owner_id,
                    u.c80_username as owner_username,
                    CASE
                        WHEN w.c10_userid = ? THEN 'O'
                        ELSE COALESCE(p.c88_permission, 'R')
                    END as permission
                FROM t10_workflow w
                INNER JOIN t80_user u ON u.c80_id = w.c10_userid
                LEFT JOIN t88_wf_permissions p ON p.c88_wf_id = w.c10_id AND p.c88_usr_id = ?
                LEFT JOIN t85_prj_wflow pw ON pw.c85_flofoid = w.c10_id
                LEFT JOIN t87_prj_user pu ON pu.c87_prj_id = pw.c85_prj_id AND pu.c87_usr_id = ?
                WHERE (w.c10_userid = ? OR p.c88_usr_id IS NOT NULL OR pu.c87_usr_id IS NOT NULL)
                AND w.c10_deleted = '1899-12-31 23:59:59'
                {$activeCond}
                ORDER BY w.c10_name";

        if ($this->execSql($sql, array($userId, $userId, $userId, $userId))) {
            return $this->getData();
        }

        return false;
    }

    // ========================================
    // INVITATION MANAGEMENT
    // ========================================

    /**
     * Create invitation
     *
     * @param string $email Email address
     * @param int $role Role ID
     * @param int $invitedBy User ID who created invitation
     * @param string $invitationCode Invitation code
     * @param string $expiresAt Expiration date
     * @return int|false Invitation ID or false
     */
    public function createInvitation($email, $role, $invitedBy, $invitationCode, $expiresAt) {
        General::addRowLog("[Handler: Create Invitation for {$email}]");

        $sql = "INSERT INTO t96_user_invitations
                (c96_email, c96_role, c96_invited_by, c96_invitation_code, c96_expires_at)
                VALUES (?, ?, ?, ?, ?)";

        return $this->execSqlGetId($sql, array($email, $role, $invitedBy, $invitationCode, $expiresAt));
    }

    /**
     * Get invitation by code
     *
     * @param string $invitationCode Invitation code
     * @param int $status Status filter
     * @return array|false Invitation data or false
     */
    public function getInvitationByCode($invitationCode, $status = 0) {
        $sql = "SELECT * FROM t96_user_invitations
                WHERE c96_invitation_code = ?
                AND c96_status = ?
                AND c96_expires_at > NOW()";

        if ($this->execSql($sql, array($invitationCode, $status))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Update invitation status
     *
     * @param string $invitationCode Invitation code
     * @param int $status New status
     * @param string|null $acceptedAt Accepted timestamp
     * @return bool True if successful
     */
    public function updateInvitationStatus($invitationCode, $status, $acceptedAt = null) {
        if ($acceptedAt) {
            $sql = "UPDATE t96_user_invitations SET c96_status = ?, c96_accepted_at = ? WHERE c96_invitation_code = ?";
            return $this->execSql($sql, array($status, $acceptedAt, $invitationCode));
        } else {
            $sql = "UPDATE t96_user_invitations SET c96_status = ? WHERE c96_invitation_code = ?";
            return $this->execSql($sql, array($status, $invitationCode));
        }
    }

    /**
     * Get invitations by user
     *
     * @param int $userId User ID who created invitations
     * @return array|false Array of invitations or false
     */
    public function getUserInvitations($userId) {
        $sql = "SELECT * FROM t96_user_invitations WHERE c96_invited_by = ? ORDER BY c96_created_at DESC";

        if ($this->execSql($sql, array($userId))) {
            return $this->getData();
        }

        return false;
    }

    /**
     * Get pending invitations
     *
     * @param int $status Status filter (default 0 = pending)
     * @return array|false Array of invitations or false
     */
    public function getPendingInvitations($status = 0) {
        $sql = "SELECT i.*, u.c80_username as invited_by_username
                FROM t96_user_invitations i
                INNER JOIN t80_user u ON u.c80_id = i.c96_invited_by
                WHERE i.c96_status = ? AND i.c96_expires_at > NOW()
                ORDER BY i.c96_created_at DESC";

        if ($this->execSql($sql, array($status))) {
            return $this->getData();
        }

        return false;
    }

    /**
     * Mark expired invitations
     *
     * @param int $expiredStatus Status to set for expired invitations
     * @param int $currentStatus Current status to filter
     * @return bool True if successful
     */
    public function markExpiredInvitations($expiredStatus = 2, $currentStatus = 0) {
        $sql = "UPDATE t96_user_invitations SET c96_status = ? WHERE c96_status = ? AND c96_expires_at < NOW()";
        return $this->execSql($sql, array($expiredStatus, $currentStatus));
    }

    // ========================================
    // SESSION MANAGEMENT
    // ========================================

    /**
     * Create user session
     *
     * @param array $data Session data
     * @return bool True if successful
     */
    public function createSession($data) {
        General::addRowLog("[Handler: Create Session for User {$data['user_id']}]");

        $sql = "INSERT INTO t94_user_sessions
                (c94_session_id, c94_usr_id, c94_api_key, c94_ip_address, c94_user_agent, c94_expires_at)
                VALUES (?, ?, ?, ?, ?, ?)";

        return $this->execSql($sql, array(
            $data['session_id'],
            $data['user_id'],
            $data['api_key'],
            $data['ip_address'],
            $data['user_agent'],
            $data['expires_at']
        ));
    }

    /**
     * Get session by session ID
     *
     * @param string $sessionId Session ID
     * @return array|false Session data or false
     */
    public function getSessionById($sessionId) {
        $sql = "SELECT s.*, u.c80_username, u.c80_email, u.c80_role
                FROM t94_user_sessions s
                INNER JOIN t80_user u ON u.c80_id = s.c94_usr_id
                WHERE s.c94_session_id = ? AND s.c94_expires_at > NOW()
                AND u.c80_deleted = '1899-12-31 23:59:59'";

        if ($this->execSql($sql, array($sessionId))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Get session by API key
     *
     * @param string $apiKey API key
     * @return array|false Session data or false
     */
    public function getSessionByApiKey($apiKey) {
        $sql = "SELECT s.*, u.c80_username, u.c80_email, u.c80_role
                FROM t94_user_sessions s
                INNER JOIN t80_user u ON u.c80_id = s.c94_usr_id
                WHERE s.c94_api_key = ? AND s.c94_expires_at > NOW()
                AND u.c80_deleted = '1899-12-31 23:59:59'";

        if ($this->execSql($sql, array($apiKey))) {
            $result = $this->getData();
            if (is_array($result) && count($result) > 0) {
                return $result[0];
            }
        }

        return false;
    }

    /**
     * Update session last activity
     *
     * @param string $sessionId Session ID
     * @return bool True if successful
     */
    public function updateSessionActivity($sessionId) {
        $sql = "UPDATE t94_user_sessions SET c94_last_activity = CURRENT_TIMESTAMP WHERE c94_session_id = ?";
        return $this->execSql($sql, array($sessionId));
    }

    /**
     * Delete session
     *
     * @param string $sessionId Session ID
     * @param int|null $userId User ID for additional security check
     * @return bool True if successful
     */
    public function deleteSession($sessionId, $userId = null) {
        $sql = "DELETE FROM t94_user_sessions WHERE c94_session_id = ?";
        $params = array($sessionId);

        if ($userId) {
            $sql .= " AND c94_usr_id = ?";
            $params[] = $userId;
        }

        return $this->execSql($sql, $params);
    }

    /**
     * Delete all sessions for user
     *
     * @param int $userId User ID
     * @return bool True if successful
     */
    public function deleteAllUserSessions($userId) {
        $sql = "DELETE FROM t94_user_sessions WHERE c94_usr_id = ?";
        return $this->execSql($sql, array($userId));
    }

    /**
     * Get active sessions for user
     *
     * @param int $userId User ID
     * @return array|false Array of sessions or false
     */
    public function getUserActiveSessions($userId) {
        $sql = "SELECT * FROM t94_user_sessions
                WHERE c94_usr_id = ? AND c94_expires_at > NOW()
                ORDER BY c94_last_activity DESC";

        if ($this->execSql($sql, array($userId))) {
            return $this->getData();
        }

        return false;
    }

    /**
     * Clean expired sessions
     *
     * @return bool True if successful
     */
    public function cleanExpiredSessions() {
        General::addRowLog("[Handler: Clean Expired Sessions]");

        $sql = "DELETE FROM t94_user_sessions WHERE c94_expires_at < NOW()";
        return $this->execSql($sql);
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
