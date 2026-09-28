<?php
/** Session-based authentication helpers. */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_role($roles): void {
    require_login();
    $roles = (array)$roles;
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}

function login_user(array $u): void {
    $_SESSION['user'] = [
        'id' => (int)$u['userId'], 'name' => $u['name'], 'email' => $u['email'],
        'phone' => $u['phone'], 'role' => $u['role'],
        'estateId' => (int)$u['estateId'], 'unit' => $u['unit'] ?? null,
    ];
    session_regenerate_id(true);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function role_home(string $role): string {
    $map = [
        'resident' => '/resident/dashboard.php',
        'provider' => '/provider/dashboard.php',
        'delivery' => '/delivery/dashboard.php',
        'manager'  => '/manager/dashboard.php',
    ];
    return BASE_URL . ($map[$role] ?? '/index.php');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool {
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
