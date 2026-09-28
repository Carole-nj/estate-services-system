<?php
/** Shared header. Set $pageTitle before including; include AFTER any redirects. */
$user = current_user();
$unread = 0;
$notifications = [];
if ($user) {
    $unread = unread_notification_count($pdo, $user['id']);
    $notifications = recent_notifications($pdo, $user['id']);
    mark_notifications_read($pdo, $user['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Estate Serve') ?> · Estate Serve</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
  <div class="container-fluid px-3">
    <a class="navbar-brand fw-semibold" href="<?= $user ? role_home($user['role']) : BASE_URL . '/index.php' ?>">🏘️ Estate Serve</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <?php if ($user): ?>
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php if ($user['role'] === 'resident'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/resident/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/resident/catalogue.php">Browse Services</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/resident/bookings.php">My Bookings</a></li>
          <?php elseif ($user['role'] === 'provider'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/provider/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/provider/listings.php">My Listings</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/provider/bookings.php">Bookings</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/provider/earnings.php">Earnings</a></li>
          <?php elseif ($user['role'] === 'delivery'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/delivery/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/delivery/tasks.php">My Tasks</a></li>
          <?php elseif ($user['role'] === 'manager'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/manager/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/manager/users.php">Users</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/manager/reports.php">Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/manager/disputes.php">Disputes</a></li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item dropdown me-lg-2">
            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
              🔔
              <?php if ($unread > 0): ?><span class="badge rounded-pill bg-danger notif-badge"><?= $unread ?></span><?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end notif-dropdown p-2">
              <li class="px-2 pb-1 small text-muted fw-semibold">Notifications</li>
              <?php if (empty($notifications)): ?>
                <li class="px-2 py-2 small text-muted">No notifications yet.</li>
              <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                  <li class="px-2 py-2 border-top small">
                    <div><?= h($n['message']) ?></div>
                    <div class="text-muted" style="font-size:11px;"><?= h(date('d M, H:i', strtotime($n['sentAt']))) ?></div>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <?= h($user['name']) ?> <span class="badge bg-secondary text-uppercase role-pill"><?= h($user['role']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/logout.php">Log out</a></li>
            </ul>
          </li>
        </ul>
      <?php else: ?>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">Log in</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/register.php">Register</a></li>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= h($f['type']) ?> alert-dismissible fade show" role="alert">
      <?= h($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
