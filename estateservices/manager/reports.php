<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('manager');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT b.bookingId, b.scheduledDate, b.status, s.title AS serviceTitle, s.category,
            r.name AS residentName, pr.name AS providerName, p.amount, p.mpesaReceiptNo, p.status AS paymentStatus
     FROM bookings b
     JOIN services s ON s.serviceId = b.serviceId
     JOIN users r ON r.userId = b.residentId
     JOIN users pr ON pr.userId = s.providerId
     LEFT JOIN payments p ON p.bookingId = b.bookingId
     WHERE b.scheduledDate BETWEEN ? AND ?
     ORDER BY b.scheduledDate DESC"
);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

$totalRevenue = array_sum(array_map(fn($r) => $r['paymentStatus'] === 'completed' ? (float)$r['amount'] : 0, $rows));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="estate-serve-report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID', 'Date', 'Service', 'Category', 'Resident', 'Provider', 'Status', 'Payment Status', 'Amount (KSh)', 'Receipt']);
    foreach ($rows as $r) {
        fputcsv($out, [
            'EST-' . str_pad((string)$r['bookingId'], 4, '0', STR_PAD_LEFT), $r['scheduledDate'], $r['serviceTitle'],
            $r['category'], $r['residentName'], $r['providerName'], status_label($r['status']),
            $r['paymentStatus'] ?? 'unpaid', $r['amount'] ?? 0, $r['mpesaReceiptNo'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print">
  <h2 class="h4 mb-0">Service activity report</h2>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">🖨️ Print</button>
    <a href="?from=<?= h($from) ?>&to=<?= h($to) ?>&export=csv" class="btn btn-ochre btn-sm">⬇️ Export CSV</a>
  </div>
</div>

<form method="get" class="row g-2 mb-4 no-print">
  <div class="col-auto"><label class="form-label small mb-0">From</label><input type="date" name="from" value="<?= h($from) ?>" class="form-control form-control-sm"></div>
  <div class="col-auto"><label class="form-label small mb-0">To</label><input type="date" name="to" value="<?= h($to) ?>" class="form-control form-control-sm"></div>
  <div class="col-auto align-self-end"><button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button></div>
</form>

<p class="text-muted">Showing <?= count($rows) ?> booking(s) between <?= h($from) ?> and <?= h($to) ?> · Total revenue: <strong><?= money($totalRevenue) ?></strong></p>

<div class="table-responsive">
  <table class="table table-sm bg-white align-middle">
    <thead><tr><th>Ref</th><th>Date</th><th>Service</th><th>Resident</th><th>Provider</th><th>Status</th><th>Payment</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="small text-muted">EST-<?= str_pad((string)$r['bookingId'], 4, '0', STR_PAD_LEFT) ?></td>
        <td><?= h(date('d M Y', strtotime($r['scheduledDate']))) ?></td>
        <td><?= h($r['serviceTitle']) ?></td>
        <td><?= h($r['residentName']) ?></td>
        <td><?= h($r['providerName']) ?></td>
        <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= status_label($r['status']) ?></span></td>
        <td class="small"><?= h(ucfirst($r['paymentStatus'] ?? 'unpaid')) ?></td>
        <td class="text-end"><?= money($r['amount'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">No bookings in this date range.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
