<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'list':
    case 'GET':
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $where = "WHERE 1=1";
        $params = [];
        if ($search) { $where .= " AND (name LIKE ? OR barcode_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $total = $pdo->prepare("SELECT COUNT(*) FROM products $where");
        $total->execute($params);
        $count = $total->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM products $where ORDER BY name LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $count, 'page' => $page, 'pages' => ceil($count/$limit)]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO products (barcode_number, name, price, quantity, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['barcode_number'] ?? ''), trim($data['name']), $data['price'] ?? 0, $data['quantity'] ?? 0, $data['category'] ?? '']);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE products SET barcode_number=?, name=?, price=?, quantity=?, category=? WHERE id=?");
        $stmt->execute([trim($data['barcode_number'] ?? ''), trim($data['name']), $data['price'] ?? 0, $data['quantity'] ?? 0, $data['category'] ?? '', $id]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        
    case 'search':
        $q = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT id, name, price, quantity, barcode_number FROM products WHERE quantity > 0 AND name LIKE ? ORDER BY name LIMIT 10");
        $stmt->execute(["%$q%"]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}
