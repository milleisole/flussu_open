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

        try {
            $invitationId = $this->handler->createInvitation($email, $role, $invitedBy, $invitationCode, $expiresAt);

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
        $invitation = $this->handler->getInvitationByCode($invitationCode, self::STATUS_PENDING);

        if ($invitation !== false) {
            return ['valid' => true, 'invitation' => $invitation];
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
            $this->handler->updateInvitationStatus($invitationCode, self::STATUS_ACCEPTED, date('Y-m-d H:i:s'));

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

        try {
            if ($this->handler->updateInvitationStatus($invitationCode, self::STATUS_REJECTED)) {
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
        $result = $this->handler->getUserInvitations($userId);
        return $result ? $result : [];
    }

    /**
     * Ottieni inviti pending
     */
    public function getPendingInvitations()
    {
        $result = $this->handler->getPendingInvitations(self::STATUS_PENDING);
        return $result ? $result : [];
    }

    /**
     * Marca inviti scaduti
     */
    public function markExpiredInvitations()
    {
        General::addRowLog("[InvitationManager: Mark expired invitations]");

        try {
            if ($this->handler->markExpiredInvitations(self::STATUS_EXPIRED, self::STATUS_PENDING)) {
                General::addRowLog("[InvitationManager: Expired invitations marked]");
                return true;
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
