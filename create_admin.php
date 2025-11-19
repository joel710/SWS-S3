<?php
// create_admin.php
// Include environment configuration
require_once __DIR__ . '/api/config/Environment.php';

// Initialize environment configuration
Environment::initialize();

include_once __DIR__ . '/api/config/database.php';

$database = new Database();
$db = $database->connect();

// Check if connection is successful
if ($db === null) {
    die('Failed to connect to database. Please check your .env configuration.');
}

$username = 'strive';
$password = 'strive2025';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$query = 'INSERT INTO users (username, password) VALUES (:username, :password)';
$stmt = $db->prepare($query);
$stmt->bindParam(':username', $username);
$stmt->bindParam(':password', $hashed_password);

if ($stmt->execute()) {
    echo 'Admin user created successfully.';
} else {
    echo 'Failed to create admin user.';
}