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
 * CLASS-NAME:       Authentication Manager
 * CREATE DATE:      17.11.2025
 * VERSION REL.:     4.5.20251117
 * UPDATES DATE:     17.11:2025
 * --------------------------------------------------------------------
 * Manages user authentication using PHP sessions
 * --------------------------------------------------------------------*/

namespace Flussu\Persons;

use Flussu\General;

/**
 * AuthManager class handles user authentication and session management
 *
 * This class provides a centralized way to manage user authentication
 * by storing User objects in PHP sessions. It handles login, logout,
 * and checking authentication status.
 */
class AuthManager
{
    /**
     * Session key for storing authenticated user data
     */
    private const SESSION_USER_KEY = 'flussu_authenticated_user';

    /**
     * Session key for storing user ID
     */
    private const SESSION_USER_ID_KEY = 'flussu_user_id';

    /**
     * Session key for storing username
     */
    private const SESSION_USERNAME_KEY = 'flussu_username';

    /**
     * Session key for storing authentication timestamp
     */
    private const SESSION_AUTH_TIME_KEY = 'flussu_auth_time';

    /**
     * Initialize session if not already started
     *
     * @return void
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Authenticate user and store in session
     *
     * @param string $userId Username or email
     * @param string $password User password
     * @return bool True if authentication successful, false otherwise
     */
    public static function login(string $userId, string $password): bool
    {
        self::ensureSessionStarted();

        // Create new User instance and try to authenticate
        $user = new User();
        $authenticated = $user->authenticate($userId, $password);

        if ($authenticated) {
            // Store user information in session
            self::storeUserInSession($user);
            General::addRowLog("[AuthManager] User authenticated: " . $user->getUserId());
            return true;
        }

        General::addRowLog("[AuthManager] Authentication failed for: " . $userId);
        return false;
    }

    /**
     * Authenticate user with token and store in session
     *
     * @param string $userId User ID
     * @param string $token Authentication token
     * @return bool True if authentication successful, false otherwise
     */
    public static function loginWithToken(string $userId, string $token): bool
    {
        self::ensureSessionStarted();

        // Create new User instance and try to authenticate with token
        $user = new User();
        $user->load($userId);

        if ($user->getId() > 0) {
            $authenticated = $user->authenticateToken($userId, $token);

            if ($authenticated) {
                // Store user information in session
                self::storeUserInSession($user);
                General::addRowLog("[AuthManager] User authenticated with token: " . $user->getUserId());
                return true;
            }
        }

        General::addRowLog("[AuthManager] Token authentication failed for: " . $userId);
        return false;
    }

    /**
     * Store user data in session
     *
     * @param User $user User object to store
     * @return void
     */
    private static function storeUserInSession(User $user): void
    {
        $_SESSION[self::SESSION_USER_ID_KEY] = $user->getId();
        $_SESSION[self::SESSION_USERNAME_KEY] = $user->getUserId();
        $_SESSION[self::SESSION_AUTH_TIME_KEY] = time();

        // Store serialized user data
        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => $user->getId(),
            'username' => $user->getUserId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'surname' => $user->getSurname()
        ];
    }

    /**
     * Logout current user and clear session
     *
     * @return void
     */
    public static function logout(): void
    {
        self::ensureSessionStarted();

        // Log the logout action
        if (self::isAuthenticated()) {
            $userId = $_SESSION[self::SESSION_USERNAME_KEY] ?? 'unknown';
            General::addRowLog("[AuthManager] User logged out: " . $userId);
        }

        // Clear authentication data from session
        unset($_SESSION[self::SESSION_USER_KEY]);
        unset($_SESSION[self::SESSION_USER_ID_KEY]);
        unset($_SESSION[self::SESSION_USERNAME_KEY]);
        unset($_SESSION[self::SESSION_AUTH_TIME_KEY]);
    }

    /**
     * Check if user is authenticated
     *
     * @return bool True if user is authenticated, false otherwise
     */
    public static function isAuthenticated(): bool
    {
        self::ensureSessionStarted();

        return isset($_SESSION[self::SESSION_USER_ID_KEY])
            && $_SESSION[self::SESSION_USER_ID_KEY] > 0
            && isset($_SESSION[self::SESSION_USER_KEY]);
    }

    /**
     * Get current authenticated user
     *
     * @return User|null User object if authenticated, null otherwise
     */
    public static function getUser(): ?User
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        // Reload user from database to ensure fresh data
        $userId = $_SESSION[self::SESSION_USER_ID_KEY];
        $user = new User();

        if ($user->load($userId)) {
            return $user;
        }

        // If user cannot be loaded, clear session
        self::logout();
        return null;
    }

    /**
     * Get user data from session without reloading from database
     *
     * @return array|null User data array if authenticated, null otherwise
     */
    public static function getUserData(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    /**
     * Get current user ID
     *
     * @return int User ID if authenticated, 0 otherwise
     */
    public static function getUserId(): int
    {
        self::ensureSessionStarted();
        return $_SESSION[self::SESSION_USER_ID_KEY] ?? 0;
    }

    /**
     * Get current username
     *
     * @return string Username if authenticated, empty string otherwise
     */
    public static function getUsername(): string
    {
        self::ensureSessionStarted();
        return $_SESSION[self::SESSION_USERNAME_KEY] ?? '';
    }

    /**
     * Check if user has been authenticated for longer than specified seconds
     *
     * @param int $seconds Number of seconds
     * @return bool True if session is older than specified seconds
     */
    public static function isSessionOlderThan(int $seconds): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }

        $authTime = $_SESSION[self::SESSION_AUTH_TIME_KEY] ?? 0;
        return (time() - $authTime) > $seconds;
    }

    /**
     * Refresh authentication timestamp
     * Useful for "remember me" functionality
     *
     * @return void
     */
    public static function refreshAuthTime(): void
    {
        if (self::isAuthenticated()) {
            $_SESSION[self::SESSION_AUTH_TIME_KEY] = time();
        }
    }

    /**
     * Require authentication or die with error
     *
     * @param string $errorMessage Custom error message
     * @return void Dies if not authenticated
     */
    public static function requireAuth(string $errorMessage = "Authentication required"): void
    {
        if (!self::isAuthenticated()) {
            header('HTTP/1.0 401 Unauthorized');
            die(json_encode([
                "error" => "401",
                "message" => $errorMessage
            ]));
        }
    }

    /**
     * Check if user has specific role/permission
     * This is a placeholder for future implementation
     *
     * @param int $ruleLevel Required rule level
     * @return bool True if user has required level
     */
    public static function checkRuleLevel(int $ruleLevel): bool
    {
        $user = self::getUser();
        if ($user === null) {
            return false;
        }

        return $user->checkRuleLelev($ruleLevel);
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
