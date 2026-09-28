<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('delivery');

$uid = current_user()['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $bookingId = (int)($_POST['bookingId'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $check = $pdo->prepare(
        "SELECT b.*, s.title AS serviceTitle FROM bookings b JOIN services s ON s.serviceId = b.serviceId
         WHERE b.bookingId = ? AND b.deliveryId = ?"
    );
    $check->execute([$bookingId, $uid]);
    $booking = $check->fetch();

    if (empty($errors) && !$booking) {
        $errors[] = 'Task not found.';
    } elseif (empty($errors) && !in_array($newStatus, ['in_progress', 'completed'], true)) {
        $errors[] = 'Invalid status.';
    } elseif (empty($errors)) {
        $pdo->prepare("UPDATE bookings SET status = ? WHERE bookingId = ?")->execute([$newStatus, $bookingId]);
        $label = $newStatus === 'in_progress' ? 'is now out for delivery' : 'has been delivered';
        notify($pdo, (int)$booking['residentId'], 'delivery_update', "\"{$booking['serviceTitle']}\" {$label}.");
        flash('success', 'Task updated.');
        header('Location: ' . BASE_URL . '/delivery/tasks.php');
        exit;
    }
}

$stmt = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, u.name AS residentName, u.unit, u.phone
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users u ON u.userId = b.residentId
     WHERE b.deliveryId = ? AND b.status IN ('confirmed','in_progress','completed')
     ORDER BY FIELD(b.status, 'confirmed','in_progress','completed'), b.scheduledDate ASC"
);
$stmt->execute([$uid]);
$tasks = $stmt->fetchAll();

$pageTitle = 'My Tasks';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">My delivery tasks</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
<?php if (empty($tasks)): ?><div class="card p-4 text-center text-muted">No deliveries assigned to you yet.</div><?php endif; ?>

<?php foreach ($tasks as $t): ?>
  <div class="card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <span class="badge <?= status_badge_class($t['status']) ?> mb-1"><?= status_label($t['status']) ?></span>
        <h3 class="h6 mb-1"><?= h($t['serviceTitle']) ?></h3>
        <p class="mb-0 small">Deliver to: <strong><?= h($t['residentName']) ?></strong>, Unit <?= h($t['unit'] ?: '—') ?> · <?= h($t['phone']) ?></p>
        <?php if ($t['notes']): ?><p class="mb-0 small text-muted">Notes: <?= h($t['notes']) ?></p><?php endif; ?>
      </div>
      <div>
        <?php if ($t['status'] === 'confirmed'): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$t['bookingId'] ?>">
            <input type="hidden" name="status" value="in_progress">
            <button type="submit" class="btn btn-primary btn-sm">Start delivery</button>
          </form>
        <?php elseif ($t['status'] === 'in_progress'): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$t['bookingId'] ?>">
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-success btn-sm">Mark delivered</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
