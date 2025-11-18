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
 * CLASS-NAME:       Password Recovery Helper
 * CREATE DATE:      2025-11-18
 * VERSION REL.:     4.5.20251118
 * --------------------------------------------------------------------*/
namespace Flussu\Persons;

use Flussu\General;
use Flussu\Beans\PasswordRecovery as PasswordRecoveryBean;
use Flussu\Persons\User;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * PasswordRecoveryHelper
 *
 * Handles secure password recovery functionality including:
 * - Token generation and validation
 * - Email sending for recovery requests
 * - Security measures against brute force attacks
 */
class PasswordRecoveryHelper
{
    /**
     * Generate a secure random token for password recovery
     * @return string Raw token (before hashing)
     */
    public static function generateToken() {
        // Generate 32 random bytes and convert to hex (64 characters)
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash a token for secure storage
     * @param string $token Raw token
     * @return string Hashed token
     */
    public static function hashToken($token) {
        return hash('sha256', $token);
    }

    /**
     * Create a password recovery request
     * @param string $userIdentifier Username or email
     * @return array ['success' => bool, 'message' => string, 'token' => string|null]
     */
    public static function requestPasswordRecovery($user) {
        try {
            // Load user by username or email
            //$user = new User();
            //$loaded = $user->load($userIdentifier);

            if (!is_null($user) && !empty($user->getEmail())) {
                // Try loading by email

                // Generate token
                $rawToken = self::generateToken();
                $hashedToken = self::hashToken($rawToken);

                // Clean up old/expired tokens for this user
                $recoveryBean = new PasswordRecoveryBean(General::$DEBUG);
                $recoveryBean->cleanupExpiredTokens($user->getId());

                // Create recovery record
                $recoveryBean->setc81_user_id($user->getId());
                $recoveryBean->setc81_token($hashedToken);
                $recoveryBean->setc81_expires(date('Y-m-d H:i:s', strtotime('+1 hour')));
                $recoveryBean->setc81_ip_address($_SERVER['REMOTE_ADDR'] ?? '');
                $recoveryBean->setc81_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '');

                if (!$recoveryBean->insert()) {
                    General::log("Failed to insert password recovery token for user " . $user->getId());
                    return [
                        'success' => false,
                        'message' => 'Errore nella generazione del token di recupero.',
                        'token' => null
                    ];
                }

                // Send email
                $emailSent = self::sendRecoveryEmail($user, $rawToken);

                if (!$emailSent['success']) {
                    General::log("Failed to send recovery email to user " . $user->getId());
                    return [
                        'success' => false,
                        'message' => 'Errore nell\'invio dell\'email di recupero.',
                        'token' => null
                    ];
                }

                General::log("Password recovery token generated for user " . $user->getId());
                return [
                    'success' => true,
                    'message' => 'Email di recupero inviata con successo.',
                    'token' => $rawToken // Return for testing purposes only
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Utente o email non esistenti.',
                    'token' => null
                ];
            }

        } catch (\Exception $e) {
            General::log("Password recovery exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante la richiesta di recupero password.',
                'token' => null
            ];
        }
    }

    /**
     * Validate a recovery token
     * @param string $token Raw token
     * @return array ['valid' => bool, 'user_id' => int|null, 'message' => string]
     */
    public static function validateToken($token) {
        try {
            $hashedToken = self::hashToken($token);

            $recoveryBean = new PasswordRecoveryBean(General::$DEBUG);
            $found = $recoveryBean->selectByToken($hashedToken);

            if (!$found) {
                return [
                    'valid' => false,
                    'user_id' => null,
                    'message' => 'Token non valido o scaduto.'
                ];
            }

            return [
                'valid' => true,
                'user_id' => $recoveryBean->getc81_user_id(),
                'message' => 'Token valido.',
                'recovery_id' => $recoveryBean->getc81_id()
            ];

        } catch (\Exception $e) {
            General::log("Token validation exception: " . $e->getMessage());
            return [
                'valid' => false,
                'user_id' => null,
                'message' => 'Errore nella validazione del token.'
            ];
        }
    }

