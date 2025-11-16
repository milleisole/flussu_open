<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - UserManager Class
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

class UserManager
{
    private $debug = false;
    private $handler;

    // Role IDs
    const ROLE_END_USER = 0;
    const ROLE_ADMIN = 1;
    const ROLE_EDITOR = 2;
    const ROLE_VIEWER = 3;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->handler = new HandlerUserNC();
    }

    /**
     * Ottieni lista di tutti gli utenti
     */
    public function getAllUsers($includeDeleted = false)
    {
        $result = $this->handler->getAllUsers($includeDeleted);
        return $result ? $result : [];
    }

    /**
     * Ottieni utente per ID
     */
    public function getUserById($userId)
    {
        return $this->handler->getUserById($userId);
    }

    /**
     * Ottieni utente per username o email
     */
    public function getUserByUsernameOrEmail($identifier)
    {
        return $this->handler->getUserByUsernameOrEmail($identifier);
    }

    /**
     * Crea nuovo utente
     */
    public function createUser($data)
    {
        General::addRowLog("[UserManager: Create User]");

        // Validazione dati
        if (empty($data['username']) || empty($data['email'])) {
            return ['success' => false, 'message' => 'Username e email sono obbligatori'];
        }

        // Verifica se username esiste
        if ($this->usernameExists($data['username'])) {
            return ['success' => false, 'message' => 'Username già esistente'];
        }

        // Verifica se email esiste
        if ($this->emailExists($data['email'])) {
            return ['success' => false, 'message' => 'Email già esistente'];
        }

        $password = isset($data['password']) ? $this->hashPassword($data['password'], $data['username']) : '';
        $pwdChange = isset($data['password']) ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('-1 week'));

        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $password,
            'role' => $data['role'] ?? self::ROLE_END_USER,
            'name' => $data['name'] ?? '',
            'surname' => $data['surname'] ?? '',
            'pwd_chng' => $pwdChange
        ];

        try {
            $userId = $this->handler->createUser($userData);

            if ($userId > 0) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'user_created', 'user', $userId, [
                    'created_by' => $data['created_by'] ?? 0
                ]);

                General::addRowLog("[UserManager: User created with ID {$userId}]");
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Utente creato con successo'
                ];
            }
        } catch (\Exception $e) {
            General::addRowLog("[UserManager: Error creating user - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore durante la creazione: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la creazione dell\'utente'];
    }

    /**
     * Aggiorna utente esistente
     */
    public function updateUser($userId, $data)
    {
        General::addRowLog("[UserManager: Update User {$userId}]");

        $user = $this->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Utente non trovato'];
        }

        $updateFields = [];

        if (isset($data['email']) && $data['email'] !== $user['c80_email']) {
            if ($this->emailExists($data['email'], $userId)) {
                return ['success' => false, 'message' => 'Email già esistente'];
            }
            $updateFields['c80_email'] = $data['email'];
        }

        if (isset($data['username']) && $data['username'] !== $user['c80_username']) {
            if ($this->usernameExists($data['username'], $userId)) {
                return ['success' => false, 'message' => 'Username già esistente'];
            }
            $updateFields['c80_username'] = $data['username'];
        }

        if (isset($data['name'])) {
            $updateFields['c80_name'] = $data['name'];
        }

        if (isset($data['surname'])) {
            $updateFields['c80_surname'] = $data['surname'];
        }

        if (isset($data['role'])) {
            $updateFields['c80_role'] = $data['role'];
        }

        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'Nessun campo da aggiornare'];
        }

        try {
            if ($this->handler->updateUser($userId, $updateFields)) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'user_updated', 'user', $userId, [
                    'updated_by' => $data['updated_by'] ?? 0,
                    'changes' => array_keys($data)
                ]);

                General::addRowLog("[UserManager: User {$userId} updated successfully]");
                return ['success' => true, 'message' => 'Utente aggiornato con successo'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[UserManager: Error updating user - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'utente'];
    }

    /**
     * Abilita/Disabilita utente (soft delete)
     */
    public function setUserStatus($userId, $active, $disabledBy = null)
    {
        General::addRowLog("[UserManager: Set User {$userId} status to ".($active ? 'active' : 'inactive')."]");

        $deletedDate = $active ? '1899-12-31 23:59:59' : date('Y-m-d H:i:s');
        $deletedBy = $active ? 0 : ($disabledBy ?? 0);

        try {
            if ($this->handler->setUserStatus($userId, $deletedDate, $deletedBy)) {
                $action = $active ? 'user_enabled' : 'user_disabled';
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, $action, 'user', $userId, [
                    'by_user' => $disabledBy
                ]);

                General::addRowLog("[UserManager: User status updated successfully]");
                return ['success' => true, 'message' => 'Stato utente aggiornato con successo'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[UserManager: Error updating status - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dello stato'];
    }

    /**
     * Cambia password utente
     */
    public function changePassword($userId, $newPassword, $temporary = false)
    {
        General::addRowLog("[UserManager: Change password for User {$userId}]");

        $user = $this->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Utente non trovato'];
        }

        $hashedPassword = $this->hashPassword($newPassword, $user['c80_username'], $userId);
        $pwdChange = $temporary
            ? date('Y-m-d H:i:s', strtotime('-1 week'))
            : date('Y-m-d H:i:s', strtotime('+1 year'));

        try {
            if ($this->handler->changePassword($userId, $hashedPassword, $pwdChange)) {
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'password_changed', 'user', $userId, [
                    'temporary' => $temporary
                ]);

                General::addRowLog("[UserManager: Password changed successfully]");
                return ['success' => true, 'message' => 'Password aggiornata con successo'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[UserManager: Error changing password - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento della password'];
    }

    /**
     * Verifica se username esiste
     */
    public function usernameExists($username, $excludeUserId = null)
    {
        return $this->handler->usernameExists($username, $excludeUserId);
    }

    /**
     * Verifica se email esiste
     */
    public function emailExists($email, $excludeUserId = null)
    {
        return $this->handler->emailExists($email, $excludeUserId);
    }

    /**
     * Hash password usando sistema esistente Flussu
     * Utilizza la classe User di Flussu\Persons per compatibilità
     */
    private function hashPassword($password, $username = "", $userId = 0)
    {
        // Se userId è 0, generiamo un hash temporaneo che verrà rigenerato dopo l'insert
        if ($userId == 0) {
            // Forza cambio password al primo login
            return '';
        }

        // Usa il metodo _genPwd della classe User per compatibilità
        // Questo metodo è privato, quindi dobbiamo usare un'istanza User
        $user = new \Flussu\Persons\User();
        if ($user->load($userId)) {
            // Usa il metodo setPassword che internamente chiama _genPwd
            if ($user->setPassword($password, false)) {
                // Ricarica l'utente per ottenere la password hashata
                $user->load($userId);
                $userBean = new \Flussu\Beans\User(General::$DEBUG);
                $userBean->select($userId);
                return $userBean->getc80_password();
            }
        }

        // Fallback: forza cambio password
        return '';
    }

    /**
     * Ottieni statistiche utenti
     */
    public function getUserStats()
    {
        $result = $this->handler->getUserStats();
        return $result ? $result : [];
    }
}
