<?php
/** Shared helpers. */

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function money($amount): string {
    return 'KSh ' . number_format((float)$amount, 0);
}

function status_label(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

function status_badge_class(string $status): string {
    return match ($status) {
        'pending'     => 'bg-warning text-dark',
        'confirmed'   => 'bg-info text-dark',
        'in_progress' => 'bg-primary',
        'completed'   => 'bg-success',
        'cancelled'   => 'bg-danger',
        'open'        => 'bg-warning text-dark',
        'resolved'    => 'bg-success',
        default       => 'bg-secondary',
    };
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// In-app notifications stored in MySQL, shown on page load
function notify(PDO $pdo, int $userId, string $type, string $message): void {
    $pdo->prepare("INSERT INTO notifications (userId, type, message) VALUES (?, ?, ?)")
        ->execute([$userId, $type, $message]);
}

function unread_notification_count(PDO $pdo, int $userId): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND isRead = 0");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

function recent_notifications(PDO $pdo, int $userId, int $limit = 8): array {
    $s = $pdo->prepare("SELECT * FROM notifications WHERE userId = ? ORDER BY sentAt DESC LIMIT ?");
    $s->bindValue(1, $userId, PDO::PARAM_INT);
    $s->bindValue(2, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll();
}

function mark_notifications_read(PDO $pdo, int $userId): void {
    $pdo->prepare("UPDATE notifications SET isRead = 1 WHERE userId = ? AND isRead = 0")->execute([$userId]);
}

function mock_mpesa_receipt(): string {
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . rand(100000, 999999);
}
