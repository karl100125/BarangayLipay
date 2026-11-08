<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db.php";

$settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

$footer_copyright = $settings['footer_copyright'] ?? '© 2025 Barangay Lipay Document Request System. All rights reserved.';
$footer_links = explode('|', $settings['footer_links_text'] ?? 'Sign In|Register|Privacy Policy|Terms & Conditions');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Terms &amp; Conditions — Barangay Lipay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#0d1b3e; --navy2:#112354; --navy3:#1a3570;
  --red:#c0392b; --red2:#e74c3c; --gold:#f39c12;
  --light:#eaf0fb; --white:#ffffff; --text:#1a2a4a;
  --muted:#5a6a8a; --border:#d4dff5; --card:#ffffff; --bg:#f0f4fc;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}
a{text-decoration:none;color:inherit}
img{max-width:100%}

/* NAV */
nav{background:var(--navy);padding:0 5%;display:flex;align-items:center;justify-content:space-between;height:68px;position:sticky;top:0;z-index:100}
.nav-logo{display:flex;align-items:center;gap:12px}
.nav-logo img{height:42px;width:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.2)}
.nav-logo-text{color:#fff;font-weight:800;font-size:17px;letter-spacing:-.3px}
.nav-logo-sub{color:rgba(255,255,255,.5);font-size:11px;font-weight:500;margin-top:1px}
.nav-links{display:flex;align-items:center;gap:6px}
.nav-links a{color:rgba(255,255,255,.8);font-size:14px;font-weight:500;padding:8px 14px;border-radius:8px;transition:.2s}
.nav-links a:hover{background:rgba(255,255,255,.1);color:#fff}
.nav-cta{display:flex;align-items:center;gap:10px}
.btn-outline{border:1.5px solid rgba(255,255,255,.35);color:#fff;font-size:13px;font-weight:600;padding:9px 20px;border-radius:8px;transition:.2s;cursor:pointer;background:transparent}
.btn-outline:hover{background:rgba(255,255,255,.1)}
.btn-primary{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;font-size:13px;font-weight:700;padding:9px 22px;border-radius:8px;transition:.2s;cursor:pointer;border:none;letter-spacing:.2px}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}

.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;background:none;border:none}
.nav-hamburger span{display:block;width:22px;height:2px;background:#fff;border-radius:2px;transition:.3s}
.nav-mobile{display:none;position:absolute;top:68px;left:0;right:0;background:var(--navy);flex-direction:column;padding:12px 5%;gap:4px;z-index:99;border-top:1px solid rgba(255,255,255,.08)}
.nav-mobile a{color:rgba(255,255,255,.8);font-size:14px;font-weight:500;padding:10px 14px;border-radius:8px;transition:.2s;display:block}
.nav-mobile a:hover{background:rgba(255,255,255,.08);color:#fff}

/* PAGE HEADER */
.page-header{background:linear-gradient(160deg,#071029 0%,#0d1b3e 45%,#122060 100%);padding:64px 5% 56px;text-align:center;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.06) 1px,transparent 1px);background-size:30px 30px;pointer-events:none}
.page-header-inner{position:relative;z-index:2}
.page-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:100px;padding:6px 16px;margin-bottom:18px;color:rgba(255,255,255,.75);font-size:12px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.page-header h1{color:#fff;font-size:clamp(28px,4vw,42px);font-weight:800;letter-spacing:-1px;margin-bottom:12px}
.page-header p{color:rgba(255,255,255,.6);font-size:14.5px;max-width:520px;margin:0 auto}

/* CONTENT */
.legal-wrap{max-width:820px;margin:0 auto;padding:56px 5% 80px}
.legal-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:48px 44px;box-shadow:0 12px 40px rgba(13,27,62,.06)}
.legal-updated{display:inline-block;background:var(--light);color:var(--navy);font-size:12px;font-weight:700;letter-spacing:.3px;padding:6px 14px;border-radius:100px;margin-bottom:28px}
.legal-card h2{font-size:19px;font-weight:800;color:var(--navy);letter-spacing:-.3px;margin:32px 0 12px}
.legal-card h2:first-of-type{margin-top:0}
.legal-card p{font-size:14px;line-height:1.85;color:var(--muted);margin-bottom:14px}
.legal-card ul{margin:0 0 14px 20px;padding:0}
.legal-card li{font-size:14px;line-height:1.85;color:var(--muted);margin-bottom:6px}
.legal-card strong{color:var(--text);font-weight:700}
.legal-card a.inline-link{color:var(--red);font-weight:600;text-decoration:underline}
.legal-divider{height:1px;background:var(--border);margin:32px 0}
.legal-contact{background:var(--light);border-radius:14px;padding:20px 22px;margin-top:8px}
.legal-contact p{margin-bottom:4px;color:var(--text);font-size:13.5px}
.legal-contact p:first-child{font-weight:700;color:var(--navy);margin-bottom:8px}

/* FOOTER */
footer{background:#050d20;padding:60px 5% 30px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;gap:48px;margin-bottom:48px}
.footer-brand p{color:rgba(255,255,255,.5);font-size:13px;line-height:1.75;margin-top:14px;max-width:280px}
.footer-logo-wrap{display:flex;align-items:center;gap:10px}
.footer-logo-text{color:#fff;font-weight:800;font-size:16px}
.footer-logo-sub{color:rgba(255,255,255,.45);font-size:11px;font-weight:500}
.footer-col h4{color:#e74c3c;font-size:13px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:16px}
.footer-col a{display:block;color:rgba(255,255,255,.55);font-size:13px;margin-bottom:10px;transition:.2s}
.footer-col a:hover{color:#fff}
.footer-contact p{color:rgba(255,255,255,.55);font-size:13px;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.footer-contact p svg{width:14px;height:14px;stroke:rgba(255,255,255,.4);flex-shrink:0;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.footer-download{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;font-size:13px;font-weight:700;padding:11px 22px;border-radius:8px;display:inline-block;margin-top:14px;transition:.2s}
.footer-download:hover{opacity:.9}
.footer-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.footer-bottom p{color:rgba(255,255,255,.3);font-size:12px}
.footer-links{display:flex;gap:20px;flex-wrap:wrap}
.footer-links a{color:rgba(255,255,255,.4);font-size:12px;transition:.2s}
.footer-links a:hover{color:rgba(255,255,255,.8)}

@media(max-width:900px){
  .footer-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:640px){
  nav{padding:0 4%}
  .nav-links,.nav-cta{display:none}
  .nav-hamburger{display:flex}
  .footer-grid{grid-template-columns:1fr}
  .page-header{padding:48px 4% 40px}
  .legal-wrap{padding:36px 4% 60px}
  .legal-card{padding:32px 22px}
}
</style>
</head>
<body>

<nav>
  <div class="nav-logo">
    <img src="uploads/lipay.png" alt="Lipay" onerror="this.style.display='none'">
    <div>
      <div class="nav-logo-text">Barangay Lipay</div>
      <div class="nav-logo-sub">Jose Dalman, Zamboanga del Norte</div>
    </div>
  </div>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="index.php#services">Services</a>
    <a href="index.php#about">About Us</a>
    <a href="index.php#process">How It Works</a>
  </div>
  <div class="nav-cta">
    <a href="auth/login.php" class="btn-outline">Sign In</a>
    <a href="auth/register.php" class="btn-primary">Register</a>
  </div>
  <button class="nav-hamburger" id="navHamburger" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<div class="nav-mobile" id="navMobile">
  <a href="index.php">Home</a>
  <a href="index.php#services">Services</a>
  <a href="index.php#about">About Us</a>
  <a href="index.php#process">How It Works</a>
</div>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="page-header-inner">
    <div class="page-eyebrow">Legal</div>
    <h1>Terms &amp; Conditions</h1>
    <p>Please read these terms carefully before using the Barangay Lipay Document Request System.</p>
  </div>
</div>

<!-- CONTENT -->
<div class="legal-wrap">
  <div class="legal-card">
    <span class="legal-updated">Last updated: <?= date('F d, Y') ?></span>

    <h2>1. Acceptance of Terms</h2>
    <p>By accessing or using the Barangay Lipay Document Request System ("the System"), you agree to be bound by these Terms &amp; Conditions. If you do not agree with any part of these terms, please refrain from using the System.</p>

    <h2>2. Eligibility and Account Registration</h2>
    <p>The System is intended for use by residents of Barangay Lipay, Jose Dalman, Zamboanga del Norte, as well as authorized barangay officials and staff. When creating an account, you agree to:</p>
    <ul>
      <li>Provide accurate, current, and complete information</li>
      <li>Keep your login credentials confidential and secure</li>
      <li>Notify the barangay immediately of any unauthorized use of your account</li>
      <li>Accept responsibility for all activities carried out under your account</li>
    </ul>

    <h2>3. Use of the Document Request Service</h2>
    <p>The System allows registered residents to request official barangay documents such as clearances, certificates, and permits online. Requests are subject to review and approval by barangay administrators, and processing times may vary depending on the type and volume of requests.</p>

    <h2>4. User Responsibilities</h2>
    <p>By using the System, you agree that you will not:</p>
    <ul>
      <li>Submit false, fraudulent, or misleading information</li>
      <li>Impersonate another person or misrepresent your affiliation</li>
      <li>Attempt to gain unauthorized access to accounts, data, or system infrastructure</li>
      <li>Use the System for any unlawful, harmful, or disruptive purpose</li>
      <li>Upload documents or content that infringe on the rights of others</li>
    </ul>

    <h2>5. Document Fees and Payment</h2>
    <p>Certain documents may require payment of applicable fees as set by the barangay. Fees, once paid, are generally non-refundable except in cases of system error or duplicate charges, subject to barangay approval.</p>

    <h2>6. Accuracy of Issued Documents</h2>
    <p>Documents generated through the System are based on the information you provide and records available to the barangay. It is your responsibility to verify that all details on a requested document are accurate before submission and upon release.</p>

    <h2>7. System Availability</h2>
    <p>While we strive to keep the System accessible at all times, Barangay Lipay does not guarantee uninterrupted or error-free service. The System may be temporarily unavailable due to maintenance, technical issues, or circumstances beyond our control.</p>

    <h2>8. Intellectual Property</h2>
    <p>All content, design, logos, and features of the System are the property of Barangay Lipay and its developers. You may not copy, modify, distribute, or reproduce any part of the System without prior written permission.</p>

    <h2>9. Limitation of Liability</h2>
    <p>Barangay Lipay and its developers shall not be held liable for any indirect, incidental, or consequential damages arising from the use of, or inability to use, the System, except as required by applicable law.</p>

    <h2>10. Termination of Access</h2>
    <p>Barangay Lipay reserves the right to suspend or terminate any account found to be in violation of these Terms &amp; Conditions, or engaged in fraudulent or harmful activity, without prior notice.</p>

    <h2>11. Changes to These Terms</h2>
    <p>These Terms &amp; Conditions may be revised from time to time. Continued use of the System after changes are posted constitutes your acceptance of the updated terms.</p>

    <div class="legal-divider"></div>

    <h2>12. Contact Us</h2>
    <p>For questions or concerns regarding these Terms &amp; Conditions, you may reach us through:</p>
    <div class="legal-contact">
      <p>Barangay Lipay Administration Office</p>
      <p>Barangay Lipay, Jose Dalman, Zamboanga del Norte</p>
      <p>admin@barangaylipay.ph</p>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-logo-wrap">
        <img src="uploads/lipay.png" alt="Lipay" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.15)" onerror="this.style.display='none'">
        <div>
          <div class="footer-logo-text">Barangay Lipay</div>
          <div class="footer-logo-sub">Jose Dalman, Zamboanga del Norte</div>
        </div>
      </div>
      <p>Empowering Barangay Lipay Through Digital Governance. A secure and efficient platform designed to modernize barangay operations and serve every resident better.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="index.php">Home</a>
      <a href="index.php#services">Services</a>
      <a href="index.php#about">About Us</a>
      <a href="index.php#process">How It Works</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="privacy-policy.php">Privacy Policy</a>
      <a href="terms-conditions.php">Terms &amp; Conditions</a>
    </div>
    <div class="footer-col footer-contact">
      <h4>Get In Touch</h4>
      <p>
        <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        barangaylipay.ph
      </p>
      <p>
        <svg viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Jose Dalman, Zamboanga del Norte
      </p>
      <a href="auth/register.php" class="footer-download">Register Now &rarr;</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p><?= htmlspecialchars($footer_copyright) ?></p>
    <div class="footer-links">
      <?php foreach ($footer_links as $link): ?>
        <?php
        $link = trim($link);
        if (empty($link)) continue;
        $url = '#';
        if (strtolower($link) == 'sign in') $url = 'auth/login.php';
        elseif (strtolower($link) == 'register') $url = 'auth/register.php';
        elseif (strtolower($link) == 'privacy policy') $url = 'privacy-policy.php';
        elseif (strtolower($link) == 'terms & conditions') $url = 'terms-conditions.php';
        ?>
        <a href="<?= $url ?>"><?= htmlspecialchars($link) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</footer>

<script>
(function(){
  var btn=document.getElementById('navHamburger');
  var menu=document.getElementById('navMobile');
  if(!btn||!menu)return;
  btn.addEventListener('click',function(){
    var open=menu.style.display==='flex';
    menu.style.display=open?'none':'flex';
  });
  document.addEventListener('click',function(e){
    if(!menu.contains(e.target)&&!btn.contains(e.target)){menu.style.display='none';}
  });
})();
</script>
</body>
</html>