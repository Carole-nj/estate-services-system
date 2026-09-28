<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) {
    header('Location: ' . role_home(current_user()['role']));
    exit;
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'resident', 'unit' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role']  = $_POST['role'] ?? 'resident';
    $old['unit']  = trim($_POST['unit'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($old['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($old['phone'] === '') $errors[] = 'Phone number is required.';
    if (!in_array($old['role'], ['resident', 'provider', 'delivery'], true)) $errors[] = 'Invalid role selected.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE email = ?");
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn()) $errors[] = 'An account with that email already exists.';
    }

    $estateId = 0;
    if (empty($errors)) {
        $estateId = (int)$pdo->query("SELECT estateId FROM estates ORDER BY estateId LIMIT 1")->fetchColumn();
        if (!$estateId) $errors[] = 'No estate is configured yet. Please run database/seed.php first.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $unit = $old['role'] === 'resident' ? ($old['unit'] ?: null) : null;
        $pdo->prepare(
            "INSERT INTO users (estateId, name, email, phone, password_hash, role, unit) VALUES (?,?,?,?,?,?,?)"
        )->execute([$estateId, $old['name'], $old['email'], $old['phone'], $hash, $old['role'], $unit]);

        flash('success', 'Account created. Please log in.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h2 class="h4 mb-3">Create an account</h2>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label">I am registering as</label>
          <select name="role" class="form-select">
            <option value="resident" <?= $old['role'] === 'resident' ? 'selected' : '' ?>>Resident</option>
            <option value="provider" <?= $old['role'] === 'provider' ? 'selected' : '' ?>>Service Provider</option>
            <option value="delivery" <?= $old['role'] === 'delivery' ? 'selected' : '' ?>>Delivery Personnel</option>
          </select>
          <div class="form-text">Estate manager accounts are created by the administration.</div>
        </div>
        <div class="mb-3"><label class="form-label">Full name</label>
          <input type="text" name="name" class="form-control" value="<?= h($old['name']) ?>" required></div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($old['email']) ?>" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">Phone number</label>
            <input type="text" name="phone" class="form-control" placeholder="07XX XXX XXX" value="<?= h($old['phone']) ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label">House / Unit no. <span class="text-muted">(residents only)</span></label>
          <input type="text" name="unit" class="form-control" placeholder="e.g. B4-12" value="<?= h($old['unit']) ?>"></div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" minlength="6" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">Confirm password</label>
            <input type="password" name="password2" class="form-control" minlength="6" required></div>
        </div>
        <button type="submit" class="btn btn-ochre w-100">Create account</button>
      </form>
      <p class="text-center mt-3 mb-0 small">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
