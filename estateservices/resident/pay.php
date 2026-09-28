<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$user = current_user();
$bookingId = (int)($_GET['booking'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, s.price
     FROM bookings b JOIN services s ON s.serviceId = b.serviceId
     WHERE b.bookingId = ? AND b.residentId = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('danger', 'Booking not found.');
    header('Location: ' . BASE_URL . '/resident/bookings.php');
    exit;
}

$payStmt = $pdo->prepare("SELECT * FROM payments WHERE bookingId = ?");
$payStmt->execute([$bookingId]);
$existingPayment = $payStmt->fetch();

$pageTitle = 'Pay for booking';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h2 class="h4 mb-3">Confirm &amp; pay</h2>
      <table class="table table-borderless mb-4">
        <tr><td class="text-muted">Service</td><td class="text-end fw-semibold"><?= h($booking['serviceTitle']) ?></td></tr>
        <tr><td class="text-muted">Date</td><td class="text-end"><?= h(date('d M Y', strtotime($booking['scheduledDate']))) ?> at <?= h(substr($booking['scheduledTime'] ?? '', 0, 5)) ?></td></tr>
        <tr><td class="text-muted">Amount</td><td class="text-end price-tag fs-5"><?= money($booking['price']) ?></td></tr>
      </table>

      <?php if ($existingPayment && $existingPayment['status'] === 'completed'): ?>
        <div class="alert alert-success">✅ Already paid, receipt <strong><?= h($existingPayment['mpesaReceiptNo']) ?></strong>.</div>
        <a href="<?= BASE_URL ?>/resident/bookings.php" class="btn btn-ochre w-100">View my bookings</a>
      <?php else: ?>
        <label class="form-label">M-Pesa phone number</label>
        <input type="text" id="phone" class="form-control mb-3" value="<?= h($user['phone']) ?>" placeholder="07XX XXX XXX">
        <button id="pay-btn" class="btn btn-ochre w-100">Pay <?= money($booking['price']) ?> via M-Pesa</button>
        <p class="text-muted small mt-2 mb-0">This simulates the Safaricom Daraja STK Push flow. No real money moves.</p>
        <div id="pay-status" class="alert alert-info mt-3 d-none"></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
  const payBtn = document.getElementById('pay-btn');
  if (payBtn) {
    payBtn.addEventListener('click', function () {
      const phone = document.getElementById('phone').value.trim();
      if (!phone) { alert('Please enter a phone number.'); return; }
      payBtn.disabled = true;
      payBtn.textContent = 'Processing…';
      const box = document.getElementById('pay-status');
      box.classList.remove('d-none', 'alert-danger', 'alert-success');
      box.classList.add('alert-info');
      triggerStkPush(
        '<?= BASE_URL ?>', <?= (int)$booking['bookingId'] ?>, phone, '<?= h(csrf_token()) ?>',
        function (msg) { box.textContent = msg; },
        function (data) {
          box.classList.remove('alert-info');
          if (data.success) {
            box.classList.add('alert-success');
            setTimeout(function () { window.location.href = '<?= BASE_URL ?>/resident/bookings.php'; }, 1200);
          } else {
            box.classList.add('alert-danger');
            payBtn.disabled = false;
            payBtn.textContent = 'Try again';
          }
        }
      );
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
