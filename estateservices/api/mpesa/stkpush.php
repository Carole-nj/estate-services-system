<?php
/**
 * Simulated M-Pesa STK Push endpoint. Mirrors the shape of Safaricom's Daraja
 * request/callback contract: receive a payment request, simulate the customer
 * approving the prompt, record the payment with a receipt number, and confirm
 * the booking. (Real Daraja is two HTTP legs; combined here since there is no
 * real gateway to call back from.)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!current_user() || current_user()['role'] !== 'resident') {
    respond(['success' => false, 'message' => 'You must be logged in as a resident.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Invalid request method.'], 405);
}
if (!verify_csrf($_POST['csrf'] ?? null)) {
    respond(['success' => false, 'message' => 'Session expired. Please refresh and try again.'], 403);
}

$user = current_user();
$bookingId = (int)($_POST['bookingId'] ?? 0);
$phone = trim($_POST['phone'] ?? '');

if ($bookingId <= 0 || $phone === '') {
    respond(['success' => false, 'message' => 'Missing booking or phone number.'], 400);
}

$stmt = $pdo->prepare(
    "SELECT b.*, s.price, s.providerId, s.title AS serviceTitle
     FROM bookings b JOIN services s ON s.serviceId = b.serviceId
     WHERE b.bookingId = ? AND b.residentId = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) respond(['success' => false, 'message' => 'Booking not found.'], 404);
if ($booking['status'] !== 'pending') respond(['success' => false, 'message' => 'This booking is not awaiting payment.'], 409);

$existing = $pdo->prepare("SELECT paymentId FROM payments WHERE bookingId = ?");
$existing->execute([$bookingId]);
if ($existing->fetchColumn()) respond(['success' => false, 'message' => 'This booking has already been paid for.'], 409);

try {
    $pdo->beginTransaction();

    $receipt = mock_mpesa_receipt();
    $pdo->prepare(
        "INSERT INTO payments (bookingId, mpesaReceiptNo, phone, amount, status) VALUES (?, ?, ?, ?, 'completed')"
    )->execute([$bookingId, $receipt, $phone, $booking['price']]);

    $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE bookingId = ?")->execute([$bookingId]);

    notify($pdo, $user['id'], 'payment', "Payment of " . money($booking['price']) . " confirmed, receipt {$receipt}.");
    notify($pdo, (int)$booking['providerId'], 'booking_confirmed',
        "Booking for \"{$booking['serviceTitle']}\" by {$user['name']} is confirmed and paid.");

    $pdo->commit();
    respond(['success' => true, 'receipt' => $receipt, 'message' => 'Payment confirmed.']);
} catch (Throwable $e) {
    $pdo->rollBack();
    respond(['success' => false, 'message' => 'Payment could not be processed. Please try again.'], 500);
}
