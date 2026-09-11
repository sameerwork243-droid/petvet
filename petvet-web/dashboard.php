<?php
require_once __DIR__ . '/config/database.php';
requireLogin();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

// Stats
$totalClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$totalPets = $pdo->query("SELECT COUNT(*) FROM pets WHERE deceased = 0")->fetchColumn();
$totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM billing WHERE status = 'PAID'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= 5")->fetchColumn();
$pendingPayments = $pdo->query("SELECT COALESCE(SUM(final_total - amount_paid),0) FROM billing WHERE status IN ('UNPAID','PARTIALLY_PAID')")->fetchColumn();
$upcomingReminders = $pdo->query("SELECT COUNT(*) FROM reminders WHERE remind_on >= CURDATE() AND is_dismissed = 0")->fetchColumn();

// Recent appointments
$recentAppts = $pdo->query("
    SELECT a.*, c.client_name, p.pet_name 
    FROM appointments a 
    JOIN clients c ON a.client_id = c.id 
    JOIN pets p ON a.pet_id = p.id 
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll();

// Recent billing
$recentBilling = $pdo->query("SELECT * FROM billing ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <span style="color:var(--text-muted);font-size:13px;">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Clients</div>
        <div class="stat-value green"><?= $totalClients ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Patients</div>
        <div class="stat-value blue"><?= $totalPets ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Today's Appointments</div>
        <div class="stat-value yellow"><?= $totalAppointments ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value green"><?= formatCurrency($totalRevenue) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Products</div>
        <div class="stat-value blue"><?= $totalProducts ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value red"><?= $lowStock ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Payments</div>
        <div class="stat-value yellow"><?= formatCurrency($pendingPayments) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Upcoming Reminders</div>
        <div class="stat-value blue"><?= $upcomingReminders ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
        <div class="card-header">
            <h2>Recent Appointments</h2>
            <a href="appointments.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Pet</th><th>Client</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($recentAppts)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No appointments yet</td></tr>
                <?php else: foreach ($recentAppts as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['pet_name']) ?></td>
                        <td><?= htmlspecialchars($a['client_name']) ?></td>
                        <td><?= formatDate($a['appointment_date']) ?></td>
                        <td><span class="badge <?= $a['status'] == 'CONFIRMED' ? 'badge-green' : ($a['status'] == 'CANCELLED' ? 'badge-red' : 'badge-blue') ?>"><?= $a['status'] ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Recent Billing</h2>
            <a href="billing.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($recentBilling)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No billing yet</td></tr>
                <?php else: foreach ($recentBilling as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['invoice_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($b['customer_name'] ?? '-') ?></td>
                        <td><?= formatCurrency($b['final_total']) ?></td>
                        <td><span class="badge <?= $b['status'] == 'PAID' ? 'badge-green' : 'badge-yellow' ?>"><?= $b['status'] ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
