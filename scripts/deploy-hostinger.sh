#!/bin/bash

# Hostinger Deployment Script
# This script prepares and deploys the application to Hostinger shared hosting

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="jahangirautos"
BACKUP_DIR="./backups/$(date +%Y%m%d_%H%M%S)"
DEPLOY_DIR="./deployment"

echo -e "${BLUE}🚀 Starting deployment to Hostinger...${NC}"

# Check if required environment variables are set
if [ -z "$HOSTINGER_FTP_SERVER" ] || [ -z "$HOSTINGER_FTP_USERNAME" ] || [ -z "$HOSTINGER_FTP_PASSWORD" ]; then
    echo -e "${RED}❌ Error: Hostinger FTP credentials not configured${NC}"
    echo "Please set the following environment variables:"
    echo "  HOSTINGER_FTP_SERVER"
    echo "  HOSTINGER_FTP_USERNAME"
    echo "  HOSTINGER_FTP_PASSWORD"
    exit 1
fi

# Create backup directory
echo -e "${YELLOW}📦 Creating backup directory: $BACKUP_DIR${NC}"
mkdir -p "$BACKUP_DIR"

# Clean previous deployment
echo -e "${YELLOW}🧹 Cleaning previous deployment files...${NC}"
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"

# Create optimized deployment package
echo -e "${YELLOW}📁 Preparing deployment package...${NC}"

# Copy essential application files
echo "Copying application files..."
rsync -av --progress \
    --exclude='vendor/' \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='node_modules/' \
    --exclude='*.log' \
    --exclude='debug_log.txt' \
    --exclude='.vscode/' \
    --exclude='*.tmp' \
    --exclude='test_*.php' \
    --exclude='composer.*' \
    --exclude='package.*' \
    --exclude='README.md' \
    . "$DEPLOY_DIR/"

# Copy composer files if they exist
if [ -f "composer.json" ] || [ -f "composer.lock" ]; then
    echo "Copying composer files..."
    cp composer.* "$DEPLOY_DIR/" 2>/dev/null || true
fi

# Environment configuration
echo -e "${YELLOW}⚙️  Configuring environment...${NC}"

# Set proper file permissions
echo "Setting file permissions..."
find "$DEPLOY_DIR" -type d -exec chmod 755 {} \;
find "$DEPLOY_DIR" -type f -exec chmod 644 {} \;

# Set executable permissions for PHP files in actions directory
find "$DEPLOY_DIR/actions" -name "*.php" -exec chmod 755 {} \;
find "$DEPLOY_DIR/scripts" -name "*.php" -exec chmod 755 {} \; 2>/dev/null || true

# Database preparation
echo -e "${YELLOW}🗄️  Preparing database...${NC}"

# Check if database SQL files exist
if [ -f "database/schema.sql" ]; then
    echo "Database schema file found"
else
    echo -e "${YELLOW}⚠️  Warning: database/schema.sql not found${NC}"
fi

# Remove sensitive information from deployment package
echo -e "${YELLOW}🔒 Securing deployment package...${NC}"
rm -f "$DEPLOY_DIR/config/.htaccess" 2>/dev/null || true

# Check for sensitive files
SENSITIVE_FILES=(
    "config/db.example.php"
    "config/db.local.php"
    ".env"
    ".env.local"
)

for file in "${SENSITIVE_FILES[@]}"; do
    if [ -f "$DEPLOY_DIR/$file" ]; then
        echo "Removing sensitive file: $file"
        rm -f "$DEPLOY_DIR/$file"
    fi
done

# Verify deployment package
echo -e "${YELLOW}🔍 Verifying deployment package...${NC}"
echo "Deployment package size: $(du -sh $DEPLOY_DIR | cut -f1)"
echo "Files to deploy: $(find $DEPLOY_DIR -type f | wc -l)"

# Show deployment summary
echo -e "${BLUE}📋 Deployment Summary:${NC}"
echo "  Application: $APP_NAME"
echo "  Deployment Directory: $DEPLOY_DIR"
echo "  Backup Directory: $BACKUP_DIR"
echo "  Target: Hostinger ($HOSTINGER_FTP_SERVER)"

# Deployment options
echo -e "${BLUE}🚀 Deployment Options:${NC}"
echo "1. Manual FTP upload (recommended for shared hosting)"
echo "2. Automatic FTP upload (requires FTP credentials)"
echo "3. Create deployment ZIP package"

read -p "Select deployment method (1-3): " choice

case $choice in
    1)
        echo -e "${GREEN}📁 Manual FTP Upload Instructions:${NC}"
        echo "1. Connect to your Hostinger cPanel FTP or use an FTP client"
        echo "2. Navigate to public_html or your domain's root directory"
        echo "3. Upload all files from: $DEPLOY_DIR"
        echo "4. Set file permissions as needed"
        echo "5. Run database migrations manually if required"
        ;;
    2)
        echo -e "${YELLOW}📡 Starting automatic FTP upload...${NC}"
        # This would use lftp or similar tools for automatic upload
        echo "Automatic FTP upload requires additional tools and configuration"
        echo "For now, please use manual upload or configure with lftp"
        ;;
    3)
        ZIP_FILE="${APP_NAME}_deployment_$(date +%Y%m%d_%H%M%S).zip"
        echo -e "${YELLOW}📦 Creating ZIP package: $ZIP_FILE${NC}"
        cd "$DEPLOY_DIR"
        zip -r "../$ZIP_FILE" .
        cd ..
        echo -e "${GREEN}✅ ZIP package created: $ZIP_FILE${NC}"
        echo "Upload this ZIP file to your hosting and extract it"
        ;;
    *)
        echo -e "${RED}Invalid option selected${NC}"
        exit 1
        ;;
esac

# Post-deployment checklist
echo -e "${BLUE}📝 Post-Deployment Checklist:${NC}"
echo "□ Verify database connection"
echo "□ Check file permissions (755 for directories, 644 for files)"
echo "□ Test login functionality"
echo "□ Run database migrations if needed"
echo "□ Check error logs"
echo "□ Test core functionality"
echo "□ Update DNS if needed"

echo -e "${GREEN}🎉 Deployment preparation completed!${NC}"
echo "Deployment package available at: $DEPLOY_DIR"
echo "Backup stored at: $BACKUP_DIR"