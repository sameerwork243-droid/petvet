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
        
        $where = '';
        $params = [];
        if ($search) {
            $where = "WHERE c.client_name LIKE ? OR c.contact_number LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        
        $total = $pdo->prepare("SELECT COUNT(*) FROM clients c $where");
        $total->execute($params);
        $count = $total->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM pets WHERE client_id = c.id) as pet_count FROM clients c $where ORDER BY c.client_name ASC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $count, 'page' => $page, 'pages' => ceil($count/$limit)]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($data['client_name'] ?? '');
        $phone = trim($data['contact_number'] ?? '');
        $address = trim($data['address'] ?? '');
        
        if (!$name) jsonResponse(['error' => 'Client name is required'], 400);
        
        $stmt = $pdo->prepare("INSERT INTO clients (client_name, contact_number, address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $phone, $address]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($data['client_name'] ?? '');
        $phone = trim($data['contact_number'] ?? '');
        $address = trim($data['address'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE clients SET client_name=?, contact_number=?, address=? WHERE id=?");
        $stmt->execute([$name, $phone, $address, $id]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
