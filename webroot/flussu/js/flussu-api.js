/**
 * Flussu API Client
 * Gestione chiamate API per il sistema di gestione utenti
 */

class FlussuAPI {
    constructor(baseUrl = '/api/flussu') {
        this.baseUrl = baseUrl;
        this.apiKey = this.getStoredApiKey();
        this.sessionId = this.getStoredSessionId();
    }

    /**
     * Esegui chiamata API
     */
    async request(endpoint, method = 'GET', data = null) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json'
        };

        if (this.apiKey) {
            headers['X-API-Key'] = this.apiKey;
        }

        if (this.sessionId) {
            headers['X-Session-ID'] = this.sessionId;
        }

        const options = {
            method,
            headers
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Errore nella richiesta');
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * Login
     */
    async login(username, password) {
        const result = await this.request('/auth/login', 'POST', { username, password });

        if (result.success) {
            this.setApiKey(result.api_key);
            this.setSessionId(result.session_id);
        }

        return result;
    }

    /**
     * Logout
     */
    async logout() {
        try {
            await this.request('/auth/logout', 'POST');
        } finally {
            this.clearAuth();
        }
    }

    /**
     * Ottieni utente corrente
     */
    async getCurrentUser() {
        return await this.request('/auth/me');
    }

    /**
     * Users - Get All
     */
    async getUsers(includeDeleted = false) {
        return await this.request(`/users?includeDeleted=${includeDeleted}`);
    }

    /**
     * Users - Get By ID
     */
    async getUser(userId) {
        return await this.request(`/users/${userId}`);
    }

    /**
     * Users - Create
     */
    async createUser(userData) {
        return await this.request('/users', 'POST', userData);
    }

    /**
     * Users - Update
     */
    async updateUser(userId, userData) {
        return await this.request(`/users/${userId}`, 'PUT', userData);
    }

    /**
     * Users - Set Status (Enable/Disable)
     */
    async setUserStatus(userId, active) {
        return await this.request(`/users/${userId}/status`, 'PUT', { active });
    }

    /**
     * Users - Change Password
     */
    async changePassword(userId, newPassword, temporary = false) {
        return await this.request(`/users/${userId}/password`, 'PUT', { newPassword, temporary });
    }

    /**
     * Users - Get Stats
     */
    async getUserStats() {
        return await this.request('/users/stats');
    }

    /**
     * Roles - Get All
     */
    async getRoles() {
        return await this.request('/roles');
    }

    /**
     * Workflows - Get User Workflows
     */
    async getUserWorkflows(userId = null) {
        const endpoint = userId ? `/workflows/user/${userId}` : '/workflows/me';
        return await this.request(endpoint);
    }

    /**
     * Workflows - Get Permissions
     */
    async getWorkflowPermissions(workflowId) {
        return await this.request(`/workflows/${workflowId}/permissions`);
    }

    /**
     * Workflows - Grant Permission
     */
    async grantWorkflowPermission(workflowId, userId, permission) {
        return await this.request(`/workflows/${workflowId}/permissions`, 'POST', {
            userId,
            permission
        });
    }

    /**
     * Workflows - Revoke Permission
     */
    async revokeWorkflowPermission(workflowId, userId) {
        return await this.request(`/workflows/${workflowId}/permissions/${userId}`, 'DELETE');
    }

    /**
     * Invitations - Create
     */
    async createInvitation(email, role, expiresInDays = 7) {
        return await this.request('/invitations', 'POST', { email, role, expiresInDays });
    }

    /**
     * Invitations - Validate
     */
    async validateInvitation(invitationCode) {
        return await this.request(`/invitations/validate/${invitationCode}`);
    }

    /**
     * Invitations - Accept
     */
    async acceptInvitation(invitationCode, userData) {
        return await this.request(`/invitations/accept/${invitationCode}`, 'POST', userData);
    }

    /**
     * Invitations - Get Pending
     */
    async getPendingInvitations() {
        return await this.request('/invitations/pending');
    }

    /**
     * Audit - Get User Logs
     */
    async getUserLogs(userId, limit = 100, offset = 0) {
        return await this.request(`/audit/users/${userId}?limit=${limit}&offset=${offset}`);
    }

    /**
     * Audit - Get Usage Stats
     */
    async getUsageStats(startDate = null, endDate = null) {
        let url = '/audit/stats';
        const params = [];

        if (startDate) params.push(`startDate=${startDate}`);
        if (endDate) params.push(`endDate=${endDate}`);

        if (params.length > 0) {
            url += '?' + params.join('&');
        }

        return await this.request(url);
    }

    /**
     * Storage - API Key
     */
    setApiKey(apiKey) {
        this.apiKey = apiKey;
        localStorage.setItem('flussu_api_key', apiKey);
    }

    getStoredApiKey() {
        return localStorage.getItem('flussu_api_key');
    }

    /**
     * Storage - Session ID
     */
    setSessionId(sessionId) {
        this.sessionId = sessionId;
        localStorage.setItem('flussu_session_id', sessionId);
    }

    getStoredSessionId() {
        return localStorage.getItem('flussu_session_id');
    }

    /**
     * Clear Auth
     */
    clearAuth() {
        this.apiKey = null;
        this.sessionId = null;
        localStorage.removeItem('flussu_api_key');
        localStorage.removeItem('flussu_session_id');
    }

    /**
     * Check if authenticated
     */
    isAuthenticated() {
        return !!this.apiKey && !!this.sessionId;
    }
}

/**
 * UI Helper Functions
 */
const FlussuUI = {
    /**
     * Mostra alert
     */
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.5s';
            setTimeout(() => alertDiv.remove(), 500);
        }, 3000);
    },

    /**
     * Mostra loading spinner
     */
    showLoading(container) {
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        spinner.id = 'loading-spinner';

        if (typeof container === 'string') {
            container = document.querySelector(container);
        }

        if (container) {
            container.appendChild(spinner);
        }
    },

    /**
     * Nascondi loading spinner
     */
    hideLoading() {
        const spinner = document.getElementById('loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    },

    /**
     * Formatta data
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Ottieni badge HTML per ruolo
     */
    getRoleBadge(roleId, roleName) {
        const badges = {
            0: { class: 'secondary', text: roleName || 'Utente' },
            1: { class: 'danger', text: roleName || 'Admin' },
            2: { class: 'success', text: roleName || 'Editor' },
            3: { class: 'info', text: roleName || 'Viewer' }
        };

        const badge = badges[roleId] || badges[0];
        return `<span class="badge badge-${badge.class}">${badge.text}</span>`;
    },

    /**
     * Ottieni badge HTML per stato
     */
    getStatusBadge(isActive) {
        return isActive
            ? '<span class="badge badge-success">Attivo</span>'
            : '<span class="badge badge-danger">Disattivato</span>';
    },

    /**
     * Conferma azione
     */
    confirm(message) {
        return window.confirm(message);
    },

    /**
     * Mostra modal
     */
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    },

    /**
     * Nascondi modal
     */
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    },

    /**
     * Sanitize HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Export per utilizzo globale
if (typeof window !== 'undefined') {
    window.FlussuAPI = FlussuAPI;
    window.FlussuUI = FlussuUI;
}
