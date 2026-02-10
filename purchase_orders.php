<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/security.php';

$pdo = db();
$_SESSION['po_wizard'] ??= ['step' => 1, 'supplier_id' => null, 'po_number' => '', 'items' => []];
$wizard = &$_SESSION['po_wizard'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'step1') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $poNumber = trim((string)($_POST['po_number'] ?? ''));

        if ($supplierId > 0 && $poNumber !== '') {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?');
            $exists->execute([$poNumber]);
            if ((int)$exists->fetchColumn() > 0) {
                $error = 'PO number already exists.';
            } else {
                $wizard['supplier_id'] = $supplierId;
                $wizard['po_number'] = $poNumber;
                $wizard['step'] = 2;
                redirect('purchase_orders.php');
            }
        } else {
            $error = 'Supplier and PO number are required.';
        }
    }

    if ($action === 'add_item') {
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $orderedQty = (float)($_POST['ordered_qty'] ?? 0);
        $unitPrice = (float)($_POST['unit_price'] ?? 0);

        if ($itemName !== '' && $orderedQty > 0) {
            $wizard['items'][] = [
                'item_name' => $itemName,
                'ordered_qty' => $orderedQty,
                'unit_price' => $unitPrice,
            ];
            $wizard['step'] = 2;
            redirect('purchase_orders.php');
        }
        $error = 'Item name and ordered quantity are required.';
    }

    if ($action === 'reset') {
        $_SESSION['po_wizard'] = ['step' => 1, 'supplier_id' => null, 'po_number' => '', 'items' => []];
        redirect('purchase_orders.php');
    }

    if ($action === 'create_po') {
        if ($wizard['supplier_id'] && count($wizard['items']) > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO purchase_orders (po_number, supplier_id, status) VALUES (?, ?, ?)');
                $stmt->execute([$wizard['po_number'], $wizard['supplier_id'], 'Pending']);
                $poId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare('INSERT INTO purchase_order_items (po_id, item_name, ordered_qty, unit_price) VALUES (?, ?, ?, ?)');
                foreach ($wizard['items'] as $item) {
                    $itemStmt->execute([$poId, $item['item_name'], $item['ordered_qty'], $item['unit_price']]);
                }

                $pdo->commit();
                $_SESSION['po_wizard'] = ['step' => 1, 'supplier_id' => null, 'po_number' => '', 'items' => []];
                redirect('purchase_orders.php?created=1');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Failed to create PO: ' . $e->getMessage();
            }
        } else {
            $error = 'Please complete steps and add at least one item.';
        }
    }
}

$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();
$orders = $pdo->query(
    "SELECT po.id, po.po_number, po.status, po.created_at, s.name AS supplier_name,
            SUM(poi.ordered_qty) AS total_ordered, SUM(poi.received_qty) AS total_received
     FROM purchase_orders po
     INNER JOIN suppliers s ON s.id = po.supplier_id
     LEFT JOIN purchase_order_items poi ON poi.po_id = po.id
     GROUP BY po.id
     ORDER BY po.created_at DESC"
)->fetchAll();

$pageTitle = 'Purchase Orders';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">Create Purchase Order (Step-by-Step)</div>
            <div class="card-body">
                <?php if (isset($_GET['created'])): ?><div class="alert alert-success">Purchase order created.</div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

                <div class="mb-3 small text-muted">Step 1: PO Header → Step 2: Add Items → Finalize</div>

                <?php if ($wizard['step'] === 1): ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="step1">
                    <div class="col-12">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Choose supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int)$supplier['id'] ?>"><?= e($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">PO Number *</label>
                        <input class="form-control" name="po_number" value="PO-<?= date('Ymd-His') ?>" required>
                    </div>
                    <div class="col-12"><button class="btn btn-primary w-100">Save & Continue</button></div>
                </form>
                <?php else: ?>
                <div class="alert alert-info py-2">
                    <strong>Supplier ID:</strong> <?= (int)$wizard['supplier_id'] ?> |
                    <strong>PO #:</strong> <?= e((string)$wizard['po_number']) ?>
                </div>

                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_item">
                    <div class="col-md-6"><input class="form-control" name="item_name" placeholder="Item name" required></div>
                    <div class="col-md-3"><input class="form-control" type="number" min="0.01" step="0.01" name="ordered_qty" placeholder="Qty" required></div>
                    <div class="col-md-3"><input class="form-control" type="number" min="0" step="0.01" name="unit_price" placeholder="Unit price"></div>
                    <div class="col-12"><button class="btn btn-outline-primary w-100">Add Item</button></div>
                </form>

                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
                        <tbody>
                        <?php foreach ($wizard['items'] as $item): ?>
                        <tr>
                            <td><?= e((string)$item['item_name']) ?></td>
                            <td><?= number_format((float)$item['ordered_qty'], 2) ?></td>
                            <td>$<?= number_format((float)$item['unit_price'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$wizard['items']): ?><tr><td colspan="3" class="text-muted text-center">No items yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_po">
                    <button class="btn btn-success flex-fill" <?= !$wizard['items'] ? 'disabled' : '' ?>>Finalize PO</button>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <button class="btn btn-link text-danger p-0">Reset wizard</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header">All Purchase Orders</div>
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Ordered</th><th>Received</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= e($order['po_number']) ?></td>
                            <td><?= e($order['supplier_name']) ?></td>
                            <td>
                                <?php $badge = $order['status'] === 'Completed' ? 'success' : ($order['status'] === 'Partial' ? 'warning' : 'secondary'); ?>
                                <span class="badge text-bg-<?= $badge ?>"><?= e($order['status']) ?></span>
                            </td>
                            <td><?= number_format((float)$order['total_ordered'], 2) ?></td>
                            <td><?= number_format((float)$order['total_received'], 2) ?></td>
                            <td><?= e(date('Y-m-d', strtotime((string)$order['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?><tr><td colspan="6" class="text-center text-muted">No purchase orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
