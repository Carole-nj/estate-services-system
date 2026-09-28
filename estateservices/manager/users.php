<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('manager');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $userId = (int)($_POST['userId'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (empty($errors) && $userId === current_user()['id']) {
        $errors[] = 'You cannot change your own account status.';
    } elseif (empty($errors)) {
        $newStatus = $action === 'suspend' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE userId = ? AND role != 'manager'")->execute([$newStatus, $userId]);
        flash('success', 'User updated.');
        header('Location: ' . BASE_URL . '/manager/users.php');
        exit;
    }
}

$roleFilter = $_GET['role'] ?? 'all';
$sql = "SELECT * FROM users";
$params = [];
if (in_array($roleFilter, ['resident', 'provider', 'delivery', 'manager'], true)) {
    $sql .= " WHERE role = ?";
    $params[] = $roleFilter;
}
$sql .= " ORDER BY role, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h4 mb-3">Users</h2>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>

<ul class="nav nav-pills mb-3">
  <?php foreach (['all' => 'All', 'resident' => 'Residents', 'provider' => 'Providers', 'delivery' => 'Delivery', 'manager' => 'Managers'] as $val => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $roleFilter === $val ? 'active' : '' ?>" href="?role=<?= $val ?>"><?= h($label) ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="table-responsive">
  <table class="table align-middle bg-white">
    <thead><tr><th>Name</th><th>Role</th><th>Contact</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= h($u['name']) ?><?php if ($u['unit']): ?><div class="small text-muted">Unit <?= h($u['unit']) ?></div><?php endif; ?></td>
        <td><span class="badge bg-light text-dark border text-uppercase"><?= h($u['role']) ?></span></td>
        <td class="small"><?= h($u['email']) ?><br><?= h($u['phone']) ?></td>
        <td><span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>"><?= h(ucfirst($u['status'])) ?></span></td>
        <td class="small text-muted"><?= h(date('d M Y', strtotime($u['createdAt']))) ?></td>
        <td>
          <?php if ($u['role'] !== 'manager'): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="userId" value="<?= (int)$u['userId'] ?>">
              <input type="hidden" name="action" value="<?= $u['status'] === 'active' ? 'suspend' : 'activate' ?>">
              <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
