<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('manager');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $disputeId = (int)($_POST['disputeId'] ?? 0);
    $note = trim($_POST['resolutionNote'] ?? '');

    if (empty($errors)) {
        $pdo->prepare("UPDATE disputes SET status = 'resolved', resolutionNote = ?, resolvedAt = NOW() WHERE disputeId = ?")
            ->execute([$note, $disputeId]);

        $d = $pdo->prepare("SELECT raisedBy, bookingId FROM disputes WHERE disputeId = ?");
        $d->execute([$disputeId]);
        $dispute = $d->fetch();
        if ($dispute) {
            notify($pdo, (int)$dispute['raisedBy'], 'dispute_resolved', "Your reported issue for booking #{$dispute['bookingId']} has been resolved.");
        }

        flash('success', 'Dispute marked resolved.');
        header('Location: ' . BASE_URL . '/manager/disputes.php');
        exit;
    }
}

$baseSql = "SELECT d.*, s.title AS serviceTitle, u.name AS residentName
            FROM disputes d
            JOIN bookings b ON b.bookingId = d.bookingId
            JOIN services s ON s.serviceId = b.serviceId
            JOIN users u ON u.userId = d.raisedBy";
$open = $pdo->query($baseSql . " WHERE d.status = 'open' ORDER BY d.createdAt ASC")->fetchAll();
$resolved = $pdo->query($baseSql . " WHERE d.status = 'resolved' ORDER BY d.resolvedAt DESC LIMIT 15")->fetchAll();

$pageTitle = 'Disputes';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Disputes</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>

<h3 class="h6">Open (<?= count($open) ?>)</h3>
<?php if (empty($open)): ?><div class="card p-3 text-muted mb-4">No open disputes. 🎉</div><?php endif; ?>
<?php foreach ($open as $d): ?>
  <div class="card p-3 mb-3">
    <span class="badge bg-warning text-dark mb-1 align-self-start">Open</span>
    <h4 class="h6 mb-1"><?= h($d['serviceTitle']) ?> · booking #<?= (int)$d['bookingId'] ?></h4>
    <p class="mb-1 small">Raised by <?= h($d['residentName']) ?> on <?= h(date('d M Y', strtotime($d['createdAt']))) ?></p>
    <p class="mb-0"><?= nl2br(h($d['description'])) ?></p>
    <form method="post" class="mt-3 d-flex gap-2">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="disputeId" value="<?= (int)$d['disputeId'] ?>">
      <input type="text" name="resolutionNote" class="form-control form-control-sm" placeholder="Resolution note (optional)">
      <button type="submit" class="btn btn-success btn-sm text-nowrap">Mark resolved</button>
    </form>
  </div>
<?php endforeach; ?>

<h3 class="h6 mt-4">Recently resolved</h3>
<?php if (empty($resolved)): ?><div class="card p-3 text-muted">None yet.</div><?php endif; ?>
<?php foreach ($resolved as $d): ?>
  <div class="card p-3 mb-2">
    <span class="badge bg-success mb-1 align-self-start">Resolved</span>
    <h4 class="h6 mb-1"><?= h($d['serviceTitle']) ?> · booking #<?= (int)$d['bookingId'] ?></h4>
    <p class="mb-1 small text-muted">Raised by <?= h($d['residentName']) ?> · resolved <?= h(date('d M Y', strtotime($d['resolvedAt']))) ?></p>
    <p class="mb-1"><?= nl2br(h($d['description'])) ?></p>
    <?php if ($d['resolutionNote']): ?><p class="mb-0 small text-success">Resolution: <?= h($d['resolutionNote']) ?></p><?php endif; ?>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
