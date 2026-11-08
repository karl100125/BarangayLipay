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
<title>Privacy Policy — Barangay Lipay</title>
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
    <h1>Privacy Policy</h1>
    <p>How Barangay Lipay collects, uses, and protects your personal information.</p>
  </div>
</div>

<!-- CONTENT -->
<div class="legal-wrap">
  <div class="legal-card">
    <span class="legal-updated">Last updated: <?= date('F d, Y') ?></span>

    <h2>1. Introduction</h2>
    <p>Barangay Lipay, through its Document Request System, is committed to protecting the privacy and personal data of every resident who uses this platform. This Privacy Policy explains what information we collect, how we use it, and the rights you have over your data, in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> and its implementing rules and regulations.</p>

    <h2>2. Information We Collect</h2>
    <p>When you register or use our services, we may collect the following:</p>
    <ul>
      <li>Full name, address, contact number, and email address</li>
      <li>Valid government-issued identification details</li>
      <li>Proof of residency and other supporting documents you submit</li>
      <li>Document request details (type, purpose, and status)</li>
      <li>Account credentials and login activity</li>
    </ul>

    <h2>3. How We Use Your Information</h2>
    <p>Your personal information is used solely for legitimate barangay purposes, including:</p>
    <ul>
      <li>Processing and issuing barangay clearances, certificates, and permits</li>
      <li>Verifying your identity and residency</li>
      <li>Sending updates regarding your request status</li>
      <li>Maintaining accurate barangay records</li>
      <li>Improving our online services and system security</li>
    </ul>

    <h2>4. Data Sharing and Disclosure</h2>
    <p>We do not sell, rent, or trade your personal information. Data may only be shared with authorized barangay officials and staff who need it to process your request, or when required by law, court order, or a government regulatory body.</p>

    <h2>5. Data Storage and Security</h2>
    <p>All personal data is stored in secure, access-controlled systems. We apply reasonable administrative, technical, and physical safeguards to protect your information against unauthorized access, alteration, disclosure, or destruction.</p>

    <h2>6. Data Retention</h2>
    <p>Your information is retained only for as long as necessary to fulfill the purposes stated in this policy, or as required by applicable barangay and government record-keeping regulations.</p>

    <h2>7. Your Rights</h2>
    <p>As a data subject under the Data Privacy Act, you have the right to:</p>
    <ul>
      <li>Be informed of how your data is processed</li>
      <li>Access your personal data on file</li>
      <li>Request correction of inaccurate information</li>
      <li>Object to or withdraw consent for certain processing activities</li>
      <li>File a complaint with the National Privacy Commission</li>
    </ul>

    <h2>8. Changes to This Policy</h2>
    <p>This Privacy Policy may be updated from time to time to reflect changes in our practices or legal requirements. Any updates will be posted on this page with a revised effective date.</p>

    <div class="legal-divider"></div>

    <h2>9. Contact Us</h2>
    <p>For questions, concerns, or requests regarding your personal data, you may reach us through:</p>
    <div class="legal-contact">
      <p>Barangay Lipay Data Protection Office</p>
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