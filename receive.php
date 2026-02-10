<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/security.php';

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $poId = (int)($_POST['po_id'] ?? 0);

    if ($poId > 0) {
        $itemsStmt = $pdo->prepare('SELECT id, item_name, ordered_qty, received_qty FROM purchase_order_items WHERE po_id = ?');
        $itemsStmt->execute([$poId]);
        $items = $itemsStmt->fetchAll();

        if ($items) {
            $pdo->beginTransaction();
            try {
                $updateItem = $pdo->prepare('UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ?');
                $insertLog = $pdo->prepare('INSERT INTO receiving_logs (po_item_id, received_qty, note) VALUES (?, ?, ?)');
                $insertStock = $pdo->prepare('INSERT INTO stock_items (item_name, stock_qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE stock_qty = stock_qty + VALUES(stock_qty)');

                $hasReceipt = false;
                foreach ($items as $item) {
                    $field = 'receive_' . $item['id'];
                    $qty = (float)($_POST[$field] ?? 0);
                    $remaining = (float)$item['ordered_qty'] - (float)$item['received_qty'];

                    if ($qty > 0) {
                        if ($qty > $remaining) {
                            throw new RuntimeException('Received quantity cannot exceed remaining quantity for ' . $item['item_name']);
                        }
                        $updateItem->execute([$qty, $item['id']]);
                        $insertLog->execute([$item['id'], $qty, trim((string)($_POST['note'] ?? '')) ?: null]);
                        $insertStock->execute([$item['item_name'], $qty]);
                        $hasReceipt = true;
                    }
                }

                if (!$hasReceipt) {
                    throw new RuntimeException('Enter at least one received quantity.');
                }

                $statusStmt = $pdo->prepare('SELECT SUM(ordered_qty) AS ordered_total, SUM(received_qty) AS received_total FROM purchase_order_items WHERE po_id = ?');
                $statusStmt->execute([$poId]);
                $totals = $statusStmt->fetch();
                $orderedTotal = (float)$totals['ordered_total'];
                $receivedTotal = (float)$totals['received_total'];
                $status = $receivedTotal <= 0 ? 'Pending' : ($receivedTotal < $orderedTotal ? 'Partial' : 'Completed');

                $updatePO = $pdo->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?');
                $updatePO->execute([$status, $poId]);

                $pdo->commit();
                redirect('receive.php?saved=1');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        } else {
            $error = 'No items found for selected PO.';
        }
    } else {
        $error = 'Please select a purchase order.';
    }
}

$poList = $pdo->query(
    "SELECT po.id, po.po_number, po.status, s.name AS supplier_name
     FROM purchase_orders po
     INNER JOIN suppliers s ON s.id = po.supplier_id
     WHERE po.status IN ('Pending', 'Partial')
     ORDER BY po.created_at DESC"
)->fetchAll();

$selectedPoId = (int)($_GET['po_id'] ?? 0);
$selectedItems = [];
if ($selectedPoId > 0) {
    $itemStmt = $pdo->prepare('SELECT id, item_name, ordered_qty, received_qty FROM purchase_order_items WHERE po_id = ?');
    $itemStmt->execute([$selectedPoId]);
    $selectedItems = $itemStmt->fetchAll();
}

$pageTitle = 'Receive Goods';
require __DIR__ . '/partials/header.php';
?>
<div class="card shadow-sm">
    <div class="card-header">Record Received Items & Update Stock</div>
    <div class="card-body">
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Receiving recorded and stock updated.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="get" class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label">Select Pending/Partial PO</label>
                <select name="po_id" class="form-select" required>
                    <option value="">Choose purchase order</option>
                    <?php foreach ($poList as $po): ?>
                        <option value="<?= (int)$po['id'] ?>" <?= $selectedPoId === (int)$po['id'] ? 'selected' : '' ?>>
                            <?= e($po['po_number']) ?> - <?= e($po['supplier_name']) ?> (<?= e($po['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Load Items</button></div>
        </form>

        <?php if ($selectedPoId && $selectedItems): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="po_id" value="<?= $selectedPoId ?>">
            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Receive Now</th></tr></thead>
                    <tbody>
                    <?php foreach ($selectedItems as $item): $remaining = (float)$item['ordered_qty'] - (float)$item['received_qty']; ?>
                        <tr>
                            <td><?= e($item['item_name']) ?></td>
                            <td><?= number_format((float)$item['ordered_qty'], 2) ?></td>
                            <td><?= number_format((float)$item['received_qty'], 2) ?></td>
                            <td><?= number_format($remaining, 2) ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="<?= $remaining ?>" class="form-control" name="receive_<?= (int)$item['id'] ?>" placeholder="0">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" placeholder="Optional receiving note"></div>
            <button class="btn btn-success">Save Receiving</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
