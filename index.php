<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminUrl = 'admin/login.php';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Vib Tools license usage, safety, device-change and support guide.">
    <title>Vib Tools [License]</title>
    <link rel="icon" href="https://vibtools.github.io/vibtools-brand-assets/logos/icon-512.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin/assets/css/admin-ui.css">
</head>
<body class="root-landing root-license-page">
<main class="root-license-shell">
    <header class="root-license-topbar">
        <a class="root-license-brand" href="https://vib.tools" target="_blank" rel="noopener noreferrer" aria-label="Visit Vib Tools website">
            <img src="https://vibtools.github.io/vibtools-brand-assets/logos/icon-512.png" alt="Vib Tools" referrerpolicy="no-referrer">
            <span><strong>Vib Tools</strong><small>License Center</small></span>
        </a>
        <a href="<?php echo $escape($adminUrl); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-lock"></i> Admin Login</a>
    </header>

    <section class="root-license-hero" aria-labelledby="license-heading">
        <div>
            <span class="root-license-eyebrow"><i class="bi bi-patch-check-fill"></i> Official license guide</span>
            <h1 id="license-heading">Use your software license safely</h1>
            <p>Your Vib Tools license gives access to the approved application and device limit. Keep it private, use it only in the official application, and contact us before changing ownership or devices.</p>
        </div>
        <div class="root-license-hero-badge"><i class="bi bi-key"></i><strong>Private access</strong><span>Protect your license key</span></div>
    </section>

    <section class="root-license-section" aria-labelledby="usage-heading">
        <div class="root-license-section-heading"><div><span>Getting started</span><h2 id="usage-heading">How to use your license</h2></div><i class="bi bi-arrow-right-circle"></i></div>
        <ol class="root-license-step-grid">
            <li><span>1</span><div><strong>Purchase officially</strong><p>Keep your order ID, purchase email and invoice for ownership verification.</p></div></li>
            <li><span>2</span><div><strong>Activate in the app</strong><p>Enter the key only inside the official Vib Tools application's license screen.</p></div></li>
            <li><span>3</span><div><strong>Stay within your limit</strong><p>Use only the licensed user, application and permitted number of devices.</p></div></li>
        </ol>
    </section>

    <div class="root-license-policy-grid">
        <section class="root-license-section root-license-good" aria-labelledby="safe-heading">
            <div class="root-license-section-heading"><div><span>Recommended</span><h2 id="safe-heading">Keep it safe</h2></div><i class="bi bi-shield-check"></i></div>
            <ul class="root-license-list">
                <li><i class="bi bi-check-circle-fill"></i><span>Store the key privately and keep the application updated.</span></li>
                <li><i class="bi bi-check-circle-fill"></i><span>Use a separate valid license when another person or business needs access.</span></li>
                <li><i class="bi bi-check-circle-fill"></i><span>Send support only a masked key, order ID and registered email.</span></li>
            </ul>
        </section>

        <section class="root-license-section root-license-bad" aria-labelledby="misuse-heading">
            <div class="root-license-section-heading"><div><span>Prohibited</span><h2 id="misuse-heading">Avoid license misuse</h2></div><i class="bi bi-shield-exclamation"></i></div>
            <ul class="root-license-list">
                <li><i class="bi bi-x-circle-fill"></i><span>Do not share, publish, screenshot, sell or upload your license key.</span></li>
                <li><i class="bi bi-x-circle-fill"></i><span>Do not bypass activation, clone device identity or exceed device limits.</span></li>
                <li><i class="bi bi-x-circle-fill"></i><span>Do not place the key in public chat, source code, logs or repositories.</span></li>
            </ul>
        </section>
    </div>

    <section class="root-license-support" aria-labelledby="change-heading">
        <div class="root-license-support-icon"><i class="bi bi-arrow-repeat"></i></div>
        <div><span>Device, owner or license change</span><h2 id="change-heading">Contact Vib Tools before making a change</h2><p>For a new computer, device-limit reset, lost access, email or ownership change, contact support with your order ID, registered email, application name and reason. Never post the full key publicly.</p></div>
        <div class="root-license-support-actions">
            <a class="btn btn-primary" href="mailto:support@vib.tools?subject=License%20Support"><i class="bi bi-envelope"></i> Email Support</a>
            <a class="btn btn-outline-secondary" href="https://wa.me/8801795470603" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        </div>
    </section>

    <section class="root-license-section" aria-labelledby="contact-heading">
        <div class="root-license-section-heading"><div><span>Official channels</span><h2 id="contact-heading">Contact Vib Tools</h2></div><i class="bi bi-chat-square-dots"></i></div>
        <div class="root-license-contact-grid">
            <a href="https://vib.tools" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe2"></i><span><strong>Website</strong><small>vib.tools</small></span></a>
            <a href="mailto:hello@vib.tools"><i class="bi bi-envelope"></i><span><strong>General email</strong><small>hello@vib.tools</small></span></a>
            <a href="mailto:support@vib.tools"><i class="bi bi-headset"></i><span><strong>Support email</strong><small>support@vib.tools</small></span></a>
            <a href="https://wa.me/8801795470603" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i><span><strong>WhatsApp</strong><small>+880 1795-470603</small></span></a>
            <a href="https://github.com/vibtools" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i><span><strong>GitHub</strong><small>github.com/vibtools</small></span></a>
            <a href="https://gitlab.com/vibtools" target="_blank" rel="noopener noreferrer"><i class="bi bi-git"></i><span><strong>GitLab</strong><small>gitlab.com/vibtools</small></span></a>
        </div>
        <div class="root-license-socials" aria-label="Vib Tools social profiles">
            <a href="https://www.facebook.com/vib.tools" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i> Facebook</a>
            <a href="https://www.instagram.com/vib.tools/" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i> Instagram</a>
            <a href="https://www.tiktok.com/@vibtools" target="_blank" rel="noopener noreferrer"><i class="bi bi-tiktok"></i> TikTok</a>
            <a href="https://x.com/vibtools" target="_blank" rel="noopener noreferrer"><i class="bi bi-twitter"></i> X</a>
            <a href="https://www.reddit.com/user/VibTools/" target="_blank" rel="noopener noreferrer"><i class="bi bi-reddit"></i> Reddit</a>
        </div>
        <address class="root-license-address"><i class="bi bi-geo-alt"></i><span><strong>Office address</strong>5660 Kochakata, Nageswari, Kurigram, Rangpur, Bangladesh</span></address>
    </section>

    <footer class="root-license-footer"><span>© <?php echo date('Y'); ?> Vib Tools. Use each license according to its purchase terms.</span><a href="https://vib.tools" target="_blank" rel="noopener noreferrer">vib.tools <i class="bi bi-box-arrow-up-right"></i></a></footer>
</main>
</body>
</html>
