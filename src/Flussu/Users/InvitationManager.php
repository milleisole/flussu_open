<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * User Management System - InvitationManager Class
 * --------------------------------------------------------------------*/
namespace Flussu\Users;

use Flussu\Beans\Dbh;
use PDO;

class InvitationManager extends Dbh
{
    private $debug = false;

    // Status codes
    const STATUS_PENDING = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_REJECTED = 3;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Crea nuovo invito
     */
    public function createInvitation($email, $role, $invitedBy, $expiresInDays = 7)
    {
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
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([$email, $role, $invitedBy, $invitationCode, $expiresAt]);

            if ($result) {
                $invitationId = $this->getLastInsertId();

                // Log audit
                $audit = new AuditLogger($this->debug);
                $audit->log($invitedBy, 'user_invited', 'invitation', $invitationId, [
                    'email' => $email,
                    'role' => $role
                ]);

                return [
                    'success' => true,
                    'invitation_id' => $invitationId,
                    'invitation_code' => $invitationCode,
                    'expires_at' => $expiresAt,
                    'message' => 'Invito creato con successo'
                ];
            }
        } catch (\Exception $e) {
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$invitationCode, self::STATUS_PENDING]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invitation) {
            return ['valid' => true, 'invitation' => $invitation];
        }

        return ['valid' => false, 'message' => 'Invito non valido o scaduto'];
    }

    /**
     * Accetta invito e crea utente
     */
    public function acceptInvitation($invitationCode, $userData)
    {
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

            $stmt = $this->connect()->prepare($sql);
            $stmt->execute([self::STATUS_ACCEPTED, $invitationCode]);

            // Log audit
            $audit = new AuditLogger($this->debug);
            $audit->log($result['user_id'], 'invitation_accepted', 'invitation', $invitation['c96_id']);

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
        $sql = "UPDATE t96_user_invitations
                SET c96_status = ?
                WHERE c96_invitation_code = ? AND c96_status = ?";

        try {
            $stmt = $this->connect()->prepare($sql);
            $result = $stmt->execute([self::STATUS_REJECTED, $invitationCode, self::STATUS_PENDING]);

            if ($result) {
                return ['success' => true, 'message' => 'Invito rifiutato'];
            }
        } catch (\Exception $e) {
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $this->connect()->prepare($sql);
        $stmt->execute([self::STATUS_PENDING]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca inviti scaduti
     */
    public function markExpiredInvitations()
    {
        $sql = "UPDATE t96_user_invitations
                SET c96_status = ?
                WHERE c96_status = ? AND c96_expires_at < NOW()";

        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute([self::STATUS_EXPIRED, self::STATUS_PENDING]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("InvitationManager markExpiredInvitations error: " . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Genera codice invito
     */
    private function generateInvitationCode()
    {
        return bin2hex(random_bytes(32));
    }
}
