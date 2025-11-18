<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - AuditLogger Class
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

class AuditLogger
{
    private $debug = false;
    private $handler;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->handler = new HandlerUserNC();
    }

    /**
     * Registra un'azione utente
     */
    public function log($userId, $action, $targetType = null, $targetId = null, $details = [])
    {
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO t92_user_audit
                (c92_usr_id, c92_action, c92_target_type, c92_target_id,
                 c92_ip_address, c92_user_agent, c92_details)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->handler->execSql($sql, [
                $userId,
                $action,
                $targetType,
                $targetId,
                $ipAddress,
                substr($userAgent, 0, 255),
                json_encode($details)
            ]);

            return true;
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("AuditLogger error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Ottieni log di un utente
     */
    public function getUserLogs($userId, $limit = 100, $offset = 0)
    {
        $sql = "SELECT * FROM t92_user_audit
                WHERE c92_usr_id = ?
                ORDER BY c92_timestamp DESC
                LIMIT ? OFFSET ?";

        if ($this->handler->execSql($sql, [$userId, $limit, $offset])) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Ottieni log per azione specifica
     */
    public function getLogsByAction($action, $limit = 100, $offset = 0)
    {
        $sql = "SELECT a.*, u.c80_username, u.c80_email
                FROM t92_user_audit a
                INNER JOIN t80_user u ON u.c80_id = a.c92_usr_id
                WHERE a.c92_action = ?
                ORDER BY a.c92_timestamp DESC
                LIMIT ? OFFSET ?";

        if ($this->handler->execSql($sql, [$action, $limit, $offset])) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Ottieni log per target specifico
     */
    public function getLogsForTarget($targetType, $targetId, $limit = 50)
    {
        $sql = "SELECT a.*, u.c80_username, u.c80_email
                FROM t92_user_audit a
                INNER JOIN t80_user u ON u.c80_id = a.c92_usr_id
                WHERE a.c92_target_type = ? AND a.c92_target_id = ?
                ORDER BY a.c92_timestamp DESC
                LIMIT ?";

        if ($this->handler->execSql($sql, [$targetType, $targetId, $limit])) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Ottieni statistiche di utilizzo
     */
    public function getUsageStats($startDate = null, $endDate = null)
    {
        $where = "1=1";
        $params = [];

        if ($startDate) {
            $where .= " AND c92_timestamp >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $where .= " AND c92_timestamp <= ?";
            $params[] = $endDate;
        }

        $sql = "SELECT
                    c92_action as action,
                    COUNT(*) as count,
                    COUNT(DISTINCT c92_usr_id) as unique_users
                FROM t92_user_audit
                WHERE {$where}
                GROUP BY c92_action
                ORDER BY count DESC";

        if ($this->handler->execSql($sql, $params)) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Pulisci log vecchi
     */
    public function cleanOldLogs($daysToKeep = 90)
    {
        General::addRowLog("[AuditLogger: Clean old logs (>{$daysToKeep} days)]");

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $sql = "DELETE FROM t92_user_audit WHERE c92_timestamp < ?";

        try {
            if ($this->handler->execSql($sql, [$cutoffDate])) {
                $result = $this->handler->getData();
                $count = is_array($result) ? count($result) : 0;
                General::addRowLog("[AuditLogger: Deleted {$count} old logs]");
                return $count;
            }
        } catch (\Exception $e) {
            General::addRowLog("[AuditLogger: Error cleaning logs - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("AuditLogger cleanOldLogs error: " . $e->getMessage());
            }
            return 0;
        }

        return 0;
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
