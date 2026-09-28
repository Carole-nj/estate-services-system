<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('provider');

$uid = current_user()['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    $action = $_POST['action'] ?? '';

    if (empty($errors) && $action === 'create') {
        $category = $_POST['category'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = $_POST['price'] ?? '';
        $duration = trim($_POST['duration'] ?? '');

        if (!in_array($category, SERVICE_CATEGORIES, true)) $errors[] = 'Please choose a valid category.';
        if ($title === '') $errors[] = 'Title is required.';
        if (!is_numeric($price) || (float)$price <= 0) $errors[] = 'Please enter a valid price.';

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO services (providerId, category, title, description, price, durationLabel, active) VALUES (?,?,?,?,?,?,1)"
            )->execute([$uid, $category, $title, $description, (float)$price, $duration]);
            flash('success', 'Listing added.');
            header('Location: ' . BASE_URL . '/provider/listings.php');
            exit;
        }
    } elseif (empty($errors) && $action === 'toggle') {
        $pdo->prepare("UPDATE services SET active = NOT active WHERE serviceId = ? AND providerId = ?")
            ->execute([(int)($_POST['serviceId'] ?? 0), $uid]);
        flash('success', 'Listing updated.');
        header('Location: ' . BASE_URL . '/provider/listings.php');
        exit;
    } elseif (empty($errors) && $action === 'update_price') {
        $price = $_POST['price'] ?? '';
        if (is_numeric($price) && (float)$price > 0) {
            $pdo->prepare("UPDATE services SET price = ? WHERE serviceId = ? AND providerId = ?")
                ->execute([(float)$price, (int)($_POST['serviceId'] ?? 0), $uid]);
            flash('success', 'Price updated.');
        } else {
            flash('danger', 'Invalid price.');
        }
        header('Location: ' . BASE_URL . '/provider/listings.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM services WHERE providerId = ? ORDER BY createdAt DESC");
$stmt->execute([$uid]);
$listings = $stmt->fetchAll();

$pageTitle = 'My Listings';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">My listings</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <?php if (empty($listings)): ?>
      <div class="card p-4 text-center text-muted">You have no listings yet. Add one to start receiving bookings.</div>
    <?php endif; ?>
    <?php foreach ($listings as $svc): ?>
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="badge bg-light text-dark border mb-1"><?= h($svc['category']) ?></span>
            <h3 class="h6 mb-1"><?= h($svc['title']) ?></h3>
            <p class="text-muted small mb-1"><?= h($svc['description']) ?></p>
            <p class="text-muted small mb-0"><?= h($svc['durationLabel']) ?></p>
          </div>
          <span class="badge <?= $svc['active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $svc['active'] ? 'Active' : 'Inactive' ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 mt-3">
          <form method="post" class="d-flex gap-2 align-items-center flex-grow-1">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="serviceId" value="<?= (int)$svc['serviceId'] ?>">
            <span class="input-group-text bg-white">KSh</span>
            <input type="number" step="1" min="1" name="price" value="<?= (int)$svc['price'] ?>" class="form-control form-control-sm" style="max-width:120px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Update price</button>
          </form>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="serviceId" value="<?= (int)$svc['serviceId'] ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm"><?= $svc['active'] ? 'Deactivate' : 'Reactivate' ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="col-lg-5">
    <div class="card p-3">
      <h3 class="h6 mb-3">Add a new listing</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="mb-2"><label class="form-label small">Category</label>
          <select name="category" class="form-select form-select-sm" required>
            <?php foreach (SERVICE_CATEGORIES as $cat): ?><option value="<?= h($cat) ?>"><?= h($cat) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="form-label small">Title</label><input type="text" name="title" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small">Description</label><textarea name="description" class="form-control form-control-sm" rows="2"></textarea></div>
        <div class="row">
          <div class="col-6 mb-2"><label class="form-label small">Price (KSh)</label><input type="number" name="price" min="1" class="form-control form-control-sm" required></div>
          <div class="col-6 mb-2"><label class="form-label small">Duration</label><input type="text" name="duration" placeholder="e.g. 45 min" class="form-control form-control-sm"></div>
        </div>
        <button type="submit" class="btn btn-ochre btn-sm w-100 mt-2">Add listing</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
