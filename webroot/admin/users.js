/* --------------------------------------------------------------------
 * Flussu v4.5 - User Management JavaScript
 * -------------------------------------------------------------------- */

// API Base URL
const API_URL = '../api.php';

// State
let users = [];
let currentUser = null;
let confirmCallback = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    loadUsers();
});

// Initialize all event listeners
function initializeEventListeners() {
    // New User button
    document.getElementById('btnNewUser').addEventListener('click', showNewUserModal);

    // Show deleted checkbox
    document.getElementById('chkShowDeleted').addEventListener('click', loadUsers);

    // Search input
    document.getElementById('searchInput').addEventListener('input', filterUsers);

    // User Form
    document.getElementById('userForm').addEventListener('submit', handleUserFormSubmit);
    document.getElementById('cancelBtn').addEventListener('click', hideUserModal);
    document.getElementById('closeModal').addEventListener('click', hideUserModal);

    // Password Form
    document.getElementById('passwordForm').addEventListener('submit', handlePasswordFormSubmit);
    document.getElementById('cancelPasswordBtn').addEventListener('click', hidePasswordModal);
    document.getElementById('closePasswordModal').addEventListener('click', hidePasswordModal);

    // Password strength indicator
    document.getElementById('newPassword').addEventListener('input', checkPasswordStrength);

    // Confirm password match
    document.getElementById('confirmPassword').addEventListener('input', checkPasswordMatch);

    // Confirm dialog
    document.getElementById('confirmCancel').addEventListener('click', hideConfirmDialog);

    // Close modals on outside click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            hideUserModal();
            hidePasswordModal();
            hideConfirmDialog();
        }
    });
}

// Load users from API
async function loadUsers() {
    showLoading();

    const includeDeleted = document.getElementById('chkShowDeleted').checked;

    try {
        const response = await fetch(`${API_URL}?action=list&includeDeleted=${includeDeleted}`);
        const data = await response.json();

        if (data.success) {
            users = data.data;
            renderUsers(users);
        } else {
            showToast(data.message || 'Failed to load users', 'error');
            showEmptyState();
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showToast('Error loading users', 'error');
        showEmptyState();
    } finally {
        hideLoading();
    }
}

// Render users table
function renderUsers(usersToRender) {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '';

    if (usersToRender.length === 0) {
        showEmptyState();
        return;
    }

    hideEmptyState();

    usersToRender.forEach(user => {
        const tr = document.createElement('tr');
        if (user.deleted) {
            tr.classList.add('deleted');
        }

        const statusBadge = user.deleted
            ? '<span class="status-badge deleted">Deleted</span>'
            : '<span class="status-badge active">Active</span>';

        const actions = user.deleted
            ? `<div class="action-buttons">
                <button class="btn btn-sm btn-secondary" onclick="restoreUser(${user.id})">Restore</button>
               </div>`
            : `<div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">Edit</button>
                <button class="btn btn-sm btn-secondary" onclick="changeUserPassword(${user.id})">Password</button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">Delete</button>
               </div>`;

        tr.innerHTML = `
            <td>${user.id}</td>
            <td>${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.name || '')}</td>
            <td>${escapeHtml(user.surname || '')}</td>
            <td>${user.role}</td>
            <td>${formatDate(user.created)}</td>
            <td>${statusBadge}</td>
            <td>${actions}</td>
        `;

        tbody.appendChild(tr);
    });
}

// Filter users based on search
function filterUsers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();

    if (!searchTerm) {
        renderUsers(users);
        return;
    }

    const filtered = users.filter(user =>
        user.username.toLowerCase().includes(searchTerm) ||
        user.email.toLowerCase().includes(searchTerm) ||
        (user.name && user.name.toLowerCase().includes(searchTerm)) ||
        (user.surname && user.surname.toLowerCase().includes(searchTerm))
    );

    renderUsers(filtered);
}

// Show new user modal
function showNewUserModal() {
    currentUser = null;
    document.getElementById('modalTitle').textContent = 'New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('passwordGroup').style.display = 'block';
    showModal('userModal');
}

// Edit user
async function editUser(userId) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                userId: userId
            })
        });

        const params = new URLSearchParams();
        params.append('action', 'get');

        const getResponse = await fetch(`${API_URL}?${params}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ userId: userId })
        });

        const data = await getResponse.json();

        if (data.success) {
            currentUser = data.data;
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = currentUser.id;
            document.getElementById('username').value = currentUser.username;
            document.getElementById('email').value = currentUser.email;
            document.getElementById('name').value = currentUser.name || '';
            document.getElementById('surname').value = currentUser.surname || '';
            document.getElementById('passwordGroup').style.display = 'none';
            showModal('userModal');
        } else {
            showToast(data.message || 'Failed to load user', 'error');
        }
    } catch (error) {
        console.error('Error loading user:', error);
        showToast('Error loading user', 'error');
    }
}

// Handle user form submit
async function handleUserFormSubmit(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const userData = Object.fromEntries(formData.entries());

    const isEdit = !!userData.userId;
    const action = isEdit ? 'update' : 'create';

    try {
        const params = new URLSearchParams();
        params.append('action', action);

        const response = await fetch(`${API_URL}?${params}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });

        const data = await response.json();

        if (data.success) {
            showToast(isEdit ? 'User updated successfully' : 'User created successfully', 'success');

            // Show temporary password if generated
            if (data.temporaryPassword) {
                showToast(`Temporary password: ${data.temporaryPassword}`, 'warning', 10000);
            }

            hideUserModal();
            loadUsers();
        } else {
            showToast(data.message || 'Failed to save user', 'error');
        }
    } catch (error) {
        console.error('Error saving user:', error);
        showToast('Error saving user', 'error');
    }
}

