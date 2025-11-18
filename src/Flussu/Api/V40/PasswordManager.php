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
 * CLASS-NAME:       Password Manager API
 * CREATED DATE:     2025-11-16
 * VERSION REL.:     4.5.1
 * UPDATES DATE:     16.11.2025
 * -------------------------------------------------------*/
/**
 * PasswordManager.php
 *
 * This class manages password operations including:
 * - Forced password change (when user must change password)
 * - Password reset for forgotten passwords
 * - Password expiry checks
 *
 * The password reset flow uses the existing t50_otcmd table to store temporary tokens.
 * Each token is valid for a limited time (default: 1 hour) and can be used only once.
 *
 * @package Flussu\Api\V40
 * @version 4.5.1
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

namespace Flussu\Api\V40;

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Flussuserver\NC\HandlerNC;

class PasswordManager {

    const TOKEN_VALIDITY_MINUTES = 60; // Token validity: 1 hour
    const COMMAND_RESET_PASSWORD = "RESET_PASSWORD";
    const COMMAND_VERIFY_TOKEN = "VERIFY_RESET_TOKEN";

    /**
     * Requests a password reset for a user identified by email
     *
     * This method generates a reset token, stores it in the database,
     * and returns it (to be sent via email by the calling code).
     *
     * @param HandlerNC $db Database handler
     * @param string $emailOrUsername User email address or username
     *
     * @return array Response with result and token/message
     */
    public function requestPasswordReset($db, $emailOrUsername) {
        General::addRowLog("[Password Reset Request] for: " . $emailOrUsername);

        $usr = new User();
        $userId = null;
        $userEmail = null;

        // Check if it's an email address
        if (General::isEmailAddress($emailOrUsername)) {
            $emailCheck = $usr->emailExist($emailOrUsername);
            if ($emailCheck[0]) {
                $userId = $emailCheck[1];
                $userEmail = $emailOrUsername;
                $usr->load($userId);
            }
        } else {
            // Try to load by username
            if ($usr->load($emailOrUsername)) {
                $userId = $usr->getId();
                $userEmail = $usr->getEmail();
            }
        }

        if ($userId === null || $userId <= 0) {
            General::addRowLog("[Password Reset] User not found");
            // For security reasons, don't reveal if user exists or not
            return array(
                "result" => "OK",
                "message" => "If the email exists, a reset link will be sent"
            );
        }

        // Generate reset token
        $resetToken = General::getUuidv4();

        // Store token in t50_otcmd table with expiry time
        $SQL = "INSERT INTO t50_otcmd (c50_key, c50_command, c50_uid, c50_created) VALUES (?, ?, ?, NOW())";
        $tokenId = $db->execSqlGetId($SQL, array($resetToken, self::COMMAND_RESET_PASSWORD, $userId));

        if ($tokenId > 0) {
            General::addRowLog("[Password Reset] Token generated for user ID: " . $userId);

            // Here you would normally send an email with the reset link
            // For now, we return the token (in production, this should be sent via email)
            $resetLink = $this->generateResetLink($resetToken);

            // TODO: Send email with reset link
            // $this->sendResetEmail($userEmail, $resetLink);

            return array(
                "result" => "OK",
                "message" => "Password reset token generated",
                "token" => $resetToken,  // In production, don't return token in response
                "resetLink" => $resetLink, // In production, only send via email
                "email" => $userEmail,
                "expiresInMinutes" => self::TOKEN_VALIDITY_MINUTES
            );
        }

        return array(
            "result" => "ERROR",
            "message" => "Failed to generate reset token"
        );
    }

    /**
     * Verifies if a reset token is valid
     *
     * @param HandlerNC $db Database handler
     * @param string $token Reset token
     *
     * @return array Response with validity status and user info
     */
    public function verifyResetToken($db, $token) {
        General::addRowLog("[Verify Reset Token] " . $token);

        $SQL = "SELECT c50_id, c50_uid, c50_created, c50_command
                FROM t50_otcmd
                WHERE c50_key = ?
                AND c50_command = ?
                ORDER BY c50_created DESC
                LIMIT 1";

        $db->execSql($SQL, array($token, self::COMMAND_RESET_PASSWORD));
        $data = $db->getData();

        if (empty($data)) {
            return array(
                "result" => "ERROR",
                "message" => "Invalid or expired token"
            );
        }

        $tokenData = $data[0];
        $createdTime = strtotime($tokenData['c50_created']);
        $expiryTime = $createdTime + (self::TOKEN_VALIDITY_MINUTES * 60);
        $currentTime = time();

        if ($currentTime > $expiryTime) {
            // Delete expired token
            $db->execSql("DELETE FROM t50_otcmd WHERE c50_id = ?", array($tokenData['c50_id']));

            return array(
                "result" => "ERROR",
                "message" => "Token has expired"
            );
        }

        $usr = new User();
        $usr->load($tokenData['c50_uid']);

        return array(
            "result" => "OK",
            "message" => "Token is valid",
            "userId" => $usr->getUserId(),
            "email" => $usr->getEmail(),
            "expiresIn" => ($expiryTime - $currentTime) . " seconds"
        );
    }

    /**
     * Resets password using a valid token
     *
     * @param HandlerNC $db Database handler
     * @param string $token Reset token
     * @param string $newPassword New password
     *
     * @return array Response with result
     */
    public function resetPasswordWithToken($db, $token, $newPassword) {
        General::addRowLog("[Reset Password with Token]");

        // First verify the token is valid
        $verification = $this->verifyResetToken($db, $token);

        if ($verification['result'] !== "OK") {
            return $verification;
        }

        // Get user ID from token
        $SQL = "SELECT c50_id, c50_uid FROM t50_otcmd WHERE c50_key = ? AND c50_command = ?";
        $db->execSql($SQL, array($token, self::COMMAND_RESET_PASSWORD));
        $data = $db->getData();

        if (empty($data)) {
            return array(
                "result" => "ERROR",
                "message" => "Invalid token"
            );
        }

        $tokenData = $data[0];
        $userId = $tokenData['c50_uid'];

        // Load user and set new password
        $usr = new User();
        $usr->load($userId);

        if ($usr->getId() <= 0) {
            return array(
                "result" => "ERROR",
                "message" => "User not found"
            );
        }

        // Set new password (not temporary, so expiry is +1 year)
        $passwordSet = $usr->setPassword($newPassword, false);

        if ($passwordSet) {
            // Delete used token
            $db->execSql("DELETE FROM t50_otcmd WHERE c50_id = ?", array($tokenData['c50_id']));

            General::addRowLog("[Reset Password] Success for user ID: " . $userId);

            return array(
                "result" => "OK",
                "message" => "Password has been reset successfully"
            );
        }

        return array(
            "result" => "ERROR",
            "message" => "Failed to set new password"
        );
    }

    /**
     * Forces a password change for authenticated user
     *
     * This is used when a user must change their password (expired or temporary password)
     *
     * @param string $userId User ID or username
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     *
     * @return array Response with result
     */
    public function forcePasswordChange($userId, $currentPassword, $newPassword) {
        General::addRowLog("[Force Password Change] for user: " . $userId);

        $usr = new User();

        // Authenticate user with current password
        if (!$usr->authenticate($userId, $currentPassword)) {
            return array(
                "result" => "ERROR",
                "message" => "Current password is incorrect"
            );
        }

        // Check if password must be changed
        if (!$usr->mustChangePassword() && $usr->hasPassword()) {
            return array(
                "result" => "ERROR",
                "message" => "Password change is not required"
            );
        }

        // Set new password (not temporary)
        $passwordSet = $usr->setPassword($newPassword, false);

        if ($passwordSet) {
            General::addRowLog("[Force Password Change] Success for user: " . $userId);

            return array(
                "result" => "OK",
                "message" => "Password has been changed successfully",
                "userId" => $usr->getUserId()
            );
        }

        return array(
            "result" => "ERROR",
            "message" => "Failed to change password"
        );
    }

    /**
     * Checks if a user must change their password
     *
     * @param string $userId User ID or username
     *
     * @return array Response with password status
     */
    public function checkPasswordStatus($userId) {
        General::addRowLog("[Check Password Status] for user: " . $userId);

        $usr = new User();

        if (!$usr->load($userId)) {
            return array(
                "result" => "ERROR",
                "message" => "User not found"
            );
        }

        $mustChange = $usr->mustChangePassword();
        $hasPassword = $usr->hasPassword();
        $changeDate = $usr->getChangePassDt();

        return array(
            "result" => "OK",
            "userId" => $usr->getUserId(),
            "email" => $usr->getEmail(),
            "mustChangePassword" => $mustChange,
            "hasPassword" => $hasPassword,
            "passwordChangeDate" => $changeDate,
            "message" => $mustChange ? "Password must be changed" : "Password is valid"
        );
    }

    /**
     * Generates a password reset link
     *
     * @param string $token Reset token
     *
     * @return string Reset link URL
     */
    private function generateResetLink($token) {
        // Get base URL from environment or config
        $baseUrl = isset($_SERVER['HTTP_HOST']) ?
                   ($_SERVER['HTTPS'] ?? 'off' === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] :
                   'http://localhost';

        // Generate reset link (adjust path according to your frontend route)
        return $baseUrl . "/reset-password?token=" . urlencode($token);
    }

    /**
     * Sends password reset email (placeholder for future implementation)
     *
     * @param string $email User email
     * @param string $resetLink Reset link
     *
     * @return bool Success status
     */
    private function sendResetEmail($email, $resetLink) {
        // TODO: Implement email sending
        // This could use PHPMailer, SwiftMailer, or a service like SendGrid

        General::addRowLog("[Send Reset Email] to: " . $email);
        General::addRowLog("[Reset Link] " . $resetLink);

        /* Example with PHPMailer:
        $mail = new PHPMailer(true);
        try {
            $mail->setFrom('noreply@flussu.com', 'Flussu');
            $mail->addAddress($email);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "Click this link to reset your password: " . $resetLink;
            $mail->send();
            return true;
        } catch (Exception $e) {
            General::addRowLog("[Email Error] " . $mail->ErrorInfo);
            return false;
        }
        */

        return true; // Placeholder
    }

    /**
     * Cleans up expired reset tokens
     *
     * This method should be called periodically (e.g., via cron job)
     * to remove expired password reset tokens from the database
     *
     * @param HandlerNC $db Database handler
     *
     * @return array Number of deleted tokens
     */
    public function cleanupExpiredTokens($db) {
        $expiryThreshold = date('Y-m-d H:i:s', time() - (self::TOKEN_VALIDITY_MINUTES * 60));

        $SQL = "DELETE FROM t50_otcmd
                WHERE c50_command = ?
                AND c50_created < ?";

        $result = $db->execSql($SQL, array(self::COMMAND_RESET_PASSWORD, $expiryThreshold));

        General::addRowLog("[Cleanup] Removed expired password reset tokens");

        return array(
            "result" => "OK",
            "message" => "Expired tokens cleaned up"
        );
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
