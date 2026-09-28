# Estate Serve — Integrated Estate Services Management System

PHP + MySQL + Bootstrap 5 + vanilla JS, built for XAMPP. Four roles: resident,
service provider, delivery personnel, estate manager. Payments use a simulated
M-Pesa STK Push (api/mpesa/stkpush.php).

## Setup (XAMPP)
1. Copy this folder into `xampp/htdocs/` (any folder name works).
2. Start Apache and MySQL in the XAMPP control panel.
3. Open phpMyAdmin (http://localhost/phpmyadmin) > Import > choose `database/schema.sql`.
4. Open http://localhost/estate-serve/database/seed.php once (creates demo accounts).
5. Open http://localhost/estate-serve/ and log in.

If your MySQL root password isn't blank, edit `config/database.php`.

## Demo logins (password: password123)
- manager@estateserve.test
- resident@estateserve.test  (or wanjiru@estateserve.test)
- peter@estateserve.test     (also james, grace, samuel, faith @estateserve.test)
- brian@estateserve.test     (delivery)

## Flow to try
Resident books a service > pays via mock M-Pesa > provider starts and completes it
(for Grocery / Food Delivery the provider can assign brian) > resident reviews or
raises an issue > manager sees charts, reports (CSV / print) and resolves disputes.

## Notes
- Status tracking is manual refresh; notifications are stored in MySQL and shown on
  page load, as described in the proposal.
- Receipts/reports use print and CSV. For real PDFs, add TCPDF/FPDF via Composer.
- Delete database/seed.php before any real deployment.
- Not run against a live PHP/MySQL stack yet, so do a first click-through on your XAMPP.
