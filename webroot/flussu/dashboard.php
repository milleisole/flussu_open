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
 *      This is the main entrance to the Flussu Server, a PHP script
 *      to handle all the requests to this server. 
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.09.2025
 * --------------------------------------------------------------------*/

<<<<<<< HEAD
require_once 'inc/includebase.php';
=======
define('PROJECT_ROOT', dirname(__DIR__, 2)."/");

require_once PROJECT_ROOT . 'vendor/autoload.php';

use Flussu\Persons\User;
use Flussu\General;
use Flussu\Config;

$dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
$dotenv->load();

if (!function_exists('config')) {
    function config(string $key,$default=null) {
        return Config::init()->get($key,$default);
    }
}

$FVP=explode(".", config("flussu.version","5.0").".".config("flussu.release","0"));
$v=$FVP[0];
$m=$FVP[1];

// Avvia sessione
session_start();

// Verifica se l'utente è autenticato
if (!isset($_SESSION['flussu_logged_in']) || $_SESSION['flussu_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Ottieni dati utente dalla sessione
$userId = $_SESSION['flussu_user_id'] ?? 0;
$username = $_SESSION['flussu_username'] ?? '';
$userEmail = $_SESSION['flussu_email'] ?? '';
$userDisplayName = trim(($_SESSION['flussu_name'] ?? '') . ' ' . ($_SESSION['flussu_surname'] ?? ''));
if (empty($userDisplayName)) {
    $userDisplayName = $username;
}
>>>>>>> 355b2a08918d807d3517d6ce2c39239ab1ede32e

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flussu - Dashboard</title>
    <link rel="stylesheet" href="css/flussu-admin.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.html" class="logo">FLUSSU</a>

                <nav>
                    <ul class="nav-menu">
                        <li><a href="dashboard.html" class="active">Dashboard</a></li>
                        <li><a href="users.html" id="usersLink" style="display:none;">Utenti</a></li>
                        <li><a href="workflows.html">Workflow</a></li>
                    </ul>
                </nav>

                <div class="user-info">
<<<<<<< HEAD
                    <span id="userDisplayName">...</span>
                    <button class="btn btn-secondary btn-sm" id="logoutBtn">Esci</button>
=======
                    <span id="userDisplayName"><?php echo htmlspecialchars($userDisplayName); ?></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Esci</a>
>>>>>>> 355b2a08918d807d3517d6ce2c39239ab1ede32e
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h1 class="page-title">Dashboard</h1>

            <!-- Stats Cards -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-label">Workflow Attivi</div>
                    <div class="stat-value" id="statActiveWorkflows">-</div>
                </div>
                <div class="stat-card" id="statUsersCard" style="display:none;">
                    <div class="stat-label">Utenti Totali</div>
                    <div class="stat-value" id="statTotalUsers">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">I Miei Workflow</div>
                    <div class="stat-value" id="statMyWorkflows">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sessioni Attive</div>
                    <div class="stat-value" id="statActiveSessions">-</div>
                </div>
            </div>

            <!-- Recent Workflows -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-between align-center">
                        <span>I Miei Workflow</span>
                        <a href="workflows.html" class="btn btn-primary btn-sm">Vedi tutti</a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table" id="workflowsTable">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Descrizione</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="workflowsTableBody">
                            <tr>
                                <td colspan="4" class="text-center">Caricamento...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity (for admins) -->
            <div class="card" id="activityCard" style="display:none;">
                <div class="card-header">Attività Recente</div>
                <div class="card-body">
                    <table class="table" id="activityTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Utente</th>
                                <th>Azione</th>
                                <th>Target</th>
                            </tr>
                        </thead>
                        <tbody id="activityTableBody">
                            <tr>
                                <td colspan="4" class="text-center">Nessuna attività recente</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="js/flussu-api.js"></script>
    <script>
        const api = new FlussuAPI();
        let currentUser = null;

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

        // Carica dati iniziali
        async function loadDashboard() {
            try {
                // Carica utente corrente
                const userResult = await api.getCurrentUser();
                if (userResult.success) {
                    currentUser = userResult.user;
                    document.getElementById('userDisplayName').textContent =
                        `${currentUser.c80_name || ''} ${currentUser.c80_surname || ''}`.trim() ||
                        currentUser.c80_username;

                    // Mostra link utenti solo per admin
                    if (currentUser.c80_role === 1) {
                        document.getElementById('usersLink').style.display = 'block';
                        document.getElementById('statUsersCard').style.display = 'block';
                        document.getElementById('activityCard').style.display = 'block';
                        loadUserStats();
                    }
                }

                // Carica workflow
                await loadWorkflows();

            } catch (error) {
                console.error('Error loading dashboard:', error);
                FlussuUI.showAlert('Errore durante il caricamento della dashboard', 'danger');
            }
        }

        // Carica workflow
        async function loadWorkflows() {
            try {
                const result = await api.getUserWorkflows();

                if (result.success) {
                    const workflows = result.workflows || [];

                    // Aggiorna statistiche
                    const activeWorkflows = workflows.filter(w => w.is_active);
                    document.getElementById('statActiveWorkflows').textContent = activeWorkflows.length;
                    document.getElementById('statMyWorkflows').textContent = workflows.length;

                    // Popola tabella (solo primi 5)
                    const tbody = document.getElementById('workflowsTableBody');
                    tbody.innerHTML = '';

                    if (workflows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nessun workflow trovato</td></tr>';
                        return;
                    }

                    workflows.slice(0, 5).forEach(workflow => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${FlussuUI.escapeHtml(workflow.wf_name)}</td>
                            <td>${FlussuUI.escapeHtml(workflow.wf_description || '-')}</td>
                            <td>${FlussuUI.getStatusBadge(workflow.is_active)}</td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="openWorkflow('${workflow.wf_id}')">
                                    Apri
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Error loading workflows:', error);
                document.getElementById('workflowsTableBody').innerHTML =
                    '<tr><td colspan="4" class="text-center text-danger">Errore durante il caricamento</td></tr>';
            }
        }

        // Carica statistiche utenti (solo admin)
        async function loadUserStats() {
            try {
                const result = await api.getUserStats();

                if (result.success) {
                    const stats = result.stats || [];
                    const totalUsers = stats.reduce((sum, s) => sum + parseInt(s.active_count || 0), 0);
                    document.getElementById('statTotalUsers').textContent = totalUsers;
                }
            } catch (error) {
                console.error('Error loading user stats:', error);
            }
        }

        // Funzione per aprire workflow
        function openWorkflow(workflowId) {
            // TODO: Implementare redirect all'editor workflow
            FlussuUI.showAlert('Apertura workflow: ' + workflowId, 'info');
        }

        // Inizializza dashboard
        loadDashboard();

        // Aggiorna sessioni attive (placeholder)
        document.getElementById('statActiveSessions').textContent = '1';
    </script>
</body>
</html>
