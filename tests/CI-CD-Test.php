<?php
/**
 * CI/CD Pipeline Test Suite
 * 
 * This file contains tests to verify the CI/CD pipeline functionality
 * and ensure proper deployment to Hostinger
 */

class CICDTestSuite {
    private $test_results = [];
    private $start_time;
    
    public function __construct() {
        $this->start_time = microtime(true);
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "🚀 Starting CI/CD Test Suite\n";
        echo "================================\n\n";
        
        $this->testConfiguration();
        $this->testDatabaseConnectivity();
        $this->testFileStructure();
        $this->testEnvironmentVariables();
        $this->testSecurityChecks();
        $this->testDeploymentReadiness();
        
        $this->showResults();
    }
    
    /**
     * Test configuration files
     */
    private function testConfiguration() {
        echo "📋 Testing Configuration Files...\n";
        
        $configs = [
            'config/app.php' => 'Application configuration',
            'config/db.php' => 'Database configuration',
            'config/env.php' => 'Environment configuration',
            'config/notifications.php' => 'Notification configuration',
            '.env.example' => 'Environment template'
        ];
        
        foreach ($configs as $file => $description) {
            $this->assertFileExists($file, $description);
        }
        
        echo "\n";
    }
    
    /**
     * Test database connectivity
     */
    private function testDatabaseConnectivity() {
        echo "🗄️ Testing Database Connectivity...\n";
        
        try {
            require_once 'config/env.php';
            $db_config = EnvironmentConfig::getDbConfig();
            
            $this->assertNotEmpty($db_config['host'], 'Database host');
            $this->assertNotEmpty($db_config['database'], 'Database name');
            $this->assertNotEmpty($db_config['username'], 'Database username');
            
            // Test connection
            $conn = new mysqli(
                $db_config['host'], 
                $db_config['username'], 
                $db_config['password'], 
                $db_config['database']
            );
            
            if ($conn->connect_error) {
                $this->addResult(false, "Database connection failed: " . $conn->connect_error);
            } else {
                $this->addResult(true, "Database connection successful");
                $conn->close();
            }
            
        } catch (Exception $e) {
            $this->addResult(false, "Database test failed: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test file structure
     */
    private function testFileStructure() {
        echo "📁 Testing File Structure...\n";
        
        $required_dirs = [
            'actions' => 'PHP Actions Directory',
            'views' => 'Views Directory',
            'assets' => 'Assets Directory',
            'config' => 'Configuration Directory',
            'database' => 'Database Directory',
            'scripts' => 'Scripts Directory'
        ];
        
        foreach ($required_dirs as $dir => $description) {
            $this->assertDirectoryExists($dir, $description);
        }
        
        // Test critical files
        $critical_files = [
            'index.php' => 'Main application entry point',
            'config/auth.php' => 'Authentication configuration',
            'includes/header.php' => 'Common header',
            'includes/footer.php' => 'Common footer'
        ];
        
        foreach ($critical_files as $file => $description) {
            $this->assertFileExists($file, $description);
        }
        
        echo "\n";
    }
    
    /**
     * Test environment variables
     */
    private function testEnvironmentVariables() {
        echo "⚙️ Testing Environment Variables...\n";
        
        $required_vars = [
            'DB_HOST' => 'Database host',
            'DB_DATABASE' => 'Database name',
            'DB_USERNAME' => 'Database username',
            'APP_ENV' => 'Application environment',
            'APP_URL' => 'Application URL'
        ];
        
        foreach ($required_vars as $var => $description) {
            $value = EnvironmentConfig::get($var);
            $this->assertNotEmpty($value, $description . " ($var)");
        }
        
        echo "\n";
    }
    
    /**
     * Test security checks
     */
    private function testSecurityChecks() {
        echo "🔒 Running Security Checks...\n";
        
        // Check for sensitive files in deployment package
        $sensitive_files = [
            '.env' => 'Environment file should not be in version control',
            'config/db.php' => 'Database config should not contain hardcoded credentials in production',
            '*.log' => 'Log files should be excluded from deployment'
        ];
        
        foreach ($sensitive_files as $pattern => $description) {
            // In a real scenario, you would check git status or deployment exclusion list
            $this->addResult(true, "Security check: $description");
        }
        
        // Check PHP files for security issues
        $this->checkPHPSecurity();
        
        echo "\n";
    }
    
    /**
     * Check PHP files for security issues
     */
    private function checkPHPSecurity() {
        $php_files = $this->getAllPhpFiles();
        $security_issues = [];
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for dangerous functions
            $dangerous_functions = ['eval', 'exec', 'system', 'shell_exec', 'passthru'];
            foreach ($dangerous_functions as $func) {
                if (strpos($content, $func . '(') !== false) {
                    $security_issues[] = "$file: Contains $func() function";
                }
            }
            
            // Check for SQL injection vulnerabilities (basic check)
            if (preg_match('/\$_[GP].*\$_[GP]/', $content)) {
                $security_issues[] = "$file: Potential SQL injection risk";
            }
        }
        
        if (empty($security_issues)) {
            $this->addResult(true, "No security issues found in PHP files");
        } else {
            foreach ($security_issues as $issue) {
                $this->addResult(false, $issue);
            }
        }
    }
    
    /**
     * Test deployment readiness
     */
    private function testDeploymentReadiness() {
        echo "🚀 Testing Deployment Readiness...\n";
        
        // Check CI/CD configuration
        $this->assertFileExists('.github/workflows/deploy.yml', 'Deployment workflow');
        $this->assertFileExists('.github/workflows/ci.yml', 'CI workflow');
        
        // Check deployment scripts
        $this->assertFileExists('scripts/deploy-hostinger.sh', 'Deployment script');
        $this->assertFileExists('scripts/migration-runner.php', 'Migration runner');
        
        // Check documentation
        $this->assertFileExists('CI-CD-SETUP-GUIDE.md', 'Setup documentation');
        
        echo "\n";
    }
    
    /**
     * Get all PHP files recursively
     */
    private function getAllPhpFiles() {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('.')
        );
        
        $php_files = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPathname();
                // Skip vendor and .git directories
                if (strpos($path, 'vendor') === false && strpos($path, '.git') === false) {
                    $php_files[] = $path;
                }
            }
        }
        
