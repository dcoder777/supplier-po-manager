<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/security.php';

$pdo = db();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO suppliers (name, phone, address, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $phone ?: null, $address ?: null, $notes ?: null]);
        redirect('suppliers.php?saved=1');
    }
    $message = 'Supplier name is required.';
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Supplier Management';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">Add Supplier</div>
            <div class="card-body">
                <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Supplier saved.</div><?php endif; ?>
                <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="col-12"><label class="form-label">Name *</label><input class="form-control" name="name" required></div>
                    <div class="col-12"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Save Supplier</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">Suppliers</div>
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?= e($supplier['name']) ?></td>
                            <td><?= e((string)$supplier['phone']) ?></td>
                            <td><?= e((string)$supplier['address']) ?></td>
                            <td><?= e((string)$supplier['notes']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$suppliers): ?><tr><td colspan="4" class="text-center text-muted">No suppliers added yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
