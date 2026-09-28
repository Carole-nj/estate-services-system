<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: ' . role_home(current_user()['role']));
    exit;
}

$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 rounded-4" style="background:linear-gradient(180deg,#26362C,#1D2A22); color:#F3F0E3;">
  <h1 class="display-6 fw-semibold">Estate Serve</h1>
  <p class="lead mb-1">Book, pay for, and track household services within your estate: laundry, car wash, groceries,
  cleaning, plumbing, food delivery, and salon &amp; barber, all in one place.</p>
  <p class="mb-4" style="color:#C6D4C8;">Payments use a simulated M-Pesa STK Push that mirrors the Safaricom Daraja contract.</p>
  <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-ochre btn-lg me-2">Get started</a>
  <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-lg">Log in</a>
</div>

<div class="row g-3">
  <div class="col-md-3 col-6"><div class="card h-100 p-3"><div class="fs-3">🧺</div><div class="fw-semibold">Residents</div><div class="text-muted small">Browse, book, pay via M-Pesa, and track status.</div></div></div>
  <div class="col-md-3 col-6"><div class="card h-100 p-3"><div class="fs-3">🧹</div><div class="fw-semibold">Service Providers</div><div class="text-muted small">Manage listings, accept bookings, track earnings.</div></div></div>
  <div class="col-md-3 col-6"><div class="card h-100 p-3"><div class="fs-3">🛵</div><div class="fw-semibold">Delivery Personnel</div><div class="text-muted small">View assigned deliveries and update status.</div></div></div>
  <div class="col-md-3 col-6"><div class="card h-100 p-3"><div class="fs-3">📊</div><div class="fw-semibold">Estate Managers</div><div class="text-muted small">Oversee users, revenue, and service quality.</div></div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
