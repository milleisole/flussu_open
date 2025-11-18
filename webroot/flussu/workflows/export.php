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
 *      Pagina per l'esportazione del workflow in formato JSON
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once '../inc/includebase.php';
require_once '../inc/header.php';
?>
    <style>
        .export-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .json-viewer {
            background: #f8f9fa;
            padding: 20px;
            border: 2px inset #f0f0f0;
            border-radius: 4px;
            max-height: 80vh;
            overflow: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .json-key {
            color: green;
            font-weight: bold;
        }
        
        .json-value {
            color: black;
        }
        
        .json-block {
            color: blue;
            cursor: pointer;
        }
        
        .json-code {
            color: white;
            background: black;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
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
    </style>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-2 col-md-3">
                    <div class="card">
                        <div class="card-body sidebar-actions">
                            <h5>Workflow Export</h5>
                            <button class="btn btn-secondary btn-flussu" onclick="goBack()">
                                <i class="fas fa-arrow-left"></i> Indietro
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenuto principale -->
                <div class="col-lg-10 col-md-9">
                    <!-- Header -->
                    <div class="export-container mb-3">
                        <h4>
                            <span class="text-muted">Workflow</span> 
                            <span id="workflowId">-</span>
                        </h4>
                        <h5 id="workflowName">-</h5>
                        <p class="text-muted" id="workflowDescription">-</p>
                    </div>
                    
                    <!-- JSON Viewer -->
                    <div class="export-container">
                        <div class="d-flex justify-between align-center mb-3">
                            <h5>JSON Format</h5>
                            <button class="btn btn-primary" onclick="downloadJSON()" id="downloadBtn" disabled>
                                <i class="fas fa-download"></i> Scarica File .flussu
                            </button>
                        </div>
                        
                        <div class="json-viewer" id="jsonViewer">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Caricamento...
                            </div>
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
        let workflowData = null;
        let rawJSON = null;
        
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
                const backupId = urlParams.get('b') || 0;
                const backupDate = urlParams.get('bd');
                
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
                
                // Carica workflow per export
                await loadWorkflowForExport(backupId, backupDate);
                
            } catch (error) {
                console.error('Error initializing page:', error);
                FlussuUI.showAlert('Errore durante il caricamento della pagina', 'danger');
            }
        }
        
        // Carica workflow per export
        async function loadWorkflowForExport(backupId, backupDate) {
            try {
                // TODO: Chiamare API specifica per export (C=E nel blade originale)
                // Per ora usiamo l'API standard
                const result = await api.getWorkflow(workflowId);
                
                if (result.success && result.workflow) {
                    workflowData = result.workflow;
                    rawJSON = JSON.stringify(workflowData, null, 2);
                    
                    // Aggiorna header
                    document.getElementById('workflowId').textContent = workflowData.wf_id || workflowId;
                    document.getElementById('workflowName').textContent = workflowData.wf_name || 'Senza nome';
                    document.getElementById('workflowDescription').textContent = workflowData.wf_description || 'Nessuna descrizione';
                    
                    // Se è un backup, mostra info
                    if (backupId && parseInt(backupId) > 0) {
                        document.getElementById('workflowDescription').textContent += 
                            ` (Backup-${backupId}${backupDate ? ' - ' + unescape(backupDate) : ''})`;
                    }
                    
                    // Visualizza JSON formattato
                    displayFormattedJSON(workflowData);
                    
                    // Abilita download
                    document.getElementById('downloadBtn').disabled = false;
                    
                } else {
                    FlussuUI.showAlert('Workflow non trovato', 'danger');
                    document.getElementById('jsonViewer').innerHTML = 
                        '<div class="text-center text-danger">Errore durante il caricamento del workflow</div>';
                }
            } catch (error) {
                console.error('Error loading workflow:', error);
                FlussuUI.showAlert('Errore durante il caricamento', 'danger');
                document.getElementById('jsonViewer').innerHTML = 
                    '<div class="text-center text-danger">Errore durante il caricamento</div>';
            }
        }
        
        // Visualizza JSON formattato
        function displayFormattedJSON(data) {
            const viewer = document.getElementById('jsonViewer');
            viewer.innerHTML = formatJSON(data, 0, '');
        }
        
        // Formatta JSON in HTML
        function formatJSON(obj, indent, prefix) {
            let html = '';
            const indentPx = indent * 5;
            
            if (typeof obj === 'object' && obj !== null) {
                for (let key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        const value = obj[key];
                        
                        html += `<div style="margin-left:${indentPx}px; border-top:solid 1px silver; padding:5px 0;">`;
                        
                        if (typeof value === 'object' && value !== null) {
                            html += `<span class="json-key">${FlussuUI.escapeHtml(key)}:</span>`;
                            
                            if (key === 'blocks') {
                                prefix = 'B';
                            }
                            
                            html += formatJSON(value, indent + 1, prefix);
                        } else {
                            // Gestione valori speciali
                            if (key === 'exec' && value && value.length > 0) {
                                html += `<span class="json-key">${FlussuUI.escapeHtml(key)}:</span>`;
                                html += `<pre class="json-code">${FlussuUI.escapeHtml(value)}</pre>`;
                            } else if (key === 'exit_dir' && value && value.length > 5) {
                                html += `<span class="json-key">${FlussuUI.escapeHtml(key)}:</span> `;
                                html += `<span class="json-block" onclick="scrollToBlock('${value}')">${FlussuUI.escapeHtml(value)}</span>`;
                            } else if (prefix === 'B' && typeof value === 'string') {
                                // ID blocco
                                html += `<span class="json-key">${FlussuUI.escapeHtml(key)}:</span> `;
                                html += `<span id="block_${value}" class="json-value" style="color:blue">${FlussuUI.escapeHtml(value)}</span>`;
                            } else {
                                html += `<span class="json-key">${FlussuUI.escapeHtml(key)}:</span> `;
                                html += `<span class="json-value">${FlussuUI.escapeHtml(String(value))}</span>`;
                            }
                        }
                        
                        html += '</div>';
                    }
                }
            }
            
            return html;
        }
        
        // Scroll a blocco specifico
        function scrollToBlock(blockId) {
            const element = document.getElementById('block_' + blockId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.style.backgroundColor = '#ffff99';
                setTimeout(() => {
                    element.style.backgroundColor = '';
                }, 2000);
            }
        }
        
        // Download JSON come file .flussu
        function downloadJSON() {
            if (!rawJSON || !workflowData) {
                FlussuUI.showAlert('Dati non disponibili per il download', 'warning');
                return;
            }
            
            try {
                // Codifica in Base64
                const base64Data = btoa(unescape(encodeURIComponent(rawJSON)));
                const dataStr = 'data:text/json;charset=base64,' + base64Data;
                
                // Crea link download
                const downloadLink = document.createElement('a');
                downloadLink.setAttribute('href', dataStr);
                downloadLink.setAttribute('download', `Export.${workflowId}.flussu`);
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                
                FlussuUI.showAlert('File scaricato con successo', 'success');
            } catch (error) {
                console.error('Error downloading file:', error);
                FlussuUI.showAlert('Errore durante il download', 'danger');
            }
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