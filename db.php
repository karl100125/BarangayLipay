<?php
$host = "localhost";        // Localhost
$db   = "blipay";          // Database name (gawin mo muna sa phpMyAdmin)
$user = "root";            // Default XAMPP/WAMP user
$pass = "";                // Default XAMPP/WAMP password (walang laman)

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>