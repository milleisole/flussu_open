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
 * --------------------------------------------------------------------
 * VERSION REL.:     4.5.20250929
 * UPDATES DATE:     16.11.2025
 * --------------------------------------------------------------------
 * USER MANAGER - Handles user CRUD operations
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Beans\User as UserBean;

class UserManager {

    /**
     * Get all users
     * @param bool $includeDeleted Include deleted users
     * @return array
     */
    public static function getAllUsers(bool $includeDeleted = false): array {
        try {
            General::addRowLog("[UserManager] Getting all users");

            $userBean = new UserBean(General::$DEBUG);
            $whereClause = $includeDeleted ? "" : "c80_deleted = '1899-12-31 00:00:00'";

            $rows = $userBean->selectRows("*", $whereClause);

            if (!is_array($rows)) {
                return [];
            }

            // Format the data
            $users = [];
            foreach ($rows as $row) {
                $users[] = [
                    'id' => $row['c80_id'],
                    'username' => $row['c80_username'],
                    'email' => $row['c80_email'],
                    'name' => $row['c80_name'],
                    'surname' => $row['c80_surname'],
                    'role' => $row['c80_role'],
                    'created' => $row['c80_created'],
                    'modified' => $row['c80_modified'],
                    'pwd_change' => $row['c80_pwd_chng'],
                    'deleted' => $row['c80_deleted'] !== '1899-12-31 00:00:00'
                ];
            }

            return $users;

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in getAllUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user by ID
     * @param int $userId User ID
     * @return array|null
     */
    public static function getUserById(int $userId): ?array {
        try {
            General::addRowLog("[UserManager] Getting user by ID: " . $userId);

            $user = new User();
            if (!$user->load($userId) || $user->getId() == 0) {
                return null;
            }

            return [
                'id' => $user->getId(),
                'username' => $user->getUserId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'displayName' => $user->getDisplayName(),
                'hasPassword' => $user->hasPassword(),
                'mustChangePassword' => $user->mustChangePassword(),
                'changePassDate' => $user->getChangePassDt()
            ];

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in getUserById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new user
     * @param array $userData User data
     * @return array ['success' => bool, 'message' => string, 'userId' => int|null]
     */
    public static function createUser(array $userData): array {
        try {
            General::addRowLog("[UserManager] Creating new user: " . ($userData['username'] ?? 'unknown'));

            // Validate required fields
            $required = ['username', 'email'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required",
                        'userId' => null
                    ];
                }
            }

            // Check if username exists
            if (User::existUsername($userData['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username already exists',
                    'userId' => null
                ];
            }

            // Check if email exists
            if (User::existEmail($userData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'userId' => null
                ];
            }

            // Validate email
            if (!General::isEmailAddress($userData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address',
                    'userId' => null
                ];
            }

            $user = new User();

            // Generate temporary password if not provided
            $password = $userData['password'] ?? PasswordManager::generateTemporaryPassword();

            // Register new user
            $user->registerNew(
                $userData['username'],
                $password,
                $userData['email'],
                $userData['name'] ?? '',
                $userData['surname'] ?? ''
            );

            if ($user->getId() > 0) {
                General::addRowLog("[UserManager] User created successfully: " . $user->getId());

                $result = [
                    'success' => true,
                    'message' => 'User created successfully',
                    'userId' => $user->getId()
                ];

                // Include temporary password if it was auto-generated
                if (!isset($userData['password'])) {
                    $result['temporaryPassword'] = $password;
                }

                return $result;
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create user',
                    'userId' => null
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in createUser: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error creating user: ' . $e->getMessage(),
                'userId' => null
            ];
        }
    }

    /**
     * Update user data
     * @param int $userId User ID
     * @param array $userData User data to update
     * @return array ['success' => bool, 'message' => string]
     */
    public static function updateUser(int $userId, array $userData): array {
        try {
            General::addRowLog("[UserManager] Updating user: " . $userId);

            $userBean = new UserBean(General::$DEBUG);
            $userBean->select($userId);

            if ($userBean->getc80_id() == 0) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Update fields if provided
            if (isset($userData['username'])) {
                // Check if new username already exists for another user
                if (User::existUsername($userData['username'])) {
                    $tempUser = new User();
                    $tempUser->load($userData['username']);
                    if ($tempUser->getId() != $userId) {
                        return [
                            'success' => false,
                            'message' => 'Username already exists'
                        ];
                    }
                }
                $userBean->setc80_username($userData['username']);
            }

            if (isset($userData['email'])) {
                // Validate email
                if (!General::isEmailAddress($userData['email'])) {
                    return [
                        'success' => false,
                        'message' => 'Invalid email address'
                    ];
                }

                // Check if new email already exists for another user
                if (User::existEmail($userData['email'])) {
                    $emailCheck = (new User())->emailExist($userData['email']);
                    if ($emailCheck[0] && $emailCheck[1] != $userId) {
                        return [
                            'success' => false,
                            'message' => 'Email already exists'
                        ];
                    }
                }
                $userBean->setc80_email($userData['email']);
            }

            if (isset($userData['name'])) {
                $userBean->setc80_name($userData['name']);
            }

            if (isset($userData['surname'])) {
                $userBean->setc80_surname($userData['surname']);
            }

            if (isset($userData['role'])) {
                $userBean->setc80_role($userData['role']);
            }

            $result = $userBean->update();

            if ($result) {
                General::addRowLog("[UserManager] User updated successfully: " . $userId);
                return [
                    'success' => true,
                    'message' => 'User updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update user'
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in updateUser: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete user (soft delete)
     * @param int $userId User ID
     * @param int $deletedBy User ID of who is deleting
     * @return array ['success' => bool, 'message' => string]
     */
    public static function deleteUser(int $userId, int $deletedBy = 0): array {
        try {
            General::addRowLog("[UserManager] Deleting user: " . $userId);

            $userBean = new UserBean(General::$DEBUG);
            $userBean->select($userId);

            if ($userBean->getc80_id() == 0) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Soft delete - set deleted date
            $userBean->setc80_deleted(date('Y-m-d H:i:s'));
            $userBean->setc80_deleted_by($deletedBy);

            $result = $userBean->update();

            if ($result) {
                General::addRowLog("[UserManager] User deleted successfully: " . $userId);
                return [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete user'
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in deleteUser: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error deleting user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore deleted user
     * @param int $userId User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function restoreUser(int $userId): array {
        try {
            General::addRowLog("[UserManager] Restoring user: " . $userId);

            $userBean = new UserBean(General::$DEBUG);
            $userBean->select($userId);

            if ($userBean->getc80_id() == 0) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Restore - clear deleted date
            $userBean->setc80_deleted('1899-12-31 00:00:00');
            $userBean->setc80_deleted_by(0);

            $result = $userBean->update();

            if ($result) {
                General::addRowLog("[UserManager] User restored successfully: " . $userId);
                return [
                    'success' => true,
                    'message' => 'User restored successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to restore user'
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in restoreUser: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error restoring user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Permanently delete user
     * @param int $userId User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function permanentlyDeleteUser(int $userId): array {
        try {
            General::addRowLog("[UserManager] Permanently deleting user: " . $userId);

            $userBean = new UserBean(General::$DEBUG);
            $result = $userBean->delete($userId);

            if ($result) {
                General::addRowLog("[UserManager] User permanently deleted: " . $userId);
                return [
                    'success' => true,
                    'message' => 'User permanently deleted'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to permanently delete user'
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[UserManager] Exception in permanentlyDeleteUser: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error permanently deleting user: ' . $e->getMessage()
            ];
        }
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
