/**
 * --------------------------------------------------------------------
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------
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
 * --------------------------------------------------------------------
 * 
 * FlussuAPI - Client JavaScript per API Flussu
 * Basato sulle chiamate presenti nei blade Laravel originali
 * 
 * --------------------------------------------------------------------
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------
 */

class FlussuAPI {
    constructor(baseUrl = '') {
        // Se non specificato, usa il dominio corrente
        this.baseUrl = baseUrl || window.location.origin;
        this.apiVersion = 'v2.0'; // Versione API da blade
        this.authKey = null; // AUK (Authentication Key)
        this.currentUser = null;
    }

    /**
     * Ottiene l'API Key di autenticazione
     * Nei blade viene passata come <?php echo $authApiCall ?>
     * In questa implementazione può essere passata dal PHP o recuperata dalla configurazione
     */
    getAuthKey() {
        return this.authKey;
    }

    /**
     * Imposta l'API Key di autenticazione
     */
    setAuthKey(key) {
        this.authKey = key;
    }

    /**
     * Imposta l'utente corrente (passato da PHP)
     * L'utente è già in sessione PHP, non serve chiamata API
     */
    setCurrentUser(userData) {
        this.currentUser = userData;
        return { success: true, user: this.currentUser };
    }

    /**
     * Ottiene l'utente corrente (già impostato da PHP)
     */
    getCurrentUser() {
        if (this.currentUser) {
            return { success: true, user: this.currentUser };
        }
        return { success: false, error: 'User not set' };
    }

