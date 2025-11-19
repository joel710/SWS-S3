<?php
// Test database connection with detailed diagnostics
echo "=== Database Connection Test ===\n\n";

// Load environment variables manually for testing
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            echo "Loaded $key: $value\n";
        }
    }
} else {
    echo "No .env file found\n";
}

echo "\n=== Connection Parameters ===\n";
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';
$dbPort = getenv('DB_PORT') ?: '3306';

echo "Host: $dbHost\n";
echo "Port: $dbPort\n";
echo "Database: $dbName\n";
echo "Username: $dbUser\n";
// Note: We don't echo the password for security reasons

echo "\n=== Testing Connection ===\n";

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    echo "DSN: $dsn\n";
    
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "✅ Connection successful!\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "MySQL Version: " . $result['version'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    
    // Additional diagnostics
    echo "\n=== Diagnostic Information ===\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "PDO MySQL Extension: " . (extension_loaded('pdo_mysql') ? 'Available' : 'Not available') . "\n";
    
    // Test DNS resolution
    echo "DNS Resolution for $dbHost: ";
    $ip = gethostbyname($dbHost);
    if ($ip !== $dbHost) {
        echo "$ip\n";
    } else {
        echo "Failed\n";
    }
    
    // Test port connectivity
    echo "Testing port connectivity to $dbHost:$dbPort... ";
    $connection = @fsockopen($dbHost, $dbPort, $errno, $errstr, 5);
    if ($connection) {
        echo "Port is open\n";
        fclose($connection);
    } else {
        echo "Port is closed or blocked (Error $errno: $errstr)\n";
    }
}