<?php
/**
 * IntuiFy — Landing page (software house: web, e-commerce, apps, business software, AI).
 *
 * Main language: Spanish (es). Italian and English share the same i18n keys.
 * The same file renders the page and handles the AJAX contact form.
 * STACK: PHP 8.2, Tailwind (CDN), vanilla JS, WebGL "Nova" background, PHPMailer.
 */

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/lead-mailer.php';
require_once __DIR__ . '/includes/client-ip.php';

const SITE_URL = 'https://intuify.net';
const SITE_LANGS = ['es', 'it', 'en'];

if (!isset($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}
// IP-based rate limiting (real visitor IP behind Cloudflare and Traefik, see includes/client-ip.php)
$rateLimitFile = sys_get_temp_dir() . '/intuify_ratelimit_' . md5(clientIp() ?: 'unknown') . '.json';

/**
 * ?lang= → session → browser language (it/en) → Spanish (main version).
 */
function detectLanguage(): string
{
    $lang = $_GET['lang'] ?? null;
    if (is_string($lang) && in_array($lang, SITE_LANGS, true)) {
        $_SESSION['lang'] = $lang;
        return $lang;
    }
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SITE_LANGS, true)) {
        return $_SESSION['lang'];
    }
    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if (str_starts_with($accept, 'it')) {
        return 'it';
    }
    if (str_starts_with($accept, 'en')) {
        return 'en';
    }
    return 'es';
}

function loadTranslations(string $lang): array
{
    return json_decode((string) file_get_contents(__DIR__ . "/i18n/{$lang}.json"), true) ?: [];
}

$currentLang = detectLanguage();
$t = loadTranslations($currentLang);
$isPreview = !empty($config['site_preview']);

