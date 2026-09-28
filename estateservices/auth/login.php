<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) {
    header('Location: ' . role_home(current_user()['role']));
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $errors[] = 'Incorrect email or password.';
        } elseif ($row['status'] === 'suspended') {
            $errors[] = 'This account has been suspended. Contact your estate manager.';
        } else {
            login_user($row);
            header('Location: ' . role_home($row['role']));
            exit;
        }
    }
}

$pageTitle = 'Log in';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h2 class="h4 mb-3">Log in</h2>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($email) ?>" required autofocus></div>
        <div class="mb-3"><label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required></div>
        <button type="submit" class="btn btn-ochre w-100">Log in</button>
      </form>
      <p class="text-center mt-3 mb-1 small">No account yet? <a href="<?= BASE_URL ?>/auth/register.php">Register</a></p>
      <p class="text-center small text-muted mb-0">Demo (password <code>password123</code>): manager@estateserve.test · resident@estateserve.test · peter@estateserve.test · brian@estateserve.test</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
