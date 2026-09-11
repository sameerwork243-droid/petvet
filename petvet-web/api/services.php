<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'list':
    case 'GET':
        $category = $_GET['category'] ?? '';
        $grooming = $_GET['grooming'] ?? '';
        $where = "WHERE 1=1";
        $params = [];
        if ($category) { $where .= " AND category = ?"; $params[] = $category; }
        if ($grooming !== '') { $where .= " AND is_grooming = ?"; $params[] = intval($grooming); }
        $stmt = $pdo->prepare("SELECT * FROM services $where ORDER BY category, name");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO services (name, category, base_rate, purchase_price, is_grooming) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['name']), $data['category'] ?? 'General', $data['base_rate'] ?? 0, $data['purchase_price'] ?? 0, intval($data['is_grooming'] ?? 0)]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE services SET name=?, category=?, base_rate=?, purchase_price=?, is_grooming=? WHERE id=?");
        $stmt->execute([trim($data['name']), $data['category'] ?? 'General', $data['base_rate'] ?? 0, $data['purchase_price'] ?? 0, intval($data['is_grooming'] ?? 0), $id]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM services WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
