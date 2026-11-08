<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include "db.php";

// ===== MAINTENANCE MODE CHECK =====
// Check if maintenance mode is enabled
try {
    $stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_maintenance'");
    $maintenance = $stmt->fetchColumn();
} catch (PDOException $e) {
    $maintenance = '0';
}

if ($maintenance == '1') {
    $isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
    
    if (!$isSuperAdmin) {
        try {
            $stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
            $message = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $message = 'System is under maintenance. Please check back later.';
        }
        $message = $message ?: 'System is under maintenance. Please check back later.';
        
        // Maintenance page
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — Barangay Lipay</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Plus Jakarta Sans',sans-serif;
            background:linear-gradient(160deg,#071029 0%,#0d1b3e 45%,#122060 100%);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .maintenance-box{
            background:rgba(255,255,255,.05);
            backdrop-filter:blur(12px);
            border:1px solid rgba(255,255,255,.1);
            border-radius:24px;
            padding:50px 40px;
            max-width:500px;
            width:100%;
            text-align:center;
            box-shadow:0 24px 64px rgba(0,0,0,.4);
        }
        .maintenance-box .badge{
            display:inline-flex;
            align-items:center;
            background:rgba(231,76,60,.15);
            border:1px solid rgba(231,76,60,.3);
            border-radius:100px;
            padding:4px 14px;
            font-size:11px;
            font-weight:600;
            color:#e74c3c;
            margin-bottom:16px;
        }
        .maintenance-box h1{
            color:#fff;
            font-size:28px;
            font-weight:800;
            margin-bottom:12px;
        }
        .maintenance-box p{
            color:rgba(255,255,255,.6);
            font-size:15px;
            line-height:1.7;
        }
        .status-dot{
            display:inline-block;
            width:8px;
            height:8px;
            border-radius:50%;
            background:#e74c3c;
            margin-right:8px;
            animation:pulse 1.5s infinite;
        }
        @keyframes pulse{
            0%,100%{opacity:1;transform:scale(1)}
            50%{opacity:.4;transform:scale(.8)}
        }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="badge">
            <span class="status-dot"></span>
            UNDER MAINTENANCE
        </div>
        <h1>We'll Be Back Soon</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <p style="font-size:13px;color:rgba(255,255,255,.35);margin-top:8px;">
            Estimated downtime: <span style="color:rgba(255,255,255,.5);">30 minutes</span>
        </p>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
// ===== END MAINTENANCE MODE CHECK =====


// ===== GROQ CHATBOT ENDPOINT =====
// ⬇️ DITO LANG ANG ILALAGAY MO: ang Groq API key mo (console.groq.com → API Keys)
define('GROQ_API_KEY', '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');
define('GROQ_MODEL',   'llama-3.3-70b-versatile'); // i-check sa Groq console kung available pa

if (isset($_GET['chat_api'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Kung hindi pa napalitan ang key, mag-fallback agad sa KB ng chatbot
    if (GROQ_API_KEY === '' || strpos(GROQ_API_KEY, 'PALITAN') !== false) {
        http_response_code(503);
        echo json_encode(['error' => 'API key not set']);
        exit;
    }

    // Rate limit: max 8 messages kada 60 segundo bawat session
    $now = time();
    $_SESSION['cb_hits'] = array_filter($_SESSION['cb_hits'] ?? [], fn($t) => $now - $t < 60);
    if (count($_SESSION['cb_hits']) >= 8) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    $_SESSION['cb_hits'][] = $now;

    // Input
    $in = json_decode(file_get_contents('php://input'), true);
    $message = trim($in['message'] ?? '');
    if ($message === '' || mb_strlen($message) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid message']);
        exit;
    }

    // Linisin ang history (user/assistant lang, huling 6, max 500 chars)
    $history = [];
    foreach (array_slice($in['history'] ?? [], -6) as $h) {
        if (!isset($h['role'], $h['content'])) continue;
        if (!in_array($h['role'], ['user', 'assistant'], true)) continue;
        $history[] = [
            'role'    => $h['role'],
            'content' => mb_substr((string)$h['content'], 0, 500),
        ];
    }

    // 'barangay' = facts lang ng Barangay Lipay | 'general' = pangkalahatang tanong
    $mode = (($in['mode'] ?? '') === 'barangay') ? 'barangay' : 'general';

    $facts = <<<TXT
Ikaw si "Lipay Assistant", ang chatbot ng Barangay Lipay Document Request System sa Jose Dalman, Zamboanga del Norte.

MGA KATOTOHANAN (ito lang ang pwede mong gamiting impormasyon):
- Mga dokumento online: Barangay Clearance, Certificate of Death, Certificate of Residency, Certificate of Indigency, Business Permit Clearance, Community Tax Certificate (Cedula), Certificate of Good Moral.
- Proseso: 1) Mag-register (libre) 2) Hintayin ang verification ng admin 3) Pumili ng dokumento at punan ang form 4) I-track ang status sa dashboard 5) Kunin ang dokumento sa Barangay Hall kapag na-approve.
- Oras ng opisina: Lunes-Biyernes, 8:00 AM-5:00 PM. Sarado tuwing Sabado, Linggo, at pista opisyal.
- Libre ang registration. Ang bayad sa dokumento ay depende sa uri ng sertipiko.
- Contact: admin@barangaylipay.ph

TXT;

    if ($mode === 'barangay') {
        $rules = <<<RULES_B
PATAKARAN (tanong tungkol sa Barangay Lipay):
- Sumagot sa wika ng user (Filipino/Tagalog, English, o halo). Maikli at magalang: 2-5 pangungusap.
- Gamitin LANG ang mga katotohanan sa itaas. Huwag mag-imbento ng presyo, requirements, pangalan ng opisyal, o patakaran na wala roon.
- Kung wala sa itaas ang sagot, sabihing magtanong sa Barangay Hall o mag-email sa admin.
- Huwag humingi ng password, OTP, o sensitibong personal na impormasyon.
RULES_B;
    } else {
        $rules = <<<RULES_G
PATAKARAN (pangkalahatang tanong na labas sa barangay):
- Sumagot sa wika ng user (Filipino/Tagalog, English, o halo). Malinaw, tama, at maikli: 2-6 pangungusap.
- Sagutin nang direkta ang tanong gamit ang pangkalahatang kaalaman mo (kasaysayan, agham, edukasyon, atbp.).
- Kung hindi ka sigurado, sabihin mo iyon. Huwag manghula o mag-imbento ng datos.
- Huwag gamitin ang mga katotohanan ng Barangay Lipay para sa mga sagot na walang kinalaman dito.
- Huwag humingi ng password, OTP, o sensitibong personal na impormasyon.
RULES_G;
    }

    $system = $facts . "\n\n" . $rules;

    $payload = [
        'model'       => GROQ_MODEL,
        'messages'    => array_merge(
            [['role' => 'system', 'content' => $system]],
            $history,
            [['role' => 'user', 'content' => $message]]
        ),
        'temperature' => 0.4,
        'max_tokens'  => 400,
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $code !== 200) {
        error_log("Groq error ($code): " . substr((string)$res, 0, 300));
        http_response_code(502);
        echo json_encode(['error' => 'AI unavailable']);
        exit;
    }

    $data  = json_decode($res, true);
    $reply = trim($data['choices'][0]['message']['content'] ?? '');
    if ($reply === '') {
        http_response_code(502);
        echo json_encode(['error' => 'Empty reply']);
        exit;
    }

    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}
// ===== END GROQ CHATBOT ENDPOINT =====


// ⬇️⬇️⬇️ ITO LANG ANG ISANG BESES NA DAPAT MAG-QUERY ⬇️⬇️⬇️
$settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

$hero_title = $settings['hero_title'] ?? 'Empowering Barangay Lipay Through <em>Digital Services</em>';
$hero_subtitle = $settings['hero_subtitle'] ?? 'A secure and modern platform designed to bring barangay services online — request documents, track status, and connect with local officials from anywhere.';
$announcement_text = $settings['announcement_text'] ?? '📢 NEW: Online document requesting is now available! Register today to get started.';
$announcement_active = ($settings['announcement_is_active'] ?? '1') == '1';
$footer_copyright = $settings['footer_copyright'] ?? '© 2025 Barangay Lipay Document Request System. All rights reserved.';
$footer_links = explode('|', $settings['footer_links_text'] ?? 'Sign In|Register|Privacy Policy|Terms & Conditions');

