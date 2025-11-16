<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - REST API Controller
 * --------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\Users\UserManager;
use Flussu\Users\RoleManager;
use Flussu\Users\SessionManager;
use Flussu\Users\InvitationManager;
use Flussu\Users\AuditLogger;
use Flussu\General;
use Flussu\Persons\User as FlussuUser;

class UserManagementController
{
    private $debug = false;
    private $currentUser = null;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Gestisce le richieste API
     */
    public function handleRequest($request)
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Session-ID');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $path = $request['path'] ?? '';

        try {
            // Route pubbliche (no auth)
            if (strpos($path, '/auth/login') === 0 && $method === 'POST') {
                return $this->login();
            }

            if (strpos($path, '/invitations/validate/') === 0 && $method === 'GET') {
                $code = basename($path);
                return $this->validateInvitation($code);
            }

            if (strpos($path, '/invitations/accept/') === 0 && $method === 'POST') {
                $code = str_replace('/invitations/accept/', '', $path);
                return $this->acceptInvitation($code);
            }

            // Verifica autenticazione per le altre route
            $this->authenticate();

            // Auth routes
            if ($path === '/auth/logout' && $method === 'POST') {
                return $this->logout();
            }

            if ($path === '/auth/me' && $method === 'GET') {
                return $this->getCurrentUser();
            }

            // User routes
            if ($path === '/users' && $method === 'GET') {
                return $this->getUsers($request);
            }

            if ($path === '/users' && $method === 'POST') {
                return $this->createUser();
            }

            if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'GET') {
                return $this->getUser($matches[1]);
            }

