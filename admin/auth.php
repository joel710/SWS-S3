<?php
// admin/auth.php
session_start();
include_once __DIR__ . '/../api/config/database.php';

$database = new Database();
$db = $database->connect();

if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = 'SELECT * FROM users WHERE username = :username';
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $user['password'])) {
            $_SESSION['is_admin'] = true;
            header('Location: index.php');
            exit;
        }
    }
}

header('Location: login.php');
