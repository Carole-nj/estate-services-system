<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('provider');

$uid = current_user()['id'];

$s = $pdo->prepare(
    "SELECT SUM(b.status = 'confirmed') AS awaiting,
            SUM(b.status = 'in_progress') AS active,
            SUM(b.status = 'completed') AS completed,
            SUM(CASE WHEN b.status = 'completed' THEN s.price ELSE 0 END) AS earnings
     FROM bookings b JOIN services s ON s.serviceId = b.serviceId
     WHERE s.providerId = ?"
);
$s->execute([$uid]);
$row = $s->fetch();

$lc = $pdo->prepare("SELECT COUNT(*) FROM services WHERE providerId = ? AND active = 1");
$lc->execute([$uid]);
$listingCount = (int)$lc->fetchColumn();

$r = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, s.price, u.name AS residentName, u.unit
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users u ON u.userId = b.residentId
     WHERE s.providerId = ? AND b.status IN ('confirmed','in_progress')
     ORDER BY b.scheduledDate ASC LIMIT 6"
);
$r->execute([$uid]);
$recentBookings = $r->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Provider dashboard</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= $listingCount ?></div><div class="lbl">Active listings</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($row['awaiting'] ?? 0) ?></div><div class="lbl">Awaiting start</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)($row['active'] ?? 0) ?></div><div class="lbl">In progress</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= money($row['earnings'] ?? 0) ?></div><div class="lbl">Total earnings</div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="h5 mb-0">Upcoming bookings</h3>
  <a href="<?= BASE_URL ?>/provider/bookings.php" class="btn btn-ochre btn-sm">Manage all bookings</a>
</div>

<?php if (empty($recentBookings)): ?>
  <div class="card p-4 text-center text-muted">No confirmed or active bookings right now.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle bg-white">
      <thead><tr><th>Resident</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recentBookings as $b): ?>
        <tr>
          <td><?= h($b['residentName']) ?> <span class="text-muted small">(<?= h($b['unit'] ?: '—') ?>)</span></td>
          <td><?= h($b['serviceTitle']) ?></td>
          <td><?= h(date('d M Y', strtotime($b['scheduledDate']))) ?></td>
          <td><span class="badge <?= status_badge_class($b['status']) ?>"><?= status_label($b['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
