<?php
/**
 * Configuration Loader
 * Loads environment variables from .env file
 */

class Config {
    private static $config = [];
    private static $loaded = false;

    /**
     * Load configuration from .env file
     */
    public static function load($envFile = '.env') {
        if (self::$loaded) {
            return;
        }

        $envPath = __DIR__ . '/' . $envFile;
        
        if (!file_exists($envPath)) {
            throw new Exception(".env file not found at: $envPath");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                self::$config[$key] = $value;
                
                // Also set as environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    /**
     * Get database configuration
     */
    public static function getDatabase() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'username' => self::get('DB_USERNAME'),
            'password' => self::get('DB_PASSWORD'),
            'database' => self::get('DB_NAME')
        ];
    }

    /**
     * Get email configuration
     */
    public static function getEmail() {
        return [
            'host' => self::get('SMTP_HOST'),
            'port' => self::get('SMTP_PORT', 587),
            'username' => self::get('SMTP_USERNAME'),
            'password' => self::get('SMTP_PASSWORD'),
            'notification_email' => self::get('NOTIFICATION_EMAIL')
        ];
    }

    /**
     * Get application settings
     */
    public static function getApp() {
        return [
            'site_name' => self::get('SITE_NAME', 'Gallery Art Submissions'),
            'timezone' => self::get('TIMEZONE', 'UTC'),
            'debug_mode' => filter_var(self::get('DEBUG_MODE', 'false'), FILTER_VALIDATE_BOOLEAN),
            'max_file_size' => intval(self::get('MAX_FILE_SIZE', 5242880)), // 5MB default
            'max_files_per_submission' => intval(self::get('MAX_FILES_PER_SUBMISSION', 10)),
            'session_timeout' => intval(self::get('ADMIN_SESSION_TIMEOUT', 24))
        ];
    }

    /**
     * Create database connection
     */
    public static function getConnection() {
        $db = self::getDatabase();
        
        if (!$db['username'] || !$db['password'] || !$db['database']) {
            throw new Exception("Database configuration is incomplete. Please check your .env file.");
        }

        $conn = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);

        if ($conn->connect_error) {
            if (self::getApp()['debug_mode']) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            } else {
                throw new Exception("Database connection failed. Please check your configuration.");
            }
        }

        return $conn;
    }

    /**
     * Validate required environment variables
     */
    public static function validate() {
        $required = [
            'DB_HOST',
            'DB_USERNAME', 
            'DB_PASSWORD',
            'DB_NAME'
        ];

        $missing = [];
        foreach ($required as $key) {
            if (!self::get($key)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new Exception("Missing required environment variables: " . implode(', ', $missing));
        }

        return true;
    }
}

// Auto-load configuration
try {
    Config::load();
    Config::validate();
} catch (Exception $e) {
    if (Config::getApp()['debug_mode']) {
        die("Configuration Error: " . $e->getMessage());
    } else {
        die("Configuration Error: Please check your environment setup.");
    }
}
?>