<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$user = current_user();

$stmt = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, s.price, s.category, u.name AS providerName,
            p.status AS paymentStatus, p.mpesaReceiptNo, r.reviewId
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users u ON u.userId = s.providerId
     LEFT JOIN payments p ON p.bookingId = b.bookingId
     LEFT JOIN reviews r ON r.bookingId = b.bookingId
     WHERE b.residentId = ?
     ORDER BY b.createdAt DESC"
);
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="h4 mb-0">My bookings</h2>
  <div>
    <a href="<?= BASE_URL ?>/resident/bookings.php" class="btn btn-outline-secondary btn-sm">🔄 Refresh status</a>
    <a href="<?= BASE_URL ?>/resident/catalogue.php" class="btn btn-ochre btn-sm">+ New booking</a>
  </div>
</div>

<?php if (empty($bookings)): ?>
  <div class="card p-4 text-center text-muted">No bookings yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle bg-white">
      <thead><tr><th>#</th><th>Service</th><th>Provider</th><th>Date</th><th>Price</th><th>Status</th><th>Payment</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="small text-muted">EST-<?= str_pad((string)$b['bookingId'], 4, '0', STR_PAD_LEFT) ?></td>
          <td><?= h($b['serviceTitle']) ?><div class="small text-muted"><?= h($b['category']) ?></div></td>
          <td><?= h($b['providerName']) ?></td>
          <td><?= h(date('d M Y', strtotime($b['scheduledDate']))) ?><div class="small text-muted"><?= h(substr($b['scheduledTime'] ?? '', 0, 5)) ?></div></td>
          <td><?= money($b['price']) ?></td>
          <td><span class="badge <?= status_badge_class($b['status']) ?>"><?= status_label($b['status']) ?></span></td>
          <td><?= $b['paymentStatus'] === 'completed' ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-secondary">Unpaid</span>' ?></td>
          <td class="text-end">
            <?php if ($b['status'] === 'pending' && $b['paymentStatus'] !== 'completed'): ?>
              <a href="<?= BASE_URL ?>/resident/pay.php?booking=<?= (int)$b['bookingId'] ?>" class="btn btn-ochre btn-sm">Pay now</a>
            <?php elseif ($b['status'] === 'completed' && !$b['reviewId']): ?>
              <a href="<?= BASE_URL ?>/resident/review.php?booking=<?= (int)$b['bookingId'] ?>" class="btn btn-outline-secondary btn-sm">Leave review</a>
            <?php endif; ?>
            <?php if ($b['status'] !== 'cancelled'): ?>
              <a href="<?= BASE_URL ?>/resident/dispute.php?booking=<?= (int)$b['bookingId'] ?>" class="btn btn-outline-danger btn-sm">Raise issue</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
