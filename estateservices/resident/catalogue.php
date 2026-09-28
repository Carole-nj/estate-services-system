<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('resident');

$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'price_asc';

$sql = "SELECT s.*, u.name AS providerName,
          (SELECT AVG(r.rating) FROM reviews r JOIN bookings b ON b.bookingId = r.bookingId WHERE b.serviceId = s.serviceId) AS avgRating,
          (SELECT COUNT(*) FROM reviews r JOIN bookings b ON b.bookingId = r.bookingId WHERE b.serviceId = s.serviceId) AS reviewCount
        FROM services s JOIN users u ON u.userId = s.providerId
        WHERE s.active = 1 AND u.status = 'active'";
$params = [];
if ($category !== '') {
    $sql .= " AND s.category = ?";
    $params[] = $category;
}
$sql .= match ($sort) {
    'price_desc' => " ORDER BY s.price DESC",
    'rating'     => " ORDER BY avgRating DESC",
    default      => " ORDER BY s.price ASC",
};

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$pageTitle = 'Browse Services';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Service catalogue</h2>

<form method="get" class="row g-2 mb-4">
  <div class="col-auto">
    <select name="category" class="form-select" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach (SERVICE_CATEGORIES as $cat): ?>
        <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="sort" class="form-select" onchange="this.form.submit()">
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
      <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
    </select>
  </div>
</form>

<div class="row g-3">
  <?php if (empty($services)): ?><div class="col-12 text-muted">No services match that filter.</div><?php endif; ?>
  <?php foreach ($services as $svc): ?>
    <div class="col-md-4 col-sm-6">
      <div class="card h-100 p-3">
        <span class="badge bg-light text-dark border mb-2 align-self-start"><?= h($svc['category']) ?></span>
        <h3 class="h6"><?= h($svc['title']) ?></h3>
        <p class="text-muted small mb-2"><?= h($svc['description']) ?></p>
        <p class="small mb-1">By <?= h($svc['providerName']) ?></p>
        <?php if ($svc['reviewCount'] > 0): ?>
          <p class="small mb-2">⭐ <?= number_format((float)$svc['avgRating'], 1) ?> (<?= (int)$svc['reviewCount'] ?> review<?= $svc['reviewCount'] == 1 ? '' : 's' ?>)</p>
        <?php else: ?>
          <p class="small text-muted mb-2">No reviews yet</p>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mt-auto">
          <div>
            <div class="price-tag"><?= money($svc['price']) ?></div>
            <div class="text-muted small"><?= h($svc['durationLabel']) ?></div>
          </div>
          <a href="<?= BASE_URL ?>/resident/book.php?service=<?= (int)$svc['serviceId'] ?>" class="btn btn-ochre btn-sm">Book</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
