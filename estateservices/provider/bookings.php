<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('provider');

$uid = current_user()['id'];
$errors = [];
$DELIVERY_CATEGORIES = ['Food Delivery', 'Grocery'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $bookingId = (int)($_POST['bookingId'] ?? 0);
    $action = $_POST['action'] ?? '';

    $check = $pdo->prepare(
        "SELECT b.*, s.providerId, s.title AS serviceTitle, s.category
         FROM bookings b JOIN services s ON s.serviceId = b.serviceId
         WHERE b.bookingId = ? AND s.providerId = ?"
    );
    $check->execute([$bookingId, $uid]);
    $booking = $check->fetch();

    if (empty($errors) && !$booking) {
        $errors[] = 'Booking not found.';
    } elseif (empty($errors)) {
        if ($action === 'start' && $booking['status'] === 'confirmed') {
            $pdo->prepare("UPDATE bookings SET status = 'in_progress' WHERE bookingId = ?")->execute([$bookingId]);
            notify($pdo, (int)$booking['residentId'], 'status_update', "\"{$booking['serviceTitle']}\" is now in progress.");
            flash('success', 'Booking marked in progress.');
        } elseif ($action === 'complete' && $booking['status'] === 'in_progress') {
            $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE bookingId = ?")->execute([$bookingId]);
            notify($pdo, (int)$booking['residentId'], 'status_update', "\"{$booking['serviceTitle']}\" is complete. Please leave a review!");
            flash('success', 'Booking marked complete.');
        } elseif ($action === 'decline' && in_array($booking['status'], ['pending', 'confirmed'], true)) {
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE bookingId = ?")->execute([$bookingId]);
            notify($pdo, (int)$booking['residentId'], 'status_update', "\"{$booking['serviceTitle']}\" was declined by the provider.");
            flash('warning', 'Booking declined.');
        } elseif ($action === 'assign_delivery') {
            $deliveryId = (int)($_POST['deliveryId'] ?? 0);
            $q = $pdo->prepare("SELECT userId FROM users WHERE userId = ? AND role = 'delivery'");
            $q->execute([$deliveryId]);
            if ($q->fetchColumn()) {
                $pdo->prepare("UPDATE bookings SET deliveryId = ? WHERE bookingId = ?")->execute([$deliveryId, $bookingId]);
                notify($pdo, $deliveryId, 'delivery_assigned', "You've been assigned to deliver \"{$booking['serviceTitle']}\" for booking #{$bookingId}.");
                flash('success', 'Delivery person assigned.');
            } else {
                flash('danger', 'Invalid delivery person.');
            }
        } else {
            $errors[] = "That action is not valid for this booking's current status.";
        }

        if (empty($errors)) {
            header('Location: ' . BASE_URL . '/provider/bookings.php');
            exit;
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT b.*, s.title AS serviceTitle, s.price, s.category, u.name AS residentName, u.unit, u.phone, d.name AS deliveryName
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users u ON u.userId = b.residentId
     LEFT JOIN users d ON d.userId = b.deliveryId
     WHERE s.providerId = ? AND b.status != 'cancelled'
     ORDER BY FIELD(b.status, 'confirmed','in_progress','pending','completed'), b.scheduledDate ASC"
);
$stmt->execute([$uid]);
$bookings = $stmt->fetchAll();

$deliveryPeople = $pdo->query("SELECT userId, name FROM users WHERE role = 'delivery' AND status = 'active' ORDER BY name")->fetchAll();

$pageTitle = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Bookings</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
<?php if (empty($bookings)): ?><div class="card p-4 text-center text-muted">No bookings yet.</div><?php endif; ?>

<?php foreach ($bookings as $b): ?>
  <div class="card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <span class="badge <?= status_badge_class($b['status']) ?> mb-1"><?= status_label($b['status']) ?></span>
        <h3 class="h6 mb-1"><?= h($b['serviceTitle']) ?> · <?= money($b['price']) ?></h3>
        <p class="mb-1 small">Resident: <strong><?= h($b['residentName']) ?></strong> (Unit <?= h($b['unit'] ?: '—') ?>, <?= h($b['phone']) ?>)</p>
        <p class="mb-1 small text-muted"><?= h(date('d M Y', strtotime($b['scheduledDate']))) ?> at <?= h(substr($b['scheduledTime'] ?? '', 0, 5)) ?></p>
        <?php if ($b['notes']): ?><p class="mb-1 small text-muted">Notes: <?= h($b['notes']) ?></p><?php endif; ?>
        <?php if ($b['deliveryName']): ?><p class="mb-0 small text-muted">Delivery: <?= h($b['deliveryName']) ?></p><?php endif; ?>
      </div>

      <div class="d-flex flex-column gap-2" style="min-width:200px;">
        <?php if ($b['status'] === 'confirmed'): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$b['bookingId'] ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn-primary btn-sm w-100">Start service</button>
          </form>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$b['bookingId'] ?>">
            <input type="hidden" name="action" value="decline">
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Decline</button>
          </form>
        <?php elseif ($b['status'] === 'in_progress'): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$b['bookingId'] ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="btn btn-success btn-sm w-100">Mark complete</button>
          </form>
        <?php elseif ($b['status'] === 'pending'): ?>
          <span class="small text-muted">Awaiting resident payment</span>
        <?php endif; ?>

        <?php if (in_array($b['category'], $DELIVERY_CATEGORIES, true) && !$b['deliveryName'] && $b['status'] !== 'completed'): ?>
          <form method="post" class="d-flex gap-1">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bookingId" value="<?= (int)$b['bookingId'] ?>">
            <input type="hidden" name="action" value="assign_delivery">
            <select name="deliveryId" class="form-select form-select-sm" required>
              <option value="">Assign delivery…</option>
              <?php foreach ($deliveryPeople as $dp): ?><option value="<?= (int)$dp['userId'] ?>"><?= h($dp['name']) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm">Go</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
