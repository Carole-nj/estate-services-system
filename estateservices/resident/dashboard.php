<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$uid = current_user()['id'];

$c = $pdo->prepare(
    "SELECT SUM(status = 'pending') AS pending,
            SUM(status IN ('confirmed','in_progress')) AS active,
            SUM(status = 'completed') AS completed,
            COUNT(*) AS total
     FROM bookings WHERE residentId = ?"
);
$c->execute([$uid]);
$stats = $c->fetch();

$r = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, s.price
     FROM bookings b JOIN services s ON s.serviceId = b.serviceId
     WHERE b.residentId = ? ORDER BY b.createdAt DESC LIMIT 5"
);
$r->execute([$uid]);
$recentBookings = $r->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Welcome back, <?= h(explode(' ', current_user()['name'])[0]) ?> 👋</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($stats['total'] ?? 0) ?></div><div class="lbl">Total bookings</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($stats['pending'] ?? 0) ?></div><div class="lbl">Pending payment</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($stats['active'] ?? 0) ?></div><div class="lbl">In progress</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($stats['completed'] ?? 0) ?></div><div class="lbl">Completed</div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="h5 mb-0">Recent bookings</h3>
  <a href="<?= BASE_URL ?>/resident/catalogue.php" class="btn btn-ochre btn-sm">+ Book a service</a>
</div>

<?php if (empty($recentBookings)): ?>
  <div class="card p-4 text-center text-muted">No bookings yet. <a href="<?= BASE_URL ?>/resident/catalogue.php">Browse the service catalogue</a> to get started.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle bg-white">
      <thead><tr><th>Service</th><th>Date</th><th>Price</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentBookings as $b): ?>
        <tr>
          <td><?= h($b['serviceTitle']) ?></td>
          <td><?= h(date('d M Y', strtotime($b['scheduledDate']))) ?></td>
          <td><?= money($b['price']) ?></td>
          <td><span class="badge <?= status_badge_class($b['status']) ?>"><?= status_label($b['status']) ?></span></td>
          <td><a href="<?= BASE_URL ?>/resident/bookings.php" class="small">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
