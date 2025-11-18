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
 *      Pagina per l'importazione del workflow da file .flussu
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once '../inc/includebase.php';
require_once '../inc/header.php';
?>
    <style>
        .import-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .file-upload-area {
            background: #f8f9fa;
            padding: 20px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-area:hover {
            border-color: #188d4d;
            background: #f0f8f4;
        }
        
        .file-upload-area.drag-over {
            border-color: #188d4d;
            background: #e6f4ec;
        }
        
        .json-preview {
            background: #f8f9fa;
            padding: 20px;
            border: 2px inset #f0f0f0;
            border-radius: 4px;
            max-height: 30vh;
            overflow: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .warning-box h5 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .import-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #0066cc;
            margin: 20px 0;
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
        
        #importButtons {
            display: none;
        }
    </style>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-2 col-md-3">
                    <div class="card">
                        <div class="card-body sidebar-actions">
                            <h5>Workflow Import</h5>
                            <button class="btn btn-secondary btn-flussu" onclick="goBack()">
                                <i class="fas fa-arrow-left"></i> Indietro
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenuto principale -->
                <div class="col-lg-10 col-md-9">
                    <!-- Header -->
                    <div class="import-container mb-3">
                        <h4>
                            <span class="text-muted">Workflow</span> 
                            <span id="workflowId">-</span>
                        </h4>
                        <h5 id="workflowName">-</h5>
                    </div>
                    
                    <!-- Avviso -->
                    <div class="warning-box">
                        <h5><i class="fas fa-exclamation-triangle"></i> Attenzione</h5>
                        <ul class="mb-0">
                            <li><strong>Sostituisci</strong>: Sostituisce completamente il workflow corrente con quello importato (sovrascrive tutti i dati)</li>
                            <li><strong>Importa</strong>: Crea un nuovo workflow con i dati importati (il workflow corrente rimane invariato)</li>
                        </ul>
                    </div>
                    
                    <!-- Area upload -->
                    <div class="import-container mb-3">
                        <h5>Seleziona file da importare</h5>
                        <p class="text-muted">File .flussu (formato JSON)</p>
                        
                        <div class="file-upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                            <p class="mb-2">Trascina qui il file .flussu oppure</p>
                            <input type="file" id="fileInput" accept=".flussu" style="display:none">
                            <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-folder-open"></i> Scegli file
                            </button>
                        </div>
                    </div>
                    
                    <!-- Anteprima file importato -->
                    <div class="import-container" id="previewSection" style="display:none;">
                        <div class="import-info">
                            <h5>Workflow da importare</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>ID:</strong> <span id="importWfId">-</span>
                                </div>
                                <div class="col-md-8">
                                    <strong>Nome:</strong> <span id="importWfName">-</span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <strong>Descrizione:</strong> <span id="importWfDesc">-</span>
                            </div>
                        </div>
                        
                        <h5>Anteprima JSON</h5>
                        <div class="json-preview" id="jsonPreview"></div>
                        
                        <div id="importButtons" class="mt-3">
                            <button class="btn btn-warning" id="substituteBtn" onclick="importWorkflow('substitute')">
                                <i class="fas fa-exchange-alt"></i> Sostituisci Workflow
                            </button>
                            <button class="btn btn-primary btn-flussu" id="importBtn" onclick="importWorkflow('import')">
                                <i class="fas fa-file-import"></i> Importa come Nuovo
                            </button>
                            <button class="btn btn-secondary" onclick="resetImport()">
                                <i class="fas fa-times"></i> Annulla
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
        let workflowId = null;
        let importedData = null;
        let importedRawData = null;
        
        // Verifica autenticazione
        if (!api.isAuthenticated()) {
            window.location.href = 'login.php';
        }
        
        // Logout
        document.getElementById('logoutBtn').addEventListener('click', async () => {
            if (FlussuUI.confirm('Sei sicuro di voler uscire?')) {
                await api.logout();
                window.location.href = 'login.php';
            }
        });
        
        // Inizializza pagina
        async function initPage() {
            try {
                // Ottieni WID dai parametri URL
                const urlParams = new URLSearchParams(window.location.search);
                workflowId = urlParams.get('wid');
                
                if (!workflowId || workflowId === 'new') {
                    FlussuUI.showAlert('ID workflow non valido', 'danger');
                    setTimeout(() => window.location.href = 'workflows.php', 2000);
                    return;
                }
                
                // Carica utente corrente
                const userResult = await api.getCurrentUser();
                if (userResult.success) {
                    const currentUser = userResult.user;
                    document.getElementById('userDisplayName').textContent = 
                        `${currentUser.c80_name || ''} ${currentUser.c80_surname || ''}`.trim() || 
                        currentUser.c80_username;
                    
                    if (currentUser.c80_role === 1) {
                        document.getElementById('usersLink').style.display = 'block';
                    }
                }
                
                // Carica info workflow corrente
                await loadCurrentWorkflow();
                
                // Setup file input
                setupFileInput();
                
            } catch (error) {
                console.error('Error initializing page:', error);
                FlussuUI.showAlert('Errore durante il caricamento della pagina', 'danger');
            }
        }
        
        // Carica workflow corrente
        async function loadCurrentWorkflow() {
            try {
                const result = await api.getWorkflow(workflowId);
                
                if (result.success && result.workflow) {
                    document.getElementById('workflowId').textContent = result.workflow.wf_id || workflowId;
                    document.getElementById('workflowName').textContent = result.workflow.wf_name || 'Senza nome';
                }
            } catch (error) {
                console.error('Error loading workflow:', error);
            }
        }
        
        // Setup file input e drag & drop
        function setupFileInput() {
            const fileInput = document.getElementById('fileInput');
            const uploadArea = document.getElementById('uploadArea');
            
            // File input change
            fileInput.addEventListener('change', handleFileSelect);
            
            // Drag & drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelect();
                }
            });
        }
        
        // Gestione selezione file
        function handleFileSelect() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if (!file) return;
            
            // Verifica estensione
            if (!file.name.endsWith('.flussu')) {
                FlussuUI.showAlert('File non valido. Selezionare un file .flussu', 'warning');
                return;
            }
            
            // Leggi file
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    // Il contenuto è in base64
                    const base64Content = e.target.result.split(',')[1];
                    importedRawData = base64Content;
                    
                    // Decodifica base64
                    const decodedData = atob(decodeURIComponent(escape(atob(base64Content))));
                    importedData = JSON.parse(decodedData);
                    
                    // Visualizza anteprima
                    showPreview(importedData);
                    
                } catch (error) {
                    console.error('Error reading file:', error);
                    FlussuUI.showAlert('Errore durante la lettura del file. Assicurarsi che sia un file .flussu valido.', 'danger');
                }
            };
            
            reader.readAsDataURL(file);
        }
        
        // Mostra anteprima dati importati
        function showPreview(data) {
            // Estrai workflow dai dati
            let workflow = data;
            if (data.workflow && Array.isArray(data.workflow)) {
                workflow = data.workflow[0];
            }
            
            // Verifica validità
            if (!workflow || !workflow.name) {
                FlussuUI.showAlert('File non valido: workflow non trovato', 'danger');
                return;
            }
            
            // Mostra info workflow
            document.getElementById('importWfId').textContent = workflow.wid || '-';
            document.getElementById('importWfName').textContent = workflow.name || '-';
            document.getElementById('importWfDesc').textContent = workflow.description || '-';
            
            // Mostra anteprima JSON
            const preview = document.getElementById('jsonPreview');
            preview.innerHTML = formatJSONPreview(workflow);
            
            // Mostra sezione preview e pulsanti
            document.getElementById('previewSection').style.display = 'block';
            document.getElementById('importButtons').style.display = 'block';
        }
        
        // Formatta JSON per anteprima
        function formatJSONPreview(obj) {
            let html = '';
            
            for (let key in obj) {
                if (obj.hasOwnProperty(key)) {
                    const value = obj[key];
                    
                    html += '<div style="margin:5px 0;">';
                    html += `<span style="color:green;font-weight:bold">${FlussuUI.escapeHtml(key)}:</span> `;
                    
                    if (typeof value === 'object' && value !== null) {
                        html += '<span style="color:blue">[Object/Array]</span>';
                    } else {
                        const strValue = String(value);
                        if (strValue.length > 100) {
                            html += `<span>${FlussuUI.escapeHtml(strValue.substring(0, 100))}...</span>`;
                        } else {
                            html += `<span>${FlussuUI.escapeHtml(strValue)}</span>`;
                        }
                    }
                    
                    html += '</div>';
                }
            }
            
            return html;
        }
        
        // Importa workflow
        async function importWorkflow(mode) {
            if (!importedData || !importedRawData) {
                FlussuUI.showAlert('Nessun dato da importare', 'warning');
                return;
            }
            
            const modeText = mode === 'substitute' ? 'sostituire' : 'importare';
            const confirmMsg = mode === 'substitute' 
                ? 'Sei sicuro di voler SOSTITUIRE il workflow corrente? Tutti i dati attuali saranno sovrascritti!'
                : 'Sei sicuro di voler importare questo workflow come nuovo?';
            
            if (!FlussuUI.confirm(confirmMsg)) {
                return;
            }
            
            try {
                // Disabilita pulsanti
                document.getElementById('substituteBtn').disabled = true;
                document.getElementById('importBtn').disabled = true;
                
                // TODO: Implementare chiamata API per import
                // Nel blade originale:
                // - mode='substitute' usa C=IS (Import Substitute)
                // - mode='import' usa C=IN (Import New)
                
                FlussuUI.showAlert(`Importazione in modalità "${modeText}" in corso...`, 'info');
                
                // Esempio di chiamata (da implementare nell'API):
                // const result = await api.importWorkflow(workflowId, importedRawData, mode);
                
                // Per ora mostra messaggio temporaneo
                setTimeout(() => {
                    FlussuUI.showAlert('Funzionalità di import in sviluppo', 'warning');
                    document.getElementById('substituteBtn').disabled = false;
                    document.getElementById('importBtn').disabled = false;
                }, 1000);
                
            } catch (error) {
                console.error('Error importing workflow:', error);
                FlussuUI.showAlert('Errore durante l\'importazione', 'danger');
                document.getElementById('substituteBtn').disabled = false;
                document.getElementById('importBtn').disabled = false;
            }
        }
        
        // Reset import
        function resetImport() {
            document.getElementById('fileInput').value = '';
            document.getElementById('previewSection').style.display = 'none';
            importedData = null;
            importedRawData = null;
        }
        
        // Torna indietro
        function goBack() {
            window.location.href = `workflow-properties.php?wid=${workflowId}`;
        }
        
        // Inizializza pagina
        initPage();
    </script>

<?php 
    require_once '../inc/footer.php'; 
?>