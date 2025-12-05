<?php
/**
 * Notification Configuration
 * 
 * Manages deployment notifications and alerts for the CI/CD pipeline
 */

class NotificationConfig {
    
    /**
     * Send deployment notification
     */
    public static function sendDeploymentNotification($status, $environment, $commit_info = []) {
        $message = self::formatDeploymentMessage($status, $environment, $commit_info);
        
        // Log notification
        self::logNotification($message, $status);
        
        // Send email notification if configured
        if (self::isEmailConfigured()) {
            self::sendEmailNotification($message, $status, $environment);
        }
        
        // Send Slack notification if configured
        if (self::isSlackConfigured()) {
            self::sendSlackNotification($message, $status, $environment);
        }
        
        // Send webhook notification if configured
        if (self::isWebhookConfigured()) {
            self::sendWebhookNotification($message, $status, $environment, $commit_info);
        }
    }
    
    /**
     * Format deployment message
     */
    private static function formatDeploymentMessage($status, $environment, $commit_info) {
        $emoji = $status === 'success' ? '✅' : '❌';
        $status_text = strtoupper($status);
        
        $message = "$emoji CI/CD Deployment $status_text\n";
        $message .= "Environment: $environment\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        if (!empty($commit_info)) {
            $message .= "Commit: " . ($commit_info['message'] ?? 'N/A') . "\n";
            $message .= "Author: " . ($commit_info['author'] ?? 'N/A') . "\n";
            $message .= "Branch: " . ($commit_info['branch'] ?? 'N/A') . "\n";
            $message .= "SHA: " . ($commit_info['sha'] ?? 'N/A') . "\n";
        }
        
        return $message;
    }
    
    /**
     * Check if email is configured
     */
    private static function isEmailConfigured() {
        return !empty($_ENV['MAIL_HOST']) && !empty($_ENV['MAIL_USERNAME']);
    }
    
    /**
     * Send email notification
     */
    private static function sendEmailNotification($message, $status, $environment) {
        $subject = "CI/CD Deployment $status - " . ($_ENV['APP_NAME'] ?? 'Application');
        $to = $_ENV['NOTIFICATION_EMAIL'] ?? $_ENV['MAIL_USERNAME'];
        
        // Basic email sending logic
        $headers = [
            'From: ' . ($_ENV['MAIL_USERNAME'] ?? 'noreply@domain.com'),
            'Reply-To: ' . ($_ENV['MAIL_USERNAME'] ?? 'noreply@domain.com'),
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        // In production, you would use PHP mail() function or a library like PHPMailer
        if (function_exists('mail')) {
            mail($to, $subject, $message, implode("\r\n", $headers));
        }
    }
    
    /**
     * Check if Slack is configured
     */
    private static function isSlackConfigured() {
        return !empty($_ENV['SLACK_WEBHOOK_URL']);
    }
    
    /**
     * Send Slack notification
     */
    private static function sendSlackNotification($message, $status, $environment) {
        $webhook_url = $_ENV['SLACK_WEBHOOK_URL'];
        
        $payload = [
            'text' => $message,
            'username' => 'CI/CD Bot',
            'icon_emoji' => $status === 'success' ? ':white_check_mark:' : ':x:',
            'channel' => $_ENV['SLACK_CHANNEL'] ?? '#general'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Check if webhook is configured
     */
    private static function isWebhookConfigured() {
        return !empty($_ENV['WEBHOOK_URL']);
    }
    
    /**
     * Send webhook notification
     */
    private static function sendWebhookNotification($message, $status, $environment, $commit_info) {
        $webhook_url = $_ENV['WEBHOOK_URL'];
        
        $payload = [
            'status' => $status,
            'environment' => $environment,
            'message' => $message,
            'timestamp' => time(),
            'commit' => $commit_info
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Log notification to file
     */
    private static function logNotification($message, $status) {
        $log_file = $_ENV['LOG_PATH'] ?? './logs/';
        $log_file .= 'notifications.log';
        
        // Ensure log directory exists
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_entry = date('Y-m-d H:i:s') . " [$status] $message\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get notification status summary
     */
    public static function getNotificationStatus() {
        return [
            'email' => self::isEmailConfigured(),
            'slack' => self::isSlackConfigured(),
            'webhook' => self::isWebhookConfigured(),
            'webhook_url' => !empty($_ENV['WEBHOOK_URL']),
            'slack_webhook' => !empty($_ENV['SLACK_WEBHOOK_URL']),
        ];
    }
}

// GitHub Actions Integration
class GitHubActionsNotifier {
    
    /**
     * Set GitHub Actions environment variables
     */
    public static function setEnvironmentVariables() {
        // Set deployment status for GitHub Actions
        if (isset($_ENV['CI']) && $_ENV['CI'] === 'true') {
            $deployment_status = [
                'status' => 'success',
                'environment' => self::getEnvironmentFromBranch(),
                'timestamp' => date('c'),
                'runner_os' => $_ENV['RUNNER_OS'] ?? 'unknown',
                'runner_name' => $_ENV['RUNNER_NAME'] ?? 'unknown'
            ];
            
            // Output variables that can be used in subsequent steps
            echo "DEPLOYMENT_STATUS=" . json_encode($deployment_status);
        }
    }
    
    /**
     * Get environment from Git branch
     */
    private static function getEnvironmentFromBranch() {
        $branch = $_ENV['GITHUB_REF'] ?? 'unknown';
        
        if (strpos($branch, 'master') !== false || strpos($branch, 'main') !== false) {
            return 'production';
        } elseif (strpos($branch, 'develop') !== false) {
            return 'staging';
        } elseif (strpos($branch, 'feature') !== false) {
            return 'development';
        }
        
        return 'unknown';
    }
    
    /**
     * Create GitHub deployment status
     */
    public static function createDeploymentStatus($environment, $state, $description = '') {
        $github_token = $_ENV['GITHUB_TOKEN'];
        $repo = $_ENV['GITHUB_REPOSITORY'] ?? 'owner/repo';
        
        if (!$github_token) {
            return;
        }
        
        $payload = [
            'environment' => $environment,
            'state' => $state,
            'description' => $description ?: "Deployment $state to $environment",
            'auto_inactive' => $state !== 'success'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo/deployments");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $github_token,
            'Accept: application/vnd.github.v3+json',
            'Content-Type: application/json'
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

// Usage examples:
// NotificationConfig::sendDeploymentNotification('success', 'production', $commit_info);
// GitHubActionsNotifier::setEnvironmentVariables();
// GitHubActionsNotifier::createDeploymentStatus('production', 'success');
?>