    /**
     * Reset password using valid token
     * @param string $token Raw token
     * @param string $newPassword New password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function resetPassword($token, $newPassword) {
        try {
            // Validate token
            $validation = self::validateToken($token);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Load user
            $user = new User();
            if (!$user->load($validation['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'Utente non trovato.'
                ];
            }

            // Validate password strength
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'La password deve contenere almeno 8 caratteri.'
                ];
            }

            // Set new password (non-temporary)
            if (!$user->setPassword($newPassword, false)) {
                return [
                    'success' => false,
                    'message' => 'Errore nell\'aggiornamento della password.'
                ];
            }

            // Mark token as used
            $recoveryBean = new PasswordRecoveryBean(General::$DEBUG);
            $recoveryBean->selectByToken(self::hashToken($token));
            $recoveryBean->markAsUsed();

            General::log("Password successfully reset for user " . $user->getId());

            return [
                'success' => true,
                'message' => 'Password aggiornata con successo.'
            ];

        } catch (\Exception $e) {
            General::log("Password reset exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante il reset della password.'
            ];
        }
    }

    /**
     * Send recovery email to user
     * @param User $user User object
     * @param string $token Raw recovery token
     * @return array ['success' => bool, 'message' => string]
     */
    private static function sendRecoveryEmail($user, $token) {
        try {
            // Get email configuration
            $provider = config("services.email.default");
            $providerPath = "services.email." . $provider;

            $email_server = config($providerPath . ".smtp_host");
            $email_port = config($providerPath . ".smtp_port");
            $email_auth = config($providerPath . ".smtp_auth", 0) != 0;
            $email_user = config($providerPath . ".smtp_user");
            $email_passwd = config($providerPath . ".smtp_pass");
            $email_encrypt = config($providerPath . ".smtp_encrypt");

            if (General::isCurtatoned($email_passwd)) {
                $email_passwd = General::montanara($email_passwd, 999);
            }

            $mail = new PHPMailer(true);

            // Server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $email_server;
            $mail->SMTPAuth = $email_auth;
            $mail->Username = $email_user;
            $mail->Password = $email_passwd;

            if ($email_encrypt == "STARTTLS") {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($email_encrypt == "SMTPS") {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->Port = $email_port;
            $mail->CharSet = "UTF-8";

            // Recipients
            $fromEmail = $email_user;
            $fromName = "Flussu Server";
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($user->getEmail(), $user->getDisplayName());

            // Build recovery URL
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $recoveryUrl = $baseUrl . "/flussu/reset-password.php?token=" . urlencode($token);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Recupero Password - Flussu Server';

            $htmlBody = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 30px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .warning { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Recupero Password</h1>
        </div>
        <div class='content'>
            <p>Gentile <strong>" . htmlspecialchars($user->getDisplayName()) . "</strong>,</p>
            <p>Abbiamo ricevuto una richiesta per reimpostare la password del tuo account.</p>
            <p>Se hai richiesto tu il recupero della password, clicca sul pulsante qui sotto:</p>
            <p style='text-align: center;'>
                <a style='text-decoration:none;color:#fff' href='" . htmlspecialchars($recoveryUrl) . "' class='button'><strong>Reimposta Password</strong></a>
            </p>
            <p>In alternativa, copia e incolla questo link nel tuo browser:</p>
            <p style='word-break: break-all; background-color: #fff; padding: 10px; border: 1px solid #ddd;'>
                " . htmlspecialchars($recoveryUrl) . "
            </p>
            <p class='warning'>Questo link è valido per 1 ora.</p>
            <p>Se non hai richiesto il recupero della password, ignora questa email. La tua password rimarrà invariata.</p>
            <p><strong>Per motivi di sicurezza:</strong></p>
            <ul>
                <li>Non condividere mai questo link con nessuno</li>
                <li>Assicurati di essere su una connessione sicura prima di reimpostare la password</li>
                <li>Scegli una password forte e unica</li>
            </ul>
        </div>
        <div class='footer'>
            <p>Questa è un'email automatica, si prega di non rispondere.</p>
            <p>&copy; " . date('Y') . " Flussu Server - Mille Isole SRL</p>
        </div>
    </div>
</body>
</html>";

            $textBody = "
Recupero Password - Flussu Server

Gentile " . $user->getDisplayName() . ",

Abbiamo ricevuto una richiesta per reimpostare la password del tuo account.

Se hai richiesto tu il recupero della password, copia e incolla questo link nel tuo browser:
$recoveryUrl

ATTENZIONE: Questo link è valido per 1 ora.

Se non hai richiesto il recupero della password, ignora questa email. La tua password rimarrà invariata.

Per motivi di sicurezza:
- Non condividere mai questo link con nessuno
- Assicurati di essere su una connessione sicura prima di reimpostare la password
- Scegli una password forte e unica

Questa è un'email automatica, si prega di non rispondere.
© " . date('Y') . " Flussu Server - Mille Isole SRL
";

            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();

            General::log("Recovery email sent to " . $user->getEmail());

            return [
                'success' => true,
                'message' => 'Email inviata con successo'
            ];

        } catch (PHPMailerException $e) {
            General::log("PHPMailer exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Errore invio email: {$e->getMessage()}"
            ];
        } catch (\Exception $e) {
            General::log("Email sending exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante l\'invio dell\'email'
            ];
        }
    }

    /**
     * Change expired password
     * @param string $userIdentifier Username or email
     * @param string $oldPassword Current password
     * @param string $newPassword New password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function changeExpiredPassword($userIdentifier, $oldPassword, $newPassword) {
        try {
            $user = new User();

            // Authenticate with old password
            if (!$user->authenticate($userIdentifier, $oldPassword)) {
                return [
                    'success' => false,
                    'message' => 'Credenziali non valide.'
                ];
            }

            // Check if password actually needs changing
            if (!$user->mustChangePassword()) {
                return [
                    'success' => false,
                    'message' => 'La password non è scaduta.'
                ];
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'La nuova password deve contenere almeno 8 caratteri.'
                ];
            }

            // Set new password (non-temporary)
            if (!$user->setPassword($newPassword, false)) {
                return [
                    'success' => false,
                    'message' => 'Errore nell\'aggiornamento della password.'
                ];
            }

            General::log("Expired password changed for user " . $user->getId());

            return [
                'success' => true,
                'message' => 'Password aggiornata con successo. Ora puoi effettuare il login.'
            ];

        } catch (\Exception $e) {
            General::log("Change expired password exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante il cambio password.'
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
