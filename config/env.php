<?php
/**
 * Environment Configuration Loader
 * 
 * This file loads environment variables and provides configuration
 * for different deployment environments (local, staging, production)
 */

class EnvironmentConfig {
    private static $config = [];
    
    /**
     * Load environment configuration
     */
    public static function load($env_file = null) {
        $env_file = $env_file ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($env_file)) {
            // Try to load from environment variables or fall back to defaults
            self::loadFromEnvironment();
        } else {
            self::loadFromFile($env_file);
        }
        
        // Set default values if not set
        self::setDefaults();
    }
    
    /**
     * Load configuration from .env file
     */
    private static function loadFromFile($env_file) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (($value[0] === '"' && $value[-1] === '"') || 
                    ($value[0] === "'" && $value[-1] === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    /**
     * Load from environment variables
     */
    private static function loadFromEnvironment() {
        // Database configuration from environment
        $_ENV['DB_HOST'] = getenv('DB_HOST') ?: 'localhost';
        $_ENV['DB_DATABASE'] = getenv('DB_DATABASE') ?: 'installment_db';
        $_ENV['DB_USERNAME'] = getenv('DB_USERNAME') ?: 'root';
        $_ENV['DB_PASSWORD'] = getenv('DB_PASSWORD') ?: '';
        
        // Application settings
        $_ENV['APP_ENV'] = getenv('APP_ENV') ?: 'production';
        $_ENV['APP_DEBUG'] = getenv('APP_DEBUG') ?: 'false';
        $_ENV['APP_URL'] = getenv('APP_URL') ?: self::getCurrentURL();
    }
    
    /**
     * Set default configuration values
     */
    private static function setDefaults() {
        // Set defaults for missing values
        $_ENV['APP_NAME'] = $_ENV['APP_NAME'] ?? 'JahangirAutos';
        $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
        $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'false';
        $_ENV['TIMEZONE'] = $_ENV['TIMEZONE'] ?? 'Asia/Karachi';
        $_ENV['MAX_FILE_SIZE'] = $_ENV['MAX_FILE_SIZE'] ?? '5242880'; // 5MB
        $_ENV['SESSION_LIFETIME'] = $_ENV['SESSION_LIFETIME'] ?? '3600';
        $_ENV['ENABLE_DEBUG_MODE'] = $_ENV['ENABLE_DEBUG_MODE'] ?? 'false';
        $_ENV['ENABLE_LOGGING'] = $_ENV['ENABLE_LOGGING'] ?? 'true';
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * Get database configuration
     */
    public static function getDbConfig() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'database' => self::get('DB_DATABASE', 'installment_db'),
            'username' => self::get('DB_USERNAME', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
        ];
    }
    
    /**
     * Check if running in development mode
     */
    public static function isDevelopment() {
        return self::get('APP_ENV') === 'development';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugEnabled() {
        return self::get('APP_DEBUG', 'false') === 'true';
    }
    
    /**
     * Get current environment
     */
    public static function getEnvironment() {
        return self::get('APP_ENV', 'production');
    }
    
    /**
     * Get upload configuration
     */
    public static function getUploadConfig() {
        return [
            'max_size' => (int) self::get('MAX_FILE_SIZE', 5242880),
            'upload_path' => self::get('UPLOAD_PATH', './uploads/'),
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']
        ];
    }
    
    /**
     * Get email configuration
     */
    public static function getEmailConfig() {
        return [
            'host' => self::get('MAIL_HOST', ''),
            'port' => (int) self::get('MAIL_PORT', 587),
            'username' => self::get('MAIL_USERNAME', ''),
            'password' => self::get('MAIL_PASSWORD', ''),
            'encryption' => self::get('MAIL_ENCRYPTION', 'tls'),
        ];
    }
    
    /**
     * Get FTP configuration for deployment
     */
    public static function getFtpConfig() {
        return [
            'server' => self::get('HOSTINGER_FTP_SERVER', ''),
            'username' => self::get('HOSTINGER_FTP_USERNAME', ''),
            'password' => self::get('HOSTINGER_FTP_PASSWORD', ''),
            'site_url' => self::get('HOSTINGER_SITE_URL', ''),
        ];
    }
    
    /**
     * Get current URL for base URL detection
     */
    private static function getCurrentURL() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }
    
    /**
     * Validate required configuration
     */
    public static function validate() {
        $required = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
        $missing = [];
        
        foreach ($required as $key) {
            if (empty($_ENV[$key])) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Missing required configuration: " . implode(', ', $missing));
        }
    }
    
    /**
     * Get all configuration as array
     */
    public static function getAll() {
        return $_ENV;
    }
}

// Load configuration automatically
EnvironmentConfig::load();

// Example usage:
// $db_config = EnvironmentConfig::getDbConfig();
// $upload_config = EnvironmentConfig::getUploadConfig();
// $is_dev = EnvironmentConfig::isDevelopment();
?>