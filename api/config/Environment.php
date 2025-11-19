<?php
// api/config/Environment.php

/**
 * Environment Configuration Management
 * Handles loading and validating environment variables
 */
class Environment
{
    private static $loaded = false;
    private static $config = [];

    /**
     * Load environment configuration
     */
    public static function load($envPath = null)
    {
        if (self::$loaded) {
            return true;
        }

        // Default .env file path
        if ($envPath === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
        }

        // Load .env file if it exists
        if (file_exists($envPath)) {
            self::loadEnvFile($envPath);
        }

        // Validate required environment variables
        self::validateRequired();

        // Set configuration constants and defaults
        self::setConfiguration();

        self::$loaded = true;
        return true;
    }

    /**
     * Load .env file contents
     */
    private static function loadEnvFile($envPath)
    {
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
                $value = self::removeQuotes($value);

                // Set environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Remove surrounding quotes from environment variable values
     */
    private static function removeQuotes($value)
    {
        if (empty($value)) {
            return $value;
        }

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Validate required environment variables
     */
    private static function validateRequired()
    {
        $required = [
            'DB_HOST',
            'DB_NAME',
            'DB_USER'
        ];

        $missing = [];
        foreach ($required as $var) {
            if (empty(getenv($var))) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new Exception("Required environment variables missing: " . implode(', ', $missing));
        }
    }

    /**
     * Set configuration constants and defaults
     */
    private static function setConfiguration()
    {
        // Database configuration
        define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
        define('DB_PORT', getenv('DB_PORT') ?: '3306');
        define('DB_NAME', getenv('DB_NAME') ?: 'object_storage');
        define('DB_USER', getenv('DB_USER') ?: 'root');
        define('DB_PASS', getenv('DB_PASS') ?: '');
        define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

        // Application configuration
        define('APP_ENV', getenv('APP_ENV') ?: 'production');
        define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
        define('TIMEZONE', getenv('TIMEZONE') ?: 'UTC');

        // Set timezone
        date_default_timezone_set(TIMEZONE);

        // Security configuration
        define('SSL_ENFORCE', getenv('SSL_ENFORCE') !== 'false');
        define('RATE_LIMIT', (int)(getenv('RATE_LIMIT') ?: 1000));

        // File storage configuration
        define('MAX_FILE_SIZE', (int)(getenv('MAX_FILE_SIZE') ?: 52428800)); // 50MB
        define('STORAGE_PATH', getenv('STORAGE_PATH') ?: './storage');
        define('UPLOAD_TEMP_PATH', getenv('UPLOAD_TEMP_PATH') ?: './tmp');

        // Image optimization configuration
        define('ENABLE_OPTIMIZATION', getenv('ENABLE_OPTIMIZATION') !== 'false');
        define('THUMBNAIL_SIZES', getenv('THUMBNAIL_SIZES') ?: '100,300,600,1200');
        define('JPEG_QUALITY', (int)(getenv('JPEG_QUALITY') ?: 85));
        define('WEBP_QUALITY', (int)(getenv('WEBP_QUALITY') ?: 80));
        define('ENABLE_WEBP_CONVERSION', getenv('ENABLE_WEBP_CONVERSION') !== 'false');

        // Maximum image dimensions
        define('MAX_IMAGE_WIDTH', (int)(getenv('MAX_IMAGE_WIDTH') ?: 4096));
        define('MAX_IMAGE_HEIGHT', (int)(getenv('MAX_IMAGE_HEIGHT') ?: 4096));

        // CORS configuration
        define('ALLOW_ORIGINS', getenv('ALLOW_ORIGINS') ?: '');

        // Logging configuration
        define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'info');
        define('LOG_FILE', getenv('LOG_FILE') ?: './logs/application.log');
        define('ENABLE_REQUEST_LOGGING', getenv('ENABLE_REQUEST_LOGGING') !== 'false');

        // Performance configuration
        define('ENABLE_COMPRESSION', getenv('ENABLE_COMPRESSION') !== 'false');
        define('STATIC_CACHE_DURATION', (int)(getenv('STATIC_CACHE_DURATION') ?: 31536000));
        define('API_CACHE_DURATION', (int)(getenv('API_CACHE_DURATION') ?: 3600));

        // Cache configuration
        define('CACHE_DRIVER', getenv('CACHE_DRIVER') ?: 'file');
        define('CACHE_HOST', getenv('CACHE_HOST') ?: 'localhost');
        define('CACHE_PORT', (int)(getenv('CACHE_PORT') ?: 6379));
        define('CACHE_PREFIX', getenv('CACHE_PREFIX') ?: 'object_storage');

        // Monitoring configuration
        define('ENABLE_HEALTH_CHECK', getenv('ENABLE_HEALTH_CHECK') !== 'false');
        define('ENABLE_METRICS', getenv('ENABLE_METRICS') !== 'false');

        // API configuration
        define('API_VERSION', getenv('API_VERSION') ?: 'v1');
        define('API_TIMEOUT', (int)(getenv('API_TIMEOUT') ?: 300));
        define('MAX_CONCURRENT_UPLOADS', (int)(getenv('MAX_CONCURRENT_UPLOADS') ?: 5));

        // Admin interface configuration
        define('ADMIN_PATH', getenv('ADMIN_PATH') ?: '/admin');

        // Email configuration
        define('ENABLE_EMAIL_NOTIFICATIONS', getenv('ENABLE_EMAIL_NOTIFICATIONS') !== 'false');
        define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
        define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
        define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
        define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
        define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');

        // Feature flags
        define('ENABLE_MULTIPART_UPLOAD', getenv('ENABLE_MULTIPART_UPLOAD') !== 'false');
        define('ENABLE_VERSIONING', getenv('ENABLE_VERSIONING') === 'true');
        define('ENABLE_ENCRYPTION_AT_REST', getenv('ENABLE_ENCRYPTION_AT_REST') === 'true');
        define('ENABLE_AUDIT_LOG', getenv('ENABLE_AUDIT_LOG') !== 'false');

        // S3 backend configuration
        define('ENABLE_S3_BACKEND', getenv('ENABLE_S3_BACKEND') === 'true');
        define('S3_REGION', getenv('S3_REGION') ?: '');
        define('S3_BUCKET', getenv('S3_BUCKET') ?: '');
        define('S3_ACCESS_KEY', getenv('S3_ACCESS_KEY') ?: '');
        define('S3_SECRET_KEY', getenv('S3_SECRET_KEY') ?: '');
        define('S3_ENDPOINT', getenv('S3_ENDPOINT') ?: '');

        // Development settings
        define('DEBUG', getenv('DEBUG') === 'true');
        define('DISPLAY_ERRORS', getenv('DISPLAY_ERRORS') === 'true');
        define('ENABLE_QUERY_LOG', getenv('ENABLE_QUERY_LOG') === 'true');

        // Error reporting settings based on environment
        if (APP_ENV === 'production') {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', DISPLAY_ERRORS ? '1' : '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        // Memory and execution limits
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('upload_max_filesize', '50M');
        ini_set('post_max_size', '52M');
        ini_set('max_input_time', 300);

        // Store configuration in class property for access
        self::$config = [
            'database' => [
                'host' => DB_HOST,
                'port' => DB_PORT,
                'name' => DB_NAME,
                'user' => DB_USER,
                'charset' => DB_CHARSET
            ],
            'storage' => [
                'path' => STORAGE_PATH,
                'temp_path' => UPLOAD_TEMP_PATH,
                'max_file_size' => MAX_FILE_SIZE
            ],
            'optimization' => [
                'enabled' => ENABLE_OPTIMIZATION,
                'thumbnail_sizes' => THUMBNAIL_SIZES,
                'jpeg_quality' => JPEG_QUALITY,
                'webp_quality' => WEBP_QUALITY,
                'webp_enabled' => ENABLE_WEBP_CONVERSION
            ],
            'security' => [
                'ssl_enforce' => SSL_ENFORCE,
                'rate_limit' => RATE_LIMIT,
                'allowed_origins' => ALLOW_ORIGINS
            ],
            'cache' => [
                'driver' => CACHE_DRIVER,
                'host' => CACHE_HOST,
                'port' => CACHE_PORT,
                'prefix' => CACHE_PREFIX
            ]
        ];
    }

    /**
     * Get configuration value
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        // Check if key exists in environment variables first
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        // Check in configuration array
        $keys = explode('.', $key);
        $config = self::$config;

        foreach ($keys as $k) {
            if (isset($config[$k])) {
                $config = $config[$k];
            } else {
                return $default;
            }
        }

        return $config;
    }

    /**
     * Check if application is in development mode
     */
    public static function isDevelopment()
    {
        return APP_ENV === 'development';
    }

    /**
     * Check if application is in production mode
     */
    public static function isProduction()
    {
        return APP_ENV === 'production';
    }

    /**
     * Check if application is in staging mode
     */
    public static function isStaging()
    {
        return APP_ENV === 'staging';
    }

    /**
     * Get all configuration
     */
    public static function getAll()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Validate environment file exists
     */
    public static function validateEnvFile($envPath = null)
    {
        if ($envPath === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
        }

        return file_exists($envPath) && is_readable($envPath);
    }

    /**
     * Create necessary directories
     */
    public static function createDirectories()
    {
        $directories = [
            STORAGE_PATH,
            UPLOAD_TEMP_PATH,
            dirname(LOG_FILE),
            './backups',
            './logs'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Initialize environment
     */
    public static function initialize($envPath = null)
    {
        try {
            // Load environment
            self::load($envPath);

            // Create necessary directories
            self::createDirectories();

            // Set error handler
            if (DEBUG) {
                set_error_handler([self::class, 'errorHandler']);
                set_exception_handler([self::class, 'exceptionHandler']);
            }

            return true;
        } catch (Exception $e) {
            // Fallback to basic configuration
            die("Environment initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Custom error handler for development
     */
    public static function errorHandler($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorType = match ($severity) {
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => 'Unknown'
        };

        error_log("[$errorType] $message in $file on line $line");

        if (DISPLAY_ERRORS) {
            echo "<div style='color: red; border: 1px solid #ccc; padding: 10px; margin: 10px;'>",
                 "<strong>[$errorType]</strong> $message<br>",
                 "<em>File: $file, Line: $line</em>",
                 "</div>";
        }

        return true;
    }

    /**
     * Custom exception handler for development
     */
    public static function exceptionHandler($exception)
    {
        error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());

        if (DISPLAY_ERRORS) {
            echo "<div style='color: red; border: 1px solid #ccc; padding: 10px; margin: 10px;'>",
                 "<strong>Uncaught Exception:</strong> " . $exception->getMessage() . "<br>",
                 "<em>File: " . $exception->getFile() . ", Line: " . $exception->getLine() . "</em><br>",
                 "<pre>" . $exception->getTraceAsString() . "</pre>",
                 "</div>";
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }
}