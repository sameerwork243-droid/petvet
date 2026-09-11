<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'list':
    case 'GET':
        $date = $_GET['date'] ?? '';
        $where = "WHERE 1=1";
        $params = [];
        if ($date) { $where .= " AND e.date = ?"; $params[] = $date; }
        $stmt = $pdo->prepare("SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id $where ORDER BY e.date DESC");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'categories':
        $stmt = $pdo->query("SELECT * FROM expense_categories ORDER BY name");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'add_category':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
        $stmt->execute([trim($data['name']), trim($data['description'] ?? '')]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO expenses (name, category_id, amount, date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['name']), $data['category_id'] ?: null, $data['amount'] ?? 0, $data['date'] ?? date('Y-m-d'), trim($data['notes'] ?? '')]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE expenses SET name=?, category_id=?, amount=?, date=?, notes=? WHERE id=?");
        $stmt->execute([trim($data['name']), $data['category_id'] ?: null, $data['amount'] ?? 0, $data['date'] ?? date('Y-m-d'), trim($data['notes'] ?? ''), $id]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
