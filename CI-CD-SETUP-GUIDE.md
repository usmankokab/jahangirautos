# CI/CD Setup Guide for Hostinger Deployment

This guide will help you set up a complete CI/CD pipeline using GitHub Actions to automatically deploy your PHP installment management system to Hostinger shared hosting.

## Table of Contents
- [Prerequisites](#prerequisites)
- [GitHub Repository Setup](#github-repository-setup)
- [GitHub Secrets Configuration](#github-secrets-configuration)
- [Hostinger Preparation](#hostinger-preparation)
- [Workflow Configuration](#workflow-configuration)
- [Testing the Pipeline](#testing-the-pipeline)
- [Troubleshooting](#troubleshooting)
- [Maintenance](#maintenance)

## Prerequisites

Before setting up CI/CD, ensure you have:

1. **GitHub Account** with a repository containing your PHP application
2. **Hostinger Hosting Account** with FTP/cPanel access
3. **Domain configured** with your Hostinger hosting
4. **Database setup** on Hostinger (MySQL)
5. **Basic understanding** of Git and GitHub Actions

## GitHub Repository Setup

### 1. Repository Structure
Your repository should have this structure:
```
installment_app/
├── .github/
│   └── workflows/
│       ├── ci.yml           # Development testing
│       └── deploy.yml       # Production deployment
├── config/
│   ├── app.php              # Application configuration
│   ├── db.php               # Database configuration
│   └── db.example.php       # Template
├── database/
│   └── schema.sql           # Database schema
├── scripts/
│   ├── deploy-hostinger.sh  # Deployment script
│   └── migration-runner.php # Database migration tool
├── actions/                 # PHP backend actions
├── views/                   # Frontend views
├── assets/                  # CSS, JS, images
└── .env.example             # Environment template
```

### 2. Ensure Important Files Exist
- `config/db.php` - Database configuration
- `database/schema.sql` - Database structure
- `.gitignore` - Ignore sensitive files

## GitHub Secrets Configuration

### Required Secrets
Go to your repository on GitHub → Settings → Secrets and variables → Actions, then add:

| Secret Name | Description | Value |
|-------------|-------------|-------|
| `HOSTINGER_FTP_SERVER` | FTP server address | `ftp.yourdomain.com` |
| `HOSTINGER_FTP_USERNAME` | FTP username | Your FTP username |
| `HOSTINGER_FTP_PASSWORD` | FTP password | Your FTP password |
| `HOSTINGER_SITE_URL` | Your website URL | `https://yourdomain.com` |

### How to Find Hostinger FTP Credentials:
1. Log into your Hostinger account
2. Go to "Hosting" section
3. Click "Manage" on your hosting plan
4. Find "FTP Accounts" or "File Manager"
5. Use the main FTP account or create a new one

## Hostinger Preparation

### 1. Database Setup
1. Access your Hostinger control panel
2. Go to "Databases" → "MySQL Databases"
3. Create a new database (e.g., `u473559570_installment_db`)
4. Create a database user with full privileges
5. Note down the database credentials

### 2. Domain Configuration
1. Point your domain to Hostinger nameservers
2. Ensure your domain is active and accessible
3. Test basic PHP functionality by uploading a test file

### 3. FTP Access
1. Note your FTP server address (usually `ftp.yourdomain.com`)
2. Get your FTP username and password
3. Test FTP connection with an FTP client

## Workflow Configuration

### Development Workflow (`ci.yml`)
- **Trigger**: Push to `develop` or `feature/*` branches
- **Tests**: PHP syntax check, database connectivity, security scan
- **Purpose**: Ensure code quality before merging

### Production Deployment (`deploy.yml`)
- **Trigger**: Push to `master` branch
- **Steps**:
  1. PHP syntax validation
  2. Database migration testing
  3. Code security scanning
  4. FTP deployment to Hostinger
  5. Post-deployment verification

## Testing the Pipeline

### 1. Initial Test
1. Make a small change to your code
2. Commit and push to `develop` branch
3. Check GitHub Actions tab for workflow execution
4. Verify all tests pass

### 2. Production Deployment Test
1. Merge `develop` to `master`
2. Monitor the deployment workflow
3. Verify deployment completed successfully
4. Test your live site functionality

### 3. Manual Deployment (Backup)
If automated deployment fails:

```bash
# Run locally
chmod +x scripts/deploy-hostinger.sh
./scripts/deploy-hostinger.sh

# Or use the migration tool directly
php scripts/migration-runner.php localhost username password database migrate
```

## Troubleshooting

### Common Issues and Solutions

#### 1. FTP Connection Failed
**Problem**: "Authentication failed" or "Connection refused"
**Solution**:
- Verify FTP credentials in GitHub Secrets
- Check if FTP server is correct
- Ensure FTP account has proper permissions
- Try using your main FTP account instead of creating new ones

#### 2. Database Connection Error
**Problem**: Database connection fails after deployment
**Solution**:
- Verify database credentials in `config/db.php`
- Check if database exists on Hostinger
- Ensure database user has correct privileges
- Test connection from Hostinger control panel

#### 3. File Permissions Issue
**Problem**: "Permission denied" errors
**Solution**:
```bash
# Set proper permissions after deployment
chmod 755 directories
chmod 644 files
chmod 755 actions/*.php
```

#### 4. PHP Version Mismatch
**Problem**: PHP syntax errors or compatibility issues
**Solution**:
- Check Hostinger PHP version (7.4 or 8.1+ recommended)
- Update `config/app.php` for correct paths
- Ensure PHP extensions are available on Hostinger

#### 5. Environment Configuration
**Problem**: Wrong configuration loaded
**Solution**:
- Copy `.env.example` to `.env` with correct values
- Update `config/db.php` for production environment
- Ensure sensitive files are not in version control

### Debugging Steps

1. **Check GitHub Actions Logs**:
   - Go to Actions tab
   - Click on failed workflow
   - Expand job details to see error messages

2. **Test FTP Connection**:
   ```bash
   # Use FTP client to test
   ftp your-ftp-server
   # Enter credentials and test basic commands
   ```

3. **Database Testing**:
   - Use Hostinger phpMyAdmin to test queries
   - Check if tables exist and have correct structure

4. **Local Testing**:
   ```bash
   # Run deployment script locally
   ./scripts/deploy-hostinger.sh
   ```

## Maintenance

### Regular Tasks

1. **Monitor Deployment Logs**: Check GitHub Actions regularly
2. **Database Backups**: Set up regular database backups on Hostinger
3. **Security Updates**: Keep PHP and dependencies updated
4. **Test After Deployment**: Always test functionality after deployment

### Environment-Specific Configurations

#### Development Environment
- Use `develop` branch for testing
- Enable debug mode in `.env`
- Use local database for testing

#### Production Environment
- Use `master` branch for live deployment
- Disable debug mode in `.env`
- Use Hostinger database

### Scaling Considerations

For larger applications:
1. Implement caching strategies
2. Use CDN for static assets
3. Set up monitoring and alerting
4. Consider staging environment

## Additional Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Hostinger Documentation](https://support.hostinger.com/en)
- [PHP Best Practices](https://phpbestpractices.org/)
- [MySQL Optimization](https://dev.mysql.com/doc/)

## Support

If you encounter issues:
1. Check this guide first
2. Review GitHub Actions logs
3. Test individual components separately
4. Contact Hostinger support for hosting-specific issues
5. Use GitHub discussions for community help

---

**Note**: This setup is designed for shared hosting environments. For VPS or dedicated servers, additional configuration may be needed.