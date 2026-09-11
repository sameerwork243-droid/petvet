<?php
require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$petId = $_GET['pet_id'] ?? null;

switch ($action) {
    case 'soap_notes':
        if (!$petId) jsonResponse(['error' => 'pet_id required'], 400);
        $stmt = $pdo->prepare("SELECT * FROM soap_notes WHERE pet_id = ? ORDER BY created_at DESC");
        $stmt->execute([$petId]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'add_soap':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO soap_notes (pet_id, appointment_id, doctor, subjective, objective, assessment, diagnosis, `plan`, temperature, heart_rate, respiratory_rate, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['pet_id'], $data['appointment_id'] ?: null, trim($data['doctor'] ?? ''),
            trim($data['subjective'] ?? ''), trim($data['objective'] ?? ''),
            trim($data['assessment'] ?? ''), trim($data['diagnosis'] ?? ''),
            trim($data['plan'] ?? ''), $data['temperature'] ?: null,
            $data['heart_rate'] ?: null, $data['respiratory_rate'] ?: null, $data['weight'] ?: null
        ]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'delete_soap':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $pdo->prepare("DELETE FROM soap_notes WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);
        
    case 'vaccinations':
        if (!$petId) jsonResponse(['error' => 'pet_id required'], 400);
        $stmt = $pdo->prepare("SELECT * FROM vaccinations WHERE pet_id = ? ORDER BY administered_on DESC");
        $stmt->execute([$petId]);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        
    case 'add_vaccination':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO vaccinations (pet_id, vaccine_name, administered_on, next_due_date, batch_number, notes, administered_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['pet_id'], trim($data['vaccine_name'] ?? ''), $data['administered_on'] ?: null, $data['next_due_date'] ?: null, trim($data['batch_number'] ?? ''), trim($data['notes'] ?? ''), trim($data['administered_by'] ?? '')]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    case 'delete_vaccination':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $pdo->prepare("DELETE FROM vaccinations WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);
        
    case 'prescriptions':
        if (!$petId) jsonResponse(['error' => 'pet_id required'], 400);
        $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE pet_id = ? ORDER BY prescribed_on DESC");
        $stmt->execute([$petId]);
        $rxs = $stmt->fetchAll();
        foreach ($rxs as &$rx) {
            $meds = $pdo->prepare("SELECT * FROM prescription_medications WHERE prescription_id=?");
            $meds->execute([$rx['id']]);
            $rx['medications'] = $meds->fetchAll();
        }
        jsonResponse(['success' => true, 'data' => $rxs]);
        
    case 'add_prescription':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("INSERT INTO prescriptions (pet_id, prescribed_by, prescribed_on, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['pet_id'], trim($data['prescribed_by'] ?? ''), $data['prescribed_on'] ?: date('Y-m-d'), trim($data['notes'] ?? '')]);
        $rxId = $pdo->lastInsertId();
        if (!empty($data['medications']) && is_array($data['medications'])) {
            $medStmt = $pdo->prepare("INSERT INTO prescription_medications (prescription_id, name, dosage, frequency, duration) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['medications'] as $m) {
                $medStmt->execute([$rxId, trim($m['name'] ?? ''), trim($m['dosage'] ?? ''), trim($m['frequency'] ?? ''), trim($m['duration'] ?? '')]);
            }
        }
        jsonResponse(['success' => true, 'id' => $rxId]);
        
    case 'delete_prescription':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $pdo->prepare("DELETE FROM prescription_medications WHERE prescription_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM prescriptions WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);
        
    case 'get_patient':
        if (!$id) jsonResponse(['error' => 'pet_id required'], 400);
        $stmt = $pdo->prepare("SELECT p.*, c.client_name, c.contact_number FROM pets p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'data' => $stmt->fetch()]);
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
