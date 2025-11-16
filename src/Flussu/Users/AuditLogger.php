<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - AuditLogger Class
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\Beans\Dbh;
use PDO;

class AuditLogger extends Dbh
{
    private $debug = false;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
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
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute([
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$action, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$targetType, $targetId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pulisci log vecchi
     */
    public function cleanOldLogs($daysToKeep = 90)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $sql = "DELETE FROM t92_user_audit WHERE c92_timestamp < ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute([$cutoffDate]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("AuditLogger cleanOldLogs error: " . $e->getMessage());
            }
            return 0;
        }
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
