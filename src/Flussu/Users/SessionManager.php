<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - SessionManager Class
 * --------------------------------------------------------------------*
 * VERSION REL.:     5.0.20250216
 * UPDATE DATE:      16.02:2025
 *
 * REFACTORED: Now uses HandlerUserNC for all database operations
 * instead of direct DB access via Dbh extension
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\General;
use Flussu\Flussuserver\NC\HandlerUserNC;

class SessionManager
{
    private $debug = false;
    private $sessionLifetime = 7200; // 2 ore default
    private $handler;

    public function __construct($debug = false, $sessionLifetime = null)
    {
        $this->debug = $debug;
        $this->handler = new HandlerUserNC();
        if ($sessionLifetime) {
            $this->sessionLifetime = $sessionLifetime;
        }
    }

    /**
     * Crea nuova sessione
     */
    public function createSession($userId, $apiKeyLifetimeMinutes = 240)
    {
        General::addRowLog("[SessionManager: Create session for User {$userId}]");

        $sessionId = $this->generateSessionId();
        $apiKey = General::getDateTimedApiKeyFromUser($userId, $apiKeyLifetimeMinutes);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionLifetime);
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sessionData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'api_key' => $apiKey,
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 255),
            'expires_at' => $expiresAt
        ];

        try {
            if ($this->handler->createSession($sessionData)) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'session_created', 'session', null, [
                    'session_id' => $sessionId,
                    'expires_at' => $expiresAt
                ]);

                General::addRowLog("[SessionManager: Session created successfully]");
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'api_key' => $apiKey,
                    'expires_at' => $expiresAt
                ];
            }
        } catch (\Exception $e) {
            General::addRowLog("[SessionManager: Error creating session - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("SessionManager createSession error: " . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Errore durante la creazione della sessione'];
        }

        return ['success' => false, 'message' => 'Impossibile creare la sessione'];
    }

    /**
     * Valida sessione
     */
    public function validateSession($sessionId)
    {
        $session = $this->handler->getSessionById($sessionId);

        if ($session !== false) {
            // Aggiorna last_activity
            $this->updateLastActivity($sessionId);
            return ['valid' => true, 'session' => $session];
        }

        return ['valid' => false, 'message' => 'Sessione non valida o scaduta'];
    }

    /**
     * Valida API key
     */
    public function validateApiKey($apiKey)
    {
        $session = $this->handler->getSessionByApiKey($apiKey);

        if ($session !== false) {
            // Aggiorna last_activity
            $this->updateLastActivity($session['c94_session_id']);
            return ['valid' => true, 'session' => $session];
        }

        // Fallback: usa sistema esistente di Flussu
        $userId = General::getUserFromDateTimedApiKey($apiKey);
        if ($userId > 0) {
            $user = $this->handler->getUserById($userId);
            if ($user && $user['c80_deleted'] == '1899-12-31 23:59:59') {
                return ['valid' => true, 'session' => [
                    'c94_usr_id' => $user['c80_id'],
                    'c80_username' => $user['c80_username'],
                    'c80_email' => $user['c80_email'],
                    'c80_role' => $user['c80_role']
                ]];
            }
        }

        return ['valid' => false, 'message' => 'API key non valida o scaduta'];
    }

    /**
     * Chiudi sessione
     */
    public function closeSession($sessionId, $userId = null)
    {
        General::addRowLog("[SessionManager: Close session {$sessionId}]");

        try {
            if ($this->handler->deleteSession($sessionId, $userId)) {
                if ($userId) {
                    $audit = new AuditLogger($this->debug);
                    $audit->log($userId, 'session_closed', 'session', null, [
                        'session_id' => $sessionId
                    ]);
                }

                General::addRowLog("[SessionManager: Session closed successfully]");
                return ['success' => true, 'message' => 'Sessione chiusa'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[SessionManager: Error closing session - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("SessionManager closeSession error: " . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Errore durante la chiusura della sessione'];
        }

        return ['success' => false, 'message' => 'Errore durante la chiusura della sessione'];
    }

    /**
     * Chiudi tutte le sessioni di un utente
     */
    public function closeAllUserSessions($userId)
    {
        General::addRowLog("[SessionManager: Close all sessions for User {$userId}]");

        try {
            if ($this->handler->deleteAllUserSessions($userId)) {
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'all_sessions_closed', 'session', null);

                General::addRowLog("[SessionManager: All sessions closed successfully]");
                return ['success' => true, 'message' => 'Tutte le sessioni chiuse'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[SessionManager: Error closing all sessions - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("SessionManager closeAllUserSessions error: " . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Errore durante la chiusura delle sessioni'];
        }

        return ['success' => false, 'message' => 'Errore durante la chiusura delle sessioni'];
    }

    /**
     * Ottieni sessioni attive di un utente
     */
    public function getUserActiveSessions($userId)
    {
        $result = $this->handler->getUserActiveSessions($userId);
        return $result ? $result : [];
    }

    /**
     * Pulisci sessioni scadute
     */
    public function cleanExpiredSessions()
    {
        General::addRowLog("[SessionManager: Clean expired sessions]");

        try {
            if ($this->handler->cleanExpiredSessions()) {
                General::addRowLog("[SessionManager: Expired sessions cleaned]");
                return true;
            }
        } catch (\Exception $e) {
            General::addRowLog("[SessionManager: Error cleaning sessions - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("SessionManager cleanExpiredSessions error: " . $e->getMessage());
            }
            return 0;
        }

        return 0;
    }

    /**
     * Aggiorna last activity
     */
    private function updateLastActivity($sessionId)
    {
        try {
            $this->handler->updateSessionActivity($sessionId);
        } catch (\Exception $e) {
            // Non logghiamo errori per questa operazione
        }
    }

    /**
     * Genera session ID
     */
    private function generateSessionId()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Ottieni IP client
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',
                   'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
