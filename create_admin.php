<?php
// create_admin.php
include_once __DIR__ . '/api/config/database.php';

$database = new Database();
$db = $database->connect();

$username = 'admin';
$password = 'admin';
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
