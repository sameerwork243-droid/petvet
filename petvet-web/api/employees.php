<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'list':
    case 'GET':
        $search = $_GET['search'] ?? '';
        $where = $search ? "WHERE name LIKE ? OR position LIKE ?" : "";
        $params = $search ? ["%$search%", "%$search%"] : [];
        $stmt = $pdo->prepare("SELECT * FROM employees $where ORDER BY name");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO employees (name, position, designation, salary, contact, joined_on) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['name']), trim($data['position'] ?? ''), trim($data['designation'] ?? ''), $data['salary'] ?? 0, trim($data['contact'] ?? ''), $data['joined_on'] ?: null]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE employees SET name=?, position=?, designation=?, salary=?, contact=?, joined_on=? WHERE id=?");
        $stmt->execute([trim($data['name']), trim($data['position'] ?? ''), trim($data['designation'] ?? ''), $data['salary'] ?? 0, trim($data['contact'] ?? ''), $data['joined_on'] ?: null, $id]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
