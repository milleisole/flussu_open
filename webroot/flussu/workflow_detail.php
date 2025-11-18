<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Workflow Detail - Informazioni dettagliate, backup e restore
 * --------------------------------------------------------------------*/

require_once 'init.php';

use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\General;

// Richiede autenticazione
requireLogin();

// Ottieni l'utente corrente
$currentUser = getCurrentUser();

// Ottieni il WID dal parametro GET
$wid = isset($_GET['wid']) ? $_GET['wid'] : '';

if (empty($wid)) {
    header('Location: dashboard.php');
    exit;
}

// Gestione azioni
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'backup':
            // TODO: Implementare backup workflow
            $message = 'Funzionalità di backup in fase di implementazione';
            $messageType = 'info';
            break;

        case 'restore':
            // TODO: Implementare restore workflow
            $message = 'Funzionalità di restore in fase di implementazione';
            $messageType = 'info';
            break;

        case 'toggle_active':
            // TODO: Implementare attivazione/disattivazione workflow
            $message = 'Funzionalità di attivazione/disattivazione in fase di implementazione';
            $messageType = 'info';
            break;
    }
}

// Ottieni i dettagli del workflow
$handler = new HandlerNC();
$workflow = null;
$workflowBlocks = [];

try {
    // Ottieni tutti i workflow dell'utente
    $workflows = $handler->getUserFlussus($currentUser->getId(), "0");

    // Trova il workflow specifico
    foreach ($workflows as $wf) {
        if ($wf['wid'] === $wid) {
            $workflow = $wf;
            break;
        }
    }

    if (!$workflow) {
        header('Location: dashboard.php');
        exit;
    }

    // Prova a ottenere i blocchi del workflow
    // Note: Questo potrebbe richiedere il wofoid numerico invece del wid camuffato
    // Per ora gestiamo l'errore con un try-catch
    try {
        // $workflowBlocks = $handler->getFlussuBlocks(false, $workflow['wfauid'] ?? 0);
    } catch (Exception $e) {
        General::addRowLog("Error loading workflow blocks: " . $e->getMessage());
    }

} catch (Exception $e) {
    General::addRowLog("Error loading workflow: " . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}

$pageTitle = htmlspecialchars($workflow['name']) . " - Workflow Details";
?>

<?php include 'header.php'; ?>

<style>
    .breadcrumb {
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
    }

    .breadcrumb a {
        color: #3498db;
        text-decoration: none;
    }

    .breadcrumb a:hover {
        text-decoration: underline;
    }

    .breadcrumb-separator {
        margin: 0 0.5rem;
        color: #7f8c8d;
    }

    .workflow-detail-header {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .workflow-title {
        font-size: 2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 1rem;
    }

    .workflow-badges {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .badge {
        display: inline-block;
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-active {
        background-color: #d4edda;
        color: #155724;
    }

    .badge-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }

    .badge-info {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .info-card {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
    }

    .info-label {
        font-size: 0.85rem;
        color: #7f8c8d;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        font-size: 1.1rem;
        color: #2c3e50;
        word-break: break-word;
    }

    .info-value-mono {
        font-family: monospace;
        background-color: #ecf0f1;
        padding: 0.5rem;
        border-radius: 4px;
    }

    .actions-section {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 1.5rem;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .action-card {
        border: 2px solid #ecf0f1;
        border-radius: 8px;
        padding: 1.5rem;
        transition: all 0.2s;
    }

    .action-card:hover {
        border-color: #3498db;
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.1);
    }

    .action-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }

    .action-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .action-description {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-bottom: 1rem;
    }

    .btn {
        display: inline-block;
        padding: 0.6rem 1.5rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s;
        text-align: center;
    }

    .btn-primary {
        background-color: #3498db;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2980b9;
    }

    .btn-success {
        background-color: #27ae60;
        color: white;
    }

    .btn-success:hover {
        background-color: #229954;
    }

    .btn-warning {
        background-color: #f39c12;
        color: white;
    }

    .btn-warning:hover {
        background-color: #d68910;
    }

    .btn-danger {
        background-color: #e74c3c;
        color: white;
    }

    .btn-danger:hover {
        background-color: #c0392b;
    }

    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #7f8c8d;
    }

    .message {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .message-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .message-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .description-section {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .description-text {
        color: #555;
        line-height: 1.6;
        font-size: 1rem;
    }
</style>

<div class="breadcrumb">
    <a href="dashboard.php">Dashboard</a>
    <span class="breadcrumb-separator">/</span>
    <span><?php echo htmlspecialchars($workflow['name']); ?></span>
</div>

<?php if ($message): ?>
    <div class="message message-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="workflow-detail-header">
    <h1 class="workflow-title"><?php echo htmlspecialchars($workflow['name']); ?></h1>

    <div class="workflow-badges">
        <span class="badge <?php echo $workflow['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
            <?php echo $workflow['is_active'] ? 'Attivo' : 'Inattivo'; ?>
        </span>
        <?php if (!empty($workflow['proj'])): ?>
            <span class="badge badge-info">
                Progetto: <?php echo htmlspecialchars($workflow['proj']); ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($workflow['def_lang'])): ?>
            <span class="badge badge-info">
                <?php echo strtoupper($workflow['def_lang']); ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="info-label">Workflow ID</div>
            <div class="info-value info-value-mono"><?php echo htmlspecialchars($workflow['wid']); ?></div>
        </div>

        <?php if (!empty($workflow['last_mod'])): ?>
        <div class="info-card">
            <div class="info-label">Ultima modifica</div>
            <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($workflow['last_mod'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($workflow['valid_from'])): ?>
        <div class="info-card">
            <div class="info-label">Valido da</div>
            <div class="info-value"><?php echo date('d/m/Y', strtotime($workflow['valid_from'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($workflow['valid_until'])): ?>
        <div class="info-card">
            <div class="info-label">Valido fino a</div>
            <div class="info-value"><?php echo date('d/m/Y', strtotime($workflow['valid_until'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($workflow['supp_lang'])): ?>
        <div class="info-card">
            <div class="info-label">Lingue supportate</div>
            <div class="info-value"><?php echo strtoupper(htmlspecialchars($workflow['supp_lang'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($workflow['description'])): ?>
<div class="description-section">
    <h2 class="section-title">Descrizione</h2>
    <p class="description-text"><?php echo nl2br(htmlspecialchars($workflow['description'])); ?></p>
</div>
<?php endif; ?>

<div class="actions-section">
    <h2 class="section-title">Azioni Workflow</h2>

    <div class="actions-grid">
        <div class="action-card">
            <div class="action-icon">📥</div>
            <div class="action-title">Backup Workflow</div>
            <div class="action-description">
                Esporta il workflow in formato JSON per conservarne una copia di backup
            </div>
            <form method="POST" onsubmit="return confirm('Funzionalità in fase di implementazione');">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-primary">Scarica Backup</button>
            </form>
        </div>

        <div class="action-card">
            <div class="action-icon">📤</div>
            <div class="action-title">Restore Workflow</div>
            <div class="action-description">
                Ripristina il workflow da un file di backup precedentemente salvato
            </div>
            <form method="POST" onsubmit="return confirm('Funzionalità in fase di implementazione');">
                <input type="hidden" name="action" value="restore">
                <button type="submit" class="btn btn-success">Ripristina da Backup</button>
            </form>
        </div>

        <div class="action-card">
            <div class="action-icon"><?php echo $workflow['is_active'] ? '⏸️' : '▶️'; ?></div>
            <div class="action-title"><?php echo $workflow['is_active'] ? 'Disattiva' : 'Attiva'; ?> Workflow</div>
            <div class="action-description">
                <?php echo $workflow['is_active'] ? 'Metti in pausa' : 'Riattiva'; ?> l'esecuzione di questo workflow
            </div>
            <form method="POST" onsubmit="return confirm('Funzionalità in fase di implementazione');">
                <input type="hidden" name="action" value="toggle_active">
                <button type="submit" class="btn <?php echo $workflow['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                    <?php echo $workflow['is_active'] ? 'Disattiva' : 'Attiva'; ?>
                </button>
            </form>
        </div>

        <div class="action-card">
            <div class="action-icon">🔧</div>
            <div class="action-title">Modifica Workflow</div>
            <div class="action-description">
                Modifica la struttura e le impostazioni del workflow
            </div>
            <button type="button" class="btn btn-secondary" onclick="alert('Funzionalità in fase di implementazione')">
                Modifica
            </button>
        </div>

        <div class="action-card">
            <div class="action-icon">▶️</div>
            <div class="action-title">Esegui Workflow</div>
            <div class="action-description">
                Avvia una nuova esecuzione di questo workflow
            </div>
            <button type="button" class="btn btn-primary" onclick="alert('Funzionalità in fase di implementazione')">
                Esegui Ora
            </button>
        </div>

        <div class="action-card">
            <div class="action-icon">🗑️</div>
            <div class="action-title">Elimina Workflow</div>
            <div class="action-description">
                Elimina definitivamente questo workflow (azione irreversibile)
            </div>
            <button type="button" class="btn btn-danger" onclick="if(confirm('Sei sicuro di voler eliminare questo workflow? Questa azione è irreversibile!')) { alert('Funzionalità in fase di implementazione'); }">
                Elimina
            </button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
