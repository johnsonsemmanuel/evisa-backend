#!/bin/bash

# MySQL Migration Script for eVisa System
# This script automates the migration from SQLite to MySQL

set -e  # Exit on error

echo "🚀 eVisa System - MySQL Migration Script"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if MySQL is installed
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}❌ MySQL is not installed${NC}"
    echo "Please install MySQL first:"
    echo "  Ubuntu/Debian: sudo apt install mysql-server"
    echo "  macOS: brew install mysql"
    exit 1
fi

echo -e "${GREEN}✓${NC} MySQL is installed"

# Check if MySQL is running
if ! mysqladmin ping -h localhost --silent; then
    echo -e "${YELLOW}⚠${NC}  MySQL is not running. Attempting to start..."
    
    # Try to start MySQL
    if command -v systemctl &> /dev/null; then
        sudo systemctl start mysql
    elif command -v brew &> /dev/null; then
        brew services start mysql
    else
        echo -e "${RED}❌ Could not start MySQL. Please start it manually${NC}"
        exit 1
    fi
    
    sleep 2
    
    if ! mysqladmin ping -h localhost --silent; then
        echo -e "${RED}❌ MySQL failed to start${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}✓${NC} MySQL is running"

# Prompt for database credentials
echo ""
echo "Database Configuration:"
read -p "MySQL root password (press Enter if no password): " -s MYSQL_ROOT_PASSWORD
echo ""
read -p "Database name [evisa_system]: " DB_NAME
DB_NAME=${DB_NAME:-evisa_system}

read -p "Create dedicated database user? (y/n) [n]: " CREATE_USER
CREATE_USER=${CREATE_USER:-n}

if [[ $CREATE_USER == "y" ]]; then
    read -p "Database username [evisa_user]: " DB_USER
    DB_USER=${DB_USER:-evisa_user}
    read -p "Database password: " -s DB_PASSWORD
    echo ""
else
    DB_USER="root"
    DB_PASSWORD=$MYSQL_ROOT_PASSWORD
fi

# Create database
echo ""
echo "Creating database..."

if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
        echo -e "${RED}❌ Failed to create database. Check your MySQL root access${NC}"
        exit 1
    }
else
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
        echo -e "${RED}❌ Failed to create database. Check your password${NC}"
        exit 1
    }
fi

echo -e "${GREEN}✓${NC} Database '$DB_NAME' created"

# Create user if requested
if [[ $CREATE_USER == "y" ]]; then
    echo "Creating database user..."
    
    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        mysql -u root -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';" 2>/dev/null
        mysql -u root -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';" 2>/dev/null
        mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null
    else
        mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';" 2>/dev/null
        mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';" 2>/dev/null
        mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;" 2>/dev/null
    fi
    
    echo -e "${GREEN}✓${NC} User '$DB_USER' created with full privileges"
fi

# Update .env file
echo ""
echo "Updating .env configuration..."

# Backup current .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
echo -e "${GREEN}✓${NC} Backed up .env file"

# Update database configuration
sed -i.bak "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i.bak "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i.bak "s/^DB_PORT=.*/DB_PORT=3306/" .env
sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i.bak "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i.bak "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

# Remove backup file created by sed
rm -f .env.bak

echo -e "${GREEN}✓${NC} Updated .env configuration"

# Clear Laravel caches
echo ""
echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
echo -e "${GREEN}✓${NC} Caches cleared"

# Run migrations
echo ""
echo "Running database migrations..."
php artisan migrate:fresh --force

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Migrations completed successfully"
else
    echo -e "${RED}❌ Migration failed${NC}"
    exit 1
fi

# Seed database
echo ""
echo "Seeding database..."

echo "  - Seeding Phase 1 data..."
php artisan db:seed --class=Phase1Seeder --force

echo "  - Seeding reason codes..."
php artisan db:seed --class=ReasonCodeSeeder --force

echo "  - Seeding border verification users..."
php artisan db:seed --class=BorderVerificationUsersSeeder --force

echo -e "${GREEN}✓${NC} Database seeded successfully"

# Verify installation
echo ""
echo "Verifying installation..."

REASON_CODES=$(php artisan tinker --execute="echo App\Models\ReasonCode::count();")
APPLICATIONS=$(php artisan tinker --execute="echo App\Models\Application::count();")
USERS=$(php artisan tinker --execute="echo App\Models\User::count();")

echo "  - Reason codes: $REASON_CODES"
echo "  - Applications: $APPLICATIONS"
echo "  - Users: $USERS"

# Run comprehensive test
echo ""
echo "Running comprehensive tests..."
php artisan test:reason-codes-admin

echo ""
echo -e "${GREEN}=========================================="
echo "✅ Migration to MySQL completed successfully!"
echo "==========================================${NC}"
echo ""
echo "Database Details:"
echo "  - Host: 127.0.0.1"
echo "  - Port: 3306"
echo "  - Database: $DB_NAME"
echo "  - Username: $DB_USER"
echo ""
echo "Next Steps:"
echo "  1. Test your application: php artisan serve"
echo "  2. Run frontend: cd ../frontend && npm run dev"
echo "  3. Check admin dashboard for comprehensive data"
echo ""
echo "Backup files created:"
echo "  - .env.backup.* (your old configuration)"
echo ""
