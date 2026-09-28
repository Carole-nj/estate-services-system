<?php
/**
 * Demo data seeder. Run ONCE after importing schema.sql:
 *   http://localhost/estate-serve/database/seed.php
 * Safe to re-run. All demo accounts use the password: password123
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: text/plain');

function getOrCreateEstate(PDO $pdo, string $name, string $location): int {
    $s = $pdo->prepare("SELECT estateId FROM estates WHERE name = ?");
    $s->execute([$name]);
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare("INSERT INTO estates (name, location) VALUES (?, ?)")->execute([$name, $location]);
    return (int)$pdo->lastInsertId();
}

function getOrCreateUser(PDO $pdo, int $estateId, string $name, string $email, string $phone, string $role, ?string $unit = null, ?string $bio = null): int {
    $s = $pdo->prepare("SELECT userId FROM users WHERE email = ?");
    $s->execute([$email]);
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $pdo->prepare(
        "INSERT INTO users (estateId, name, email, phone, password_hash, role, unit, bio) VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$estateId, $name, $email, $phone, $hash, $role, $unit, $bio]);
    return (int)$pdo->lastInsertId();
}

function getOrCreateService(PDO $pdo, int $providerId, string $category, string $title, string $description, float $price, string $duration): int {
    $s = $pdo->prepare("SELECT serviceId FROM services WHERE providerId = ? AND title = ?");
    $s->execute([$providerId, $title]);
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare(
        "INSERT INTO services (providerId, category, title, description, price, durationLabel, active) VALUES (?,?,?,?,?,?,1)"
    )->execute([$providerId, $category, $title, $description, $price, $duration]);
    return (int)$pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();
    $estateId = getOrCreateEstate($pdo, 'Greenridge Estate', 'Nairobi, Kenya');

    getOrCreateUser($pdo, $estateId, 'Estate Manager', 'manager@estateserve.test', '0700111000', 'manager');

    $peter  = getOrCreateUser($pdo, $estateId, 'Peter Mwangi',  'peter@estateserve.test',  '0711222333', 'provider', null, 'Laundry & house cleaning');
    $james  = getOrCreateUser($pdo, $estateId, 'James Kiptoo',  'james@estateserve.test',  '0712333444', 'provider', null, 'Car wash specialist');
    $grace  = getOrCreateUser($pdo, $estateId, 'Grace Achieng', 'grace@estateserve.test',  '0722333444', 'provider', null, 'Groceries & food delivery');
    $samuel = getOrCreateUser($pdo, $estateId, 'Samuel Otieno', 'samuel@estateserve.test', '0733444555', 'provider', null, 'Plumbing & general repairs');
    $faith  = getOrCreateUser($pdo, $estateId, 'Faith Njeri',   'faith@estateserve.test',  '0744555666', 'provider', null, 'Salon & barber services');

    getOrCreateUser($pdo, $estateId, 'Brian Otieno', 'brian@estateserve.test', '0755666777', 'delivery');

    getOrCreateUser($pdo, $estateId, 'Wanjiru Kamau', 'wanjiru@estateserve.test', '0712345678', 'resident', 'B4-12');
    getOrCreateUser($pdo, $estateId, 'Test Resident', 'resident@estateserve.test', '0700000000', 'resident', 'A1-01');

    getOrCreateService($pdo, $peter,  'Laundry',            'Standard Laundry',     'Wash, dry and fold for a standard household load.', 800,  '24 hrs turnaround');
    getOrCreateService($pdo, $james,  'Car Wash',           'Car Wash Basic',       'Exterior wash and interior vacuum for a saloon car.', 500,  '30 min');
    getOrCreateService($pdo, $grace,  'Grocery',            'Grocery Shopping',     'Personal shopper picks up your grocery list from the nearest supermarket.', 300, '1–2 hrs');
    getOrCreateService($pdo, $peter,  'House Cleaning',     'House Cleaning (2BR)', 'Full clean of a 2-bedroom unit: floors, kitchen, bathrooms.', 2500, '3 hrs');
    getOrCreateService($pdo, $samuel, 'Plumbing & Repairs', 'Plumbing Repair',      'Call-out visit for leaks, blockages, and fixture repairs.', 1500, '1–2 hrs, call-out');
    getOrCreateService($pdo, $grace,  'Food Delivery',      'Food Delivery',        'Pickup and delivery from any restaurant near the estate.', 200, '45 min');
    getOrCreateService($pdo, $faith,  'Salon & Barber',     'Haircut & Style',      'In-home haircut and styling appointment.', 1000, '45 min');

    $pdo->commit();
    echo "Seeding done.\n\nDemo logins (password: password123):\n";
    echo "  manager@estateserve.test\n  resident@estateserve.test\n  peter@estateserve.test\n  brian@estateserve.test\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
