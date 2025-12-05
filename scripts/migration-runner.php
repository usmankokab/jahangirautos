<?php
/**
 * Database Migration Runner for Hostinger
 * 
 * This script runs database migrations in the correct order
 * for the installment management system.
 */

class MigrationRunner {
    private $conn;
    private $migrations_dir;
    private $schema_file;
    
    public function __construct($host, $username, $password, $database) {
        $this->conn = new mysqli($host, $username, $password, $database);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->migrations_dir = dirname(__FILE__) . '/../database/migrations';
        $this->schema_file = dirname(__FILE__) . '/../database/schema.sql';
    }
    
    /**
     * Initialize migrations table if it doesn't exist
     */
    private function initializeMigrationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$this->conn->query($sql)) {
            throw new Exception("Failed to create migrations table: " . $this->conn->error);
        }
    }
    
    /**
     * Get executed migrations
     */
    private function getExecutedMigrations() {
        $result = $this->conn->query("SELECT migration_name FROM migrations ORDER BY migration_name");
        $executed = [];
        
        while ($row = $result->fetch_assoc()) {
            $executed[] = $row['migration_name'];
        }
        
        return $executed;
    }
    
    /**
     * Get available migration files
     */
    private function getMigrationFiles() {
        $files = [];
        
        if (is_dir($this->migrations_dir)) {
            $migration_files = glob($this->migrations_dir . '/*.sql');
            
            foreach ($migration_files as $file) {
                $filename = basename($file);
                $files[] = $filename;
            }
        }
        
        return $files;
    }
    
    /**
     * Mark migration as executed
     */
    private function markMigrationExecuted($migration_name) {
        $stmt = $this->conn->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $stmt->bind_param("s", $migration_name);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Run schema.sql if it exists
     */
    public function runSchema() {
        if (file_exists($this->schema_file)) {
            echo "Running base schema...\n";
            
            $sql = file_get_contents($this->schema_file);
            
            // Split by semicolon and execute each statement
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    if (!$this->conn->query($statement)) {
                        echo "Warning: Failed to execute statement: " . $this->conn->error . "\n";
                    }
                }
            }
            
            echo "Base schema executed successfully.\n";
        }
    }
    
    /**
     * Run pending migrations
     */
    public function runPendingMigrations() {
        $this->initializeMigrationTable();
        
        $executed = $this->getExecutedMigrations();
        $available = $this->getMigrationFiles();
        
        $pending = array_diff($available, $executed);
        
        if (empty($pending)) {
            echo "No pending migrations.\n";
            return;
        }
        
        echo "Found " . count($pending) . " pending migrations.\n";
        
        foreach ($pending as $migration_file) {
            $this->runMigration($migration_file);
        }
    }
    
    /**
     * Run a single migration
     */
    private function runMigration($migration_file) {
        $file_path = $this->migrations_dir . '/' . $migration_file;
        
        echo "Running migration: $migration_file\n";
        
        try {
            $sql = file_get_contents($file_path);
            
            // Split by semicolon and execute each statement
            $statements = explode(';', $sql);
            
            $this->conn->begin_transaction();
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    if (!$this->conn->query($statement)) {
                        throw new Exception("Failed to execute statement: " . $this->conn->error);
                    }
                }
            }
            
            $this->conn->commit();
            $this->markMigrationExecuted($migration_file);
            
            echo "Migration $migration_file completed successfully.\n";
            
        } catch (Exception $e) {
            $this->conn->rollback();
            echo "Migration $migration_file failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Get migration status
     */
    public function getStatus() {
        $this->initializeMigrationTable();
        
        $executed = $this->getExecutedMigrations();
        $available = $this->getMigrationFiles();
        
        echo "Migration Status:\n";
        echo "================\n";
        
        if (empty($available)) {
            echo "No migration files found in: " . $this->migrations_dir . "\n";
            return;
        }
        
        foreach ($available as $migration) {
            $status = in_array($migration, $executed) ? '✓ Executed' : '○ Pending';
            echo sprintf("%-30s %s\n", $migration, $status);
        }
    }
    
    /**
     * Close database connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Usage examples:
// $runner = new MigrationRunner('localhost', 'username', 'password', 'database');
// $runner->runSchema();           // Run initial schema
// $runner->runPendingMigrations(); // Run pending migrations
// $runner->getStatus();           // Show migration status

// For command line usage
if (php_sapi_name() === 'cli') {
    $host = $argv[1] ?? 'localhost';
    $username = $argv[2] ?? 'root';
    $password = $argv[3] ?? '';
    $database = $argv[4] ?? 'installment_db';
    
    $action = $argv[5] ?? 'migrate';
    
    try {
        $runner = new MigrationRunner($host, $username, $password, $database);
        
        switch ($action) {
            case 'status':
                $runner->getStatus();
                break;
            case 'schema':
                $runner->runSchema();
                break;
            case 'migrate':
            default:
                $runner->runSchema();
                $runner->runPendingMigrations();
                break;
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>