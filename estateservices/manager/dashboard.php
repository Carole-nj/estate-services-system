<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('manager');

$totals = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM bookings) AS totalBookings,
            (SELECT COUNT(*) FROM users WHERE role = 'resident') AS residents,
            (SELECT COUNT(*) FROM users WHERE role = 'provider') AS providers,
            (SELECT SUM(amount) FROM payments WHERE status = 'completed') AS revenue,
            (SELECT COUNT(*) FROM disputes WHERE status = 'open') AS openDisputes"
)->fetch();

$byStatus = $pdo->query("SELECT status, COUNT(*) AS c FROM bookings GROUP BY status")->fetchAll();
$statusLabels = array_map(fn($r) => status_label($r['status']), $byStatus);
$statusCounts = array_map(fn($r) => (int)$r['c'], $byStatus);

$byCategory = $pdo->query(
    "SELECT s.category, COALESCE(SUM(p.amount), 0) AS revenue
     FROM services s
     LEFT JOIN bookings b ON b.serviceId = s.serviceId
     LEFT JOIN payments p ON p.bookingId = b.bookingId AND p.status = 'completed'
     GROUP BY s.category ORDER BY revenue DESC"
)->fetchAll();
$categoryLabels = array_column($byCategory, 'category');
$categoryRevenue = array_map('floatval', array_column($byCategory, 'revenue'));

$topProviders = $pdo->query(
    "SELECT u.name, COUNT(b.bookingId) AS jobs, COALESCE(SUM(p.amount),0) AS revenue,
            (SELECT AVG(r.rating) FROM reviews r JOIN bookings b2 ON b2.bookingId = r.bookingId
             JOIN services s2 ON s2.serviceId = b2.serviceId WHERE s2.providerId = u.userId) AS avgRating
     FROM users u
     JOIN services s ON s.providerId = u.userId
     JOIN bookings b ON b.serviceId = s.serviceId AND b.status = 'completed'
     JOIN payments p ON p.bookingId = b.bookingId AND p.status = 'completed'
     WHERE u.role = 'provider'
     GROUP BY u.userId, u.name
     ORDER BY revenue DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Estate overview</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)$totals['totalBookings'] ?></div><div class="lbl">Total bookings</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= money($totals['revenue'] ?? 0) ?></div><div class="lbl">Total revenue</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)$totals['residents'] ?> / <?= (int)$totals['providers'] ?></div><div class="lbl">Residents / Providers</div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 stat-card"><div class="num"><?= (int)$totals['openDisputes'] ?></div><div class="lbl">Open disputes</div></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-6"><div class="card p-3"><h3 class="h6 mb-2">Bookings by status</h3><canvas id="statusChart" height="220"></canvas></div></div>
  <div class="col-md-6"><div class="card p-3"><h3 class="h6 mb-2">Revenue by category</h3><canvas id="categoryChart" height="220"></canvas></div></div>
</div>

<div class="card p-3">
  <h3 class="h6 mb-3">Top providers by revenue</h3>
  <?php if (empty($topProviders)): ?>
    <p class="text-muted small mb-0">No completed, paid bookings yet.</p>
  <?php else: ?>
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Provider</th><th>Completed jobs</th><th>Rating</th><th class="text-end">Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($topProviders as $p): ?>
        <tr>
          <td><?= h($p['name']) ?></td>
          <td><?= (int)$p['jobs'] ?></td>
          <td><?= $p['avgRating'] ? '⭐ ' . number_format((float)$p['avgRating'], 1) : '—' ?></td>
          <td class="text-end fw-semibold"><?= money($p['revenue']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode($statusLabels) ?>,
            datasets: [{ data: <?= json_encode($statusCounts) ?>,
                         backgroundColor: ['#C6841A','#2E6459','#39508F','#2F6B3B','#A8432F'] }] },
    options: { plugins: { legend: { position: 'bottom' } } },
  });
  new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($categoryLabels) ?>,
            datasets: [{ label: 'Revenue (KSh)', data: <?= json_encode($categoryRevenue) ?>, backgroundColor: '#2E6459' }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
