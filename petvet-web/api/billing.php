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
        $status = $_GET['status'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        if ($search) { $where .= " AND (customer_name LIKE ? OR invoice_no LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($status) { $where .= " AND status = ?"; $params[] = $status; }
        
        $total = $pdo->prepare("SELECT COUNT(*) FROM billing $where");
        $total->execute($params);
        $count = $total->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT b.*, c.contact_number as client_phone FROM billing b LEFT JOIN clients c ON b.client_id = c.id $where ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        
        $billings = $stmt->fetchAll();
        foreach ($billings as &$bill) {
            $items = $pdo->prepare("SELECT * FROM billing_items WHERE billing_id=?");
            $items->execute([$bill['id']]);
            $bill['items'] = $items->fetchAll();
        }
        jsonResponse(['success' => true, 'data' => $billings, 'total' => $count, 'page' => $page, 'pages' => ceil($count/$limit)]);
        
    case 'add':
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        $finalTotal = $subtotal - $discount;
        $amountPaid = floatval($data['amount_paid'] ?? 0);
        $status = $amountPaid >= $finalTotal ? 'PAID' : ($amountPaid > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
        
        $stmt = $pdo->prepare("INSERT INTO billing (appointment_id, client_id, customer_name, customer_phone, subtotal, discount, final_total, amount_paid, status, payment_mode, coupon_code, invoice_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['appointment_id'] ?: null, $data['client_id'] ?: null,
            trim($data['customer_name'] ?? ''), trim($data['customer_phone'] ?? ''),
            $subtotal, $discount, $finalTotal, $amountPaid, $status,
            $data['payment_mode'] ?? 'CASH', trim($data['coupon_code'] ?? ''), $invoiceNo
        ]);
        $billingId = $pdo->lastInsertId();
        
        if (!empty($data['items']) && is_array($data['items'])) {
            $itemStmt = $pdo->prepare("INSERT INTO billing_items (billing_id, product_id, name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['items'] as $item) {
                $qty = intval($item['quantity'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $itemStmt->execute([$billingId, $item['product_id'] ?? null, $item['name'] ?? '', $qty, $price, $qty * $price]);
                if (!empty($item['product_id'])) {
                    $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?")->execute([$qty, $item['product_id'], $qty]);
                }
            }
        }
        
        jsonResponse(['success' => true, 'id' => $billingId, 'invoice_no' => $invoiceNo]);
        
    case 'edit':
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE billing SET customer_name=?, customer_phone=?, subtotal=?, discount=?, final_total=?, amount_paid=?, status=?, payment_mode=? WHERE id=?");
        $stmt->execute([
            trim($data['customer_name'] ?? ''), trim($data['customer_phone'] ?? ''),
            $data['subtotal'] ?? 0, $data['discount'] ?? 0, $data['final_total'] ?? 0,
            $data['amount_paid'] ?? 0, $data['status'] ?? 'UNPAID', $data['payment_mode'] ?? 'CASH', $id
        ]);
        jsonResponse(['success' => true]);
        
    case 'delete':
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID required'], 400);
        $pdo->prepare("DELETE FROM billing_items WHERE billing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM billing WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);
}
