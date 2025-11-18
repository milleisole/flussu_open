<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - RoleManager Class
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

class RoleManager
{
    private $debug = false;
    private $handler;

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
        $this->handler = new HandlerUserNC();
    }

    /**
     * Ottieni tutti i ruoli
     */
    public function getAllRoles()
    {
        return $this->handler->getAllRoles();
    }

    /**
     * Ottieni ruolo per ID
     */
    public function getRoleById($roleId)
    {
        return $this->handler->getRoleById($roleId);
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
        $ownerId = $this->handler->getWorkflowOwner($workflowId);
        if ($ownerId === false) {
            return false;
        }

        // Se è il proprietario, ha tutti i permessi
        if ($ownerId == $userId) {
            return true;
        }

        // Verifica permessi espliciti
        $permission = $this->handler->getWorkflowPermission($workflowId, $userId);
        if ($permission !== false && strpos($permission, $requiredPermission) !== false) {
            return true;
        }

        // Verifica se è in un progetto condiviso
        return $this->handler->hasWorkflowProjectAccess($workflowId, $userId);
    }

    /**
     * Concedi permesso su workflow a un utente
     */
    public function grantWorkflowPermission($workflowId, $userId, $permission, $grantedBy)
    {
        General::addRowLog("[RoleManager: Grant workflow {$workflowId} permission to User {$userId}]");

        // Verifica che chi concede abbia i permessi
        if (!$this->canAccessWorkflow($grantedBy, $workflowId, self::PERM_UPDATE)) {
            return ['success' => false, 'message' => 'Non hai i permessi per concedere accesso'];
        }

        try {
            if ($this->handler->grantWorkflowPermission($workflowId, $userId, $permission, $grantedBy)) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($grantedBy, 'permission_granted', 'workflow', $workflowId, [
                    'to_user' => $userId,
                    'permission' => $permission
                ]);

                General::addRowLog("[RoleManager: Permission granted successfully]");
                return ['success' => true, 'message' => 'Permesso concesso con successo'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[RoleManager: Error granting permission - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la concessione del permesso'];
    }

    /**
     * Revoca permesso su workflow a un utente
     */
    public function revokeWorkflowPermission($workflowId, $userId, $revokedBy)
    {
        General::addRowLog("[RoleManager: Revoke workflow {$workflowId} permission from User {$userId}]");

        // Verifica che chi revoca abbia i permessi
        if (!$this->canAccessWorkflow($revokedBy, $workflowId, self::PERM_UPDATE)) {
            return ['success' => false, 'message' => 'Non hai i permessi per revocare accesso'];
        }

        try {
            if ($this->handler->revokeWorkflowPermission($workflowId, $userId)) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($revokedBy, 'permission_revoked', 'workflow', $workflowId, [
                    'from_user' => $userId
                ]);

                General::addRowLog("[RoleManager: Permission revoked successfully]");
                return ['success' => true, 'message' => 'Permesso revocato con successo'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[RoleManager: Error revoking permission - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la revoca del permesso'];
    }

    /**
     * Ottieni lista permessi per un workflow
     */
    public function getWorkflowPermissions($workflowId)
    {
        $result = $this->handler->getWorkflowPermissions($workflowId);
        return $result ? $result : [];
    }

    /**
     * Ottieni workflows accessibili da un utente
     */
    public function getUserWorkflows($userId, $includeInactive = false)
    {
        $result = $this->handler->getUserWorkflows($userId, $includeInactive);
        return $result ? $result : [];
    }

    /**
     * Verifica se un utente è amministratore
     */
    public function isAdmin($userId)
    {
        return $this->handler->checkUserRoleLevel($userId, self::ROLE_ADMIN);
    }

    /**
     * Verifica se un utente può editare workflow
     */
    public function canEditWorkflow($userId)
    {
        $userData = $this->handler->getUserById($userId);

        if (!$userData) {
            return false;
        }

        return in_array($userData['c80_role'], [self::ROLE_ADMIN, self::ROLE_EDITOR]);
    }
}