// ============================================================================
// FORM HANDLING (AJAX)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $err = $t['contact']['errors'];
    $fail = function (string $key) use ($err): never {
        echo json_encode(['success' => false, 'error' => $err[$key] ?? $err['server']]);
        exit;
    };
    $field = fn(string $name, int $max) => mb_substr(trim((string) ($_POST[$name] ?? '')), 0, $max);

    // Anti-spam: honeypot (pretend success so bots learn nothing)
    if (!empty($_POST['website'])) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Anti-spam: the form must stay open for at least 3 seconds
    if (time() - (int) ($_POST['_timestamp'] ?? 0) < 3) {
        $fail('wait');
    }

    // Anti-spam: reCAPTCHA v3, only when keys are configured
    if (!empty($config['recaptcha_secret_key'])) {
        $verify = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => $config['recaptcha_secret_key'],
                'response' => (string) ($_POST['recaptcha_token'] ?? ''),
                'remoteip' => clientIp(),
            ]),
            'timeout' => 8,
        ]]));
        if ($verify !== false) {
            $r = json_decode($verify, true);
            if (empty($r['success']) || ($r['score'] ?? 0) < ($config['recaptcha_min_score'] ?? 0.5)) {
                error_log('CONTACT FORM [SPAM] reCAPTCHA failed: score=' . ($r['score'] ?? 'N/A'));
                $fail('security');
            }
        }
    }

    // Anti-spam: rate limits (session: 3 per 5 min · IP: 5 per hour)
    $now = time();
    $_SESSION['form_submissions'] = array_values(array_filter($_SESSION['form_submissions'], fn($ts) => $now - $ts < 300));
    if (count($_SESSION['form_submissions']) >= 3) {
        $fail('too_many');
    }
    $ipSubmissions = [];
    if (is_file($rateLimitFile)) {
        $ipSubmissions = array_values(array_filter(json_decode((string) file_get_contents($rateLimitFile), true) ?: [], fn($ts) => $now - $ts < 3600));
    }
    if (count($ipSubmissions) >= 5) {
        $fail('too_many_network');
    }

    // Fields
    $lead = [
        'name'    => $field('nombre', 120),
        'company' => $field('empresa', 160),
        'email'   => $field('email', 190),
        'phone'   => preg_replace('/[^0-9+()\s.-]/', '', $field('telefono', 40)),
        'message' => $field('mensaje', 5000),
    ];
    $type = $field('tipo', 20);

    if ($lead['name'] === '' || $lead['email'] === '' || $lead['message'] === '') {
        $fail('required');
    }
    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        $fail('invalid_email');
    }
    if (!in_array($type, LEAD_REQUEST_TYPES, true)) {
        $fail('invalid_type');
    }

    $_SESSION['form_submissions'][] = $now;
    $ipSubmissions[] = $now;
    file_put_contents($rateLimitFile, json_encode($ipSubmissions), LOCK_EX);

    // Type labels: Spanish in the CRM, Italian in the internal email, visitor's language in the reply
    $typeEs = loadTranslations('es')['contact']['form']['types'][$type];
    $typeIt = loadTranslations('it')['contact']['form']['types'][$type];
    $typeVisitor = $t['contact']['form']['types'][$type];

    // STEP 1: save the lead first (always)
    $leadSaved = false;
    try {
        require_once __DIR__ . '/admin/includes/supabase.php';
        $result = getSupabase()->insert('leads', [
            'name'    => $lead['name'],
            'email'   => $lead['email'],
            'company' => $lead['company'] !== '' ? $lead['company'] : null,
            'phone'   => $lead['phone'] !== '' ? $lead['phone'] : null,
            'message' => leadMetaLine($typeEs, $currentLang) . "\n\n" . $lead['message'],
            'source'  => 'landing_form',
            'status'  => 'new',
        ]);
        $leadSaved = $result !== null;
        error_log($leadSaved ? 'CONTACT FORM [LEAD] saved' : 'CONTACT FORM [LEAD] insert returned null — check Supabase key/RLS');
    } catch (\Throwable $e) {
        error_log('CONTACT FORM [LEAD] failed: ' . $e->getMessage());
    }

    // STEP 2: notify the team
    $emailSent = false;
    try {
        sendLeadNotification($config, $lead, $typeIt, $currentLang);
        $emailSent = true;
    } catch (\Throwable $e) {
        error_log('CONTACT FORM [EMAIL] failed: ' . $e->getMessage());
    }

    // Success only if the enquiry really reached us (saved or emailed)
    if (!$leadSaved && !$emailSent) {
        error_log('CONTACT FORM [RESULT] neither saved nor emailed');
        $fail('server');
    }

    // STEP 3: AI reply to the visitor, in their language — only for enquiries we really received
    try {
        sendLeadReply($config, $lead, $typeVisitor, $currentLang, $t);
    } catch (\Throwable $e) {
        error_log('CONTACT FORM [AI-REPLY] failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true]);
    exit;
}

// ============================================================================
// PAGE DATA
// ============================================================================

$projects = array_values(array_filter(require __DIR__ . '/includes/projects.php', fn($p) => $p['confirmed'] || $isPreview));
$requestTypes = LEAD_REQUEST_TYPES;
$preselectType = in_array($_GET['solicitud'] ?? '', $requestTypes, true) ? $_GET['solicitud'] : '';
$whatsappNumber = (string) ($config['whatsapp_number'] ?? '');
$whatsappUrl = $whatsappNumber !== '' ? 'https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode($t['contact']['whatsapp_message']) : '';
$contactEmail = 'info@intuify.net';
$canonical = SITE_URL . '/?lang=' . $currentLang;

$serviceIcons = [
    'web'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>',
    'app'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>',
    'software' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
    'ai'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>',
];
$serviceRequestType = ['web' => 'web', 'app' => 'app', 'software' => 'software', 'ai' => 'ai'];

// ============================================================================
// "NOVA" UI COMPONENTS — frosted-glass pieces over the WebGL scene
// ============================================================================

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Frosted eyebrow pill with an animated gradient ring. */
function novaEyebrow(string $label): string
{
    return '<span class="top-label">'
        . '<span class="nova-ring" aria-hidden="true"></span>'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>'
        . '<span>' . e($label) . '</span></span>';
}

/** Glass pill button with gradient ring and arrow badge. */
function novaBtnPrimary(string $href, string $label, string $extraClass = '', string $attrs = ''): string
{
    return '<a href="' . e($href) . '" class="' . e(trim('btn-primary ' . $extraClass)) . '" ' . $attrs . '>'
        . '<span class="nova-ring" aria-hidden="true"></span><span>' . e($label) . '</span>' . novaArrowBadge() . '</a>';
}

function novaArrowBadge(): string
{
    return '<span class="btn-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M17 7H7M17 7v10"/></svg></span>';
}

function novaBtnSecondary(string $href, string $label, string $attrs = ''): string
{
    return '<a href="' . e($href) . '" class="btn-secondary" ' . $attrs . '>' . e($label) . '</a>';
}

/** Cache-busting version stamp for local assets. */
function novaAsset(string $path): string
{
    $full = __DIR__ . '/' . ltrim($path, '/');
    return $path . '?v=' . (is_file($full) ? filemtime($full) : time());
}

/** Link to the contact form with a request type preselected (works without JS too). */
function contactLink(string $type): string
{
    return '?solicitud=' . rawurlencode($type) . '#contacto';
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($t['meta']['title']) ?></title>
    <meta name="description" content="<?= e($t['meta']['description']) ?>">
    <meta name="robots" content="<?= $isPreview ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php foreach (SITE_LANGS as $l): ?>
    <link rel="alternate" hreflang="<?= $l ?>" href="<?= SITE_URL ?>/?lang=<?= $l ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= SITE_URL ?>/">

    <!-- Link previews (WhatsApp, LinkedIn, X…) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="IntuiFy">
    <meta property="og:title" content="<?= e($t['meta']['title']) ?>">
    <meta property="og:description" content="<?= e($t['meta']['description']) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= SITE_URL ?>/assets/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($t['meta']['og_image_alt']) ?>">
    <meta property="og:locale" content="<?= e($t['meta']['og_locale']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($t['meta']['title']) ?>">
    <meta name="twitter:description" content="<?= e($t['meta']['description']) ?>">
    <meta name="twitter:image" content="<?= SITE_URL ?>/assets/og-image.jpg">
    <script type="application/ld+json">
    <?= json_encode([
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => 'IntuiFy',
        'legalName'   => 'IntuiFy Ventures, S.L.',
        'url'         => SITE_URL . '/',
        'logo'        => SITE_URL . '/assets/logo.png',
        'email'       => $contactEmail,
        'description' => $t['meta']['description'],
        'address'     => ['@type' => 'PostalAddress', 'addressRegion' => 'Illes Balears', 'addressCountry' => 'ES'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>

    <link rel="icon" href="logo/intuifylogo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- WebGL scene dependencies (ES modules, no build step) -->
    <script type="importmap">
    {
        "imports": {
            "three": "https://unpkg.com/three@0.169.0/build/three.module.js",
            "three/addons/": "https://unpkg.com/three@0.169.0/examples/jsm/",
            "lenis": "https://unpkg.com/lenis@1.3.19/dist/lenis.mjs"
        }
    }
    </script>
    <!-- Reveal animations only when JS + IntersectionObserver are available: content is never hidden otherwise -->
    <script>
        if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('js-reveal');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { display: ['Space Grotesk', 'sans-serif'], body: ['Inter', 'sans-serif'] },
                    colors: { accent: '#6366f1', surface: '#0d0d14', 'surface-light': '#13131d' }
                }
            }
        }
    </script>
    <?php if (!empty($config['recaptcha_site_key'])): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= e($config['recaptcha_site_key']) ?>"></script>
    <?php endif; ?>
    <style>
        *, *::before, *::after { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: 'Space Grotesk', sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #06060a; }
        ::-webkit-scrollbar-thumb { background: #2a2a3d; border-radius: 3px; }
        .grid-pattern {
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .glow-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.035), rgba(255,255,255,0.012));
            border: 1px solid rgba(255,255,255,0.07);
            transition: border-color 0.4s ease, box-shadow 0.4s ease, transform 0.4s ease;
        }
        .glow-card:hover { border-color: rgba(99,102,241,0.3); box-shadow: 0 0 40px -10px rgba(99,102,241,0.15); }
        /* Reveal: hidden only when the head script enabled it (never without JS) */
        .js-reveal .reveal-element { opacity: 0; transform: translateY(18px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .js-reveal .reveal-element.visible { opacity: 1; transform: none; }
        .gradient-text {
            background: linear-gradient(135deg, #818cf8, #6366f1, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .nav-link-active { background: rgba(255,255,255,0.08) !important; color: #fff !important; }
        .form-input {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            color: #f0f0f5;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-input:focus { border-color: rgba(129,140,248,0.7); box-shadow: 0 0 0 3px rgba(99,102,241,0.18); outline: none; }
        .form-input::placeholder { color: #6b6b80; }
        select.form-input option { background: #13131d; color: #f0f0f5; }
        .grecaptcha-badge { visibility: hidden !important; }
    </style>
    <!-- Nova layer + site components: must come last -->
    <link rel="stylesheet" href="<?= novaAsset('assets/css/nova.css') ?>">
    <link rel="stylesheet" href="<?= novaAsset('assets/css/site.css') ?>">
</head>
<body class="text-slate-300 font-body antialiased overflow-x-hidden">
    <canvas id="nova-canvas" aria-hidden="true"></canvas>
    <div id="nova-fallback" aria-hidden="true"></div>
    <div id="nova-glow" aria-hidden="true"></div>

    <?php if ($isPreview): ?>
        <div class="preview-banner" role="status"><?= e($t['preview']['banner']) ?></div>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <!-- ============================================================
             1. HERO
             ============================================================ -->
        <section id="inicio" data-nova="start:0.00" class="relative min-h-[92vh] flex items-center justify-center">
            <div class="absolute inset-0 grid-pattern opacity-25 pointer-events-none"></div>
            <div class="nova-scrim nova-scrim-hero relative max-w-4xl mx-auto px-6 text-center z-10 pt-32 pb-16">
                <div class="flex items-center justify-center mb-7">
                    <?= novaEyebrow($t['hero']['eyebrow']) ?>
                </div>
                <h1 class="font-display text-[2.15rem] sm:text-5xl md:text-6xl font-bold text-white tracking-tight leading-[1.1] mb-6">
                    <?= e($t['hero']['title_line1']) ?><br>
                    <span class="gradient-text"><?= e($t['hero']['title_line2']) ?></span>
                </h1>
                <p class="text-base md:text-xl text-slate-300 max-w-2xl mx-auto leading-relaxed mb-9">
                    <?= e($t['hero']['subtitle']) ?>
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-10">
                    <?= novaBtnPrimary('#contacto', $t['hero']['cta_primary']) ?>
                    <?= novaBtnSecondary('#proyectos', $t['hero']['cta_secondary']) ?>
                </div>
                <ul class="capabilities" aria-label="<?= e($t['nav']['services']) ?>">
                    <?php foreach ($t['hero']['capabilities'] as $cap): ?>
                        <li><?= e($cap) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- ============================================================
             2. SERVICIOS
             ============================================================ -->
        <section id="servicios" data-nova="start:0.15" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-6xl mx-auto px-6">
                <div class="section-head">
                    <h2 class="cascade font-display"><?= e($t['services']['title']) ?></h2>
                    <p class="reveal-element"><?= e($t['services']['intro']) ?></p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <?php foreach ($t['services']['items'] as $srv): ?>
                        <article id="servicio-<?= e($srv['id']) ?>" class="reveal-element glow-card service-card">
                            <div class="service-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><?= $serviceIcons[$srv['id']] ?? '' ?></svg>
                            </div>
                            <h3 class="font-display"><?= e($srv['title']) ?></h3>
                            <p class="service-desc"><?= e($srv['description']) ?></p>
                            <ul class="check-list">
                                <?php foreach ($srv['features'] as $feat): ?>
                                    <li><?= e($feat) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="text-link" href="<?= e(contactLink($serviceRequestType[$srv['id']] ?? 'other')) ?>" data-request-type="<?= e($serviceRequestType[$srv['id']] ?? 'other') ?>">
                                <?= e($t['services']['cta']) ?> →
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ============================================================
             3. PROYECTOS
             ============================================================ -->
        <section id="proyectos" data-nova="start:0.30" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-6xl mx-auto px-6">
                <div class="section-head">
                    <h2 class="cascade font-display"><?= e($t['projects']['title']) ?></h2>
                    <p class="reveal-element"><?= e($t['projects']['subtitle']) ?></p>
                </div>

                <div class="flex flex-col gap-8">
                    <?php foreach ($projects as $i => $p):
                        $pt = $p['text'][$currentLang] ?? $p['text']['es'];
                        $hasPhones = (bool) array_filter($p['media'], fn($m) => $m['type'] === 'phone');
                        $hasWeb = (bool) array_filter($p['media'], fn($m) => $m['type'] === 'web');
                    ?>
                        <article id="proyecto-<?= e($p['id']) ?>" class="reveal-element glow-card project-card <?= $i % 2 ? 'is-flipped' : '' ?> <?= $p['confirmed'] ? '' : 'is-pending' ?>">
                            <div class="project-media <?= $hasPhones ? 'has-phones' : '' ?> <?= $hasPhones && !$hasWeb ? 'only-phones' : '' ?>">
                                <?php foreach ($p['media'] as $mi => $m): ?>
                                    <?php if ($m['type'] === 'phone'): ?>
                                        <figure class="shot-phone"><img src="<?= e(novaAsset($m['src'])) ?>" alt="<?= e($pt['alt']) ?>" loading="lazy" width="600" height="1299"></figure>
                                    <?php else: ?>
                                        <figure class="shot-web">
                                            <div class="shot-web-bar" aria-hidden="true"><span></span><span></span><span></span></div>
                                            <img src="<?= e(novaAsset($m['src'])) ?>" alt="<?= e($pt['alt']) ?>" loading="lazy" width="1200" height="750">
                                        </figure>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="project-body">
                                <div class="badges">
                                    <span class="badge badge-<?= e($p['kind']) ?>"><?= e($t['projects']['kind'][$p['kind']]) ?></span>
                                    <span class="badge badge-status badge-<?= e($p['status']) ?>"><span class="dot" aria-hidden="true"></span><?= e($t['projects']['status'][$p['status']]) ?></span>
                                    <?php if (!$p['confirmed']): ?>
                                        <span class="badge badge-pending"><?= e($t['projects']['pending']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-display"><?= e($p['name']) ?></h3>
                                <p class="project-tag"><?= e($pt['tag']) ?></p>
                                <dl class="project-story">
                                    <div><dt><?= e($t['projects']['need']) ?></dt><dd><?= e($pt['need']) ?></dd></div>
                                    <div><dt><?= e($t['projects']['solution']) ?></dt><dd><?= e($pt['solution']) ?></dd></div>
                                </dl>
                                <p class="features-label"><?= e($t['projects']['features']) ?></p>
                                <ul class="check-list check-list-2col">
                                    <?php foreach ($pt['features'] as $feat): ?>
                                        <li><?= e($feat) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!empty($p['links'])): ?>
                                    <div class="project-links">
                                        <?php foreach (['web', 'app_store', 'google_play'] as $lk): ?>
                                            <?php if (!empty($p['links'][$lk])): ?>
                                                <a href="<?= e($p['links'][$lk]) ?>" target="_blank" rel="noopener noreferrer" class="link-pill link-<?= $lk ?>">
                                                    <?= e($t['projects']['links'][$lk]) ?> <span aria-hidden="true">↗</span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Own products: venture builder / investors (secondary) -->
                <aside class="reveal-element own-products">
                    <h3 class="font-display"><?= e($t['projects']['own_products']['title']) ?></h3>
                    <p><?= e($t['projects']['own_products']['text']) ?></p>
                    <p class="muted"><?= e($t['projects']['own_products']['investors']) ?></p>
                    <a class="text-link" href="<?= e(contactLink('other')) ?>" data-request-type="other"><?= e($t['projects']['own_products']['cta']) ?> →</a>
                </aside>
            </div>
        </section>

        <!-- ============================================================
             4. MÉTODO
             ============================================================ -->
        <section id="metodo" data-nova="start:0.45" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-6xl mx-auto px-6">
                <div class="section-head">
                    <h2 class="cascade font-display"><?= e($t['method']['title']) ?></h2>
                </div>
                <ol class="steps">
                    <?php foreach ($t['method']['steps'] as $i => $step): ?>
                        <li class="reveal-element glow-card step">
                            <span class="step-num font-display"><?= $i + 1 ?></span>
                            <h3 class="font-display"><?= e($step['title']) ?></h3>
                            <p><?= e($step['text']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="reveal-element section-note"><?= e($t['method']['note']) ?></p>
            </div>
        </section>

        <!-- ============================================================
             5. POR QUÉ INTUIFY
             ============================================================ -->
        <section id="ventajas" data-nova="start:0.57" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-6xl mx-auto px-6">
                <div class="section-head">
                    <h2 class="cascade font-display"><?= e($t['reasons']['title']) ?></h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($t['reasons']['items'] as $r): ?>
                        <div class="reveal-element glow-card reason">
                            <h3 class="font-display"><?= e($r['title']) ?></h3>
                            <p><?= e($r['text']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ============================================================
             6. AGENCIAS
             ============================================================ -->
        <section id="agencias" data-nova="start:0.68" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-5xl mx-auto px-6">
                <div class="reveal-element glow-card agency-card">
                    <div>
                        <h2 class="cascade font-display"><?= e($t['agencies']['title']) ?></h2>
                        <p class="lead"><?= e($t['agencies']['text']) ?></p>
                        <p class="highlight"><?= e($t['agencies']['white_label']) ?></p>
                    </div>
                    <div>
                        <ul class="check-list">
                            <?php foreach ($t['agencies']['points'] as $pt): ?>
                                <li><?= e($pt) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-7">
                            <?= novaBtnPrimary(contactLink('agency'), $t['agencies']['cta'], '', 'data-request-type="agency"') ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             7. EMPRESA
             ============================================================ -->
        <section id="empresa" data-nova="start:0.84" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-5xl mx-auto px-6">
                <div class="company-grid">
                    <div class="reveal-element">
                        <h2 class="cascade font-display"><?= e($t['company']['title']) ?></h2>
                        <p class="lead"><?= e($t['company']['text']) ?></p>
                    </div>
                    <div class="reveal-element glow-card company-card">
                        <p class="features-label"><?= e($t['company']['data_title']) ?></p>
                        <dl>
                            <div><dt><?= e($t['company']['legal_name_label']) ?></dt><dd>IntuiFy Ventures, S.L.</dd></div>
                            <div><dt><?= e($t['footer']['vat']) ?></dt><dd><?= e($config['company_vat'] ?? '') ?></dd></div>
                            <div><dt><?= e($t['company']['based_in_label']) ?></dt><dd><?= e($t['contact']['location']) ?></dd></div>
                            <div><dt><?= e($t['contact']['email_label']) ?></dt><dd><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             8. CONTACTO
             ============================================================ -->
        <section id="contacto" data-nova="start:0.96" class="nova-scrim relative py-24 md:py-32">
            <div class="max-w-6xl mx-auto px-6">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 lg:gap-14">
                    <div class="lg:col-span-2">
                        <h2 class="cascade font-display text-3xl md:text-4xl font-bold text-white tracking-tight mb-5"><?= e($t['contact']['title']) ?></h2>
                        <p class="text-slate-300 leading-relaxed mb-10"><?= e($t['contact']['subtitle']) ?></p>

                        <p class="features-label"><?= e($t['contact']['direct_title']) ?></p>
                        <div class="direct-contacts">
                            <?php if ($whatsappUrl !== ''): ?>
                                <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="direct whatsapp">
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.05 21.5h-.01a9.4 9.4 0 01-4.8-1.31l-.34-.2-3.57.93.95-3.48-.22-.36a9.38 9.38 0 01-1.44-5.02c0-5.19 4.23-9.42 9.43-9.42a9.4 9.4 0 016.67 2.76 9.37 9.37 0 012.76 6.67c0 5.2-4.23 9.43-9.43 9.43zm8.02-17.45A11.27 11.27 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.67L.62 23.25l5.63-1.48a11.3 11.3 0 005.4 1.38h.01c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01z"/></svg>
                                    <span><?= e($t['contact']['whatsapp']) ?></span>
                                </a>
                            <?php elseif ($isPreview): ?>
                                <span class="direct whatsapp is-pending" title="WHATSAPP_NUMBER"><span><?= e($t['contact']['whatsapp']) ?></span><span class="badge badge-pending"><?= e($t['projects']['pending']) ?></span></span>
                            <?php endif; ?>
                            <a href="mailto:<?= e($contactEmail) ?>" class="direct">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                <span><?= e($contactEmail) ?></span>
                            </a>
                            <span class="direct is-static">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                <span><?= e($t['contact']['location']) ?></span>
                            </span>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <form id="contact-form" class="glow-card contact-form" novalidate>
                            <input type="hidden" name="ajax_submit" value="1">
                            <input type="hidden" name="_timestamp" value="<?= time() ?>">
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">
                            <div class="hp-field" aria-hidden="true">
                                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label for="f-nombre" class="form-label"><?= e($t['contact']['form']['name']) ?> <span class="req" aria-hidden="true">*</span></label>
                                    <input id="f-nombre" type="text" name="nombre" required maxlength="120" autocomplete="name" class="form-input" placeholder="<?= e($t['contact']['form']['name_placeholder']) ?>">
                                </div>
                                <div>
                                    <label for="f-empresa" class="form-label"><?= e($t['contact']['form']['company']) ?> <span class="opt">(<?= e($t['contact']['form']['optional']) ?>)</span></label>
                                    <input id="f-empresa" type="text" name="empresa" maxlength="160" autocomplete="organization" class="form-input" placeholder="<?= e($t['contact']['form']['company_placeholder']) ?>">
                                </div>
                                <div>
                                    <label for="f-email" class="form-label"><?= e($t['contact']['form']['email']) ?> <span class="req" aria-hidden="true">*</span></label>
                                    <input id="f-email" type="email" name="email" required maxlength="190" autocomplete="email" class="form-input" placeholder="<?= e($t['contact']['form']['email_placeholder']) ?>">
                                </div>
                                <div>
                                    <label for="f-telefono" class="form-label"><?= e($t['contact']['form']['phone']) ?> <span class="opt">(<?= e($t['contact']['form']['optional']) ?>)</span></label>
                                    <input id="f-telefono" type="tel" name="telefono" maxlength="40" autocomplete="tel" class="form-input" placeholder="<?= e($t['contact']['form']['phone_placeholder']) ?>">
                                </div>
                            </div>
                            <div>
                                <label for="f-tipo" class="form-label"><?= e($t['contact']['form']['type']) ?> <span class="req" aria-hidden="true">*</span></label>
                                <select id="f-tipo" name="tipo" required class="form-input">
                                    <option value="" <?= $preselectType === '' ? 'selected' : '' ?> disabled><?= e($t['contact']['form']['type_placeholder']) ?></option>
                                    <?php foreach ($requestTypes as $rt): ?>
                                        <option value="<?= e($rt) ?>" <?= $preselectType === $rt ? 'selected' : '' ?>><?= e($t['contact']['form']['types'][$rt]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="f-mensaje" class="form-label"><?= e($t['contact']['form']['message']) ?> <span class="req" aria-hidden="true">*</span></label>
                                <textarea id="f-mensaje" name="mensaje" required rows="5" maxlength="5000" class="form-input resize-y" placeholder="<?= e($t['contact']['form']['message_placeholder']) ?>"></textarea>
                            </div>
                            <p class="privacy-note">
                                <?= e($t['contact']['form']['privacy_note']) ?>
                                <a href="privacy.php?lang=<?= e($currentLang) ?>"><?= e($t['contact']['form']['privacy_link']) ?></a>.
                            </p>
                            <button type="submit" id="submit-btn" class="btn-primary btn-block">
                                <span class="nova-ring" aria-hidden="true"></span>
                                <span id="submit-text"><?= e($t['contact']['form']['submit']) ?></span>
                                <?= novaArrowBadge() ?>
                            </button>
                            <div id="form-response" class="form-response" role="status" aria-live="polite" hidden></div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php if ($whatsappUrl !== ''): ?>
        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-float" aria-label="<?= e($t['contact']['whatsapp']) ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.05 21.5h-.01a9.4 9.4 0 01-4.8-1.31l-.34-.2-3.57.93.95-3.48-.22-.36a9.38 9.38 0 01-1.44-5.02c0-5.19 4.23-9.42 9.43-9.42a9.4 9.4 0 016.67 2.76 9.37 9.37 0 012.76 6.67c0 5.2-4.23 9.43-9.43 9.43zm8.02-17.45A11.27 11.27 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.67L.62 23.25l5.63-1.48a11.3 11.3 0 005.4 1.38h.01c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01z"/></svg>
        </a>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ---- Reveal on scroll (only when enabled in <head>) ----
        if (document.documentElement.classList.contains('js-reveal')) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
            document.querySelectorAll('.reveal-element').forEach((el) => io.observe(el));
        }

        // ---- Mobile menu ----
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const setMenu = (open) => {
            if (!menuBtn || !mobileMenu) return;
            menuBtn.setAttribute('aria-expanded', String(open));
            mobileMenu.classList.toggle('is-open', open);
        };
        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', () => setMenu(menuBtn.getAttribute('aria-expanded') !== 'true'));
            mobileMenu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setMenu(false)));
            document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') setMenu(false); });
        }

        // ---- Active nav link ----
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
        const navObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    navLinks.forEach((link) => link.classList.toggle('nav-link-active', link.dataset.section === entry.target.id));
                }
            });
        }, { threshold: 0.2, rootMargin: '-80px 0px -55% 0px' });
        document.querySelectorAll('main section[id]').forEach((s) => navObserver.observe(s));

        // ---- "Ask for this" links: preselect the request type without reloading ----
        const typeSelect = document.getElementById('f-tipo');
        document.querySelectorAll('[data-request-type]').forEach((link) => {
            link.addEventListener('click', (ev) => {
                if (!typeSelect) return;
                ev.preventDefault();
                typeSelect.value = link.dataset.requestType;
                // Reuse the page's own #contacto anchor so the scroll goes through Lenis (nova.js)
                const anchor = document.querySelector('a[href="#contacto"]');
                if (anchor) anchor.click(); else document.getElementById('contacto').scrollIntoView();
                setTimeout(() => document.getElementById('f-nombre')?.focus({ preventScroll: true }), 1500);
            });
        });

        // ---- Contact form (AJAX) ----
        const form = document.getElementById('contact-form');
        const submitBtn = document.getElementById('submit-btn');
        const submitText = document.getElementById('submit-text');
        const response = document.getElementById('form-response');
        const texts = <?= json_encode([
            'submit'   => $t['contact']['form']['submit'],
            'sending'  => $t['contact']['form']['sending'],
            'success'  => $t['contact']['form']['success'],
            'error'    => $t['contact']['form']['error'],
            'required' => $t['contact']['errors']['required'],
            'email'    => $t['contact']['errors']['invalid_email'],
            'type'     => $t['contact']['errors']['invalid_type'],
        ], JSON_UNESCAPED_UNICODE) ?>;

        const show = (ok, msg) => {
            response.hidden = false;
            response.textContent = msg;
            response.className = 'form-response ' + (ok ? 'is-success' : 'is-error');
        };

        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                // Client-side check with localized messages (the server checks again)
                const fd = new FormData(form);
                if (!fd.get('nombre').trim() || !fd.get('email').trim() || !fd.get('mensaje').trim()) return show(false, texts.required);
                if (!form.querySelector('#f-email').checkValidity()) return show(false, texts.email);
                if (!fd.get('tipo')) return show(false, texts.type);

                submitBtn.disabled = true;
                submitText.textContent = texts.sending;
                <?php if (!empty($config['recaptcha_site_key'])): ?>
                try {
                    const token = await grecaptcha.execute(<?= json_encode($config['recaptcha_site_key']) ?>, { action: 'submit' });
                    fd.set('recaptcha_token', token);
                } catch (err) { console.error('reCAPTCHA error', err); }
                <?php endif; ?>
                try {
                    const res = await fetch(window.location.pathname + '?lang=<?= e($currentLang) ?>', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success === true) {
                        show(true, texts.success);
                        form.reset();
                    } else {
                        show(false, data.error || texts.error);
                    }
                } catch (err) {
                    show(false, texts.error);
                }
                submitBtn.disabled = false;
                submitText.textContent = texts.submit;
            });
        }
    });
    </script>
    <!-- WebGL scroll experience -->
    <script type="module" src="<?= novaAsset('assets/js/nova.js') ?>"></script>
</body>
</html>