// ⬇️⬇️⬇️ REST OF YOUR HTML/PHP CODE HERE ⬇️⬇️⬇️
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Jcarl Clapano - Web Developer, Founder of PocketDevs | Barangay Lipay Document Request System</title>
<meta name="description" content="Jcarl Clapano is a web developer and founder of PocketDevs (pock3tdevs.thsite.top), creators of the Barangay Lipay Document Request and Management System.">
<meta name="author" content="Jcarl Clapano">
<meta property="og:title" content="Jcarl Clapano - Web Developer, Founder of PocketDevs">
<meta property="og:description" content="Jcarl Clapano is a web developer and founder of PocketDevs (pock3tdevs.thsite.top).">
<meta property="og:url" content="https://pock3tdevs.thsite.top/">

<!-- ═══ PWA MANIFEST - MAIN PAGE ═══ -->
<!-- NOTE: relative paths on purpose so this works both on XAMPP (/barangay3/) and on the live domain root -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d1b3e">

<!-- iOS / Safari PWA support -->
<link rel="apple-touch-icon" href="uploads/icon-192-any.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Barangay Lipay">

<!-- Android / Chrome PWA support -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#0d1b3e">
<meta name="msapplication-TileImage" content="uploads/icon-192-any.png">

<!-- Favicon -->
<link rel="icon" type="image/png" href="uploads/icon-192-any.png">
<!-- ════════════════════════════════ -->

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#0d1b3e;
  --navy2:#112354;
  --navy3:#1a3570;
  --red:#c0392b;
  --red2:#e74c3c;
  --gold:#f39c12;
  --light:#eaf0fb;
  --white:#ffffff;
  --text:#1a2a4a;
  --muted:#5a6a8a;
  --border:#d4dff5;
  --card:#ffffff;
  --bg:#f0f4fc;
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

/* HAMBURGER */
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;background:none;border:none}
.nav-hamburger span{display:block;width:22px;height:2px;background:#fff;border-radius:2px;transition:.3s}
.nav-mobile{display:none;position:absolute;top:68px;left:0;right:0;background:var(--navy);flex-direction:column;padding:12px 5%;gap:4px;z-index:99;border-top:1px solid rgba(255,255,255,.08)}
.nav-mobile a{color:rgba(255,255,255,.8);font-size:14px;font-weight:500;padding:10px 14px;border-radius:8px;transition:.2s;display:block}
.nav-mobile a:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-mobile .mobile-cta{display:flex;gap:10px;padding:12px 0 4px;border-top:1px solid rgba(255,255,255,.08);margin-top:8px}
.nav-mobile .mobile-cta a{flex:1;text-align:center}

/* HERO */
.hero{
  background:linear-gradient(160deg,#071029 0%,#0d1b3e 45%,#122060 100%);
  min-height:580px;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:80px 5%;
  position:relative;
  overflow:hidden;
  text-align:center;
}
.hero-dots{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.06) 1px,transparent 1px);background-size:30px 30px;pointer-events:none}
.hero-glow{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:600px;height:360px;border-radius:50%;background:radial-gradient(ellipse,rgba(26,53,112,.55) 0%,transparent 70%);pointer-events:none}
.hero-glow2{position:absolute;bottom:-60px;right:15%;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(192,57,43,.12) 0%,transparent 70%);pointer-events:none}
#heroCanvas{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:1;opacity:.5}

.hero-rings{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:1}
.hero-ring-circle{position:absolute;border-radius:50%}

