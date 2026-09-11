<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'logout':
        session_destroy();
        header('Location: ../index.php');
        exit;
    case 'login':
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            jsonResponse(['success' => true, 'redirect' => '../dashboard.php']);
        }
        jsonResponse(['success' => false, 'error' => 'Invalid credentials'], 401);
    case 'register':
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$username || !$email || !$password) jsonResponse(['error' => 'All fields required'], 400);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, 'ADMIN')");
            $stmt->execute([$name, $username, $email, $hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'ADMIN';
            jsonResponse(['success' => true, 'redirect' => '../dashboard.php']);
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Username or email already exists'], 400);
        }
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
