<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - UserManager Class
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\Beans\Dbh;
use Flussu\General;
use PDO;

class UserManager extends Dbh
{
    private $debug = false;

    // Role IDs
    const ROLE_END_USER = 0;
    const ROLE_ADMIN = 1;
    const ROLE_EDITOR = 2;
    const ROLE_VIEWER = 3;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Ottieni lista di tutti gli utenti
     */
    public function getAllUsers($includeDeleted = false)
    {
        $sql = "SELECT * FROM v30_users_with_roles";
        if (!$includeDeleted) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY user_id";

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni utente per ID
     */
    public function getUserById($userId)
    {
        $sql = "SELECT * FROM v30_users_with_roles WHERE user_id = ?";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni utente per username o email
     */
    public function getUserByUsernameOrEmail($identifier)
    {
        $sql = "SELECT * FROM v30_users_with_roles
                WHERE (c80_username = ? OR c80_email = ?) AND is_active = 1";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea nuovo utente
     */
    public function createUser($data)
    {
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

        $sql = "INSERT INTO t80_user
                (c80_username, c80_email, c80_password, c80_role, c80_name, c80_surname, c80_pwd_chng)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $role = $data['role'] ?? self::ROLE_END_USER;
        $name = $data['name'] ?? '';
        $surname = $data['surname'] ?? '';
        $password = isset($data['password']) ? $this->hashPassword($data['password']) : '';
        $pwdChange = isset($data['password']) ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('-1 week'));

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $password,
                $role,
                $name,
                $surname,
                $pwdChange
            ]);

            if ($result) {
                $userId = $this->getLastInsertId();

                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'user_created', 'user', $userId, [
                    'created_by' => $data['created_by'] ?? 0
                ]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Utente creato con successo'
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore durante la creazione: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante la creazione dell\'utente'];
    }

    /**
     * Aggiorna utente esistente
     */
    public function updateUser($userId, $data)
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Utente non trovato'];
        }

        $updateFields = [];
        $params = [];

        if (isset($data['email']) && $data['email'] !== $user['c80_email']) {
            if ($this->emailExists($data['email'], $userId)) {
                return ['success' => false, 'message' => 'Email già esistente'];
            }
            $updateFields[] = 'c80_email = ?';
            $params[] = $data['email'];
        }

        if (isset($data['username']) && $data['username'] !== $user['c80_username']) {
            if ($this->usernameExists($data['username'], $userId)) {
                return ['success' => false, 'message' => 'Username già esistente'];
            }
            $updateFields[] = 'c80_username = ?';
            $params[] = $data['username'];
        }

        if (isset($data['name'])) {
            $updateFields[] = 'c80_name = ?';
            $params[] = $data['name'];
        }

        if (isset($data['surname'])) {
            $updateFields[] = 'c80_surname = ?';
            $params[] = $data['surname'];
        }

        if (isset($data['role'])) {
            $updateFields[] = 'c80_role = ?';
            $params[] = $data['role'];
        }

        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'Nessun campo da aggiornare'];
        }

        $params[] = $userId;
        $sql = "UPDATE t80_user SET " . implode(', ', $updateFields) . " WHERE c80_id = ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'user_updated', 'user', $userId, [
                    'updated_by' => $data['updated_by'] ?? 0,
                    'changes' => array_keys($data)
                ]);

                return ['success' => true, 'message' => 'Utente aggiornato con successo'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'utente'];
    }

    /**
     * Abilita/Disabilita utente (soft delete)
     */
    public function setUserStatus($userId, $active, $disabledBy = null)
    {
        $deletedDate = $active ? '1899-12-31 23:59:59' : date('Y-m-d H:i:s');
        $deletedBy = $active ? 0 : ($disabledBy ?? 0);

        $sql = "UPDATE t80_user SET c80_deleted = ?, c80_deleted_by = ? WHERE c80_id = ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([$deletedDate, $deletedBy, $userId]);

            if ($result) {
                $action = $active ? 'user_enabled' : 'user_disabled';
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, $action, 'user', $userId, [
                    'by_user' => $disabledBy
                ]);

                return ['success' => true, 'message' => 'Stato utente aggiornato con successo'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dello stato'];
    }

    /**
     * Cambia password utente
     */
    public function changePassword($userId, $newPassword, $temporary = false)
    {
        $hashedPassword = $this->hashPassword($newPassword);
        $pwdChange = $temporary
            ? date('Y-m-d H:i:s', strtotime('-1 week'))
            : date('Y-m-d H:i:s', strtotime('+1 year'));

        $sql = "UPDATE t80_user SET c80_password = ?, c80_pwd_chng = ? WHERE c80_id = ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([$hashedPassword, $pwdChange, $userId]);

            if ($result) {
                $audit = new AuditLogger($this->debug);
                $audit->log($userId, 'password_changed', 'user', $userId, [
                    'temporary' => $temporary
                ]);

                return ['success' => true, 'message' => 'Password aggiornata con successo'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento della password'];
    }

    /**
     * Verifica se username esiste
     */
    public function usernameExists($username, $excludeUserId = null)
    {
        $sql = "SELECT COUNT(*) FROM t80_user WHERE c80_username = ?";
        $params = [$username];

        if ($excludeUserId) {
            $sql .= " AND c80_id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica se email esiste
     */
    public function emailExists($email, $excludeUserId = null)
    {
        $sql = "SELECT COUNT(*) FROM t80_user WHERE c80_email = ?";
        $params = [$email];

        if ($excludeUserId) {
            $sql .= " AND c80_id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Hash password usando sistema esistente Flussu
     * Utilizza la classe User di Flussu\Persons per compatibilità
     */
    private function hashPassword($password)
    {
        // TODO: Integrare con il sistema di hashing esistente di Flussu\Persons\User
        // Per ora restituisce password vuota per forzare il cambio al primo login
        return '';
    }

    /**
     * Ottieni statistiche utenti
     */
    public function getUserStats()
    {
        $sql = "SELECT
                    r.c90_name as role_name,
                    COUNT(u.c80_id) as user_count,
                    SUM(CASE WHEN u.c80_deleted = '1899-12-31 23:59:59' THEN 1 ELSE 0 END) as active_count
                FROM t80_user u
                LEFT JOIN t90_role r ON r.c90_id = u.c80_role
                GROUP BY u.c80_role, r.c90_name";

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
