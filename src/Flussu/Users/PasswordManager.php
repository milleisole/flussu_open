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
 * PASSWORD MANAGER - Handles password operations
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\General;
use Flussu\Persons\User;

class PasswordManager {

    /**
     * Change password for a user
     * @param string|int $userId User ID or username
     * @param string $newPassword New password
     * @param bool $temporary If true, password will be marked as temporary (needs change)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function changePassword($userId, string $newPassword, bool $temporary = false): array {
        try {
            General::addRowLog("[PasswordManager] Changing password for user: " . $userId);

            // Validate password strength
            $validation = self::validatePasswordStrength($newPassword);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Load user
            $user = new User();
            $loaded = $user->load($userId);

            if (!$loaded || $user->getId() == 0) {
                General::addRowLog("[PasswordManager] User not found: " . $userId);
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Set new password
            $result = $user->setPassword($newPassword, $temporary);

            if ($result) {
                General::addRowLog("[PasswordManager] Password changed successfully for user: " . $userId);
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            } else {
                General::addRowLog("[PasswordManager] Failed to change password for user: " . $userId);
                return [
                    'success' => false,
                    'message' => 'Failed to update password in database'
                ];
            }

        } catch (\Exception $e) {
            General::addRowLog("[PasswordManager] Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error changing password: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate password strength
     * @param string $password Password to validate
     * @return array ['valid' => bool, 'message' => string, 'strength' => string]
     */
    public static function validatePasswordStrength(string $password): array {
        $minLength = 8;
        $errors = [];
        $strength = 'weak';

        // Check minimum length
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        // Check for uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        // Check for lowercase
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        // Check for numbers
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        // Check for special characters
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        // Calculate strength
        if (empty($errors)) {
            if (strlen($password) >= 12) {
                $strength = 'strong';
            } else {
                $strength = 'medium';
            }
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'Password is valid' : implode('. ', $errors),
            'strength' => $strength
        ];
    }

    /**
     * Check if user must change password
     * @param string|int $userId User ID or username
     * @return bool
     */
    public static function mustChangePassword($userId): bool {
        try {
            $user = new User();
            $loaded = $user->load($userId);

            if (!$loaded || $user->getId() == 0) {
                return false;
            }

            return $user->mustChangePassword();

        } catch (\Exception $e) {
            General::addRowLog("[PasswordManager] Exception in mustChangePassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify current password for a user
     * @param string|int $userId User ID or username
     * @param string $currentPassword Current password to verify
     * @return bool
     */
    public static function verifyCurrentPassword($userId, string $currentPassword): bool {
        try {
            $user = new User();
            return $user->authenticate($userId, $currentPassword);

        } catch (\Exception $e) {
            General::addRowLog("[PasswordManager] Exception in verifyCurrentPassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change password with current password verification
     * @param string|int $userId User ID or username
     * @param string $currentPassword Current password for verification
     * @param string $newPassword New password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function changePasswordWithVerification($userId, string $currentPassword, string $newPassword): array {
        // First verify current password
        if (!self::verifyCurrentPassword($userId, $currentPassword)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }

        // Then change to new password
        return self::changePassword($userId, $newPassword, false);
    }

    /**
     * Generate a random temporary password
     * @param int $length Password length (default 12)
     * @return string
     */
    public static function generateTemporaryPassword(int $length = 12): string {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $password = '';

        // Ensure at least one of each type
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest randomly
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Reset password to temporary password
     * @param string|int $userId User ID or username
     * @return array ['success' => bool, 'message' => string, 'temporaryPassword' => string|null]
     */
    public static function resetPasswordToTemporary($userId): array {
        try {
            $tempPassword = self::generateTemporaryPassword();
            $result = self::changePassword($userId, $tempPassword, true);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Password reset successfully',
                    'temporaryPassword' => $tempPassword
                ];
            } else {
                return $result;
            }

        } catch (\Exception $e) {
            General::addRowLog("[PasswordManager] Exception in resetPasswordToTemporary: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error resetting password: ' . $e->getMessage()
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