    /**
     * Ottiene la lista dei workflow dell'utente
     * Basato su: $.getJSON("{{$flussusrv}}/api/v2.0/flow?auk=<?php echo $authApiCall ?>&C=US")
     */
    async getUserWorkflows() {
        try {
            const auk = this.getAuthKey();
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=US${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // I blade si aspettano un array direttamente
            return {
                success: true,
                workflows: Array.isArray(data) ? data : []
            };
        } catch (error) {
            console.error('Get workflows error:', error);
            return { success: false, error: error.message, workflows: [] };
        }
    }

    /**
     * Ottiene i dettagli di un workflow specifico
     * Basato su: getJSON(origin+"/api/v2.0/flow?C=G&auk=<?php echo $authApiCall ?>&WID="+wid,"getWofoData")
     */
    async getWorkflow(workflowId) {
        try {
            const auk = this.getAuthKey();
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=G&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Estrae il workflow dalla risposta
            let workflow = null;
            if (data.workflow && Array.isArray(data.workflow)) {
                workflow = data.workflow[0];
            } else if (data.workflow) {
                workflow = data.workflow;
            } else {
                workflow = data;
            }
            
            return {
                success: true,
                workflow: workflow
            };
        } catch (error) {
            console.error('Get workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Crea un nuovo workflow
     * Basato su: sendJSON(server+"/api/v2.0/flow?auk=<?php echo $authApiCall ?>&C=C&N="+WF.name,false,"getNewWF")
     */
    async createWorkflow(workflowData) {
        try {
            const auk = this.getAuthKey();
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=C&N=${encodeURIComponent(workflowData.wf_name)}${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(workflowData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Verifica errori
            if (data.result && data.result.startsWith('ERR')) {
                throw new Error(data.message || data.result);
            }
            
            return {
                success: true,
                workflow: data
            };
        } catch (error) {
            console.error('Create workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Aggiorna un workflow esistente
     * Basato su: sendJSON(server+"/api/v2.0/flow?auk=<?php echo $authApiCall ?>&C=U&WID="+wid,true,null)
     */
    async updateWorkflow(workflowId, workflowData) {
        try {
            const auk = this.getAuthKey();
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=U&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(workflowData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Verifica errori
            if (data.result && data.result.startsWith('ERR')) {
                throw new Error(data.message || data.result);
            }
            
            return {
                success: true,
                data: data
            };
        } catch (error) {
            console.error('Update workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Elimina un workflow
     * Basato su: sendJSON(server+"/api/v2.0/flow?auk=<?php echo $authApiCall ?>&C=D&WID="+wid,true,null)
     */
    async deleteWorkflow(workflowId) {
        try {
            const auk = this.getAuthKey();
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=D&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(null)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Verifica errori
            if (data.result && data.result.startsWith('ERR')) {
                throw new Error(data.message || data.result);
            }
            
            return {
                success: true,
                data: data
            };
        } catch (error) {
            console.error('Delete workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Esporta un workflow (per export page)
     * Basato su: getJSON(origin+"/api/v2.0/flow?C=E&auk=<?php echo $authApiCall ?>&WID="+wid,"getWofoData")
     */
    async exportWorkflow(workflowId, backupId = null) {
        try {
            const auk = this.getAuthKey();
            let url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=E&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            
            // Se è specificato un backup
            if (backupId && parseInt(backupId) > 0) {
                url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=PDU&DT=BK&BID=${backupId}&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            }
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            return {
                success: true,
                workflow: data.workflow ? data.workflow[0] : data,
                raw: await response.text() // Per il download
            };
        } catch (error) {
            console.error('Export workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Importa un workflow
     * Basato su: sendJSON(origin+"/api/v2.0/flow?C="+Which+"&auk=<?php echo $authApiCall ?>&WID="+wid,false,rawData,"sentResult")
     * Which può essere:
     * - IS: Import Substitute (sostituisce)
     * - IN: Import New (nuovo)
     */
    async importWorkflow(workflowId, rawData, mode = 'import') {
        try {
            const auk = this.getAuthKey();
            const command = mode === 'substitute' ? 'IS' : 'IN';
            const url = `${this.baseUrl}/api/${this.apiVersion}/flow?C=${command}&WID=${workflowId}${auk ? '&auk=' + auk : ''}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: rawData // rawData è già una stringa JSON
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Verifica errori
            if (data.result && data.result.startsWith('ERR')) {
                throw new Error(data.message || data.result);
            }
            
            return {
                success: true,
                data: data
            };
        } catch (error) {
            console.error('Import workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Ottiene le statistiche degli utenti (solo admin)
     * Basato su chiamata implicita nei blade
     */
    async getUserStats() {
        try {
            // TODO: Implementare endpoint specifico per statistiche utenti
            return {
                success: true,
                stats: []
            };
        } catch (error) {
            console.error('Get user stats error:', error);
            return { success: false, error: error.message, stats: [] };
        }
    }

    /**
     * Ottiene le sessioni di un workflow
     * Basato su: $.getJSON(origin+"/api/v2.0/sess?auk=<?php echo $authApiCall ?>&WID="+wid)
     */
    async getWorkflowSessions(workflowId = null) {
        try {
            const auk = this.getAuthKey();
            let url = `${this.baseUrl}/api/${this.apiVersion}/sess?${auk ? 'auk=' + auk : ''}`;
            
            if (workflowId) {
                url += `&WID=${workflowId}`;
            }
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            return {
                success: true,
                sessions: Array.isArray(data) ? data : []
            };
        } catch (error) {
            console.error('Get sessions error:', error);
            return { success: false, error: error.message, sessions: [] };
        }
    }

    /**
     * Helper: POST generico
     */
    async post(url, data = null) {
        const response = await fetch(this.baseUrl + url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: data ? JSON.stringify(data) : null
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }

    /**
     * Helper: GET generico
     */
    async get(url) {
        const response = await fetch(this.baseUrl + url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
}

/**
 * FlussuUI - Helper per l'interfaccia utente
 */
class FlussuUI {
    /**
     * Mostra un alert/notifica
     */
    static showAlert(message, type = 'info') {
        // Usa Bootstrap alert se disponibile
        if (typeof bootstrap !== 'undefined') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Inserisce in cima al main-content
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.insertBefore(alertDiv, mainContent.firstChild);
                
                // Auto-remove dopo 5 secondi
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            } else {
                alert(message);
            }
        } else {
            // Fallback su alert nativo
            alert(message);
        }
    }

    /**
     * Mostra un confirm
     */
    static confirm(message) {
        return window.confirm(message);
    }

    /**
     * Escape HTML per prevenire XSS
     */
    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Ottiene un badge per lo stato attivo/inattivo
     */
    static getStatusBadge(isActive) {
        if (isActive == 1 || isActive === true || isActive === 'true') {
            return '<span class="badge bg-success">Attivo</span>';
        } else {
            return '<span class="badge bg-secondary">Non attivo</span>';
        }
    }

    /**
     * Formatta una data
     */
    static formatDate(dateString) {
        if (!dateString) return '-';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Formatta un numero
     */
    static formatNumber(num) {
        if (num === null || num === undefined) return '0';
        return num.toLocaleString('it-IT');
    }
}

// Esporta per uso in moduli (se supportato)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FlussuAPI, FlussuUI };
}