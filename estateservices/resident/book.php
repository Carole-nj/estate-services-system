<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$user = current_user();
$serviceId = (int)($_GET['service'] ?? $_POST['service'] ?? 0);

$stmt = $pdo->prepare("SELECT s.*, u.name AS providerName FROM services s JOIN users u ON u.userId = s.providerId WHERE s.serviceId = ? AND s.active = 1");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    flash('danger', 'That service could not be found.');
    header('Location: ' . BASE_URL . '/resident/catalogue.php');
    exit;
}

$errors = [];
$scheduledDate = date('Y-m-d');
$scheduledTime = '09:00';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $scheduledDate = $_POST['date'] ?? '';
    $scheduledTime = $_POST['time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($scheduledDate)) $errors[] = 'Please choose a date.';
    if (empty($scheduledTime)) $errors[] = 'Please choose a time.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO bookings (residentId, serviceId, status, scheduledDate, scheduledTime, notes) VALUES (?, ?, 'pending', ?, ?, ?)"
            )->execute([$user['id'], $service['serviceId'], $scheduledDate, $scheduledTime, $notes]);
            $bookingId = (int)$pdo->lastInsertId();

            notify($pdo, (int)$service['providerId'], 'booking_request',
                "New booking request for \"{$service['title']}\" from {$user['name']} on " . date('d M', strtotime($scheduledDate)) . '.');

            $pdo->commit();
            header('Location: ' . BASE_URL . '/resident/pay.php?booking=' . $bookingId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong creating your booking. Please try again.';
        }
    }
}

$pageTitle = 'Book ' . $service['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card p-4">
      <span class="badge bg-light text-dark border mb-2 align-self-start"><?= h($service['category']) ?></span>
      <h2 class="h4"><?= h($service['title']) ?></h2>
      <p class="text-muted"><?= h($service['description']) ?></p>
      <p class="mb-1">Provider: <strong><?= h($service['providerName']) ?></strong></p>
      <p class="mb-3"><span class="price-tag fs-5"><?= money($service['price']) ?></span> · <?= h($service['durationLabel']) ?></p>

      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="service" value="<?= (int)$service['serviceId'] ?>">
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Preferred date</label>
            <input type="date" name="date" class="form-control" value="<?= h($scheduledDate) ?>" min="<?= date('Y-m-d') ?>" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">Preferred time</label>
            <input type="time" name="time" class="form-control" value="<?= h($scheduledTime) ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Notes for the provider <span class="text-muted">(optional)</span></label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Gate code, grocery list, specific instructions…"><?= h($notes) ?></textarea></div>
        <div class="alert alert-secondary small">You'll pay <strong><?= money($service['price']) ?></strong> via M-Pesa on the next step to confirm this booking.</div>
        <button type="submit" class="btn btn-ochre w-100">Continue to payment</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
