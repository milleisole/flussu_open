# User Management Module

## Overview

This module provides comprehensive user management functionality for Flussu, including user CRUD operations and password management.

## Directory Structure

```
src/Flussu/Users/
├── UserManager.php       # Handles user CRUD operations
├── PasswordManager.php   # Handles password operations
└── README.md            # This file
```

## Components

### UserManager.php

Manages user data operations:

- `getAllUsers(bool $includeDeleted = false)` - Get all users
- `getUserById(int $userId)` - Get user by ID
- `createUser(array $userData)` - Create new user
- `updateUser(int $userId, array $userData)` - Update user data
- `deleteUser(int $userId, int $deletedBy = 0)` - Soft delete user
- `restoreUser(int $userId)` - Restore deleted user
- `permanentlyDeleteUser(int $userId)` - Permanently delete user

### PasswordManager.php

Manages password operations:

- `changePassword($userId, string $newPassword, bool $temporary = false)` - Change user password
- `validatePasswordStrength(string $password)` - Validate password strength
- `mustChangePassword($userId)` - Check if user must change password
- `verifyCurrentPassword($userId, string $currentPassword)` - Verify current password
- `changePasswordWithVerification($userId, string $currentPassword, string $newPassword)` - Change password with verification
- `generateTemporaryPassword(int $length = 12)` - Generate temporary password
- `resetPasswordToTemporary($userId)` - Reset password to temporary

## API Endpoints

Location: `src/Flussu/Api/V40/UsersApi.php`

### Available Actions

All requests should be sent to `/api.php?action={action_name}` with POST data as JSON.

#### List Users
```
GET /api.php?action=list&includeDeleted=false
Response: { success: true, data: [...users] }
```

#### Get User
```
POST /api.php?action=get
Body: { userId: 123 }
Response: { success: true, data: {...user} }
```

#### Create User
```
POST /api.php?action=create
Body: {
  username: "john_doe",
  email: "john@example.com",
  name: "John",
  surname: "Doe",
  role: 0,
  password: "optional" // If omitted, generates temporary password
}
Response: {
  success: true,
  message: "User created successfully",
  userId: 123,
  temporaryPassword: "..." // If password was auto-generated
}
```

#### Update User
```
POST /api.php?action=update
Body: {
  userId: 123,
  email: "newemail@example.com",
  name: "John",
  surname: "Doe"
}
Response: { success: true, message: "User updated successfully" }
```

#### Delete User
```
POST /api.php?action=delete
Body: { userId: 123 }
Response: { success: true, message: "User deleted successfully" }
```

#### Restore User
```
POST /api.php?action=restore
Body: { userId: 123 }
Response: { success: true, message: "User restored successfully" }
```

#### Change Password
```
POST /api.php?action=changePassword
Body: {
  userId: 123,
  newPassword: "NewSecurePass123!",
  temporary: false,
  currentPassword: "optional" // If provided, will verify before changing
}
Response: { success: true, message: "Password changed successfully" }
```

#### Reset Password
```
POST /api.php?action=resetPassword
Body: { userId: 123 }
Response: {
  success: true,
  message: "Password reset successfully",
  temporaryPassword: "..."
}
```

#### Validate Password
```
POST /api.php?action=validatePassword
Body: { password: "TestPassword123!" }
Response: {
  success: true,
  data: {
    valid: true,
    message: "Password is valid",
    strength: "strong" // weak, medium, or strong
  }
}
```

## Front-end

Location: `webroot/admin/`

Files:
- `users.html` - Main user management interface
- `users.css` - Styling
- `users.js` - JavaScript functionality

### Features

- User list with search
- Create/Edit users
- Change passwords with strength indicator
- Soft delete/restore users
- Show/hide deleted users
- Responsive design
- Toast notifications
- Modal dialogs

### Usage

1. Open `/admin/users.html` in your browser
2. Log in with admin credentials (authentication required)
3. Use the interface to manage users

## Password Requirements

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

Password strength levels:
- **Weak**: Does not meet all requirements
- **Medium**: Meets all requirements, 8-11 characters
- **Strong**: Meets all requirements, 12+ characters

## Security

- All API endpoints require authentication
- Soft delete preserves data integrity
- Password strength validation
- Temporary passwords force change on next login
- Current password verification for password changes

## Database Schema

Users are stored in the `t80_user` table with the following fields:

- `c80_id` - User ID (auto-increment)
- `c80_username` - Username (unique)
- `c80_email` - Email address (unique)
- `c80_password` - Hashed password
- `c80_pwd_chng` - Password change date
- `c80_role` - User role
- `c80_name` - First name
- `c80_surname` - Last name
- `c80_created` - Creation timestamp
- `c80_modified` - Last modification timestamp
- `c80_deleted` - Soft delete timestamp
- `c80_deleted_by` - ID of user who deleted

## Migration Notes

The `PasswordManager` class has been moved from `src/Flussu/Api/V40/` to `src/Flussu/Users/` to better organize user-related functionality.

## Version

- Version: 4.5.20250929
- Last Updated: 16.11.2025

## License

Apache License 2.0 - See main LICENSE.md for details
