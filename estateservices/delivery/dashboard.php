<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('delivery');

$uid = current_user()['id'];

$s = $pdo->prepare(
    "SELECT SUM(status = 'in_progress') AS active, SUM(status = 'completed') AS completed, COUNT(*) AS total
     FROM bookings WHERE deliveryId = ?"
);
$s->execute([$uid]);
$row = $s->fetch();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Delivery dashboard</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4"><div class="card p-3 stat-card"><div class="num"><?= (int)($row['total'] ?? 0) ?></div><div class="lbl">Total assigned</div></div></div>
  <div class="col-6 col-md-4"><div class="card p-3 stat-card"><div class="num"><?= (int)($row['active'] ?? 0) ?></div><div class="lbl">Out for delivery</div></div></div>
  <div class="col-6 col-md-4"><div class="card p-3 stat-card"><div class="num"><?= (int)($row['completed'] ?? 0) ?></div><div class="lbl">Completed</div></div></div>
</div>

<a href="<?= BASE_URL ?>/delivery/tasks.php" class="btn btn-ochre">View my tasks</a>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
