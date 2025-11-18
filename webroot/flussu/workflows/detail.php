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
 *      Pagina di gestione e visualizzazione dei workflow
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*/

require_once '../inc/includebase.php';
require_once '../inc/header.php';
?>
    <style>
        .workflow-actions {
            display: flex;
            gap: 5px;
        }
        
        .workflow-actions .btn {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .workflow-name {
            cursor: pointer;
            color: #188d4d;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .workflow-name:hover {
            text-decoration: underline;
        }
        
        .project-group {
            color: #6A5ACD;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .generic-group {
            color: #707070;
        }
        
        .sidebar-actions {
            position: sticky;
            top: 20px;
        }
        
        .sidebar-actions .btn {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>

    <main class="main-content">
        <div class="container-fluid">
            <h1 class="page-title">I Miei Workflow</h1>
            
            <div class="row">
                <!-- Sidebar con azioni -->
                <div class="col-lg-2 col-md-3">
                    <div class="card">
                        <div class="card-body sidebar-actions">
                            <h5>Workflow Editor</h5>
                            <p class="text-muted small">Gestisci i tuoi workflow</p>
                            
                            <button class="btn btn-success" onclick="createNewWorkflow()">
                                <i class="fas fa-plus"></i> Nuovo Workflow
                            </button>
                            
                            <button class="btn btn-primary" onclick="executeWorkflow()">
                                <i class="fas fa-play"></i> Esegui
                            </button>
                            
                            <button class="btn btn-info" onclick="viewSessions()">
                                <i class="fas fa-list"></i> Sessioni
                            </button>
                            
                            <button class="btn btn-secondary" onclick="window.location.href='index.php'">
                                <i class="fas fa-arrow-left"></i> Dashboard
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenuto principale -->
                <div class="col-lg-10 col-md-9">
                    <div class="table-container">
                        <!-- Toolbar -->
                        <div class="d-flex justify-between align-center mb-3">
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshWorkflows()">
                                    <i class="fas fa-sync-alt"></i> Aggiorna
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleGrouping()">
                                    <i class="fas fa-layer-group"></i> Raggruppa
                                </button>
                            </div>
                            <div>
                                <span class="badge badge-primary" id="workflowCount">0 workflow</span>
                            </div>
                        </div>
                        
                        <!-- Tabella workflow -->
                        <table id="workflowsTable" 
                               data-toggle="table"
                               data-show-fullscreen="true"
                               data-search="true"
                               data-sticky-header="true"
                               data-group-by="true"
                               data-group-by-field="proj"
                               data-group-by-formatter="groupFormatter">
                            <thead style="background:#188d4d;color:#fff">
                                <tr>
                                    <th data-field="actions" data-formatter="actionsFormatter" data-width="150">Azioni</th>
                                    <th data-field="name" data-formatter="nameFormatter" data-sortable="true">Nome</th>
                                    <th data-field="description" data-sortable="true">Descrizione</th>
                                    <th data-field="supp_langs" data-width="100">Lingue</th>
                                    <th data-field="is_active" data-formatter="activeFormatter" data-width="80" data-sortable="true">Stato</th>
                                    <th data-field="proj" data-visible="false">Progetto</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/flussu-api.js"></script>
    <script>
        const api = new FlussuAPI();
        let currentUser = null;
        let allWorkflows = [];
        let groupingEnabled = true;
        
        
        // Inizializza pagina
        async function initPage() {
        }
        
        // Carica workflow
        async function loadWorkflows() {
            try {
                const result = await api.getUserWorkflows();
                
                if (result.success) {
                    allWorkflows = result.workflows || [];
                    
                    // Aggiungi campo "proj" per raggruppamento (se non presente)
                    allWorkflows.forEach(workflow => {
                        if (!workflow.proj) {
                            workflow.proj = workflow.project_name || '';
                        }
                    });
                    
                    // Aggiorna contatore
                    document.getElementById('workflowCount').textContent = 
                        `${allWorkflows.length} workflow${allWorkflows.length !== 1 ? 's' : ''}`;
                    
                    // Inizializza Bootstrap Table
                    const $table = $('#workflowsTable');
                    $table.bootstrapTable('destroy');
                    $table.bootstrapTable({
                        data: allWorkflows,
                        showFullscreen: true,
                        search: true,
                        stickyHeader: true,
                        groupByToggle: groupingEnabled,
                        groupByShowToggleIcon: true
                    });
                }
            } catch (error) {
                console.error('Error loading workflows:', error);
                FlussuUI.showAlert('Errore durante il caricamento dei workflow', 'danger');
            }
        }
        
        // Formatter per le azioni
        function actionsFormatter(value, row, index) {
            return `
                <div class="workflow-actions">
                    <button class="btn btn-sm btn-outline-primary" 
                            onclick="viewWorkflowProperties('${row.wf_id}')" 
                            title="Proprietà">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-info" 
                            onclick="viewWorkflowStats('${row.wf_id}')" 
                            title="Statistiche">
                        <i class="fas fa-chart-line"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" 
                            onclick="openWorkflowEditor('${row.wf_id}')" 
                            title="Editor">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            `;
        }
        
        // Formatter per il nome (clickable)
        function nameFormatter(value, row, index) {
            const isProjectWorkflow = row.prid && parseInt(row.prid) > 0;
            const indent = isProjectWorkflow ? 'padding-left: 30px;' : '';
            
            return `
                <div style="${indent}">
                    <span class="workflow-name" onclick="openWorkflowEditor('${row.wf_id}')">
                        ${FlussuUI.escapeHtml(value)}
                    </span>
                </div>
            `;
        }
        
        // Formatter per lo stato attivo
        function activeFormatter(value, row, index) {
            return FlussuUI.getStatusBadge(value);
        }
        
        // Formatter per il raggruppamento
        function groupFormatter(value, idx, data) {
            const color = value ? '#6A5ACD' : '#707070';
            const label = value || '[Generici]';
            return `<strong class="project-group" style="color:${color}">${FlussuUI.escapeHtml(label)}</strong>`;
        }
        
        // Azioni sui workflow
        function createNewWorkflow() {
            window.location.href = 'workflow-properties.php?wid=new';
        }
        
        function viewWorkflowProperties(workflowId) {
            window.location.href = `workflow-properties.php?wid=${workflowId}`;
        }
        
        function viewWorkflowStats(workflowId) {
            window.location.href = `workflow-stats.php?wid=${workflowId}`;
        }
        
        function openWorkflowEditor(workflowId) {
            // TODO: Implementare apertura editor
            // Per ora mostra alert
            FlussuUI.showAlert('Apertura editor workflow: ' + workflowId, 'info');
            // Esempio di URL per editor esterno (come nel blade):
            // const editorUrl = `https://editor.flussu.com/home/${workflowId}/edit?...`;
            // window.location.href = editorUrl;
        }
        
        function executeWorkflow() {
            window.location.href = 'workflow-execute.php';
        }
        
        function viewSessions() {
            window.location.href = 'workflow-sessions.php';
        }
        
        function refreshWorkflows() {
            loadWorkflows();
        }
        
        function toggleGrouping() {
            groupingEnabled = !groupingEnabled;
            const $table = $('#workflowsTable');
            $table.bootstrapTable('destroy');
            $table.bootstrapTable({
                data: allWorkflows,
                showFullscreen: true,
                search: true,
                stickyHeader: true,
                groupByToggle: groupingEnabled,
                groupByShowToggleIcon: true
            });
        }
        
        // Inizializza pagina
        initPage();
    </script>

<?php 
    require_once '../inc/footer.php'; 
?>