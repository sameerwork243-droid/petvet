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
        $date = $_GET['date'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        if ($search) { $where .= " AND (c.client_name LIKE ? OR p.pet_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($date) { $where .= " AND a.appointment_date = ?"; $params[] = $date; }
        if ($status) { $where .= " AND a.status = ?"; $params[] = $status; }
        
        $total = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN clients c ON a.client_id=c.id JOIN pets p ON a.pet_id=p.id $where");
        $total->execute($params);
        $count = $total->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT a.*, c.client_name, p.pet_name,
            (SELECT COALESCE(SUM(rate*quantity),0) FROM appointment_services WHERE appointment_id=a.id) as total_amount
            FROM appointments a JOIN clients c ON a.client_id=c.id JOIN pets p ON a.pet_id=p.id $where 
            ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        
        $appts = $stmt->fetchAll();
        foreach ($appts as &$a) {
            $svc = $pdo->prepare("SELECT * FROM appointment_services WHERE appointment_id=?");
            $svc->execute([$a['id']]);
            $a['services'] = $svc->fetchAll();
        }
        jsonResponse(['success' => true, 'data' => $appts, 'total' => $count, 'page' => $page, 'pages' => ceil($count/$limit)]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO appointments (pet_id, client_id, appointment_date, appointment_time, notes, status, doctor, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['pet_id'], $data['client_id'], $data['appointment_date'],
            $data['appointment_time'] ?: null, trim($data['notes'] ?? ''),
            $data['status'] ?? 'CONFIRMED', trim($data['doctor'] ?? ''), $data['branch_id'] ?: null
        ]);
        $apptId = $pdo->lastInsertId();
        
        if (!empty($data['services']) && is_array($data['services'])) {
            $svcStmt = $pdo->prepare("INSERT INTO appointment_services (appointment_id, service_id, service_name, quantity, rate, notes) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['services'] as $s) {
                $svcStmt->execute([$apptId, $s['service_id'] ?? null, $s['service_name'] ?? '', $s['quantity'] ?? 1, $s['rate'] ?? 0, $s['notes'] ?? '']);
            }
        }
        jsonResponse(['success' => true, 'id' => $apptId]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE appointments SET pet_id=?, client_id=?, appointment_date=?, appointment_time=?, notes=?, status=?, doctor=? WHERE id=?");
        $stmt->execute([
            $data['pet_id'], $data['client_id'], $data['appointment_date'],
            $data['appointment_time'] ?: null, trim($data['notes'] ?? ''),
            $data['status'] ?? 'CONFIRMED', trim($data['doctor'] ?? ''), $id
        ]);
        
        if (!empty($data['services']) && is_array($data['services'])) {
            $pdo->prepare("DELETE FROM appointment_services WHERE appointment_id=?")->execute([$id]);
            $svcStmt = $pdo->prepare("INSERT INTO appointment_services (appointment_id, service_id, service_name, quantity, rate, notes) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['services'] as $s) {
                $svcStmt->execute([$id, $s['service_id'] ?? null, $s['service_name'] ?? '', $s['quantity'] ?? 1, $s['rate'] ?? 0, $s['notes'] ?? '']);
            }
        }
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
}
