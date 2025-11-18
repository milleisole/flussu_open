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
 * USERS API - API endpoints for user management
 * --------------------------------------------------------------------*/

namespace Flussu\Api\V40;

use Flussu\Flussuserver\Request;
use Flussu\General;
use Flussu\Persons\User;
use Flussu\Users\UserManager;
use Flussu\Users\PasswordManager;

class UsersApi {

    /**
     * Execute user management API commands
     * @param Request $Req The request object
     * @param User $theUser The authenticated user
     * @return void
     */
    public function exec(Request $Req, User $theUser) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');

        // Get command and data
        $action = General::getGetOrPost("action");
        $rcvData = file_get_contents('php://input');
        $data = json_decode($rcvData, true);

        General::addRowLog("[UsersApi] Action: " . $action);

        // Check if user is logged in
        if ($theUser->getId() == 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Please login first'
            ]);
            return;
        }

        try {
            switch ($action) {
                case 'list':
                    $this->listUsers($theUser);
                    break;

                case 'get':
                    $this->getUser($theUser, $data);
                    break;

                case 'create':
                    $this->createUser($theUser, $data);
                    break;

                case 'update':
                    $this->updateUser($theUser, $data);
                    break;

                case 'delete':
                    $this->deleteUser($theUser, $data);
                    break;

                case 'restore':
                    $this->restoreUser($theUser, $data);
                    break;

                case 'changePassword':
                    $this->changePassword($theUser, $data);
                    break;

                case 'resetPassword':
                    $this->resetPassword($theUser, $data);
                    break;

                case 'validatePassword':
                    $this->validatePassword($data);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Unknown action: ' . $action
                    ]);
                    break;
            }

        } catch (\Exception $e) {
            General::addRowLog("[UsersApi] Exception: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List all users
     */
    private function listUsers(User $theUser) {
        $includeDeleted = isset($_GET['includeDeleted']) && $_GET['includeDeleted'] === 'true';
        $users = UserManager::getAllUsers($includeDeleted);

        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get user by ID
     */
    private function getUser(User $theUser, array $data) {
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId is required'
            ]);
            return;
        }

        $user = UserManager::getUserById((int)$data['userId']);

        if ($user === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Create new user
     */
    private function createUser(User $theUser, array $data) {
        $result = UserManager::createUser($data);

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Update user
     */
    private function updateUser(User $theUser, array $data) {
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId is required'
            ]);
            return;
        }

        $userId = (int)$data['userId'];
        unset($data['userId']);

        $result = UserManager::updateUser($userId, $data);

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Delete user
     */
    private function deleteUser(User $theUser, array $data) {
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId is required'
            ]);
            return;
        }

        $result = UserManager::deleteUser((int)$data['userId'], $theUser->getId());

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Restore deleted user
     */
    private function restoreUser(User $theUser, array $data) {
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId is required'
            ]);
            return;
        }

        $result = UserManager::restoreUser((int)$data['userId']);

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Change user password
     */
    private function changePassword(User $theUser, array $data) {
        if (!isset($data['userId']) || !isset($data['newPassword'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId and newPassword are required'
            ]);
            return;
        }

        // If current password is provided, verify it
        if (isset($data['currentPassword'])) {
            $result = PasswordManager::changePasswordWithVerification(
                $data['userId'],
                $data['currentPassword'],
                $data['newPassword']
            );
        } else {
            // Admin changing password
            $temporary = isset($data['temporary']) ? (bool)$data['temporary'] : false;
            $result = PasswordManager::changePassword(
                $data['userId'],
                $data['newPassword'],
                $temporary
            );
        }

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Reset password to temporary
     */
    private function resetPassword(User $theUser, array $data) {
        if (!isset($data['userId'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'userId is required'
            ]);
            return;
        }

        $result = PasswordManager::resetPasswordToTemporary($data['userId']);

        if (!$result['success']) {
            http_response_code(400);
        }

        echo json_encode($result);
    }

    /**
     * Validate password strength
     */
    private function validatePassword(array $data) {
        if (!isset($data['password'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'password is required'
            ]);
            return;
        }

        $validation = PasswordManager::validatePasswordStrength($data['password']);

        echo json_encode([
            'success' => true,
            'data' => $validation
        ]);
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
