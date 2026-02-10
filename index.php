<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/security.php';

$pdo = db();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Pending', 'Partial')")->fetchColumn();
$totalSuppliers = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
$completedCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Completed'")->fetchColumn();

$pendingOrders = $pdo->query(
    "SELECT po.po_number, po.status, po.created_at, s.name AS supplier_name
     FROM purchase_orders po
     INNER JOIN suppliers s ON s.id = po.supplier_id
     WHERE po.status IN ('Pending', 'Partial')
     ORDER BY po.created_at DESC
     LIMIT 8"
)->fetchAll();

$supplierHistory = $pdo->query(
    "SELECT s.name,
            COUNT(po.id) AS total_orders,
            COALESCE(SUM((SELECT SUM(poi.ordered_qty * COALESCE(poi.unit_price, 0))
                          FROM purchase_order_items poi
                          WHERE poi.po_id = po.id)), 0) AS order_value
     FROM suppliers s
     LEFT JOIN purchase_orders po ON po.supplier_id = s.id
     GROUP BY s.id
     ORDER BY total_orders DESC, s.name ASC"
)->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-stat shadow-sm"><div class="card-body"><h6 class="text-muted">Pending / Partial Orders</h6><h3><?= $pendingCount ?></h3></div></div>
    </div>
    <div class="col-md-4">
        <div class="card card-stat shadow-sm"><div class="card-body"><h6 class="text-muted">Completed Orders</h6><h3><?= $completedCount ?></h3></div></div>
    </div>
    <div class="col-md-4">
        <div class="card card-stat shadow-sm"><div class="card-body"><h6 class="text-muted">Suppliers</h6><h3><?= $totalSuppliers ?></h3></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Pending Orders</div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                        <tr>
                            <td><?= e($order['po_number']) ?></td>
                            <td><?= e($order['supplier_name']) ?></td>
                            <td><span class="badge text-bg-warning"><?= e($order['status']) ?></span></td>
                            <td><?= e(date('Y-m-d', strtotime((string)$order['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pendingOrders): ?><tr><td colspan="4" class="text-center text-muted">No pending orders.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Supplier-wise Purchase History</div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Supplier</th><th>Total Orders</th><th>Total Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($supplierHistory as $history): ?>
                        <tr>
                            <td><?= e($history['name']) ?></td>
                            <td><?= (int)$history['total_orders'] ?></td>
                            <td>$<?= number_format((float)$history['order_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$supplierHistory): ?><tr><td colspan="3" class="text-center text-muted">No supplier history yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
