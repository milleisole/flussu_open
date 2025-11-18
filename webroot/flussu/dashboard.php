<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Dashboard - Lista workflow utente
 * --------------------------------------------------------------------*/

require_once 'init.php';

use Flussu\Flussuserver\NC\HandlerNC;

// Richiede autenticazione
requireLogin();

// Ottieni l'utente corrente
$currentUser = getCurrentUser();

// Ottieni i workflow dell'utente
$handler = new HandlerNC();
$workflows = [];
try {
    $workflows = $handler->getUserFlussus($currentUser->getId(), "0");
} catch (Exception $e) {
    General::addRowLog("Error loading workflows: " . $e->getMessage());
    $workflows = [];
}

$pageTitle = "I miei Workflow - Flussu Dashboard";
?>

<?php include 'header.php'; ?>

<style>
    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        color: #7f8c8d;
        font-size: 1rem;
    }

    .workflows-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .workflow-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s;
        cursor: pointer;
        border: 2px solid transparent;
    }

    .workflow-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        transform: translateY(-2px);
        border-color: #3498db;
    }

    .workflow-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .workflow-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .workflow-project {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-bottom: 0.5rem;
    }

    .workflow-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-active {
        background-color: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }

    .workflow-description {
        color: #555;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 1rem;
        min-height: 3rem;
    }

    .workflow-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding-top: 1rem;
        border-top: 1px solid #ecf0f1;
        font-size: 0.85rem;
        color: #7f8c8d;
    }

    .workflow-meta-row {
        display: flex;
        justify-content: space-between;
    }

    .workflow-meta-label {
        font-weight: 600;
    }

    .workflow-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .btn-small {
        padding: 0.4rem 1rem;
        font-size: 0.85rem;
        border-radius: 6px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: #3498db;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2980b9;
    }

    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #7f8c8d;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .empty-state-text {
        color: #7f8c8d;
        font-size: 1rem;
    }

    .workflow-id {
        font-family: monospace;
        background-color: #ecf0f1;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
    }
</style>

<div class="page-header">
    <h1 class="page-title">I miei Workflow</h1>
    <p class="page-subtitle">Gestisci e monitora i tuoi workflow Flussu</p>
</div>

<?php if (empty($workflows)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h2 class="empty-state-title">Nessun workflow trovato</h2>
        <p class="empty-state-text">Non hai ancora creato nessun workflow. Inizia creando il tuo primo workflow!</p>
    </div>
<?php else: ?>
    <div class="workflows-grid">
        <?php foreach ($workflows as $workflow): ?>
            <div class="workflow-card" onclick="location.href='workflow_detail.php?wid=<?php echo urlencode($workflow['wid']); ?>'">
                <div class="workflow-header">
                    <div>
                        <div class="workflow-name"><?php echo htmlspecialchars($workflow['name'] ?? 'Workflow senza nome'); ?></div>
                        <?php if (!empty($workflow['proj'])): ?>
                            <div class="workflow-project">Progetto: <?php echo htmlspecialchars($workflow['proj']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="workflow-status <?php echo $workflow['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $workflow['is_active'] ? 'Attivo' : 'Inattivo'; ?>
                    </span>
                </div>

                <div class="workflow-description">
                    <?php
                    $description = $workflow['description'] ?? 'Nessuna descrizione disponibile';
                    echo htmlspecialchars(strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description);
                    ?>
                </div>

                <div class="workflow-meta">
                    <div class="workflow-meta-row">
                        <span class="workflow-meta-label">ID:</span>
                        <span class="workflow-id"><?php echo htmlspecialchars($workflow['wid']); ?></span>
                    </div>
                    <?php if (!empty($workflow['last_mod'])): ?>
                    <div class="workflow-meta-row">
                        <span class="workflow-meta-label">Ultima modifica:</span>
                        <span><?php echo date('d/m/Y H:i', strtotime($workflow['last_mod'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($workflow['def_lang'])): ?>
                    <div class="workflow-meta-row">
                        <span class="workflow-meta-label">Lingua:</span>
                        <span><?php echo strtoupper($workflow['def_lang']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="workflow-actions" onclick="event.stopPropagation();">
                    <a href="workflow_detail.php?wid=<?php echo urlencode($workflow['wid']); ?>" class="btn-small btn-primary">
                        Dettagli
                    </a>
                    <a href="#" class="btn-small btn-secondary" onclick="alert('Funzionalità in arrivo'); return false;">
                        Esegui
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
