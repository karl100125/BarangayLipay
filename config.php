<?php
// ============================================================
// CONFIGURATION FILE - DO NOT COMMIT TO GITHUB!
// ============================================================

// ── EMAIL / SMTP CONFIGURATION ──────────────────────────────
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'jcarlclapano2004@gmail.com');
define('SMTP_PASSWORD', 'gcjhociujkqeflzw'); // <-- PALITAN MO NG TOTOONG PASSWORD
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_FROM_EMAIL', 'jcarlclapano2004@gmail.com');
define('SMTP_FROM_NAME', 'Barangay Lipay Office');

// ── DATABASE CONFIGURATION (OPTIONAL) ───────────────────────
// Kung gusto mong i-consolidate ang DB connection dito
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'barangay_lipay');
// define('DB_USER', 'root');
// define('DB_PASS', '');


// ── APPLICATION CONFIGURATION ───────────────────────────────
define('APP_NAME', 'Barangay Lipay');
define('APP_URL', 'http://localhost/barangay-lipay'); // Palitan sa production
define('TIMEZONE', 'Asia/Manila');

date_default_timezone_set(TIMEZONE);
?>