<?php
/**
 * Quick CI/CD Setup Script
 * 
 * This script helps you quickly set up CI/CD for your application
 * and provides setup instructions for GitHub and Hostinger
 */

class QuickCICDSetup {
    private $steps_completed = [];
    
    public function run() {
        echo "🚀 CI/CD Quick Setup for Hostinger Deployment\n";
        echo "=============================================\n\n";
        
        $this->checkRequirements();
        $this->showGitHubSetup();
        $this->showHostingerSetup();
        $this->showNextSteps();
        $this->showTestCommands();
        $this->listConfigurationFiles();
        $this->showWorkflowTriggers();
        $this->showHelp();
    }
    
    private function checkRequirements() {
        echo "📋 Checking Requirements...\n";
        
        $requirements = [
            'GitHub Repository' => 'Repository must exist with your code',
            'Hostinger Account' => 'Active hosting plan with FTP access',
            'Domain Configuration' => 'Domain pointing to Hostinger',
            'Database Setup' => 'MySQL database created on Hostinger'
        ];
        
        foreach ($requirements as $req => $desc) {
            echo "  ✓ $req: $desc\n";
        }
        
        echo "\n";
    }
    
    private function showGitHubSetup() {
        echo "🔧 GitHub Repository Setup:\n";
        echo "----------------------------\n";
        
        echo "1. Add GitHub Secrets (Repository → Settings → Secrets and variables → Actions):\n\n";
        echo "   Required Secrets:\n";
        echo "   ┌─────────────────────────────┬─────────────────────────────┐\n";
        echo "   │ Secret Name                 │ Example Value               │\n";
        echo "   ├─────────────────────────────┼─────────────────────────────┤\n";
        echo "   │ HOSTINGER_FTP_SERVER        │ ftp.yourdomain.com          │\n";
        echo "   │ HOSTINGER_FTP_USERNAME      │ u473559570_admin            │\n";
        echo "   │ HOSTINGER_FTP_PASSWORD      │ YourFTP_Password            │\n";
        echo "   │ HOSTINGER_SITE_URL          │ https://yourdomain.com      │\n";
        echo "   └─────────────────────────────┴─────────────────────────────┘\n\n";
        
        echo "2. Update .env file with your actual values:\n";
        echo "   cp .env.example .env\n\n";
        
        echo "3. Commit and push the CI/CD files:\n";
        echo "   git add .github/ scripts/ CI-CD-SETUP-GUIDE.md\n";
        echo "   git commit -m 'Add CI/CD configuration for Hostinger deployment'\n";
        echo "   git push origin master\n\n";
    }
    
    private function showHostingerSetup() {
        echo "🌐 Hostinger Setup:\n";
        echo "-------------------\n";
        
        echo "1. Database Setup:\n";
        echo "   - Log into Hostinger cPanel\n";
        echo "   - Go to 'Databases' → 'MySQL Databases'\n";
        echo "   - Create database: u473559570_installment_db\n";
        echo "   - Create user with full privileges\n";
        echo "   - Note down credentials\n\n";
        
        echo "2. FTP Access:\n";
        echo "   - Find FTP details in hosting control panel\n";
        echo "   - FTP Server: ftp.yourdomain.com\n";
        echo "   - Use main FTP account or create dedicated one\n";
        echo "   - Test FTP connection with FileZilla or similar\n\n";
        
        echo "3. Domain Configuration:\n";
        echo "   - Point domain to Hostinger nameservers\n";
        echo "   - Ensure SSL certificate is active\n";
        echo "   - Test basic PHP: Create test.php with phpinfo()\n\n";
    }
    
    private function showNextSteps() {
        echo "📝 Next Steps:\n";
        echo "-------------\n";
        
        echo "1. Test the CI/CD Pipeline:\n";
        echo "   - Push to develop branch: Triggers CI tests\n";
        echo "   - Push to master branch: Triggers deployment\n";
        echo "   - Monitor GitHub Actions tab for workflow status\n\n";
        
        echo "2. First Deployment:\n";
        echo "   - Run migration script manually on Hostinger\n";
        echo "   - Use phpMyAdmin to import database/schema.sql if needed\n";
        echo "   - Verify application works on your domain\n\n";
        
        echo "3. Environment Setup:\n";
        echo "   - Update config/db.php with Hostinger database credentials\n";
        echo "   - Set proper file permissions (755 dirs, 644 files)\n";
        echo "   - Test login functionality\n\n";
    }
    
    private function showTestCommands() {
        echo "🧪 Test Commands:\n";
        echo "----------------\n";
        
        echo "Run CI/CD test suite:\n";
        echo "  php tests/CI-CD-Test.php\n\n";
        
        echo "Run database migration:\n";
        echo "  php scripts/migration-runner.php localhost username password database migrate\n\n";
        
        echo "Manual deployment script:\n";
        echo "  bash scripts/deploy-hostinger.sh\n\n";
        
        echo "Check Git status:\n";
        echo "  git status\n  git add .github/ scripts/ *.md\n  git commit -m 'Update CI/CD configuration'\n  git push origin master\n\n";
    }
    
    private function listConfigurationFiles() {
        echo "📁 Created Files:\n";
        echo "----------------\n";
        
        $files = [
            '.github/workflows/ci.yml' => 'Development CI pipeline',
            '.github/workflows/deploy.yml' => 'Production deployment pipeline',
            'scripts/deploy-hostinger.sh' => 'Manual deployment script',
            'scripts/migration-runner.php' => 'Database migration tool',
            'config/env.php' => 'Environment configuration loader',
            'config/notifications.php' => 'Deployment notifications',
            '.env.example' => 'Environment template',
            'CI-CD-SETUP-GUIDE.md' => 'Detailed setup guide',
            'tests/CI-CD-Test.php' => 'CI/CD test suite'
        ];
        
        foreach ($files as $file => $description) {
            echo "  ✓ $file - $description\n";
        }
        
        echo "\n";
    }
    
    private function showWorkflowTriggers() {
        echo "🔄 Workflow Triggers:\n";
        echo "--------------------\n";
        
        echo "CI Workflow (ci.yml):\n";
        echo "  - Triggers: Push to 'develop' or 'feature/*' branches\n";
        echo "  - Actions: PHP syntax check, database test, security scan\n";
        echo "  - Purpose: Validate code before merging\n\n";
        
        echo "Deployment Workflow (deploy.yml):\n";
        echo "  - Triggers: Push to 'master' branch\n";
        echo "  - Actions: Full validation + FTP deployment to Hostinger\n";
        echo "  - Purpose: Automatic production deployment\n\n";
    }
    
    public function showHelp() {
        echo "\n💡 Quick Help:\n";
        echo "--------------\n";
        echo "If you encounter issues:\n";
        echo "1. Check GitHub Actions logs for detailed error messages\n";
        echo "2. Verify GitHub Secrets are configured correctly\n";
        echo "3. Test FTP connection manually with FileZilla\n";
        echo "4. Check Hostinger control panel for database status\n";
        echo "5. Review CI-CD-SETUP-GUIDE.md for detailed troubleshooting\n\n";
        
        echo "Useful Links:\n";
        echo "- GitHub Actions: https://github.com/features/actions\n";
        echo "- Hostinger Support: https://support.hostinger.com\n";
        echo "- Your Repository: https://github.com/usmankokab/jahangirautos\n\n";
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    $setup = new QuickCICDSetup();
    $setup->run();
}
?>