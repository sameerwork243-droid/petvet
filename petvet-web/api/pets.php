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
        $client_id = $_GET['client_id'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        if ($search) { $where .= " AND (p.pet_name LIKE ? OR c.client_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($client_id) { $where .= " AND p.client_id = ?"; $params[] = $client_id; }
        
        $total = $pdo->prepare("SELECT COUNT(*) FROM pets p JOIN clients c ON p.client_id = c.id $where");
        $total->execute($params);
        $count = $total->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT p.*, c.client_name, c.contact_number FROM pets p JOIN clients c ON p.client_id = c.id $where ORDER BY p.pet_name ASC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $count, 'page' => $page, 'pages' => ceil($count/$limit)]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO pets (client_id, pet_name, sex, species, breed, color, date_of_birth, age, is_neutered, is_microchipped) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['client_id'], trim($data['pet_name'] ?? ''), $data['sex'] ?? 'Unknown',
            $data['species'] ?? 'Dog', trim($data['breed'] ?? ''), trim($data['color'] ?? ''),
            $data['date_of_birth'] ?: null, trim($data['age'] ?? ''),
            intval($data['is_neutered'] ?? 0), intval($data['is_microchipped'] ?? 0)
        ]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE pets SET client_id=?, pet_name=?, sex=?, species=?, breed=?, color=?, date_of_birth=?, age=?, is_neutered=?, is_microchipped=? WHERE id=?");
        $stmt->execute([
            $data['client_id'], trim($data['pet_name'] ?? ''), $data['sex'] ?? 'Unknown',
            $data['species'] ?? 'Dog', trim($data['breed'] ?? ''), trim($data['color'] ?? ''),
            $data['date_of_birth'] ?: null, trim($data['age'] ?? ''),
            intval($data['is_neutered'] ?? 0), intval($data['is_microchipped'] ?? 0), $id
        ]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM pets WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
