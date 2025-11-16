#!/bin/bash
# ============================================================================
# Flussu User Management System - Installation Script
# ============================================================================
# Copyright © 2025 Mille Isole SRL
# ============================================================================

echo "=========================================="
echo "Flussu User Management System v4.5.1"
echo "Installation Script"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "ℹ $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_warning "Please don't run as root"
    exit 1
fi

# Check prerequisites
echo "Checking prerequisites..."

# Check MySQL/MariaDB
if ! command -v mysql &> /dev/null; then
    print_error "MySQL/MariaDB not found. Please install it first."
    exit 1
fi
print_success "MySQL/MariaDB found"

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP not found. Please install PHP 7.4 or higher."
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if (( $(echo "$PHP_VERSION < 7.4" | bc -l) )); then
    print_error "PHP version $PHP_VERSION is too old. Please upgrade to 7.4 or higher."
    exit 1
fi
print_success "PHP $PHP_VERSION found"

echo ""
echo "=========================================="
echo "Database Configuration"
echo "=========================================="
echo ""

# Load .env file
if [ -f "../../.env" ]; then
    print_info "Loading database configuration from .env..."
    source <(grep -E '^(db_host|db_name|db_user|db_pass)=' ../../.env)
else
    print_warning ".env file not found. Please enter database credentials:"
    read -p "Database host [localhost]: " db_host
    db_host=${db_host:-localhost}

    read -p "Database name [flussu_db]: " db_name
    db_name=${db_name:-flussu_db}

    read -p "Database user [flussu_user]: " db_user
    db_user=${db_user:-flussu_user}

    read -sp "Database password: " db_pass
    echo ""
fi

# Test database connection
echo ""
print_info "Testing database connection..."
if mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" -e "SELECT 1;" &> /dev/null; then
    print_success "Database connection successful"
else
    print_error "Cannot connect to database. Please check your credentials."
    exit 1
fi

# Backup existing database
echo ""
print_info "Creating database backup..."
BACKUP_FILE="backup_pre_usermgmt_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Backup created: $BACKUP_FILE"
else
    print_error "Backup failed!"
    read -p "Continue without backup? (y/N): " continue_without_backup
    if [ "$continue_without_backup" != "y" ]; then
        exit 1
    fi
fi

# Execute SQL schema
echo ""
print_info "Installing user management schema..."
if mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" < user_management_schema.sql 2>/dev/null; then
    print_success "Database schema installed successfully"
else
    print_error "Schema installation failed!"
    print_warning "Please restore from backup: $BACKUP_FILE"
    exit 1
fi

# Verify installation
echo ""
print_info "Verifying installation..."

# Check roles table
ROLE_COUNT=$(mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT COUNT(*) FROM t90_role;" 2>/dev/null)
if [ "$ROLE_COUNT" -ge 4 ]; then
    print_success "Roles table populated ($ROLE_COUNT roles)"
else
    print_error "Roles table verification failed"
fi

# Check admin user
ADMIN_EXISTS=$(mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT COUNT(*) FROM t80_user WHERE c80_id=16;" 2>/dev/null)
if [ "$ADMIN_EXISTS" -eq 1 ]; then
    print_success "Admin user verified (ID=16)"
else
    print_warning "Admin user not found. Creating default admin..."
    mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" -e "
        INSERT INTO t80_user (c80_id, c80_email, c80_username, c80_password, c80_role, c80_name, c80_surname)
        VALUES (16, 'admin@example.com', 'admin', '', 1, 'System', 'Administrator')
        ON DUPLICATE KEY UPDATE c80_role=1;
    " 2>/dev/null
    print_success "Admin user created"
fi

# Check new tables
TABLES=("t88_wf_permissions" "t92_user_audit" "t94_user_sessions" "t96_user_invitations")
for table in "${TABLES[@]}"; do
    TABLE_EXISTS=$(mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SHOW TABLES LIKE '$table';" 2>/dev/null | wc -l)
    if [ "$TABLE_EXISTS" -eq 1 ]; then
        print_success "Table $table created"
    else
        print_error "Table $table not found"
    fi
done

# File permissions
echo ""
print_info "Setting file permissions..."

# Frontend files
if [ -d "../../webroot/flussu" ]; then
    chmod 644 ../../webroot/flussu/*.html 2>/dev/null
    chmod 644 ../../webroot/flussu/css/*.css 2>/dev/null
    chmod 644 ../../webroot/flussu/js/*.js 2>/dev/null
    print_success "Frontend file permissions set"
else
    print_warning "Frontend directory not found: ../../webroot/flussu"
fi

# API file
if [ -f "../../api/user-management.php" ]; then
    chmod 644 ../../api/user-management.php
    print_success "API file permissions set"
else
    print_warning "API file not found: ../../api/user-management.php"
fi

# Summary
echo ""
echo "=========================================="
echo "Installation Complete!"
echo "=========================================="
echo ""
print_success "User Management System installed successfully"
echo ""
echo "Next Steps:"
echo "  1. Access the frontend: http://yoursite.com/flussu/"
echo "  2. Login with:"
echo "     Username: admin"
echo "     Password: [empty - press Enter]"
echo "  3. You will be prompted to set a new password"
echo "  4. Read the documentation: USER_MANAGEMENT_README.md"
echo ""
echo "Default Admin User:"
echo "  ID: 16"
echo "  Username: admin"
echo "  Email: admin@example.com"
echo "  Role: System Administrator"
echo ""
print_warning "Remember to:"
echo "  - Change the admin password immediately"
echo "  - Update the admin email address"
echo "  - Configure your web server (Apache/Nginx)"
echo "  - Review the documentation for workflow setup"
echo ""
echo "Backup file: $BACKUP_FILE"
echo ""
print_info "For support: flussu@milleisole.com"
echo ""
