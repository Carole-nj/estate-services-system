<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$user = current_user();
$bookingId = (int)($_GET['booking'] ?? $_POST['booking'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle FROM bookings b JOIN services s ON s.serviceId = b.serviceId
     WHERE b.bookingId = ? AND b.residentId = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('danger', 'Booking not found.');
    header('Location: ' . BASE_URL . '/resident/bookings.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    $description = trim($_POST['description'] ?? '');
    if ($description === '') $errors[] = 'Please describe the issue.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO disputes (bookingId, raisedBy, description) VALUES (?, ?, ?)")
            ->execute([$bookingId, $user['id'], $description]);

        foreach ($pdo->query("SELECT userId FROM users WHERE role = 'manager'")->fetchAll() as $m) {
            notify($pdo, (int)$m['userId'], 'dispute', "New dispute raised on booking #{$bookingId} ({$booking['serviceTitle']}).");
        }

        flash('success', 'Your issue has been reported to the estate manager.');
        header('Location: ' . BASE_URL . '/resident/bookings.php');
        exit;
    }
}

$pageTitle = 'Raise an issue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h2 class="h4 mb-1">Raise an issue</h2>
      <p class="text-muted mb-3"><?= h($booking['serviceTitle']) ?> · EST-<?= str_pad((string)$bookingId, 4, '0', STR_PAD_LEFT) ?></p>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="booking" value="<?= (int)$bookingId ?>">
        <div class="mb-3"><label class="form-label">What went wrong?</label>
          <textarea name="description" class="form-control" rows="4" required></textarea></div>
        <button type="submit" class="btn btn-outline-danger w-100">Submit to estate manager</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
