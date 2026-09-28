<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('provider');

$uid = current_user()['id'];

$stmt = $pdo->prepare(
    "SELECT b.bookingId, s.title AS serviceTitle, s.price, u.name AS residentName,
            p.mpesaReceiptNo, p.createdAt AS paidAt
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users u ON u.userId = b.residentId
     JOIN payments p ON p.bookingId = b.bookingId
     WHERE s.providerId = ? AND b.status = 'completed'
     ORDER BY p.createdAt DESC"
);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();
$total = array_sum(array_column($rows, 'price'));

$pageTitle = 'Earnings';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Earnings</h2>

<div class="card p-3 mb-4 d-inline-block stat-card">
  <div class="lbl">Total earned</div>
  <div class="num"><?= money($total) ?></div>
  <div class="text-muted small"><?= count($rows) ?> completed booking<?= count($rows) === 1 ? '' : 's' ?></div>
</div>

<?php if (empty($rows)): ?>
  <div class="card p-4 text-center text-muted">No completed bookings yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle bg-white">
      <thead><tr><th>Date</th><th>Service</th><th>Resident</th><th>Receipt</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h(date('d M Y', strtotime($r['paidAt']))) ?></td>
          <td><?= h($r['serviceTitle']) ?></td>
          <td><?= h($r['residentName']) ?></td>
          <td class="small text-muted"><?= h($r['mpesaReceiptNo']) ?></td>
          <td class="text-end fw-semibold"><?= money($r['price']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-end"><?= money($total) ?></th></tr></tfoot>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