.hero-inner{position:relative;z-index:2;width:100%;max-width:760px;display:flex;flex-direction:column;align-items:center}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:100px;padding:6px 16px;margin-bottom:24px}
.hero-badge-dot{width:7px;height:7px;border-radius:50%;background:#f39c12;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.3)}}
.hero-badge span{color:rgba(255,255,255,.75);font-size:12px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.hero h1{color:#fff;font-size:clamp(30px,4.5vw,54px);font-weight:800;line-height:1.1;letter-spacing:-1px;margin-bottom:20px;max-width:700px}
.hero h1 em{color:#e74c3c;font-style:normal}
.hero p{color:rgba(255,255,255,.62);font-size:16px;line-height:1.75;max-width:580px;margin-bottom:36px}
.hero-btns{display:flex;gap:14px;flex-wrap:wrap;justify-content:center}
.hero-btn-main{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;font-size:15px;font-weight:700;padding:14px 30px;border-radius:10px;transition:.2s;display:inline-flex;align-items:center;gap:8px}
.hero-btn-main:hover{opacity:.9;transform:translateY(-2px)}
.hero-btn-sec{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.2);color:#fff;font-size:15px;font-weight:600;padding:14px 28px;border-radius:10px;transition:.2s;display:inline-flex;align-items:center;gap:8px}
.hero-btn-sec:hover{background:rgba(255,255,255,.14)}

/* SECTIONS */
.section{padding:80px 5%}
.section-alt{background:var(--light)}
.section-dark{background:linear-gradient(160deg,#071029 0%,#0d1b3e 100%)}
.section-header{text-align:center;margin-bottom:56px}
.section-label{display:inline-flex;align-items:center;gap:8px;color:#e74c3c;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px}
.section-label::before,.section-label::after{content:'';width:28px;height:2px;background:#e74c3c;border-radius:2px}
.section-title{font-size:clamp(26px,3vw,38px);font-weight:800;color:var(--text);letter-spacing:-.5px;line-height:1.15}
.section-title-white{color:#fff}
.section-sub{color:var(--muted);font-size:15px;margin-top:10px;max-width:520px;margin-left:auto;margin-right:auto}
.section-sub-white{color:rgba(255,255,255,.6)}

/* PROCESS */
.proc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;position:relative}
.proc-grid::before{content:'';position:absolute;top:40px;left:calc(16.67% + 16px);right:calc(16.67% + 16px);height:2px;background:linear-gradient(90deg,#e74c3c,#9b59b6);z-index:0;border-radius:2px}
.proc-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:28px 22px;text-align:center;position:relative;z-index:1;transition:.2s}
.proc-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(13,27,62,.1)}
.proc-num{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;font-size:22px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;box-shadow:0 4px 16px rgba(192,57,43,.35)}
.proc-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px}
.proc-desc{font-size:13px;color:var(--muted);line-height:1.6}

/* CTA SECTION */
.cta-section{background:linear-gradient(160deg,#071029 0%,#0d1b3e 60%,#1a0a25 100%);padding:80px 5%;text-align:center;position:relative;overflow:hidden}
.cta-section::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.05) 1px,transparent 1px);background-size:28px 28px}
.cta-content{position:relative;z-index:2}
.cta-section h2{color:#fff;font-size:clamp(28px,4vw,44px);font-weight:800;letter-spacing:-.5px;margin-bottom:14px}
.cta-section h2 span{color:#e74c3c}
.cta-section p{color:rgba(255,255,255,.6);font-size:16px;max-width:480px;margin:0 auto 36px;line-height:1.7}
.cta-btns{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.cta-btn-main{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;font-size:15px;font-weight:700;padding:15px 34px;border-radius:10px;transition:.2s;display:inline-flex;align-items:center;gap:9px}
.cta-btn-main:hover{opacity:.9;transform:translateY(-2px)}
.cta-btn-ghost{border:1.5px solid rgba(255,255,255,.25);color:#fff;font-size:15px;font-weight:600;padding:15px 30px;border-radius:10px;transition:.2s;display:inline-flex;align-items:center;gap:9px}
.cta-btn-ghost:hover{background:rgba(255,255,255,.08)}

/* FOOTER */
footer{background:#050d20;padding:60px 5% 30px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1.5fr;gap:36px;margin-bottom:48px}
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
.footer-links{display:flex;gap:20px}
.footer-links a{color:rgba(255,255,255,.4);font-size:12px;transition:.2s}
.footer-links a:hover{color:rgba(255,255,255,.8)}

/* CAROUSEL */
.carousel-wrapper{overflow:hidden;position:relative;padding:.5rem 0 1rem;-webkit-mask-image:linear-gradient(to right,transparent 0%,black 5%,black 95%,transparent 100%);mask-image:linear-gradient(to right,transparent 0%,black 5%,black 95%,transparent 100%)}
.carousel-track{display:flex;gap:20px;width:max-content;cursor:grab;user-select:none}
.carousel-track:active{cursor:grabbing}
.feat-card{flex:0 0 280px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:1.5rem 1.25rem 1.25rem;position:relative;overflow:hidden;transition:border-color .2s,box-shadow .2s}
.feat-card:hover{border-color:rgba(0,0,0,.15);box-shadow:0 4px 20px rgba(0,0,0,.06)}
.feat-card-top-border{position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0}

/* KEY FEATURES — non-carousel grid layout */
.feat-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 24px}
.feat-row{display:flex;gap:16px;align-items:flex-start;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:22px 20px;transition:transform .2s,border-color .2s,box-shadow .2s}
.feat-row:hover{transform:translateY(-3px);border-color:rgba(0,0,0,.15);box-shadow:0 8px 26px rgba(13,27,62,.08)}
.feat-icon-lg{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.feat-icon-lg svg{width:24px;height:24px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.feat-row-body{flex:1;min-width:0}
.feat-row .feat-title{margin-bottom:6px}
.feat-row .feat-desc{margin-bottom:0}
.feat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.feat-icon svg{width:24px;height:24px;fill:none;stroke:#ffffff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.feat-title{font-size:15px;font-weight:600;color:#1a1a2e;margin-bottom:8px}
.feat-desc{font-size:13px;color:#6b7280;line-height:1.65}
.carousel-dots{display:flex;justify-content:center;gap:6px;margin-top:1.25rem}
.carousel-dots .dot{width:6px;height:6px;border-radius:50%;background:#d1d5db;transition:background .2s,width .25s;cursor:pointer;border:none;padding:0}
.carousel-dots .dot.active{background:#6b7280;width:18px;border-radius:3px}
.svc-card{flex:0 0 260px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:1.5rem 1.25rem 1.25rem;position:relative;overflow:hidden;transition:border-color .2s,box-shadow .2s}
.svc-card:hover{border-color:rgba(0,0,0,.15);box-shadow:0 4px 20px rgba(0,0,0,.06)}
.svc-card-top-border{position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0}
.svc-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;color:#fff}
.svc-name{font-size:15px;font-weight:600;color:#1a1a2e;margin-bottom:8px}
.svc-desc{font-size:13px;color:#6b7280;line-height:1.65}

/* SERVICES — 2-row marquee */
.svc-row{
  overflow:hidden;position:relative;padding:.5rem 0;
  -webkit-mask-image:linear-gradient(to right,transparent 0%,black 6%,black 94%,transparent 100%);
  mask-image:linear-gradient(to right,transparent 0%,black 6%,black 94%,transparent 100%);
}
.svc-row + .svc-row{margin-top:20px}
.svc-track{
  display:flex;gap:20px;width:max-content;
  animation:svcLeft var(--dur,40s) linear infinite;
  will-change:transform;
}
.svc-row.reverse .svc-track{animation-name:svcRight}
.svc-row:hover .svc-track{animation-play-state:paused}
@keyframes svcLeft{
  from{transform:translateX(0)}
  to{transform:translateX(calc(-1 * var(--shift,0px)))}
}
@keyframes svcRight{
  from{transform:translateX(calc(-1 * var(--shift,0px)))}
  to{transform:translateX(0)}
}
@media (prefers-reduced-motion:reduce){
  .svc-track{animation:none}
}

/* SCROLL REVEAL */
.js .rv{opacity:0}
.js .rv.in{animation:rvIn .8s cubic-bezier(.22,1,.36,1) var(--d,0ms) both}
.rv-up{--ty:36px}
.rv-right{--tx:90px}
.rv-left{--tx:-90px}
.rv-zoom{--s:.92}
@keyframes rvIn{
  from{opacity:0;translate:var(--tx,0) var(--ty,0);scale:var(--s,1)}
  to{opacity:1;translate:0 0;scale:1}
}
@media (prefers-reduced-motion:reduce){
  .js .rv{opacity:1}
  .js .rv.in{animation:none}
}

/* RESPONSIVE */
@media(max-width:900px){
  .proc-grid{grid-template-columns:1fr 1fr}
  .proc-grid::before{display:none}
  .footer-grid{grid-template-columns:1fr 1fr}
  .feat-grid{grid-template-columns:1fr}
}
@media(max-width:640px){
  nav{padding:0 4%}
  .nav-links,.nav-cta{display:none}
  .nav-hamburger{display:flex}
  .section{padding:60px 4%}
  .proc-grid,.footer-grid{grid-template-columns:1fr}
  .hero{padding:60px 4%;min-height:480px}
  .hero h1{font-size:32px}
  .hero p{font-size:14px}
  .feat-row{padding:18px 16px}
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════
   SPLASH SCREEN — i-paste ito sa simula ng <body>
════════════════════════════════════════════════ -->
<div id="splashScreen" style="
  position:fixed;inset:0;z-index:9999;
  background:linear-gradient(160deg,#071029 0%,#0d1b3e 45%,#122060 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:opacity .6s ease, visibility .6s ease;
">
  <!-- dot pattern background -->
  <div style="position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.05) 1px,transparent 1px);background-size:28px 28px;pointer-events:none"></div>

  <!-- logo ring -->
  <div id="splashRing" style="
    width:120px;height:120px;border-radius:50%;
    border:2px solid rgba(192,57,43,.4);
    display:flex;align-items:center;justify-content:center;
    position:relative;margin-bottom:22px;
    opacity:0;transform:scale(.5);
    transition:opacity .7s cubic-bezier(.34,1.56,.64,1),transform .7s cubic-bezier(.34,1.56,.64,1);
  ">
    <div style="position:absolute;inset:-10px;border-radius:50%;border:1px solid rgba(255,255,255,.1)"></div>
    <div style="position:absolute;inset:-20px;border-radius:50%;border:1px solid rgba(255,255,255,.05)"></div>
    <img src="uploads/lipay.png" alt="Barangay Lipay"
      onerror="this.style.display='none';document.getElementById('splashInitials').style.display='flex'"
      style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.15)">
    <div id="splashInitials" style="
      display:none;width:90px;height:90px;border-radius:50%;
      background:linear-gradient(135deg,#c0392b,#e74c3c);
      align-items:center;justify-content:center;
      font-size:28px;font-weight:800;color:#fff;letter-spacing:-1px;
    ">BL</div>
  </div>

  <!-- text -->
  <div id="splashText" style="text-align:center;opacity:0;transform:translateY(12px);transition:opacity .5s ease .35s,transform .5s ease .35s">
    <div style="color:#fff;font-size:22px;font-weight:800;letter-spacing:-.4px">Barangay Lipay</div>
    <div style="color:rgba(255,255,255,.45);font-size:12.5px;margin-top:4px;font-weight:500">Jose Dalman, Zamboanga del Norte</div>
  </div>

  <!-- progress bar -->
  <div style="width:200px;height:3px;background:rgba(255,255,255,.1);border-radius:99px;margin-top:28px;overflow:hidden">
    <div id="splashBar" style="height:100%;width:0%;background:linear-gradient(90deg,#c0392b,#e74c3c);border-radius:99px;transition:width 1.6s cubic-bezier(.4,0,.2,1)"></div>
  </div>

  <!-- bouncing dots -->
  <div style="display:flex;gap:7px;margin-top:14px" id="splashDots">
    <div class="sp-bounce" style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.25);animation:spBounce 1.2s ease-in-out .9s infinite"></div>
    <div class="sp-bounce" style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.25);animation:spBounce 1.2s ease-in-out 1.05s infinite"></div>
    <div class="sp-bounce" style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.25);animation:spBounce 1.2s ease-in-out 1.2s infinite"></div>
  </div>

  <div style="color:rgba(255,255,255,.22);font-size:11px;margin-top:24px;font-weight:500">Loading…</div>
</div>

<style>
@keyframes spBounce{0%,80%,100%{background:rgba(255,255,255,.2);transform:scale(1)}40%{background:#e74c3c;transform:scale(1.6)}}
@keyframes spRingPulse{0%,100%{box-shadow:0 0 0 0 rgba(192,57,43,.3)}50%{box-shadow:0 0 0 12px rgba(192,57,43,0)}}
</style>

<script>
  
(function(){
  var splash = document.getElementById('splashScreen');
  var ring   = document.getElementById('splashRing');
  var text   = document.getElementById('splashText');
  var bar    = document.getElementById('splashBar');

  // Step 1: animate logo in
  requestAnimationFrame(function(){
    setTimeout(function(){
      ring.style.opacity = '1';
      ring.style.transform = 'scale(1)';
      ring.style.animation = 'spRingPulse 2s ease-in-out 0.7s infinite';
    }, 80);

    // Step 2: fade in text
    setTimeout(function(){
      text.style.opacity = '1';
      text.style.transform = 'translateY(0)';
    }, 380);

    // Step 3: fill progress bar
    setTimeout(function(){
      bar.style.width = '100%';
    }, 500);

    // Step 4: hide splash after page loads (or after 2.2s minimum)
    var minTime = 2200;
    var startTime = Date.now();

    function hideSplash(){
      var elapsed = Date.now() - startTime;
      var delay = Math.max(0, minTime - elapsed);
      setTimeout(function(){
        splash.style.opacity = '0';
        splash.style.visibility = 'hidden';
        // remove from DOM after fade
        setTimeout(function(){ splash.remove(); }, 650);
      }, delay);
    }

    if(document.readyState === 'complete'){
      hideSplash();
    } else {
      window.addEventListener('load', hideSplash);
      // fallback: hide after 4s kahit hindi pa tapos mag-load
      setTimeout(hideSplash, 4000);
    }
  });
})();
</script>
<!-- ═══════════════════════════════════════ END SPLASH ═══ -->

<?php if ($announcement_active && !empty($announcement_text)): ?>
<div style="background:#fef3c7;color:#92400e;padding:10px 5%;text-align:center;font-size:13px;font-weight:500;border-bottom:1px solid #fde68a">
    <marquee behavior="scroll" direction="left" scrollamount="3" onmouseover="this.stop()" onmouseout="this.start()">
        <?= htmlspecialchars($announcement_text) ?>
    </marquee>
</div>
<?php endif; ?>

<!-- NAV -->
<nav>
  <div class="nav-logo">
    <img src="uploads/lipay.png" alt="Lipay" onerror="this.style.display='none'">
    <div>
      <div class="nav-logo-text">Barangay Lipay</div>
      <div class="nav-logo-sub">Jose Dalman, Zamboanga del Norte</div>
    </div>
  </div>
  <div class="nav-links">
    <a href="#">Home</a>
    <a href="#services">Services</a>
    <a href="#about">About Us</a>
    <a href="#process">How It Works</a>
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
  <a href="#">Home</a>
  <a href="#services">Services</a>
  <a href="#about">About Us</a>
  <a href="#process">How It Works</a>
  <div class="mobile-cta">
    <a href="auth/login.php" class="btn-outline" style="text-align:center;border-radius:8px;padding:10px 0;font-size:13px;font-weight:600;color:#fff;border:1.5px solid rgba(255,255,255,.35)">Sign In</a>
    <a href="auth/register.php" class="btn-primary" style="text-align:center;border-radius:8px;padding:10px 0;font-size:13px;font-weight:700">Register</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-dots"></div>

  <!-- Concentric rings -->
  <div class="hero-rings">
    <div class="hero-ring-circle" style="width:380px;height:380px;border:1px solid rgba(255,255,255,.055)"></div>
    <div class="hero-ring-circle" style="width:560px;height:560px;border:1px solid rgba(255,255,255,.04)"></div>
    <div class="hero-ring-circle" style="width:740px;height:740px;border:1px solid rgba(255,255,255,.025)"></div>
    <div class="hero-ring-circle" style="width:920px;height:920px;border:1px solid rgba(255,255,255,.015)"></div>
  </div>

  <div class="hero-glow"></div>
  <div class="hero-glow2"></div>
  <canvas id="heroCanvas"></canvas>

  <div class="hero-inner">
    <div class="hero-badge">
      <div class="hero-badge-dot"></div>
      <span>Digital Governance Platform</span>
    </div>

    <h1><?= $hero_title ?></h1>

    <p><?= $hero_subtitle ?></p>

    <div class="hero-btns">
      <a href="#services" class="hero-btn-sec">Explore Services</a>
    </div>
  </div>
</section>

<!-- KEY FEATURES -->
<section class="section" id="about">
  <div class="section-header">
    <div class="section-label">Key Features</div>
    <h2 class="section-title">Everything Your Barangay Needs</h2>
    <p class="section-sub">A complete digital platform built for residents and officials of Barangay Lipay.</p>
  </div>
  <div class="feat-grid">
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#1565c0,#1976d2,#42a5f5)">
        <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">Online Document Requests</div>
        <div class="feat-desc">Request official barangay documents anytime — clearances, certificates, and more — all without visiting the office.</div>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#1a237e,#3949ab,#7e57c2,#9b59b6)">
        <svg viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">Real-Time Notifications</div>
        <div class="feat-desc">Receive instant updates on your document request status — from submission to approval and pickup readiness.</div>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#004d40,#00897b,#26a69a,#80cbc4)">
        <svg viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">Submit Reports & Complaints</div>
        <div class="feat-desc">Report community concerns, blotter incidents, or submit complaints directly to barangay officials through a secure portal.</div>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#b71c1c,#c62828,#ef5350,#ff8a65)">
        <svg viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6-10l6 3m0 10l5.447-2.724A1 1 0 0021 16.382V5.618a1 1 0 00-1.447-.894L15 7m0 13V7"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">Personal Dashboard</div>
        <div class="feat-desc">Track all your requests in one place. View request history, check status updates, and manage your account easily.</div>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#1a237e,#283593,#1565c0,#1e88e5)">
        <svg viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">Secure & Verified</div>
        <div class="feat-desc">Admin-verified accounts ensure every user is a legitimate resident. Your data is protected with industry-standard security.</div>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-icon-lg" style="background:linear-gradient(135deg,#4a148c,#7b1fa2,#ab47bc,#ce93d8)">
        <svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
      </div>
      <div class="feat-row-body">
        <div class="feat-title">AI Chatbot Assistant</div>
        <div class="feat-desc">Get instant answers about services, requirements, and processes through the built-in Lipay Assistant chatbot available 24/7.</div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICES CAROUSEL -->
<section class="section section-alt" id="services">
  <div class="section-header">
    <div class="section-label">Barangay Services</div>
    <h2 class="section-title">Official Documents Available Online</h2>
    <p class="section-sub">Comprehensive digital services for residents — no queuing, no hassle.</p>
  </div>

  <!-- ROW 1: papuntang KALIWA -->
  <div class="svc-row">
    <div class="svc-track">
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#1565c0,#42a5f5)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#1565c0,#1976d2,#42a5f5)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <div class="svc-name">Barangay Clearance</div>
        <div class="svc-desc">For employment, business, travel, and other legal requirements.</div>
      </div>
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#1a237e,#7e57c2)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#1a237e,#3949ab,#7e57c2)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="svc-name">Certificate of Death</div>
        <div class="svc-desc">Official certification that a resident of the barangay has passed away.</div>
      </div>
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#004d40,#26a69a)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#004d40,#00897b,#26a69a)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        </div>
        <div class="svc-name">Certificate of Residency</div>
        <div class="svc-desc">Proof that you are an official resident within Barangay Lipay.</div>
      </div>
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#0d47a1,#1976d2)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#0d47a1,#1565c0,#1976d2)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
        </div>
        <div class="svc-name">Certificate of Indigency</div>
        <div class="svc-desc">Required for scholarship applications, financial aid, and government benefit programs.</div>
      </div>
    </div>
  </div>

  <!-- ROW 2: papuntang KANAN -->
  <div class="svc-row reverse">
    <div class="svc-track">
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#4a148c,#ab47bc)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#4a148c,#7b1fa2,#ab47bc)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <div class="svc-name">Business Permit Clearance</div>
        <div class="svc-desc">Barangay clearance required before securing a Mayor's Permit for business operations.</div>
      </div>
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#bf360c,#ff7043)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#bf360c,#e64a19,#ff7043)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z"/></svg>
        </div>
        <div class="svc-name">Community Tax Certificate</div>
        <div class="svc-desc">Annual cedula (CTC) required for most official legal and government transactions.</div>
      </div>
      <div class="svc-card">
        <div class="svc-card-top-border" style="background:linear-gradient(90deg,#1b5e20,#43a047)"></div>
        <div class="svc-icon" style="background:linear-gradient(135deg,#1b5e20,#2e7d32,#43a047)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        </div>
        <div class="svc-name">Certificate of Good Moral</div>
        <div class="svc-desc">Certifies the good moral character and standing of a barangay resident.</div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section" id="process">
  <div class="section-header">
    <div class="section-label">How It Works</div>
    <h2 class="section-title">Simple. Fast. Official.</h2>
    <p class="section-sub">Three easy steps to request and receive your barangay documents online.</p>
  </div>
  <div class="proc-grid">
    <div class="proc-card">
      <div class="proc-num">1</div>
      <div class="proc-title">Create Your Account</div>
      <div class="proc-desc">Register using your valid personal details. Wait for the barangay admin to verify and approve your account before you can start requesting.</div>
    </div>
    <div class="proc-card">
      <div class="proc-num">2</div>
      <div class="proc-title">Submit Your Request</div>
      <div class="proc-desc">Choose the document you need, fill out the online request form, and track the real-time processing status through your personal dashboard.</div>
    </div>
    <div class="proc-card">
      <div class="proc-num">3</div>
      <div class="proc-title">Collect Your Document</div>
      <div class="proc-desc">Once your request is approved, you'll receive a notification. Simply visit the barangay hall to claim your official document in person.</div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-content">
    <h2>Ready to Modernize Your<br><span>Barangay Experience?</span></h2>
    <p>Join the digital transformation. Create your free account and experience faster, easier access to all Barangay Lipay services.</p>
   <div class="cta-btns">
  
</div>
    </div>
  </div>
</section>

<!-- TEAM / POCKETDEVS -->
<section class="section section-alt" id="team">
  <style>
  .pd-stage{position:relative;display:flex;align-items:center;justify-content:center;height:500px;perspective:1200px}
  .pd-card{position:absolute;width:260px;background:#111e38;border:1px solid rgba(255,255,255,.08);border-radius:20px;overflow:hidden;cursor:pointer;transition:transform .55s cubic-bezier(.22,1,.36,1),opacity .45s,filter .45s;left:50%;top:50%;transform-origin:center center}
  .pd-card.center{transform:translate(-50%,-50%) scale(1);opacity:1;z-index:10;filter:none;border-color:rgba(192,57,43,.5)}
  .pd-card.left{transform:translate(calc(-50% - 290px),-50%) scale(.82) rotateY(18deg);opacity:.55;z-index:5;filter:brightness(.7)}
  .pd-card.right{transform:translate(calc(-50% + 290px),-50%) scale(.82) rotateY(-18deg);opacity:.55;z-index:5;filter:brightness(.7)}
  .pd-card.far-left,.pd-card.far-right{opacity:.15;z-index:1;filter:brightness(.3)}
  .pd-card.far-left{transform:translate(calc(-50% - 500px),-50%) scale(.65) rotateY(28deg)}
  .pd-card.far-right{transform:translate(calc(-50% + 500px),-50%) scale(.65) rotateY(-28deg)}
  .pd-img-wrap{height:280px;overflow:hidden;background:#0d1b3e;position:relative}
  .pd-img-wrap img{width:100%;height:100%;object-fit:cover;object-position:top center}
  .pd-img-fallback{width:100%;height:100%;display:none;align-items:center;justify-content:center;background:linear-gradient(160deg,#1a2a5e,#0d1b3e);font-size:56px;font-weight:800;color:rgba(255,255,255,.15)}
  .pd-img-overlay{position:absolute;bottom:0;left:0;right:0;height:80px;background:linear-gradient(transparent,#111e38)}
  .pd-body{padding:20px;text-align:center}
  .pd-role-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(192,57,43,.12);border:1px solid rgba(192,57,43,.25);border-radius:100px;padding:4px 12px;margin-bottom:10px}
  .pd-role-badge span{font-size:10.5px;font-weight:700;color:#e07060;letter-spacing:.3px}
  .pd-name{color:#fff;font-size:17px;font-weight:800;letter-spacing:-.3px;margin-bottom:3px}
  .pd-org{color:rgba(255,255,255,.35);font-size:12px}
  .pd-nav{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:28px}
  .pd-btn{width:44px;height:44px;border-radius:50%;border:1.5px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s}
  .pd-btn:hover{background:rgba(192,57,43,.2);border-color:#c0392b}
  .pd-btn svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
  .pd-dots{display:flex;gap:7px}
  .pd-dot-btn{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.2);border:none;cursor:pointer;padding:0;transition:.25s}
  .pd-dot-btn.active{background:#e74c3c;transform:scale(1.35)}
  .pd-info{text-align:center;margin-top:24px}
  .pd-info-name{color:#fff;font-size:20px;font-weight:800;letter-spacing:-.3px;margin-bottom:5px;transition:opacity .3s}
  .pd-info-desc{color:rgba(255,255,255,.45);font-size:13.5px;line-height:1.65;max-width:380px;margin:0 auto;transition:opacity .3s}
  </style>

  <div style="background:linear-gradient(160deg,#071029 0%,#0d1b3e 100%);padding:80px 5%;position:relative;overflow:hidden">
    <div style="text-align:center;margin-bottom:48px">
      <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(192,57,43,.12);border:1px solid rgba(192,57,43,.28);border-radius:100px;padding:5px 16px;margin-bottom:14px">
        <div style="width:6px;height:6px;border-radius:50%;background:#e74c3c;animation:pulse 2s infinite"></div>
        <span style="color:#e07060;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase">Developed By</span>
      </div>
      <h2 style="color:#fff;font-size:clamp(26px,3vw,38px);font-weight:800;letter-spacing:-.5px;margin-bottom:8px">Meet <span style="color:#e74c3c">PocketDevs</span></h2>
      <p style="color:rgba(255,255,255,.45);font-size:14px">The team behind the Barangay Lipay Document Request System.</p>
    </div>
    <div class="pd-stage">
      <div style="position:absolute;top:0;left:50%;transform:translateX(-50%);width:320px;height:500px;background:radial-gradient(ellipse 180px 260px at 50% 30%,rgba(192,57,43,.07) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;width:100%;height:100%;transform-style:preserve-3d" id="pdTrack2"></div>
    </div>
    <div class="pd-nav">
      <button class="pd-btn" id="pdPrev2"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
      <div class="pd-dots" id="pdDots2"></div>
      <button class="pd-btn" id="pdNext2"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
    </div>
    <div class="pd-info">
      <div class="pd-info-name" id="pdName2"></div>
      <div class="pd-info-desc" id="pdDesc2"></div>
    </div>
  </div>
</section>

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
      <a href="#">Home</a>
      <a href="#services">Services</a>
      <a href="#about">About Us</a>
      <a href="#process">How It Works</a>
    </div>
    <div class="footer-col">
      <h4>Our Services</h4>
      <a href="#">Barangay Clearances</a>
      <a href="#">Business Permits</a>
      <a href="#">Certificates</a>
      <a href="#">Community Tax Cert.</a>
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

<!-- CHATBOT FAB -->
<style>
.cb-fab{position:fixed;bottom:1.75rem;right:1.75rem;z-index:200;width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 24px rgba(192,57,43,.45);transition:transform .3s cubic-bezier(.34,1.56,.64,1),box-shadow .3s}
.cb-fab:hover{transform:scale(1.1);box-shadow:0 10px 32px rgba(192,57,43,.55)}
.cb-fab svg{width:24px;height:24px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:opacity .2s}
.cb-fab .icon-chat,.cb-fab .icon-close{position:absolute;transition:opacity .2s,transform .2s}
.cb-fab .icon-close{opacity:0;transform:rotate(-90deg)}
.cb-fab.open .icon-chat{opacity:0;transform:rotate(90deg)}
.cb-fab.open .icon-close{opacity:1;transform:rotate(0)}
.cb-badge{position:absolute;top:-4px;right:-4px;width:18px;height:18px;background:#e74c3c;border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#fff}
.cb-window{position:fixed;bottom:6.5rem;right:1.75rem;z-index:199;width:340px;max-width:calc(100vw - 2rem);border-radius:20px;background:#fff;box-shadow:0 24px 64px rgba(13,27,62,.22);border:1px solid #d4dff5;display:flex;flex-direction:column;overflow:hidden;transform:scale(.88) translateY(18px);opacity:0;pointer-events:none;transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .28s ease}
.cb-window.open{transform:scale(1) translateY(0);opacity:1;pointer-events:auto}
.cb-header{background:linear-gradient(135deg,#0d1b3e,#1a3570);padding:14px 16px;display:flex;align-items:center;gap:10px}
.cb-header-icon{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cb-header-icon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.cb-header-info{flex:1}
.cb-header-name{color:#fff;font-size:13px;font-weight:700}
.cb-header-status{display:flex;align-items:center;gap:5px;margin-top:2px}
.cb-header-dot{width:6px;height:6px;border-radius:50%;background:#2ecc71;animation:pulse 2s infinite}
.cb-header-status span{color:rgba(255,255,255,.6);font-size:11px;font-weight:500}
.cb-header-close{background:rgba(255,255,255,.1);border:none;cursor:pointer;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:.2s}
.cb-header-close:hover{background:rgba(255,255,255,.2)}
.cb-header-close svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.cb-msgs{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;min-height:260px;max-height:320px;background:#f8faff}
.cb-msgs::-webkit-scrollbar{width:4px}
.cb-msgs::-webkit-scrollbar-track{background:transparent}
.cb-msgs::-webkit-scrollbar-thumb{background:#d4dff5;border-radius:4px}
.cb-msg-row{display:flex;flex-direction:column}
.cb-msg-row.user{align-items:flex-end}
.cb-msg-row.bot{align-items:flex-start}
.cb-bubble{padding:9px 13px;border-radius:16px;font-size:12.5px;line-height:1.6;max-width:88%;word-break:break-word}
.cb-bubble.bot{background:#fff;color:#1a2a4a;border-bottom-left-radius:4px;border:1px solid #e0e8f5}
.cb-bubble.user{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;border-bottom-right-radius:4px}
.cb-quick{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.cb-quick-btn{font-size:11px;font-weight:600;padding:5px 12px;border-radius:100px;border:1.5px solid #c0392b;color:#c0392b;background:rgba(192,57,43,.06);cursor:pointer;transition:.15s}
.cb-quick-btn:hover{background:#c0392b;color:#fff}
.cb-typing{display:flex;align-items:center;gap:4px;padding:10px 14px;background:#fff;border:1px solid #e0e8f5;border-radius:16px;border-bottom-left-radius:4px;width:fit-content}
.cb-dot{width:6px;height:6px;border-radius:50%;background:#c0392b;animation:cb-bounce .9s ease-in-out infinite}
.cb-dot:nth-child(2){animation-delay:.18s}
.cb-dot:nth-child(3){animation-delay:.36s}
@keyframes cb-bounce{0%,80%,100%{transform:translateY(0);opacity:.4}40%{transform:translateY(-5px);opacity:1}}
.cb-input-row{display:flex;gap:8px;padding:10px 12px;background:#fff;border-top:1px solid #e0e8f5}
.cb-input{flex:1;background:#f0f4fc;border:1.5px solid #d4dff5;border-radius:100px;padding:8px 14px;font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:#1a2a4a;outline:none;transition:.2s}
.cb-input:focus{border-color:#c0392b;background:#fff}
.cb-input::placeholder{color:#9aaac5}
.cb-send{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.2s}
.cb-send:hover{opacity:.88;transform:scale(1.06)}
.cb-send svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
</style>

<button class="cb-fab" id="cbFab" aria-label="Open chat assistant">
  <span class="icon-chat"><svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></span>
  <span class="icon-close"><svg viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></span>
  <div class="cb-badge" id="cbBadge">1</div>
</button>

<div class="cb-window" id="cbWindow">
  <div class="cb-header">
    <div class="cb-header-icon"><svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></div>
    <div class="cb-header-info">
      <div class="cb-header-name">Lipay Assistant</div>
      <div class="cb-header-status"><div class="cb-header-dot"></div><span>Barangay Lipay · Online</span></div>
    </div>
    <button class="cb-header-close" id="cbClose"><svg viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
  </div>
  <div class="cb-msgs" id="cbMsgs"></div>
  <div class="cb-input-row">
    <input class="cb-input" id="cbInput" type="text" placeholder="Magtanong tungkol sa serbisyo...">
    <button class="cb-send" id="cbSend"><svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/></svg></button>
  </div>
</div>

<script>
// ── Hamburger Nav ─────────────────────────────────────────
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

// ── Services 2-row marquee ───────────────────────────────
(function(){
  var SPEED = 40; // px per second, taasan para bumilis

  function build(){
    document.querySelectorAll('.svc-row').forEach(function(row){
      var track = row.querySelector('.svc-track');
      if(!track) return;

      track.querySelectorAll('[data-clone]').forEach(function(c){ c.remove(); });
      var originals = Array.from(track.querySelectorAll('.svc-card'));
      if(!originals.length) return;

      var gap = parseFloat(getComputedStyle(track).columnGap) || 20;
      var groupW = originals.reduce(function(sum,c){ return sum + c.offsetWidth + gap; }, 0);

      var groups = Math.ceil(row.offsetWidth / groupW) + 1;
      for(var g = 1; g < groups; g++){
        originals.forEach(function(c){
          var clone = c.cloneNode(true);
          clone.setAttribute('data-clone','1');
          clone.setAttribute('aria-hidden','true');
          track.appendChild(clone);
        });
      }

      track.style.setProperty('--shift', groupW + 'px');
      track.style.setProperty('--dur', (groupW / SPEED) + 's');
    });
  }

  window.addEventListener('load', build);
  var t;
  window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(build, 200); });
})();


// ── Scroll Reveal ────────────────────────────────────────
(function(){
  var groups=[
    {sel:'.section-header',        v:'up'},
    {sel:'.feat-row',              v:'up',    cols:2},
    {sel:'.svc-row:not(.reverse)', v:'right'},
    {sel:'.svc-row.reverse',       v:'left'},
    {sel:'.proc-card',             v:'up',    cols:3},
    {sel:'.cta-content',           v:'zoom'},
    {sel:'#team > div',            v:'up'},
    {sel:'.footer-grid > *',       v:'up',    cols:5}
  ];

  function init(){
    if(!('IntersectionObserver' in window)) return;
    var io=new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); }
      });
    },{threshold:.15,rootMargin:'0px 0px -40px 0px'});

    document.documentElement.classList.add('js');
    groups.forEach(function(g){
      document.querySelectorAll(g.sel).forEach(function(el,i){
        el.classList.add('rv','rv-'+g.v);
        if(g.cols) el.style.setProperty('--d',((i%g.cols)*120)+'ms');
        io.observe(el);
      });
    });
  }

  // Hintayin muna matapos ang splash screen (~2.2s)
  window.addEventListener('load',function(){ setTimeout(init,2300); });
})();

// ── PocketDevs ───────────────────────────────────────────
(function(){
  const members=[
    {name:"Arjene Elias",role:"UI/UX Designer",img:"uploads/arjene.png",init:"AE",desc:"Crafted the visual identity and user experience of the Barangay Lipay system."},
    {name:"Christopher Jael",role:"Front-end Developer",img:"uploads/jael.png",init:"CJ",desc:"Built the responsive and interactive interfaces residents use every day."},
    {name:"Jcarl Clapano",role:"Back-end Developer",img:"uploads/karl.png",init:"JC",desc:"Engineered the server logic, database, and security that power the platform."},
  ];
  const track=document.getElementById('pdTrack2');
  const dotsEl=document.getElementById('pdDots2');
  const nameEl=document.getElementById('pdName2');
  const descEl=document.getElementById('pdDesc2');
  let cur=0;
  function getPos(i){const l=members.length,r=((i-cur)%l+l)%l;return r===0?'center':r===1?'right':r===l-1?'left':r===2?'far-right':'far-left';}
  function buildCards(){track.innerHTML='';members.forEach((m,i)=>{const c=document.createElement('div');c.className='pd-card '+getPos(i);c.dataset.idx=i;c.innerHTML=`<div class="pd-img-wrap"><img src="${m.img}" alt="${m.name}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><div class="pd-img-fallback">${m.init}</div><div class="pd-img-overlay"></div></div><div class="pd-body"><div class="pd-role-badge"><span>${m.role}</span></div><div class="pd-name">${m.name}</div><div class="pd-org">Barangay Lipay · 2025</div></div>`;c.addEventListener('click',()=>{if(i!==cur){cur=i;update();}});track.appendChild(c);});}
  function buildDots(){dotsEl.innerHTML='';members.forEach((_,i)=>{const d=document.createElement('button');d.className='pd-dot-btn'+(i===cur?' active':'');d.addEventListener('click',()=>{cur=i;update();});dotsEl.appendChild(d);});}
  function update(){document.querySelectorAll('#pdTrack2 .pd-card').forEach(c=>{c.className='pd-card '+getPos(parseInt(c.dataset.idx));});document.querySelectorAll('#pdDots2 .pd-dot-btn').forEach((d,i)=>{d.className='pd-dot-btn'+(i===cur?' active':'');});nameEl.style.opacity=0;descEl.style.opacity=0;setTimeout(()=>{nameEl.textContent=members[cur].name;descEl.textContent=members[cur].desc;nameEl.style.opacity=1;descEl.style.opacity=1;},180);}
  document.getElementById('pdPrev2').addEventListener('click',()=>{cur=(cur-1+members.length)%members.length;update();});
  document.getElementById('pdNext2').addEventListener('click',()=>{cur=(cur+1)%members.length;update();});
  buildCards();buildDots();
  nameEl.textContent=members[0].name;descEl.textContent=members[0].desc;
  let ap=setInterval(()=>{cur=(cur+1)%members.length;update();},3800);
  track.addEventListener('click',()=>clearInterval(ap));
})();

// ── Chatbot (Groq AI + KB fallback) ───────────────────────
(function(){
  const fab=document.getElementById('cbFab');
  const win=document.getElementById('cbWindow');
  const msgs=document.getElementById('cbMsgs');
  const input=document.getElementById('cbInput');
  const send=document.getElementById('cbSend');
  const close=document.getElementById('cbClose');
  const badge=document.getElementById('cbBadge');
  let open=false,greeted=false;
  const kb=[
    {keys:['kumusta','hello','hi','musta','good morning','good afternoon','good evening','hey'],answer:'Mabuhay!  Ako si <strong>Lipay Assistant</strong>, ang iyong gabay sa mga serbisyo ng <strong>Barangay Lipay</strong>. Paano kita matutulungan ngayon?',quick:['Mga Dokumento','Paano Mag-Request','Oras ng Opisina']},
    {keys:['dokumento','serbisyo','document','service','ano','available','list'],answer:'Narito ang mga opisyal na dokumento na available online:\n\n Barangay Clearance\n Certificate of Death\n  Certificate of Residency\n Certificate of Indigency\n Business Permit Clearance\n Community Tax Certificate (Cedula)\n Certificate of Good Moral\n\nAlin ang gusto mong malaman pa?',quick:['Barangay Clearance','Certificate of Indigency','Paano Mag-Request']},
    {keys:['clearance','barangay clearance'],answer:' <strong>Barangay Clearance</strong>\n\nGinagamit para sa trabaho, negosyo, biyahe, at iba pang legal na layunin.\n\n<em>Mga kailangan:</em>\n• Valid ID\n• Proof of residency\n\nMag-request online sa aming sistema!',quick:['Paano Mag-Request','Certificate of Residency']},
    {keys:['indigency','scholarship','ayuda','benefit'],answer:'💙 <strong>Certificate of Indigency</strong>\n\nKailangan para sa:\n• Scholarship applications\n• Financial aid / ayuda\n• Government benefit programs\n\nMag-login sa iyong account para mag-request.',quick:['Paano Mag-Request','Mga Dokumento']},
    {keys:['residency','residente'],answer:' <strong>Certificate of Residency</strong>\n\nPatunay na ikaw ay opisyal na naninirahan sa Barangay Lipay. Karaniwang ginagamit para sa enrollment, legal transactions, at iba pa.',quick:['Paano Mag-Request','Barangay Clearance']},
    {keys:['good moral','moral'],answer:' <strong>Certificate of Good Moral</strong>\n\nSertipiko na nagpapatunay ng iyong mabuting asal at katayuan bilang residente ng barangay.',quick:['Paano Mag-Request','Mga Dokumento']},
    {keys:['business','permit','negosyo'],answer:' <strong>Business Permit Clearance</strong>\n\nKailangan bago makakuha ng Mayor\'s Permit para sa negosyo.',quick:['Paano Mag-Request','Mga Dokumento']},
    {keys:['cedula','community tax','ctc'],answer:' <strong>Community Tax Certificate (Cedula)</strong>\n\nTaunang cedula na kailangan para sa halos lahat ng opisyal na transaksyon.',quick:['Paano Mag-Request','Mga Dokumento']},
    {keys:['paano','hakbang','proseso','mag-request','how','step','request'],answer:' <strong>Paano Mag-Request ng Dokumento:</strong>\n\n<strong>1.</strong> Lumikha ng account (libre)\n<strong>2.</strong> Hintayin ang pag-apruba ng admin\n<strong>3.</strong> Pumili ng dokumento at punan ang form\n<strong>4.</strong> I-track ang status sa iyong dashboard\n<strong>5.</strong> Kunin ang dokumento sa Barangay Hall',quick:['Mag-Register','Oras ng Opisina','Mga Dokumento']},
    {keys:['oras','opisina','office hours','bukas','open'],answer:' <strong>Oras ng Opisina:</strong>\n\n Lunes – Biyernes\n 8:00 AM – 5:00 PM\n\n Sabado, Linggo, at Pista Opisyal — Sarado',quick:['Makipag-ugnayan','Paano Mag-Request']},
    {keys:['contact','makipag-ugnayan','address','saan','location'],answer:'📍 <strong>Makipag-ugnayan:</strong>\n\n  Barangay Lipay\nJose Dalman, Zamboanga del Norte\n\n admin@barangaylipay.ph',quick:['Oras ng Opisina','Mag-Register']},
    {keys:['register','mag-register','account','create','gawa'],answer:' <strong>Paano Mag-Register:</strong>\n\n1. I-click ang <strong>"Register Now"</strong> button sa itaas\n2. Punan ang iyong personal na impormasyon\n3. Hintayin ang verification ng admin\n\nLibre ang pagpaparehistro!',quick:['Paano Mag-Request','Mga Dokumento']},
    {keys:['bayad','libre','bayaran','fee','charge','cost'],answer:' Ang pagpaparehistro ng account ay <strong>LIBRE</strong>. Ang bayad sa dokumento ay depende sa uri ng sertipiko.',quick:['Makipag-ugnayan','Oras ng Opisina']},
    {keys:['status','track','update','nasaan'],answer:'🔍 Makikita mo ang real-time status ng iyong request sa iyong personal na <strong>dashboard</strong> pagkatapos mag-login.',quick:['Paano Mag-Request','Mag-Register']},
  ];
  const quickMap={'Mga Dokumento':'dokumento','Paano Mag-Request':'paano mag-request','Oras ng Opisina':'oras ng opisina','Makipag-ugnayan':'contact','Barangay Clearance':'barangay clearance','Certificate of Indigency':'indigency','Certificate of Residency':'residency','Mag-Register':'mag-register'};
  function esc(s){return s.replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]))}
  function scrollBottom(){msgs.scrollTop=msgs.scrollHeight}
  function addUser(text){const d=document.createElement('div');d.className='cb-msg-row user';d.innerHTML=`<div class="cb-bubble user">${esc(text)}</div>`;msgs.appendChild(d);scrollBottom();}
  function addBot(html,quick){const typing=document.createElement('div');typing.className='cb-msg-row bot';typing.innerHTML='<div class="cb-typing"><div class="cb-dot"></div><div class="cb-dot"></div><div class="cb-dot"></div></div>';msgs.appendChild(typing);scrollBottom();setTimeout(()=>{typing.remove();const d=document.createElement('div');d.className='cb-msg-row bot';let h=`<div class="cb-bubble bot">${html.replace(/\n/g,'<br>')}</div>`;if(quick&&quick.length)h+=`<div class="cb-quick">${quick.map(q=>`<button class="cb-quick-btn" onclick="cbSendText('${q}')">${q}</button>`).join('')}</div>`;d.innerHTML=h;msgs.appendChild(d);scrollBottom();},620);}
  window.cbSendText=function(t){handleInput(t)};
  function findAnswer(t){t=t.toLowerCase().trim();const mapped=Object.keys(quickMap).find(k=>k.toLowerCase()===t);if(mapped)t=quickMap[mapped].toLowerCase();let best=null,bestScore=0;for(const item of kb){let sc=0;for(const k of item.keys)if(t.includes(k))sc+=k.length;if(sc>bestScore){bestScore=sc;best=item;}}return best&&bestScore>0?best:{answer:'Pasensya na, hindi ko pa naiintindihan ang iyong tanong.  Subukan mong tanungin tungkol sa mga <strong>dokumento</strong> o <strong>proseso ng pag-request</strong>.',quick:['Mga Dokumento','Paano Mag-Request','Oras ng Opisina']};}

  // ── Groq AI integration ──
  const history=[];
  let busy=false;

  function showTyping(){
    const t=document.createElement('div');
    t.className='cb-msg-row bot';
    t.innerHTML='<div class="cb-typing"><div class="cb-dot"></div><div class="cb-dot"></div><div class="cb-dot"></div></div>';
    msgs.appendChild(t);scrollBottom();return t;
  }
  // I-escape muna ang galing sa AI (iwas XSS), tapos **bold** at line breaks lang ang papayagan
  function fmt(s){
    return esc(s).replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
  }
  // ── Routing: barangay ba o hindi? ──
  const BRGY_WORDS=['barangay','brgy','lipay','clearance','indigency','residency','good moral','cedula','ctc','community tax','permit','sertipiko','certificate','dokumento','document','serbisyo','register','registration','dashboard','opisina','office hours','kapitan','admin','verification','mag-request','bayad','fee','requirements'];
  const KB_SKIP=['ano','list','available','create','update','open','step','how'];
  function hasKey(t,k){
    const e=k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    return new RegExp('(^|[^a-z0-9])'+e+(k.length<5?'($|[^a-z0-9])':''),'i').test(t);
  }
  function isBarangayTopic(text){
    const t=text.toLowerCase().trim();
    if(t.length<=25&&/^(kumusta|musta|hello|hi|hey|good (morning|afternoon|evening))\b/.test(t))return true; // simpleng bati
    return BRGY_WORDS.some(k=>hasKey(t,k));
  }
  function kbMatch(text){
    const t=text.toLowerCase();
    let best=null,bestScore=0;
    for(const item of kb){
      let sc=0;
      for(const k of item.keys){if(!KB_SKIP.includes(k)&&hasKey(t,k))sc+=k.length;}
      if(sc>bestScore){bestScore=sc;best=item;}
    }
    return best;
  }

  async function askAI(text,mode){
    const r=await fetch('index.php?chat_api=1',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:text,mode:mode,history:history.slice(-6)})
    });
    if(!r.ok)throw new Error('HTTP '+r.status);
    const d=await r.json();
    if(!d.reply)throw new Error('Empty reply');
    return d.reply;
  }

  async function handleInput(text){
    text=text.trim();
    if(!text||busy)return;
    addUser(text);input.value='';

    // Quick buttons → KB (instant, libre)
    const isQuick=Object.keys(quickMap).some(k=>k.toLowerCase()===text.toLowerCase());
    if(isQuick){const r=findAnswer(text);addBot(r.answer,r.quick);return;}

    // 1) Tungkol sa barangay → KB ang sasagot (kung may tugma)
    const brgy=isBarangayTopic(text);
    if(brgy){
      const hit=kbMatch(text);
      if(hit){addBot(hit.answer,hit.quick);return;}
    }

    // 2) Labas sa barangay (o barangay pero walang tugma sa KB) → Groq
    //    mode 'barangay' = facts lang | mode 'general' = pangkalahatang tanong
    busy=true;
    const typing=showTyping();
    try{
      const reply=await askAI(text,brgy?'barangay':'general');
      typing.remove();
      history.push({role:'user',content:text},{role:'assistant',content:reply});
      const d=document.createElement('div');
      d.className='cb-msg-row bot';
      d.innerHTML=`<div class="cb-bubble bot">${fmt(reply)}</div>`;
      msgs.appendChild(d);scrollBottom();
    }catch(e){
      typing.remove();
      const r=findAnswer(text);
      addBot(r.answer,r.quick);
    }finally{
      busy=false;
    }
  }

  function toggleChat(){open=!open;win.classList.toggle('open',open);fab.classList.toggle('open',open);if(open){badge.style.display='none';if(!greeted){greeted=true;setTimeout(()=>addBot('Mabuhay!  Ako si <strong>Lipay Assistant</strong>. Narito ako para sagutin ang iyong mga katanungan tungkol sa mga serbisyo ng <strong>Barangay Lipay</strong>.',['Mga Dokumento','Paano Mag-Request','Oras ng Opisina']),300);}setTimeout(()=>input.focus(),400);}}
  fab.addEventListener('click',toggleChat);
  close.addEventListener('click',()=>{open=false;win.classList.remove('open');fab.classList.remove('open');});
  send.addEventListener('click',()=>handleInput(input.value));
  input.addEventListener('keypress',e=>{if(e.key==='Enter')handleInput(input.value);});
})();

// ── Hero Canvas ───────────────────────────────────────────
(function(){
  const canvas=document.getElementById('heroCanvas');
  if(!canvas)return;
  const ctx=canvas.getContext('2d');
  const hero=canvas.parentElement;
  function resize(){canvas.width=hero.offsetWidth;canvas.height=hero.offsetHeight;}
  resize();window.addEventListener('resize',resize);
  const COLORS=['rgba(192,57,43,','rgba(231,76,60,','rgba(255,255,255,','rgba(100,140,220,'];
  const NODE_COUNT=52,CONNECT_DIST=160;
  const MOUSE={x:-9999,y:-9999};
  hero.addEventListener('mousemove',e=>{const r=canvas.getBoundingClientRect();MOUSE.x=e.clientX-r.left;MOUSE.y=e.clientY-r.top;});
  hero.addEventListener('mouseleave',()=>{MOUSE.x=-9999;MOUSE.y=-9999;});
  function rand(a,b){return a+Math.random()*(b-a);}
  const nodes=Array.from({length:NODE_COUNT},()=>({x:rand(0,1),y:rand(0,1),vx:rand(-.00012,.00012),vy:rand(-.00012,.00012),r:rand(1.5,3.2),ci:Math.floor(rand(0,COLORS.length)),a:rand(.4,.95)}));
  function draw(){const W=canvas.width,H=canvas.height;ctx.clearRect(0,0,W,H);nodes.forEach(n=>{n.x+=n.vx;n.y+=n.vy;if(n.x<0)n.x=1;if(n.x>1)n.x=0;if(n.y<0)n.y=1;if(n.y>1)n.y=0;});for(let i=0;i<nodes.length;i++){for(let j=i+1;j<nodes.length;j++){const a=nodes[i],b=nodes[j];const ax=a.x*W,ay=a.y*H,bx=b.x*W,by=b.y*H;const dx=ax-bx,dy=ay-by,dist=Math.sqrt(dx*dx+dy*dy);if(dist<CONNECT_DIST){const alpha=(1-dist/CONNECT_DIST)*.28;ctx.beginPath();ctx.strokeStyle=`rgba(150,180,255,${alpha})`;ctx.lineWidth=.8;ctx.moveTo(ax,ay);ctx.lineTo(bx,by);ctx.stroke();}}const nx=nodes[i].x*W,ny=nodes[i].y*H;const mdx=nx-MOUSE.x,mdy=ny-MOUSE.y,mdist=Math.sqrt(mdx*mdx+mdy*mdy);if(mdist<130){const alpha=(1-mdist/130)*.55;ctx.beginPath();ctx.strokeStyle=`rgba(192,57,43,${alpha})`;ctx.lineWidth=1.1;ctx.moveTo(nx,ny);ctx.lineTo(MOUSE.x,MOUSE.y);ctx.stroke();}}nodes.forEach(n=>{const px=n.x*W,py=n.y*H;ctx.beginPath();ctx.arc(px,py,n.r,0,Math.PI*2);ctx.fillStyle=COLORS[n.ci]+n.a+')';ctx.fill();});requestAnimationFrame(draw);}
  draw();
})();

// ═══ PWA SERVICE WORKER REGISTRATION - MAIN PAGE ═══
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("sw.js")
        .then(registration => {
            console.log("Service Worker registered with scope:", registration.scope);
        })
        .catch(error => {
            console.log("Service Worker registration failed:", error);
        });
}
// ══════════════════════════════════════════════════════════

</script>
</body>
</html>