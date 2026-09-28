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
if ($booking['status'] !== 'completed') {
    flash('warning', 'You can only review a booking once the service is marked complete.');
    header('Location: ' . BASE_URL . '/resident/bookings.php');
    exit;
}
$existing = $pdo->prepare("SELECT reviewId FROM reviews WHERE bookingId = ?");
$existing->execute([$bookingId]);
if ($existing->fetchColumn()) {
    flash('info', "You've already reviewed this booking.");
    header('Location: ' . BASE_URL . '/resident/bookings.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) $errors[] = 'Please choose a rating between 1 and 5.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO reviews (bookingId, rating, comment) VALUES (?, ?, ?)")->execute([$bookingId, $rating, $comment]);
        flash('success', 'Thanks for your review!');
        header('Location: ' . BASE_URL . '/resident/bookings.php');
        exit;
    }
}

$pageTitle = 'Leave a review';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h2 class="h4 mb-1">Rate your experience</h2>
      <p class="text-muted mb-3"><?= h($booking['serviceTitle']) ?></p>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="booking" value="<?= (int)$bookingId ?>">
        <div class="mb-3"><label class="form-label">Rating</label>
          <select name="rating" class="form-select" required>
            <option value="">Choose a rating</option>
            <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
            <option value="4">⭐⭐⭐⭐ Good</option>
            <option value="3">⭐⭐⭐ Okay</option>
            <option value="2">⭐⭐ Poor</option>
            <option value="1">⭐ Very poor</option>
          </select></div>
        <div class="mb-3"><label class="form-label">Comment <span class="text-muted">(optional)</span></label>
          <textarea name="comment" class="form-control" rows="3"></textarea></div>
        <button type="submit" class="btn btn-ochre w-100">Submit review</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