// Delete user
function deleteUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;

    showConfirmDialog(
        'Delete User',
        `Are you sure you want to delete user "${user.username}"? This action can be reversed.`,
        async () => {
            try {
                const params = new URLSearchParams();
                params.append('action', 'delete');

                const response = await fetch(`${API_URL}?${params}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ userId: userId })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('User deleted successfully', 'success');
                    loadUsers();
                } else {
                    showToast(data.message || 'Failed to delete user', 'error');
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                showToast('Error deleting user', 'error');
            }
        }
    );
}

// Restore user
function restoreUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;

    showConfirmDialog(
        'Restore User',
        `Are you sure you want to restore user "${user.username}"?`,
        async () => {
            try {
                const params = new URLSearchParams();
                params.append('action', 'restore');

                const response = await fetch(`${API_URL}?${params}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ userId: userId })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('User restored successfully', 'success');
                    loadUsers();
                } else {
                    showToast(data.message || 'Failed to restore user', 'error');
                }
            } catch (error) {
                console.error('Error restoring user:', error);
                showToast('Error restoring user', 'error');
            }
        }
    );
}

// Change user password
function changeUserPassword(userId) {
    document.getElementById('passwordForm').reset();
    document.getElementById('passwordUserId').value = userId;
    showModal('passwordModal');
}

// Handle password form submit
async function handlePasswordFormSubmit(event) {
    event.preventDefault();

    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const userId = document.getElementById('passwordUserId').value;
    const temporary = document.getElementById('temporaryPassword').checked;

    if (newPassword !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    try {
        const params = new URLSearchParams();
        params.append('action', 'changePassword');

        const response = await fetch(`${API_URL}?${params}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                userId: userId,
                newPassword: newPassword,
                temporary: temporary
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Password changed successfully', 'success');
            hidePasswordModal();
        } else {
            showToast(data.message || 'Failed to change password', 'error');
        }
    } catch (error) {
        console.error('Error changing password:', error);
        showToast('Error changing password', 'error');
    }
}

// Check password strength
async function checkPasswordStrength() {
    const password = document.getElementById('newPassword').value;
    const strengthIndicator = document.getElementById('passwordStrength');

    if (!password) {
        strengthIndicator.className = 'password-strength';
        return;
    }

    try {
        const params = new URLSearchParams();
        params.append('action', 'validatePassword');

        const response = await fetch(`${API_URL}?${params}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ password: password })
        });

        const data = await response.json();

        if (data.success && data.data) {
            strengthIndicator.className = `password-strength ${data.data.strength}`;
        }
    } catch (error) {
        console.error('Error validating password:', error);
    }
}

// Check password match
function checkPasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const confirmInput = document.getElementById('confirmPassword');

    if (confirmPassword && newPassword !== confirmPassword) {
        confirmInput.setCustomValidity('Passwords do not match');
    } else {
        confirmInput.setCustomValidity('');
    }
}

// Modal functions
function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function hideUserModal() {
    document.getElementById('userModal').classList.remove('show');
}

function hidePasswordModal() {
    document.getElementById('passwordModal').classList.remove('show');
}

function showConfirmDialog(title, message, callback) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    confirmCallback = callback;

    document.getElementById('confirmOk').onclick = function() {
        if (confirmCallback) {
            confirmCallback();
        }
        hideConfirmDialog();
    };

    showModal('confirmDialog');
}

function hideConfirmDialog() {
    document.getElementById('confirmDialog').classList.remove('show');
    confirmCallback = null;
}

// Loading and empty states
function showLoading() {
    document.getElementById('loadingState').style.display = 'block';
    document.querySelector('.table-container').style.display = 'none';
}

function hideLoading() {
    document.getElementById('loadingState').style.display = 'none';
    document.querySelector('.table-container').style.display = 'block';
}

function showEmptyState() {
    document.getElementById('emptyState').style.display = 'block';
    document.querySelector('.table-container').style.display = 'none';
}

function hideEmptyState() {
    document.getElementById('emptyState').style.display = 'none';
}

// Toast notification
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString || dateString === '1899-12-31 00:00:00') return '-';

    const date = new Date(dateString);
    return date.toLocaleDateString('it-IT', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}
