<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - RoleManager Class
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\Beans\Dbh;
use PDO;

class RoleManager extends Dbh
{
    private $debug = false;

    // Role IDs
    const ROLE_END_USER = 0;
    const ROLE_ADMIN = 1;
    const ROLE_EDITOR = 2;
    const ROLE_VIEWER = 3;

    // Permission flags
    const PERM_CREATE = 'C';
    const PERM_READ = 'R';
    const PERM_UPDATE = 'U';
    const PERM_DELETE = 'D';
    const PERM_EXECUTE = 'X';
    const PERM_OWNER = 'O';

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Ottieni tutti i ruoli
     */
    public function getAllRoles()
    {
        $sql = "SELECT * FROM t90_role ORDER BY c90_id";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni ruolo per ID
     */
    public function getRoleById($roleId)
    {
        $sql = "SELECT * FROM t90_role WHERE c90_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se un ruolo ha un permesso specifico
     */
    public function hasPermission($roleId, $permission)
    {
        $role = $this->getRoleById($roleId);
        if (!$role) {
            return false;
        }

        return strpos($role['c90_crud'], $permission) !== false;
    }

    /**
     * Verifica se un utente ha accesso a un workflow
     */
    public function canAccessWorkflow($userId, $workflowId, $requiredPermission = self::PERM_READ)
    {
        // Prima verifica se è il proprietario
        $sql = "SELECT c10_userid FROM t10_workflow WHERE c10_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$workflowId]);
        $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$workflow) {
            return false;
        }

        // Se è il proprietario, ha tutti i permessi
        if ($workflow['c10_userid'] == $userId) {
            return true;
        }

        // Verifica permessi espliciti
        $sql = "SELECT c88_permission FROM t88_wf_permissions
                WHERE c88_wf_id = ? AND c88_usr_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$workflowId, $userId]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permission) {
            return strpos($permission['c88_permission'], $requiredPermission) !== false;
        }

        // Verifica se è in un progetto condiviso
        $sql = "SELECT COUNT(*) FROM t85_prj_wflow pw
                INNER JOIN t87_prj_user pu ON pu.c87_prj_id = pw.c85_prj_id
                WHERE pw.c85_flofoid = ? AND pu.c87_usr_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$workflowId, $userId]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Concedi permesso su workflow a un utente
     */
    public function grantWorkflowPermission($workflowId, $userId, $permission, $grantedBy)
    {
        // Verifica che chi concede abbia i permessi
        if (!$this->canAccessWorkflow($grantedBy, $workflowId, self::PERM_UPDATE)) {
            return ['success' => false, 'message' => 'Non hai i permessi per concedere accesso'];
        }

        $sql = "INSERT INTO t88_wf_permissions
                (c88_wf_id, c88_usr_id, c88_permission, c88_granted_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                c88_permission = VALUES(c88_permission),
                c88_granted_by = VALUES(c88_granted_by),
                c88_granted_at = CURRENT_TIMESTAMP";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([$workflowId, $userId, $permission, $grantedBy]);

            if ($result) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($grantedBy, 'permission_granted', 'workflow', $workflowId, [
                    'to_user' => $userId,
                    'permission' => $permission
                ]);

                return ['success' => true, 'message' => 'Permesso concesso con successo'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la concessione del permesso'];
    }

    /**
     * Revoca permesso su workflow a un utente
     */
    public function revokeWorkflowPermission($workflowId, $userId, $revokedBy)
    {
        // Verifica che chi revoca abbia i permessi
        if (!$this->canAccessWorkflow($revokedBy, $workflowId, self::PERM_UPDATE)) {
            return ['success' => false, 'message' => 'Non hai i permessi per revocare accesso'];
        }

        $sql = "DELETE FROM t88_wf_permissions
                WHERE c88_wf_id = ? AND c88_usr_id = ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([$workflowId, $userId]);

            if ($result) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($revokedBy, 'permission_revoked', 'workflow', $workflowId, [
                    'from_user' => $userId
                ]);

                return ['success' => true, 'message' => 'Permesso revocato con successo'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la revoca del permesso'];
    }

    /**
     * Ottieni lista permessi per un workflow
     */
    public function getWorkflowPermissions($workflowId)
    {
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$workflowId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni workflows accessibili da un utente
     */
    public function getUserWorkflows($userId, $includeInactive = false)
    {
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se un utente è amministratore
     */
    public function isAdmin($userId)
    {
        $sql = "SELECT c80_role FROM t80_user WHERE c80_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user && $user['c80_role'] == self::ROLE_ADMIN;
    }

    /**
     * Verifica se un utente può editare workflow
     */
    public function canEditWorkflow($userId)
    {
        $sql = "SELECT c80_role FROM t80_user WHERE c80_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        return in_array($user['c80_role'], [self::ROLE_ADMIN, self::ROLE_EDITOR]);
    }
}