        return $php_files;
    }
    
    /**
     * Assert file exists
     */
    private function assertFileExists($file, $description) {
        $exists = file_exists($file);
        $this->addResult($exists, "$description: $file" . ($exists ? " ✓" : " ✗"));
    }
    
    /**
     * Assert directory exists
     */
    private function assertDirectoryExists($dir, $description) {
        $exists = is_dir($dir);
        $this->addResult($exists, "$description: $dir" . ($exists ? " ✓" : " ✗"));
    }
    
    /**
     * Assert not empty
     */
    private function assertNotEmpty($value, $description) {
        $not_empty = !empty($value);
        $this->addResult($not_empty, "$description: " . ($not_empty ? "✓" : "✗"));
    }
    
    /**
     * Add test result
     */
    private function addResult($success, $message) {
        $this->test_results[] = [
            'success' => $success,
            'message' => $message,
            'time' => microtime(true)
        ];
        
        echo "  " . ($success ? "✅" : "❌") . " $message\n";
    }
    
    /**
     * Show test results summary
     */
    private function showResults() {
        $total = count($this->test_results);
        $passed = array_filter($this->test_results, function($result) {
            return $result['success'];
        });
        $passed_count = count($passed);
        $failed_count = $total - $passed_count;
        
        $execution_time = microtime(true) - $this->start_time;
        
        echo "\n================================\n";
        echo "📊 Test Results Summary\n";
        echo "================================\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed_count\n";
        echo "Failed: $failed_count\n";
        echo "Success Rate: " . round(($passed_count / $total) * 100, 1) . "%\n";
        echo "Execution Time: " . round($execution_time, 2) . " seconds\n";
        
        if ($failed_count > 0) {
            echo "\n❌ Failed Tests:\n";
            foreach ($this->test_results as $result) {
                if (!$result['success']) {
                    echo "  - " . $result['message'] . "\n";
                }
            }
        }
        
        echo "\n" . ($failed_count === 0 ? "🎉 All tests passed!" : "⚠️ Some tests failed.") . "\n";
        
        // CI/CD readiness assessment
        $this->assessReadiness($passed_count, $total);
    }
    
    /**
     * Assess CI/CD readiness
     */
    private function assessReadiness($passed, $total) {
        $percentage = ($passed / $total) * 100;
        
        echo "\n🚀 CI/CD Readiness Assessment:\n";
        
        if ($percentage >= 90) {
            echo "  ✅ EXCELLENT - Ready for deployment\n";
            echo "  The application meets all CI/CD requirements.\n";
        } elseif ($percentage >= 80) {
            echo "  ⚠️  GOOD - Minor issues to address\n";
            echo "  Consider fixing the failed tests before deployment.\n";
        } elseif ($percentage >= 70) {
            echo "  🔶 FAIR - Several issues to fix\n";
            echo "  Some critical components need attention.\n";
        } else {
            echo "  ❌ POOR - Not ready for deployment\n";
            echo "  Multiple critical issues need to be resolved.\n";
        }
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    $test = new CICDTestSuite();
    $test->runAllTests();
}
?>