<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - InvitationManager Class
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

class InvitationManager
{
    private $debug = false;
    private $handler;

    // Status codes
    const STATUS_PENDING = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_REJECTED = 3;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->handler = new HandlerUserNC();
    }

    /**
     * Crea nuovo invito
     */
    public function createInvitation($email, $role, $invitedBy, $expiresInDays = 7)
    {
        General::addRowLog("[InvitationManager: Create invitation for {$email}]");

        // Verifica che l'email non esista già
        $userMgr = new UserManager($this->debug);
        if ($userMgr->emailExists($email)) {
            return ['success' => false, 'message' => 'Utente già registrato con questa email'];
        }

        // Genera codice invito
        $invitationCode = $this->generateInvitationCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));

        $sql = "INSERT INTO t96_user_invitations
                (c96_email, c96_role, c96_invited_by, c96_invitation_code, c96_expires_at)
                VALUES (?, ?, ?, ?, ?)";

        try {
            $invitationId = $this->handler->execSqlGetId($sql, [$email, $role, $invitedBy, $invitationCode, $expiresAt]);

            if ($invitationId > 0) {
                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($invitedBy, 'user_invited', 'invitation', $invitationId, [
                    'email' => $email,
                    'role' => $role
                ]);

                General::addRowLog("[InvitationManager: Invitation created with ID {$invitationId}]");
                return [
                    'success' => true,
                    'invitation_id' => $invitationId,
                    'invitation_code' => $invitationCode,
                    'expires_at' => $expiresAt,
                    'message' => 'Invito creato con successo'
                ];
            }
        } catch (\Exception $e) {
            General::addRowLog("[InvitationManager: Error creating invitation - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("InvitationManager createInvitation error: " . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Errore durante la creazione dell\'invito'];
        }

        return ['success' => false, 'message' => 'Impossibile creare l\'invito'];
    }

    /**
     * Valida codice invito
     */
    public function validateInvitation($invitationCode)
    {
        $sql = "SELECT * FROM t96_user_invitations
                WHERE c96_invitation_code = ?
                AND c96_status = ?
                AND c96_expires_at > NOW()";

        if ($this->handler->execSql($sql, [$invitationCode, self::STATUS_PENDING])) {
            $result = $this->handler->getData();
            if (is_array($result) && count($result) > 0) {
                return ['valid' => true, 'invitation' => $result[0]];
            }
        }

        return ['valid' => false, 'message' => 'Invito non valido o scaduto'];
    }

    /**
     * Accetta invito e crea utente
     */
    public function acceptInvitation($invitationCode, $userData)
    {
        General::addRowLog("[InvitationManager: Accept invitation {$invitationCode}]");

        $validation = $this->validateInvitation($invitationCode);

        if (!$validation['valid']) {
            return $validation;
        }

        $invitation = $validation['invitation'];

        // Crea utente
        $userMgr = new UserManager($this->debug);
        $userData['email'] = $invitation['c96_email'];
        $userData['role'] = $invitation['c96_role'];
        $userData['created_by'] = $invitation['c96_invited_by'];

        $result = $userMgr->createUser($userData);

        if ($result['success']) {
            // Marca invito come accettato
            $sql = "UPDATE t96_user_invitations
                    SET c96_status = ?, c96_accepted_at = NOW()
                    WHERE c96_invitation_code = ?";

            $this->handler->execSql($sql, [self::STATUS_ACCEPTED, $invitationCode]);

            // Log audit
            $audit = new AuditLogger($this->debug);
            $audit->log($result['user_id'], 'invitation_accepted', 'invitation', $invitation['c96_id']);

            General::addRowLog("[InvitationManager: Invitation accepted, user created]");
            return [
                'success' => true,
                'user_id' => $result['user_id'],
                'message' => 'Utente registrato con successo'
            ];
        }

        return $result;
    }

    /**
     * Rifiuta invito
     */
    public function rejectInvitation($invitationCode)
    {
        General::addRowLog("[InvitationManager: Reject invitation {$invitationCode}]");

        $sql = "UPDATE t96_user_invitations
                SET c96_status = ?
                WHERE c96_invitation_code = ? AND c96_status = ?";

        try {
            if ($this->handler->execSql($sql, [self::STATUS_REJECTED, $invitationCode, self::STATUS_PENDING])) {
                General::addRowLog("[InvitationManager: Invitation rejected]");
                return ['success' => true, 'message' => 'Invito rifiutato'];
            }
        } catch (\Exception $e) {
            General::addRowLog("[InvitationManager: Error rejecting invitation - ".$e->getMessage()."]");
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Impossibile rifiutare l\'invito'];
    }

    /**
     * Ottieni inviti di un utente
     */
    public function getUserInvitations($userId)
    {
        $sql = "SELECT * FROM t96_user_invitations
                WHERE c96_invited_by = ?
                ORDER BY c96_created_at DESC";

        if ($this->handler->execSql($sql, [$userId])) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Ottieni inviti pending
     */
    public function getPendingInvitations()
    {
        $sql = "SELECT i.*, u.c80_username as invited_by_username
                FROM t96_user_invitations i
                INNER JOIN t80_user u ON u.c80_id = i.c96_invited_by
                WHERE i.c96_status = ? AND i.c96_expires_at > NOW()
                ORDER BY i.c96_created_at DESC";

        if ($this->handler->execSql($sql, [self::STATUS_PENDING])) {
            return $this->handler->getData();
        }
        return [];
    }

    /**
     * Marca inviti scaduti
     */
    public function markExpiredInvitations()
    {
        General::addRowLog("[InvitationManager: Mark expired invitations]");

        $sql = "UPDATE t96_user_invitations
                SET c96_status = ?
                WHERE c96_status = ? AND c96_expires_at < NOW()";

        try {
            if ($this->handler->execSql($sql, [self::STATUS_EXPIRED, self::STATUS_PENDING])) {
                $result = $this->handler->getData();
                $count = is_array($result) ? count($result) : 0;
                General::addRowLog("[InvitationManager: Marked {$count} expired invitations]");
                return $count;
            }
        } catch (\Exception $e) {
            General::addRowLog("[InvitationManager: Error marking expired - ".$e->getMessage()."]");
            if ($this->debug) {
                error_log("InvitationManager markExpiredInvitations error: " . $e->getMessage());
            }
            return 0;
        }

        return 0;
    }

    /**
     * Genera codice invito
     */
    private function generateInvitationCode()
    {
        return bin2hex(random_bytes(32));
    }
}
