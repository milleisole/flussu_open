<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * 
 *      Pagina di gestione delle proprietà del workflow
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once '../inc/includebase.php';
require_once '../inc/header.php';
?>
    <style>
        .property-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .property-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .property-value {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .sidebar-actions {
            position: sticky;
            top: 20px;
        }
        
        .sidebar-actions .btn {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-flussu {
            background: #188d4d;
            color: white;
            border: none;
        }
        
        .btn-flussu:hover {
            background: #156b3d;
            color: white;
        }
        
        .user-table,
        .backup-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .user-table th,
        .backup-table th {
            background: #5CCF8F;
            color: #333;
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .user-table td,
        .backup-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .hide-if-new {
            display: none;
        }
        
        .form-switch {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-switch input[type="radio"] {
            margin-right: 5px;
        }
    </style>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-2 col-md-3">
                    <div class="card">
                        <div class="card-body sidebar-actions">
                            <h5>Proprietà Workflow</h5>
                            <p class="text-muted small">Gestione workflow</p>
                            
                            <button class="btn btn-secondary btn-flussu" onclick="goToList()">
                                <i class="fas fa-arrow-left"></i> Lista
                            </button>
                            
                            <button class="btn btn-secondary btn-flussu hide-if-new" id="statsBtn" onclick="goToStats()">
                                <i class="fas fa-chart-line"></i> Statistiche
                            </button>
                            
                            <button class="btn btn-secondary btn-flussu hide-if-new" id="editorBtn" onclick="openEditor()">
                                <i class="fas fa-edit"></i> Editor
                            </button>
                            
                            <button class="btn btn-secondary btn-flussu hide-if-new" id="sessionsBtn" onclick="goToSessions()">
                                <i class="fas fa-list"></i> Sessioni
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenuto principale -->
                <div class="col-lg-10 col-md-9">
                    <!-- Header -->
                    <div class="property-section">
                        <h4>
                            <span class="text-muted">Workflow</span> 
                            <span id="workflowId">-</span>
                            <span class="text-muted small" id="workflowAuId"></span>
                        </h4>
                    </div>
                    
                    <!-- Proprietà generali -->
                    <div class="property-section">
                        <h5 class="mb-3">Proprietà Generali</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="property-label">Nome *</label>
                                <input type="text" class="form-control" id="workflowName" placeholder="Nome del workflow">
                            </div>
                            <div class="col-md-4">
                                <label class="property-label">Stato *</label>
                                <div class="form-switch">
                                    <label>
                                        <input type="radio" name="workflowActive" value="1" id="activeYes">
                                        Attivo
                                    </label>
                                    <label>
                                        <input type="radio" name="workflowActive" value="0" id="activeNo">
                                        Non attivo
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="property-label">Lingue supportate *</label>
                                <input type="text" class="form-control" id="workflowLangs" placeholder="IT,EN,FR">
                                <small class="text-muted">Separare con virgola (es: IT,EN,FR)</small>
                            </div>
                            <div class="col-md-4">
                                <label class="property-label">Lingua predefinita *</label>
                                <input type="text" class="form-control" id="workflowDefLang" placeholder="IT">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="property-label">Descrizione</label>
                            <textarea class="form-control" id="workflowDescription" rows="4" placeholder="Descrizione del workflow"></textarea>
                        </div>
                    </div>
                    
                    <!-- Progetto e Utenti -->
                    <div class="property-section hide-if-new" id="projectSection">
                        <h5 class="mb-3">Progetto e Utenti</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="property-label">Progetto</label>
                                <div class="property-value" id="projectName">[Generico]</div>
                                
                                <label class="property-label mt-3">Utenti con accesso</label>
                                <table class="user-table" id="userTable">
                                    <thead>
                                        <tr>
                                            <th>Utente</th>
                                            <th>Ruolo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">Nessun utente</td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <button class="btn btn-sm btn-flussu mt-2" onclick="changeProject()" disabled id="changeProjectBtn">
                                    Cambia Progetto
                                </button>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="property-label">Backup</label>
                                <table class="backup-table" id="backupTable">
                                    <thead>
                                        <tr>
                                            <th width="50">Azioni</th>
                                            <th>Data/Ora</th>
                                        </tr>
                                    </thead>
                                    <tbody id="backupTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">Nessun backup disponibile</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Integrazioni -->
                    <div class="property-section hide-if-new" id="integrationsSection">
                        <h5 class="mb-3">Integrazioni</h5>
                        
                        <!-- Flussu APP -->
                        <div class="mb-4">
                            <h6 class="property-label">Flussu APP</h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="property-label">App Code</label>
                                    <input type="text" class="form-control" id="appCode" readonly>
                                    <button class="btn btn-sm btn-flussu mt-2" onclick="manageAppKeys()">
                                        Gestisci chiavi
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <div id="qrCodeContainer"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMS Gateway -->
                        <div class="mb-4">
                            <h6 class="property-label">SMS Gateway</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="property-label">Provider</label>
                                    <select class="form-control" id="smsProvider">
                                        <option value="">Nessuno</option>
                                        <option value="OVH">OVH</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="property-label">Chiavi</label>
                                    <button class="btn btn-sm btn-flussu" onclick="manageSmsKeys()">
                                        Gestisci chiavi SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Telegram -->
                        <div class="mb-4">
                            <h6 class="property-label">Telegram</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="property-label">Bot Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" class="form-control" id="telegramUser" placeholder="botusername">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="property-label">Bot Token</label>
                                    <input type="text" class="form-control" id="telegramKey" placeholder="Bot token">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Azioni -->
                    <div class="property-section">
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary btn-flussu" onclick="saveWorkflow()">
                                <i class="fas fa-save"></i> Salva
                            </button>
                            <button class="btn btn-warning hide-if-new" id="deleteBtn" onclick="deleteWorkflow()" disabled>
                                <i class="fas fa-trash"></i> Elimina
                            </button>
                            <button class="btn btn-info btn-flussu hide-if-new" id="importBtn" onclick="importWorkflow()">
                                <i class="fas fa-file-import"></i> Importa
                            </button>
                            <button class="btn btn-info btn-flussu hide-if-new" id="exportBtn" onclick="exportWorkflow()">
                                <i class="fas fa-file-export"></i> Esporta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/flussu-api.js"></script>
    <script>
        const api = new FlussuAPI();
        let currentUser = null;
        let workflowId = null;
        let isNew = false;
        let workflowData = null;
        
        
        // Inizializza pagina
        async function initPage() {
            try {
                // Ottieni WID dai parametri URL
                const urlParams = new URLSearchParams(window.location.search);
                workflowId = urlParams.get('wid');
                
                if (!workflowId) {
                    FlussuUI.showAlert('ID workflow mancante', 'danger');
                    setTimeout(() => window.location.href = 'workflows.php', 2000);
                    return;
                }
                
                isNew = workflowId === 'new';
                
                if (isNew) {
                    setupNewWorkflow();
                } else {
                    await loadWorkflow();
                }
                
            } catch (error) {
                console.error('Error initializing page:', error);
                FlussuUI.showAlert('Errore durante il caricamento della pagina', 'danger');
            }
        }
        
        // Setup per nuovo workflow
        function setupNewWorkflow() {
            document.getElementById('workflowId').textContent = 'NUOVO';
            document.getElementById('workflowLangs').value = 'IT,EN';
            document.getElementById('workflowDefLang').value = 'IT';
            document.getElementById('activeYes').checked = true;
            
            // Nascondi sezioni non applicabili
            document.querySelectorAll('.hide-if-new').forEach(el => {
                el.style.display = 'none';
            });
        }
        
        // Carica workflow esistente
        async function loadWorkflow() {
            try {
                const result = await api.getWorkflow(workflowId);
                
                if (result.success && result.workflow) {
                    workflowData = result.workflow;
                    
                    // Popola i campi
                    document.getElementById('workflowId').textContent = workflowData.wf_id || workflowId;
                    document.getElementById('workflowAuId').textContent = workflowData.wf_auid || '';
                    document.getElementById('workflowName').value = workflowData.wf_name || '';
                    document.getElementById('workflowDescription').value = workflowData.wf_description || '';
                    document.getElementById('workflowLangs').value = workflowData.supp_langs || 'IT';
                    document.getElementById('workflowDefLang').value = workflowData.lang || 'IT';
                    
                    // Stato attivo
                    if (workflowData.is_active == 1) {
                        document.getElementById('activeYes').checked = true;
                    } else {
                        document.getElementById('activeNo').checked = true;
                    }
                    
                    // Progetto
                    const projectName = workflowData.project_name || '[Generico]';
                    document.getElementById('projectName').textContent = projectName;
                    
                    // Telegram
                    if (workflowData.svc1) {
                        try {
                            const telegram = JSON.parse(workflowData.svc1);
                            document.getElementById('telegramUser').value = telegram.usr || '';
                            document.getElementById('telegramKey').value = telegram.key || '';
                        } catch (e) {
                            console.error('Error parsing Telegram data:', e);
                        }
                    }
                    
                    // Mostra sezioni nascoste
                    document.querySelectorAll('.hide-if-new').forEach(el => {
                        el.style.display = '';
                    });
                    
                    // Abilita pulsante elimina (solo per utenti autorizzati)
                    if (currentUser.c80_role === 1 || currentUser.c80_id === workflowData.wf_owner) {
                        document.getElementById('deleteBtn').disabled = false;
                    }
                    
                    // Carica dati aggiuntivi
                    loadWorkflowUsers();
                    loadWorkflowBackups();
                    
                } else {
                    FlussuUI.showAlert('Workflow non trovato', 'danger');
                    setTimeout(() => window.location.href = 'workflows.php', 2000);
                }
            } catch (error) {
                console.error('Error loading workflow:', error);
                FlussuUI.showAlert('Errore durante il caricamento del workflow', 'danger');
            }
        }
        
        // Carica utenti con accesso al workflow
        async function loadWorkflowUsers() {
            // TODO: Implementare API per ottenere lista utenti
            // Per ora mostra placeholder
            console.log('Loading workflow users...');
        }
        
        // Carica backup del workflow
        async function loadWorkflowBackups() {
            // TODO: Implementare API per ottenere lista backup
            // Per ora mostra placeholder
            console.log('Loading workflow backups...');
        }
        
        // Salva workflow
        async function saveWorkflow() {
            try {
                const name = document.getElementById('workflowName').value.trim();
                const description = document.getElementById('workflowDescription').value.trim();
                const langs = document.getElementById('workflowLangs').value.trim();
                const defLang = document.getElementById('workflowDefLang').value.trim();
                const isActive = document.getElementById('activeYes').checked ? 1 : 0;
                
                // Validazione
                if (!name) {
                    FlussuUI.showAlert('Inserire un nome per il workflow', 'warning');
                    return;
                }
                
                if (!langs) {
                    FlussuUI.showAlert('Inserire almeno una lingua supportata', 'warning');
                    return;
                }
                
                // Telegram (opzionale)
                const telegramUser = document.getElementById('telegramUser').value.trim();
                const telegramKey = document.getElementById('telegramKey').value.trim();
                let svc1 = '';
                if (telegramUser && telegramKey) {
                    svc1 = JSON.stringify({ usr: telegramUser, key: telegramKey });
                }
                
                const workflowUpdate = {
                    wf_name: name,
                    wf_description: description,
                    supp_langs: langs,
                    lang: defLang || langs.split(',')[0],
                    is_active: isActive,
                    svc1: svc1
                };
                
                if (isNew) {
                    // Crea nuovo workflow
                    const result = await api.createWorkflow(workflowUpdate);
                    if (result.success) {
                        FlussuUI.showAlert('Workflow creato con successo', 'success');
                        setTimeout(() => {
                            window.location.href = `workflow-properties.php?wid=${result.workflow.wf_id}`;
                        }, 1000);
                    } else {
                        FlussuUI.showAlert('Errore durante la creazione: ' + (result.error || 'Errore sconosciuto'), 'danger');
                    }
                } else {
                    // Aggiorna workflow esistente
                    if (!FlussuUI.confirm('Aggiornare il workflow?')) {
                        return;
                    }
                    
                    const result = await api.updateWorkflow(workflowId, workflowUpdate);
                    if (result.success) {
                        FlussuUI.showAlert('Workflow aggiornato con successo', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        FlussuUI.showAlert('Errore durante l\'aggiornamento: ' + (result.error || 'Errore sconosciuto'), 'danger');
                    }
                }
                
            } catch (error) {
                console.error('Error saving workflow:', error);
                FlussuUI.showAlert('Errore durante il salvataggio', 'danger');
            }
        }
        
        // Elimina workflow
        async function deleteWorkflow() {
            if (!FlussuUI.confirm('Sei sicuro di voler eliminare questo workflow? Questa azione non può essere annullata.')) {
                return;
            }
            
            try {
                const result = await api.deleteWorkflow(workflowId);
                if (result.success) {
                    FlussuUI.showAlert('Workflow eliminato con successo', 'success');
                    setTimeout(() => window.location.href = 'workflows.php', 1000);
                } else {
                    FlussuUI.showAlert('Errore durante l\'eliminazione: ' + (result.error || 'Errore sconosciuto'), 'danger');
                }
            } catch (error) {
                console.error('Error deleting workflow:', error);
                FlussuUI.showAlert('Errore durante l\'eliminazione', 'danger');
            }
        }
        
        // Naviga ad altre pagine
        function goToList() {
            window.location.href = 'workflows.php';
        }
        
        function goToStats() {
            window.location.href = `workflow-stats.php?wid=${workflowId}`;
        }
        
        function openEditor() {
            FlussuUI.showAlert('Apertura editor: ' + workflowId, 'info');
            // TODO: Implementare URL editor esterno
        }
        
        function goToSessions() {
            window.location.href = `workflow-sessions.php?wid=${workflowId}`;
        }
        
        function importWorkflow() {
            window.location.href = `workflow-import.php?wid=${workflowId}`;
        }
        
        function exportWorkflow() {
            window.location.href = `workflow-export.php?wid=${workflowId}`;
        }
        
        function changeProject() {
            FlussuUI.showAlert('Funzionalità in sviluppo', 'info');
        }
        
        function manageAppKeys() {
            FlussuUI.showAlert('Funzionalità in sviluppo', 'info');
        }
        
        function manageSmsKeys() {
            FlussuUI.showAlert('Funzionalità in sviluppo', 'info');
        }
        
        // Inizializza pagina
        initPage();
    </script>

<?php 
    require_once '../inc/footer.php'; 
?>