            if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'PUT') {
                return $this->updateUser($matches[1]);
            }

            if (preg_match('#^/users/(\d+)/status$#', $path, $matches) && $method === 'PUT') {
                return $this->setUserStatus($matches[1]);
            }

            if (preg_match('#^/users/(\d+)/password$#', $path, $matches) && $method === 'PUT') {
                return $this->changeUserPassword($matches[1]);
            }

            if ($path === '/users/stats' && $method === 'GET') {
                return $this->getUserStats();
            }

            // Role routes
            if ($path === '/roles' && $method === 'GET') {
                return $this->getRoles();
            }

            // Workflow routes
            if ($path === '/workflows/me' && $method === 'GET') {
                return $this->getUserWorkflows();
            }

            if (preg_match('#^/workflows/user/(\d+)$#', $path, $matches) && $method === 'GET') {
                return $this->getUserWorkflows($matches[1]);
            }

            if (preg_match('#^/workflows/(\d+)/permissions$#', $path, $matches) && $method === 'GET') {
                return $this->getWorkflowPermissions($matches[1]);
            }

            if (preg_match('#^/workflows/(\d+)/permissions$#', $path, $matches) && $method === 'POST') {
                return $this->grantWorkflowPermission($matches[1]);
            }

            if (preg_match('#^/workflows/(\d+)/permissions/(\d+)$#', $path, $matches) && $method === 'DELETE') {
                return $this->revokeWorkflowPermission($matches[1], $matches[2]);
            }

            // Invitation routes (admin only)
            if ($path === '/invitations' && $method === 'POST') {
                return $this->createInvitation();
            }

            if ($path === '/invitations/pending' && $method === 'GET') {
                return $this->getPendingInvitations();
            }

            // Audit routes (admin only)
            if (preg_match('#^/audit/users/(\d+)$#', $path, $matches) && $method === 'GET') {
                return $this->getUserLogs($matches[1], $request);
            }

            if ($path === '/audit/stats' && $method === 'GET') {
                return $this->getUsageStats($request);
            }

            // Route non trovata
            http_response_code(404);
            return ['success' => false, 'message' => 'Endpoint non trovato'];

        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'success' => false,
                'message' => $this->debug ? $e->getMessage() : 'Errore interno del server'
            ];
        }
    }

    /**
     * Autentica l'utente corrente
     */
    private function authenticate()
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? null;

        if (!$apiKey && !$sessionId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Autenticazione richiesta']);
            exit;
        }

        $sessionMgr = new SessionManager($this->debug);

        // Prova con session ID
        if ($sessionId) {
            $result = $sessionMgr->validateSession($sessionId);
            if ($result['valid']) {
                $this->currentUser = $result['session'];
                return;
            }
        }

        // Prova con API key
        if ($apiKey) {
            $result = $sessionMgr->validateApiKey($apiKey);
            if ($result['valid']) {
                $this->currentUser = $result['session'];
                return;
            }
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessione non valida o scaduta']);
        exit;
    }

    /**
     * Verifica se l'utente corrente è admin
     */
    private function requireAdmin()
    {
        if (!$this->currentUser || $this->currentUser['c80_role'] != UserManager::ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accesso negato: privilegi di amministratore richiesti']);
            exit;
        }
    }

    // ==================== AUTH ENDPOINTS ====================

    private function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Username e password richiesti'];
        }

        $user = new FlussuUser();
        if (!$user->authenticate($username, $password)) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Credenziali non valide'];
        }

        // Crea sessione
        $sessionMgr = new SessionManager($this->debug);
        $result = $sessionMgr->createSession($user->getId());

        if ($result['success']) {
            return [
                'success' => true,
                'session_id' => $result['session_id'],
                'api_key' => $result['api_key'],
                'expires_at' => $result['expires_at'],
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUserId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'surname' => $user->getSurname()
                ]
            ];
        }

        return $result;
    }

    private function logout()
    {
        $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? null;

        if ($sessionId && $this->currentUser) {
            $sessionMgr = new SessionManager($this->debug);
            $sessionMgr->closeSession($sessionId, $this->currentUser['c94_usr_id']);
        }

        return ['success' => true, 'message' => 'Logout effettuato'];
    }

    private function getCurrentUser()
    {
        return [
            'success' => true,
            'user' => $this->currentUser
        ];
    }

    // ==================== USER ENDPOINTS ====================

    private function getUsers($request)
    {
        $this->requireAdmin();

        $includeDeleted = isset($request['includeDeleted']) && $request['includeDeleted'] === 'true';

        $userMgr = new UserManager($this->debug);
        $users = $userMgr->getAllUsers($includeDeleted);

        return ['success' => true, 'users' => $users];
    }

    private function getUser($userId)
    {
        $this->requireAdmin();

        $userMgr = new UserManager($this->debug);
        $user = $userMgr->getUserById($userId);

        if (!$user) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Utente non trovato'];
        }

        return ['success' => true, 'user' => $user];
    }

    private function createUser()
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $data['created_by'] = $this->currentUser['c94_usr_id'];

        $userMgr = new UserManager($this->debug);
        return $userMgr->createUser($data);
    }

    private function updateUser($userId)
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $data['updated_by'] = $this->currentUser['c94_usr_id'];

        $userMgr = new UserManager($this->debug);
        return $userMgr->updateUser($userId, $data);
    }

    private function setUserStatus($userId)
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $active = $data['active'] ?? true;

        $userMgr = new UserManager($this->debug);
        return $userMgr->setUserStatus($userId, $active, $this->currentUser['c94_usr_id']);
    }

    private function changeUserPassword($userId)
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $newPassword = $data['newPassword'] ?? '';
        $temporary = $data['temporary'] ?? false;

        if (empty($newPassword)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Nuova password richiesta'];
        }

        $userMgr = new UserManager($this->debug);
        return $userMgr->changePassword($userId, $newPassword, $temporary);
    }

    private function getUserStats()
    {
        $this->requireAdmin();

        $userMgr = new UserManager($this->debug);
        $stats = $userMgr->getUserStats();

        return ['success' => true, 'stats' => $stats];
    }

    // ==================== ROLE ENDPOINTS ====================

    private function getRoles()
    {
        $roleMgr = new RoleManager($this->debug);
        $roles = $roleMgr->getAllRoles();

        return ['success' => true, 'roles' => $roles];
    }

    // ==================== WORKFLOW ENDPOINTS ====================

    private function getUserWorkflows($userId = null)
    {
        $targetUserId = $userId ?? $this->currentUser['c94_usr_id'];

        $roleMgr = new RoleManager($this->debug);
        $workflows = $roleMgr->getUserWorkflows($targetUserId);

        return ['success' => true, 'workflows' => $workflows];
    }

    private function getWorkflowPermissions($workflowId)
    {
        $roleMgr = new RoleManager($this->debug);
        $permissions = $roleMgr->getWorkflowPermissions($workflowId);

        return ['success' => true, 'permissions' => $permissions];
    }

    private function grantWorkflowPermission($workflowId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['userId'] ?? null;
        $permission = $data['permission'] ?? 'R';

        if (!$userId) {
            http_response_code(400);
            return ['success' => false, 'message' => 'User ID richiesto'];
        }

        $roleMgr = new RoleManager($this->debug);
        return $roleMgr->grantWorkflowPermission(
            $workflowId,
            $userId,
            $permission,
            $this->currentUser['c94_usr_id']
        );
    }

    private function revokeWorkflowPermission($workflowId, $userId)
    {
        $roleMgr = new RoleManager($this->debug);
        return $roleMgr->revokeWorkflowPermission(
            $workflowId,
            $userId,
            $this->currentUser['c94_usr_id']
        );
    }

    // ==================== INVITATION ENDPOINTS ====================

    private function createInvitation()
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? UserManager::ROLE_END_USER;
        $expiresInDays = $data['expiresInDays'] ?? 7;

        if (empty($email)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Email richiesta'];
        }

        $inviteMgr = new InvitationManager($this->debug);
        return $inviteMgr->createInvitation($email, $role, $this->currentUser['c94_usr_id'], $expiresInDays);
    }

    private function validateInvitation($invitationCode)
    {
        $inviteMgr = new InvitationManager($this->debug);
        return $inviteMgr->validateInvitation($invitationCode);
    }

    private function acceptInvitation($invitationCode)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $inviteMgr = new InvitationManager($this->debug);
        return $inviteMgr->acceptInvitation($invitationCode, $data);
    }

    private function getPendingInvitations()
    {
        $this->requireAdmin();

        $inviteMgr = new InvitationManager($this->debug);
        $invitations = $inviteMgr->getPendingInvitations();

        return ['success' => true, 'invitations' => $invitations];
    }

    // ==================== AUDIT ENDPOINTS ====================

    private function getUserLogs($userId, $request)
    {
        $this->requireAdmin();

        $limit = $request['limit'] ?? 100;
        $offset = $request['offset'] ?? 0;

        $audit = new AuditLogger($this->debug);
        $logs = $audit->getUserLogs($userId, $limit, $offset);

        return ['success' => true, 'logs' => $logs];
    }

    private function getUsageStats($request)
    {
        $this->requireAdmin();

        $startDate = $request['startDate'] ?? null;
        $endDate = $request['endDate'] ?? null;

        $audit = new AuditLogger($this->debug);
        $stats = $audit->getUsageStats($startDate, $endDate);

        return ['success' => true, 'stats' => $stats];
    }
}
