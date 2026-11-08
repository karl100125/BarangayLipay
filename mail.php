<?php
// mail.php - PHPMailer with direct path (no vendor)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ FIXED: Use direct path instead of vendor/autoload.php
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jcarlclapano2004@gmail.com';
    $mail->Password   = 'gcjhociujkqeflzw';  // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // SSL Options (para iwas error)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom('jcarlclapano2004@gmail.com', 'Barangay Lipay');
    $mail->isHTML(true);
    
} catch (Exception $e) {
    error_log("Mailer Error: " . $e->getMessage());
}
?>