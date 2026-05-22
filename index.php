<?php
/**
 * IntuiFy - Premium Landing Page (Silicon Valley & Apple Style)
 * 
 * DESIGN archetypes (derived from high-end-visual-design skill):
 * - Vibe Archetype: Soft Structuralism (Silver-grey backgrounds, massive bold typography, airy floating components, soft diffused shadows)
 * - Layout Archetype: Asymmetrical Bento CSS Grid & Editorial Centered Hero
 * - Component Architecture: Double-Bezel nested cards (Doppelrand), Button-in-Button CTAs
 * - Motion Choreography: Fluid nav triggers, cubic-bezier transitions, IntersectionObserver fade-ups
 */

declare(strict_types=1);

session_start();

// Load configuration
$config = require __DIR__ . '/config.php';

// Rate limiting for form (session + IP based)
if (!isset($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}

// IP-based rate limiting file
$rateLimitFile = sys_get_temp_dir() . '/intuify_ratelimit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';

// Detect language
function detectLanguage(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'it', 'en'])) {
        $_SESSION['lang'] = $_GET['lang'];
        return $_GET['lang'];
    }

    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }

    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    $lang = substr($acceptLang, 0, 2);

    if (str_starts_with($acceptLang, 'es'))
        return 'es';
    if (str_starts_with($acceptLang, 'it'))
        return 'it';
    return 'en';
}

$currentLang = detectLanguage();

// Load translations
$i18nFile = __DIR__ . "/i18n/{$currentLang}.json";
if (!file_exists($i18nFile)) {
    $i18nFile = __DIR__ . "/i18n/en.json";
}
$t = json_decode(file_get_contents($i18nFile), true);

// ============================================================================
// FORM HANDLING (AJAX)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');

    // Anti-spam: Honeypot check
    if (!empty($_POST['website'])) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Anti-spam: Time trap (form must take > 3 seconds)
    $formTime = intval($_POST['_timestamp'] ?? 0);
    if (time() - $formTime < 3) {
        echo json_encode(['success' => false, 'error' => 'Please wait a moment before submitting.']);
        exit;
    }

    // Anti-spam: reCAPTCHA v3 verification
    $recaptchaToken = $_POST['recaptcha_token'] ?? '';
    if (!empty($config['recaptcha_secret_key']) && $config['recaptcha_secret_key'] !== 'YOUR_RECAPTCHA_SECRET_KEY') {
        $recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptchaData = [
            'secret' => $config['recaptcha_secret_key'],
            'response' => $recaptchaToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $recaptchaOptions = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($recaptchaData)
            ]
        ];

        $recaptchaContext = stream_context_create($recaptchaOptions);
        $recaptchaResult = @file_get_contents($recaptchaUrl, false, $recaptchaContext);

        if ($recaptchaResult !== false) {
            $recaptchaJson = json_decode($recaptchaResult, true);
            $minScore = $config['recaptcha_min_score'] ?? 0.5;

            if (!$recaptchaJson['success'] || ($recaptchaJson['score'] ?? 0) < $minScore) {
                error_log("reCAPTCHA failed: score=" . ($recaptchaJson['score'] ?? 'N/A'));
                echo json_encode(['success' => false, 'error' => 'Security verification failed. Please try again.']);
                exit;
            }
        }
    }

    // Anti-spam: Rate limiting (session-based)
    $now = time();
    $_SESSION['form_submissions'] = array_filter(
        $_SESSION['form_submissions'],
        fn($ts) => $now - $ts < 300
    );

    if (count($_SESSION['form_submissions']) >= 3) {
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait.']);
        exit;
    }

    // Anti-spam: Rate limiting (IP-based)
    $ipSubmissions = [];
    if (file_exists($rateLimitFile)) {
        $ipSubmissions = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        $ipSubmissions = array_filter($ipSubmissions, fn($ts) => $now - $ts < 3600);
    }

    if (count($ipSubmissions) >= 5) {
        echo json_encode(['success' => false, 'error' => 'Too many submissions from your network. Please try later.']);
        exit;
    }

    // Validate required fields
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if (empty($nombre) || empty($empresa) || empty($email) || empty($mensaje)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
        exit;
    }

    // Record submission
    $_SESSION['form_submissions'][] = $now;
    $ipSubmissions[] = $now;
    file_put_contents($rateLimitFile, json_encode($ipSubmissions));

    // Send to n8n webhook
    $webhookUrl = $config['webhook_url'];

    $payload = json_encode([
        'nombre_completo' => $nombre,
        'empresa' => $empresa,
        'email' => $email,
        'mensaje' => $mensaje,
        'lang' => $currentLang,
        'timestamp' => date('c')
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
    }
    exit;
}

// Inline custom premium SVG paths for Services
$serviceIcons = [
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 .364l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'
];

$benefitIcons = [
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>'
];
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['meta']['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($t['meta']['description']) ?>">

    <!-- Premium Google Fonts: Plus Jakarta Sans for body, Outfit for geometric display titles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google reCAPTCHA v3 (if configured) -->
    <?php if (!empty($config['recaptcha_site_key']) && $config['recaptcha_site_key'] !== 'YOUR_RECAPTCHA_SITE_KEY'): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($config['recaptcha_site_key']) ?>"></script>
    <?php endif; ?>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0F172A',
                        accent: '#6366F1',     /* Premium Indigo */
                        secondary: '#8B5CF6',  /* Premium Purple */
                        'slate-bg': '#FAFBFD', /* Soft Structuralism base */
                        'bezel-outer': 'rgba(15, 23, 42, 0.04)',
                        'bezel-border': 'rgba(15, 23, 42, 0.08)'
                    },
                    fontFamily: {
                        display: ['Outfit', 'sans-serif'],
                        sans: ['Plus Jakarta Sans', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        /* Smooth scroll offset for floating island header */
        html {
            scroll-padding-top: 6.5rem;
            background-color: #FAFBFD;
        }

        /* Ambient light diffused grid */
        .bg-grid-premium {
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(99, 102, 241, 0.06) 0%, transparent 60%),
                radial-gradient(circle at 10% 70%, rgba(139, 92, 246, 0.04) 0%, transparent 50%),
                linear-gradient(rgba(15, 23, 42, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 64px 64px, 64px 64px;
        }

        /* GPU-safe transition preset using premium cubic-bezier */
        .premium-transition {
            transition: all 700ms cubic-bezier(0.32, 0.72, 0, 1);
        }

        /* Double-Bezel card inner core offset shadows */
        .bezel-inner-core {
            box-shadow: 
                inset 0 1px 1px rgba(255, 255, 255, 0.9),
                0 4px 20px -2px rgba(15, 23, 42, 0.02);
        }

        /* Hover animation triggers */
        .hover-lift {
            transition: transform 700ms cubic-bezier(0.32, 0.72, 0, 1), box-shadow 700ms cubic-bezier(0.32, 0.72, 0, 1), border-color 700ms cubic-bezier(0.32, 0.72, 0, 1);
        }
        .hover-lift:hover {
            transform: translateY(-4px) scale(1.005);
            box-shadow: 0 30px 60px -15px rgba(15, 23, 42, 0.06);
            border-color: rgba(99, 102, 241, 0.15);
        }

        /* Dynamic active link classes */
        .nav-link.active {
            background-color: #FFFFFF;
            color: #0F172A !important;
            box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
        }

        /* IntersectionObserver Scroll Entry State */
        .reveal-element {
            opacity: 0;
            transform: translateY(24px) scale(0.985);
            filter: blur(4px);
            transition: opacity 1000ms cubic-bezier(0.32, 0.72, 0, 1), 
                        transform 1000ms cubic-bezier(0.32, 0.72, 0, 1), 
                        filter 1000ms cubic-bezier(0.32, 0.72, 0, 1);
        }
        .reveal-element.revealed {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
    </style>
</head>

<body class="bg-slate-bg text-slate-600 font-sans antialiased overflow-x-hidden">

    <!-- Premium Sticky Floating Island Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="w-full">
        <!-- ================================================================
             HERO SECTION: Editorial Centered Archetype
             ================================================================ -->
        <section id="inicio" class="relative min-h-[92dvh] flex flex-col justify-center overflow-hidden bg-grid-premium pt-28 pb-16 md:pt-36">
            <!-- Background mesh orbs -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-accent/5 blur-[120px] animate-pulse"></div>
                <div class="absolute bottom-[20%] right-[-10%] w-[600px] h-[600px] rounded-full bg-secondary/3 blur-[140px]"></div>
            </div>

            <div class="relative max-w-5xl mx-auto px-6 text-center z-10">
                <!-- Eyebrow Badge Pill -->
                <div class="reveal-element inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-100 border border-slate-200/60 rounded-full mb-8 shadow-sm">
                    <span class="w-1.5 h-1.5 bg-accent rounded-full animate-ping"></span>
                    <span class="text-[10px] uppercase tracking-[0.2em] font-bold text-slate-800">
                        <?= htmlspecialchars($t['hero']['badge']) ?>
                    </span>
                </div>

                <!-- Main Display Heading -->
                <h1 class="reveal-element font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-slate-900 tracking-tight leading-[1.08] mb-8">
                    <?= htmlspecialchars($t['hero']['title']) ?>
                </h1>

                <!-- Professional Subheading -->
                <p class="reveal-element max-w-3xl mx-auto text-base md:text-lg text-slate-500 leading-relaxed mb-12">
                    <?= htmlspecialchars($t['hero']['subtitle']) ?>
                </p>

                <!-- Button-in-Button Primary and Secondary CTAs -->
                <div class="reveal-element flex flex-col sm:flex-row items-center justify-center gap-4 mb-20">
                    <a href="#contacto"
                        class="group inline-flex items-center justify-between gap-6 pl-6 pr-2.5 py-2.5 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-full premium-transition shadow-xl shadow-slate-900/10 w-full sm:w-auto">
                        <span><?= htmlspecialchars($t['hero']['cta_primary']) ?></span>
                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center premium-transition group-hover:translate-x-0.5 group-hover:-translate-y-[0.5px]">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </span>
                    </a>
                    <a href="#servicio"
                        class="inline-flex items-center justify-center px-6 py-3.5 text-xs font-bold text-slate-700 bg-white border border-slate-200/60 rounded-full hover:bg-slate-50 hover:border-slate-300 active:scale-[0.98] premium-transition w-full sm:w-auto shadow-sm">
                        <?= htmlspecialchars($t['hero']['cta_secondary']) ?>
                    </a>
                </div>
            </div>

            <!-- Massive Double-Bezel Dashboard Asset Enclosure -->
            <div class="reveal-element max-w-6xl mx-auto px-6 w-full z-10">
                <!-- Outer Bezel Shell -->
                <div class="relative w-full p-2.5 md:p-3.5 rounded-[2rem] md:rounded-[3rem] bg-bezel-outer border border-bezel-border shadow-2xl shadow-slate-200/50">
                    <!-- Inner Core -->
                    <div class="relative overflow-hidden rounded-[calc(2rem-0.625rem)] md:rounded-[calc(3rem-0.875rem)] bg-white border border-slate-200/40 shadow-inner bezel-inner-core">
                        <!-- Top browser toolbar bar -->
                        <div class="h-8 md:h-11 border-b border-slate-100 bg-slate-50/50 flex items-center px-4 md:px-5 gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                            <span class="mx-auto w-32 md:w-48 h-3.5 md:h-4.5 bg-slate-200/40 border border-slate-200/30 rounded-full"></span>
                        </div>
                        <img src="assets/hero_dashboard.png" alt="IntuiFy Premium Dashboard" class="w-full h-auto object-cover transform scale-101 hover:scale-100 duration-1000" loading="eager" width="1152" height="648">
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================
             SERVICES / FEATURES SECTION: The Asymmetrical Bento Grid Layout
             ================================================================ -->
        <section id="servicio" class="relative py-32 md:py-40 bg-white border-y border-slate-100">
            <div class="max-w-7xl mx-auto px-6">
                <!-- Eyebrow + Title -->
                <div class="text-center max-w-3xl mx-auto mb-20 md:mb-24">
                    <div class="reveal-element inline-flex items-center gap-1.5 px-3 py-1 bg-slate-50 border border-slate-200/40 rounded-full mb-4">
                        <span class="text-[9px] uppercase tracking-[0.25em] font-bold text-slate-400">Capabilities</span>
                    </div>
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                        <?= htmlspecialchars($t['services']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-500 leading-relaxed text-base md:text-lg">
                        <?= htmlspecialchars($t['services']['subtitle']) ?>
                    </p>
                </div>

                <!-- Asymmetrical Bento CSS Grid -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    
                    <!-- Card 0: Autonomous Workflows - Spans 8 Columns (Left Asymmetrical Highlight) -->
                    <?php if (isset($t['services']['items'][0])): 
                        $srv = $t['services']['items'][0]; ?>
                        <article class="reveal-element md:col-span-8 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border flex flex-col justify-between">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-10 h-full flex flex-col justify-between gap-8">
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                                    <div class="lg:col-span-6 flex flex-col gap-5">
                                        <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                            <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <?= $serviceIcons[0] ?>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                                <?= htmlspecialchars($srv['description']) ?>
                                            </span>
                                            <h3 class="font-display text-2xl font-bold text-slate-950">
                                                <?= htmlspecialchars($srv['title']) ?>
                                            </h3>
                                        </div>
                                        <ul class="flex flex-col gap-2.5 mt-2">
                                            <?php foreach ($srv['features'] as $feat): ?>
                                                <li class="flex items-start gap-3 text-sm text-slate-500">
                                                    <span class="mt-1 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                                    <span><?= htmlspecialchars($feat) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <!-- Embedded visual diagram inside card 0 -->
                                    <div class="lg:col-span-6 p-2 rounded-3xl bg-slate-50/50 border border-slate-200/30 shadow-inner">
                                        <img src="assets/bento_integration.png" alt="Workflow Orchestration Diagram" class="w-full h-auto rounded-2xl object-cover shadow-sm" loading="lazy" width="400" height="280">
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <!-- Card 1: Cognitive AI Agents - Spans 4 Columns (Right Asymmetrical) -->
                    <?php if (isset($t['services']['items'][1])): 
                        $srv = $t['services']['items'][1]; ?>
                        <article class="reveal-element md:col-span-4 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border flex flex-col justify-between">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8 h-full flex flex-col justify-between gap-6">
                                <div class="flex flex-col gap-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?= $serviceIcons[1] ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                            <?= htmlspecialchars($srv['description']) ?>
                                        </span>
                                        <h3 class="font-display text-xl font-bold text-slate-950">
                                            <?= htmlspecialchars($srv['title']) ?>
                                        </h3>
                                    </div>
                                    <ul class="flex flex-col gap-2 mt-2">
                                        <?php foreach ($srv['features'] as $feat): ?>
                                            <li class="flex items-start gap-3 text-sm text-slate-500">
                                                <span class="mt-1 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                                <span><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <!-- Card 2: Predictive Analytics - Spans 4 Columns -->
                    <?php if (isset($t['services']['items'][2])): 
                        $srv = $t['services']['items'][2]; ?>
                        <article class="reveal-element md:col-span-4 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8 h-full flex flex-col justify-between gap-6">
                                <div class="flex flex-col gap-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?= $serviceIcons[2] ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                            <?= htmlspecialchars($srv['description']) ?>
                                        </span>
                                        <h3 class="font-display text-xl font-bold text-slate-950">
                                            <?= htmlspecialchars($srv['title']) ?>
                                        </h3>
                                    </div>
                                    <ul class="flex flex-col gap-2">
                                        <?php foreach ($srv['features'] as $feat): ?>
                                            <li class="flex items-start gap-3 text-sm text-slate-500">
                                                <span class="mt-1.5 w-1 h-1.5 bg-accent rounded-full flex-shrink-0"></span>
                                                <span><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <!-- Card 3: Smart Data Pipelines - Spans 4 Columns -->
                    <?php if (isset($t['services']['items'][3])): 
                        $srv = $t['services']['items'][3]; ?>
                        <article class="reveal-element md:col-span-4 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8 h-full flex flex-col justify-between gap-6">
                                <div class="flex flex-col gap-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?= $serviceIcons[3] ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                            <?= htmlspecialchars($srv['description']) ?>
                                        </span>
                                        <h3 class="font-display text-xl font-bold text-slate-950">
                                            <?= htmlspecialchars($srv['title']) ?>
                                        </h3>
                                    </div>
                                    <ul class="flex flex-col gap-2">
                                        <?php foreach ($srv['features'] as $feat): ?>
                                            <li class="flex items-start gap-3 text-sm text-slate-500">
                                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                                <span><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <!-- Card 4: Growth Automation - Spans 4 Columns -->
                    <?php if (isset($t['services']['items'][4])): 
                        $srv = $t['services']['items'][4]; ?>
                        <article class="reveal-element md:col-span-4 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8 h-full flex flex-col justify-between gap-6">
                                <div class="flex flex-col gap-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?= $serviceIcons[4] ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                            <?= htmlspecialchars($srv['description']) ?>
                                        </span>
                                        <h3 class="font-display text-xl font-bold text-slate-950">
                                            <?= htmlspecialchars($srv['title']) ?>
                                        </h3>
                                    </div>
                                    <ul class="flex flex-col gap-2">
                                        <?php foreach ($srv['features'] as $feat): ?>
                                            <li class="flex items-start gap-3 text-sm text-slate-500">
                                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                                <span><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <!-- Card 5: Autonomous Back-Office (Large Editorial Highlight Split) - Spans 12 Columns -->
                    <?php if (isset($t['services']['items'][5])): 
                        $srv = $t['services']['items'][5]; ?>
                        <article class="reveal-element md:col-span-12 group hover-lift p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-12 h-full flex flex-col lg:flex-row items-center gap-12 justify-between">
                                <div class="lg:max-w-md flex flex-col gap-5">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-50 border border-indigo-100 text-accent rounded-2xl">
                                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?= $serviceIcons[5] ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-[10px] uppercase font-extrabold tracking-widest text-accent mb-1 block">
                                            <?= htmlspecialchars($srv['description']) ?>
                                        </span>
                                        <h3 class="font-display text-3xl font-bold text-slate-950 tracking-tight">
                                            <?= htmlspecialchars($srv['title']) ?>
                                        </h3>
                                    </div>
                                    <ul class="flex flex-col gap-2.5">
                                        <?php foreach ($srv['features'] as $feat): ?>
                                            <li class="flex items-start gap-3 text-sm text-slate-500">
                                                <span class="mt-1 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                                <span><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <!-- Silicon Valley team collaborating photo inside Double-Bezel card -->
                                <div class="relative w-full lg:max-w-lg p-2.5 rounded-3xl bg-bezel-outer border border-bezel-border shadow-md">
                                    <div class="overflow-hidden rounded-2xl border border-slate-200/30 shadow-inner">
                                        <img src="assets/people_work.png" alt="Collaborative tech team in modern office environment" class="w-full h-auto object-cover transform group-hover:scale-101 duration-700" loading="lazy" width="480" height="320">
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                </div>
            </div>
        </section>

        <!-- ================================================================
             BENEFITS SECTION: Impatto Operativo Misurabile
             ================================================================ -->
        <section id="beneficios" class="relative py-32 md:py-40 bg-slate-50/50 border-b border-slate-100">
            <div class="max-w-7xl mx-auto px-6">
                <!-- Section header -->
                <div class="text-center max-w-3xl mx-auto mb-20 md:mb-24">
                    <div class="reveal-element inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-200/30 rounded-full mb-4">
                        <span class="text-[9px] uppercase tracking-[0.25em] font-bold text-slate-400">Impact</span>
                    </div>
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                        <?= htmlspecialchars($t['benefits']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-500 leading-relaxed text-base md:text-lg">
                        <?= htmlspecialchars($t['benefits']['subtitle']) ?>
                    </p>
                </div>

                <!-- Benefits grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($t['benefits']['items'] as $i => $benefit): ?>
                        <article class="reveal-element group hover-lift p-2.5 rounded-[2rem] bg-bezel-outer border border-bezel-border flex flex-col justify-between h-full">
                            <div class="bezel-inner-core rounded-[calc(2rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8 h-full flex flex-col gap-6">
                                <div class="inline-flex items-center justify-center w-11 h-11 bg-indigo-50/50 text-accent border border-indigo-100/30 rounded-2xl">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <?= $benefitIcons[$i] ?? $benefitIcons[0] ?>
                                    </svg>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <h3 class="font-display text-lg font-bold text-slate-950 leading-snug">
                                        <?= htmlspecialchars($benefit['title']) ?>
                                    </h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        <?= htmlspecialchars($benefit['description']) ?>
                                    </p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             TESTIMONIALS SECTION: Supported by concrete results
             ================================================================ -->
        <section id="testimonios" class="relative py-32 md:py-40 bg-white">
            <div class="max-w-7xl mx-auto px-6">
                <!-- Section header -->
                <div class="text-center max-w-3xl mx-auto mb-20 md:mb-24">
                    <div class="reveal-element inline-flex items-center gap-1.5 px-3 py-1 bg-slate-50 border border-slate-200/40 rounded-full mb-4">
                        <span class="text-[9px] uppercase tracking-[0.25em] font-bold text-slate-400">Success Stories</span>
                    </div>
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                        <?= htmlspecialchars($t['testimonials']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-500 leading-relaxed text-base md:text-lg">
                        <?= htmlspecialchars($t['testimonials']['subtitle']) ?>
                    </p>
                </div>

                <!-- Testimonials grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($t['testimonials']['items'] as $testimonial): ?>
                        <article class="reveal-element group p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border flex flex-col justify-between h-full hover-lift">
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-10 h-full flex flex-col justify-between gap-8">
                                <blockquote class="relative">
                                    <svg class="absolute -top-4 -left-2 w-8 h-8 text-accent/10" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                                    </svg>
                                    <p class="relative text-slate-700 leading-relaxed text-sm italic pl-6">
                                        "<?= htmlspecialchars($testimonial['quote']) ?>"
                                    </p>
                                </blockquote>

                                <div class="mt-6 flex items-center gap-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-accent to-secondary rounded-full flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                        <?= strtoupper(substr($testimonial['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 text-xs">
                                            <?= htmlspecialchars($testimonial['name']) ?>
                                        </p>
                                        <p class="text-[10px] uppercase font-extrabold tracking-widest text-slate-400 mt-0.5">
                                            <?= htmlspecialchars($testimonial['role']) ?> – <?= htmlspecialchars($testimonial['company']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             DEMO CTA SECTION: Floating action container
             ================================================================ -->
        <section id="demo" class="relative py-28 bg-slate-50/70 overflow-hidden border-t border-slate-200/40">
            <div class="absolute inset-0 pointer-events-none opacity-40" aria-hidden="true">
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:4rem_4rem]"></div>
            </div>

            <div class="relative max-w-4xl mx-auto px-6 text-center z-10">
                <h2 class="reveal-element font-display text-3xl md:text-5xl font-extrabold text-slate-950 tracking-tight mb-6">
                    <?= htmlspecialchars($t['demo']['title']) ?>
                </h2>
                <p class="reveal-element text-sm md:text-base text-slate-500 mb-10 max-w-2xl mx-auto leading-relaxed">
                    <?= htmlspecialchars($t['demo']['subtitle']) ?>
                </p>
                <button id="demo-cta-btn" type="button"
                    class="reveal-element group inline-flex items-center justify-between gap-6 pl-6 pr-2.5 py-2.5 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-full premium-transition shadow-xl shadow-slate-900/10">
                    <span><?= htmlspecialchars($t['demo']['cta']) ?></span>
                    <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center premium-transition group-hover:translate-x-0.5 group-hover:-translate-y-[0.5px]">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </span>
                </button>
            </div>
        </section>

        <!-- ================================================================
             CONTACT SECTION: Design your digital workforce
             ================================================================ -->
        <section id="contacto" class="relative py-32 md:py-40 bg-white">
            <div class="max-w-7xl mx-auto px-6 z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-24 items-start">
                    
                    <!-- Left Column: Key contact metadata -->
                    <div>
                        <div class="reveal-element inline-flex items-center gap-1.5 px-3 py-1 bg-slate-50 border border-slate-200/40 rounded-full mb-4">
                            <span class="text-[9px] uppercase tracking-[0.25em] font-bold text-slate-400">Connection</span>
                        </div>
                        <h2 class="reveal-element font-display text-3xl md:text-5xl font-extrabold text-slate-950 tracking-tight leading-[1.1] mb-6">
                            <?= htmlspecialchars($t['contact']['title']) ?>
                        </h2>
                        <p class="reveal-element text-slate-500 leading-relaxed text-sm md:text-base mb-10">
                            <?= htmlspecialchars($t['contact']['subtitle']) ?>
                        </p>

                        <h3 class="reveal-element font-display font-bold text-xs uppercase tracking-widest text-slate-900 mb-6">
                            <?= htmlspecialchars($t['contact']['reasons_title']) ?>
                        </h3>

                        <ul class="flex flex-col gap-4 mb-10">
                            <?php foreach ($t['contact']['reasons'] as $reason): ?>
                                <li class="reveal-element flex items-center gap-3">
                                    <div class="flex-shrink-0 w-6 h-6 bg-indigo-50 border border-indigo-100 rounded-full flex items-center justify-center shadow-sm">
                                        <svg class="w-3.5 h-3.5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-slate-700 text-sm font-semibold">
                                        <?= htmlspecialchars($reason) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Direct Premium Contact Link -->
                        <a href="mailto:info@intuify.net"
                            class="reveal-element group inline-flex items-center gap-3 text-accent hover:text-indigo-800 text-sm font-semibold transition-all duration-300">
                            <span class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200/40 flex items-center justify-center shadow-sm group-hover:border-slate-300 transition-colors duration-300">
                                <svg class="w-4 h-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="group-hover:underline decoration-slate-300">info@intuify.net</span>
                        </a>
                    </div>

                    <!-- Right Column: Interactive Double-Bezel Contact Form -->
                    <div class="reveal-element">
                        <!-- Outer Shell -->
                        <div class="p-2.5 rounded-[2.5rem] bg-bezel-outer border border-bezel-border shadow-2xl shadow-slate-100">
                            <!-- Inner Core -->
                            <div class="bezel-inner-core rounded-[calc(2.5rem-0.625rem)] bg-white border border-slate-200/30 p-6 md:p-8">
                                <form id="contact-form" class="space-y-6" novalidate>
                                    <!-- Honeypot -->
                                    <div class="hidden" aria-hidden="true">
                                        <label for="website">Website</label>
                                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                                    </div>

                                    <!-- Security & Identification inputs -->
                                    <input type="hidden" name="_timestamp" id="form-timestamp" value="">
                                    <input type="hidden" name="ajax_submit" value="1">
                                    <input type="hidden" name="recaptcha_token" id="recaptcha-token" value="">

                                    <!-- Name Field (Double-Bezel Input Structure) -->
                                    <div class="flex flex-col gap-2">
                                        <label for="nombre_completo" class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                            <?= htmlspecialchars($t['contact']['form']['name']) ?> <span class="text-accent">*</span>
                                        </label>
                                        <div class="p-1 rounded-2xl bg-bezel-outer border border-bezel-border">
                                            <input type="text" id="nombre_completo" name="nombre_completo" required
                                                placeholder="<?= htmlspecialchars($t['contact']['form']['name_placeholder']) ?>"
                                                class="w-full px-4 py-3 bg-white border border-transparent rounded-[calc(1rem-0.25rem)] text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-accent focus:border-transparent transition-all shadow-sm">
                                        </div>
                                    </div>

                                    <!-- Company Field -->
                                    <div class="flex flex-col gap-2">
                                        <label for="empresa" class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                            <?= htmlspecialchars($t['contact']['form']['company']) ?> <span class="text-accent">*</span>
                                        </label>
                                        <div class="p-1 rounded-2xl bg-bezel-outer border border-bezel-border">
                                            <input type="text" id="empresa" name="empresa" required
                                                placeholder="<?= htmlspecialchars($t['contact']['form']['company_placeholder']) ?>"
                                                class="w-full px-4 py-3 bg-white border border-transparent rounded-[calc(1rem-0.25rem)] text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-accent focus:border-transparent transition-all shadow-sm">
                                        </div>
                                    </div>

                                    <!-- Email Field -->
                                    <div class="flex flex-col gap-2">
                                        <label for="email" class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                            <?= htmlspecialchars($t['contact']['form']['email']) ?> <span class="text-accent">*</span>
                                        </label>
                                        <div class="p-1 rounded-2xl bg-bezel-outer border border-bezel-border">
                                            <input type="email" id="email" name="email" required
                                                placeholder="<?= htmlspecialchars($t['contact']['form']['email_placeholder']) ?>"
                                                class="w-full px-4 py-3 bg-white border border-transparent rounded-[calc(1rem-0.25rem)] text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-accent focus:border-transparent transition-all shadow-sm">
                                        </div>
                                    </div>

                                    <!-- Message Field -->
                                    <div class="flex flex-col gap-2">
                                        <label for="mensaje" class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                            <?= htmlspecialchars($t['contact']['form']['message']) ?> <span class="text-accent">*</span>
                                        </label>
                                        <div class="p-1 rounded-2xl bg-bezel-outer border border-bezel-border">
                                            <textarea id="mensaje" name="mensaje" rows="4" required
                                                placeholder="<?= htmlspecialchars($t['contact']['form']['message_placeholder']) ?>"
                                                class="w-full px-4 py-3 bg-white border border-transparent rounded-[calc(1rem-0.25rem)] text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-accent focus:border-transparent transition-all resize-none shadow-sm"></textarea>
                                        </div>
                                    </div>

                                    <!-- Button-in-Button Submit CTA -->
                                    <button type="submit" id="submit-btn"
                                        class="group w-full inline-flex items-center justify-between gap-6 pl-6 pr-2.5 py-2.5 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-full premium-transition shadow-xl shadow-slate-900/10 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span id="btn-text">
                                            <?= htmlspecialchars($t['contact']['form']['submit']) ?>
                                        </span>
                                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center premium-transition group-hover:translate-x-0.5 group-hover:-translate-y-[0.5px]">
                                            <svg id="btn-svg-arrow" class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                            </svg>
                                            <svg id="btn-spinner" class="hidden w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>

                                    <!-- Feedback status alerts -->
                                    <div id="form-success" class="hidden p-4 bg-green-50 border border-green-200/60 rounded-2xl text-green-700 text-xs font-semibold leading-relaxed">
                                        <?= htmlspecialchars($t['contact']['form']['success']) ?>
                                    </div>
                                    <div id="form-error" class="hidden p-4 bg-red-50 border border-red-200/60 rounded-2xl text-red-700 text-xs font-semibold leading-relaxed">
                                        <?= htmlspecialchars($t['contact']['form']['error']) ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <!-- Premium Footer Component -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- ================================================================
         MOTION & CONTROLLER SCRIPT
         ================================================================ -->
    <script>
        (function () {
            'use strict';

            // Mobile Navigation Menu Controller
            const menuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const line1 = document.getElementById('hamburger-line-1');
            const line2 = document.getElementById('hamburger-line-2');
            const mobileLinks = document.querySelectorAll('.mobile-nav-link');

            if (menuBtn && mobileMenu) {
                const toggleMenu = () => {
                    const isOpen = menuBtn.getAttribute('aria-expanded') === 'true';
                    menuBtn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
                    
                    // Toggle drawer opacity, scaling, and interactive properties
                    mobileMenu.classList.toggle('opacity-0');
                    mobileMenu.classList.toggle('pointer-events-none');
                    mobileMenu.classList.toggle('translate-y-4');
                    mobileMenu.classList.toggle('scale-95');
                    mobileMenu.classList.toggle('opacity-100');
                    mobileMenu.classList.toggle('pointer-events-auto');
                    mobileMenu.classList.toggle('translate-y-0');
                    mobileMenu.classList.toggle('scale-100');

                    // Morph hamburger lines to 'X'
                    line1.classList.toggle('translate-y-[-3px]');
                    line1.classList.toggle('translate-y-0');
                    line1.classList.toggle('rotate-45');

                    line2.classList.toggle('translate-y-[3px]');
                    line2.classList.toggle('translate-y-0');
                    line2.classList.toggle('-rotate-45');
                };

                menuBtn.addEventListener('click', toggleMenu);

                // Auto-close when clicking any link
                mobileLinks.forEach(link => {
                    link.addEventListener('click', toggleMenu);
                });
            }

            // Establish timestamp for form time-trap validation
            document.getElementById('form-timestamp').value = Math.floor(Date.now() / 1000);

            // Active section highlighting pill with IntersectionObserver
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');

            const observerOptions = {
                root: null,
                rootMargin: '-30% 0px -40% 0px',
                threshold: 0
            };

            const sectionObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(function (link) {
                            link.classList.remove('active');
                            if (link.getAttribute('data-section') === id) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(function (section) {
                sectionObserver.observe(section);
            });

            // Fluid Page Entry Animation Observer (GPU-Safe Scroll Reveals)
            const revealElements = document.querySelectorAll('.reveal-element');
            const revealObserverOptions = {
                root: null,
                rootMargin: '0px 0px -10% 0px',
                threshold: 0.05
            };

            const revealObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target); // Trigger exactly once
                    }
                });
            }, revealObserverOptions);

            revealElements.forEach(function (el) {
                revealObserver.observe(el);
            });

            // Smooth Scroll for Demo CTA triggering target auto-focus
            const demoCta = document.getElementById('demo-cta-btn');
            const mensajeField = document.getElementById('mensaje');

            if (demoCta && mensajeField) {
                demoCta.addEventListener('click', function () {
                    const demoMessages = {
                        'es': 'Solicito demo gratuita',
                        'it': 'Richiedo demo gratuita',
                        'en': 'I request a free demo'
                    };
                    const lang = document.documentElement.lang || 'en';
                    mensajeField.value = demoMessages[lang] || demoMessages['en'];
                    document.getElementById('contacto').scrollIntoView({ behavior: 'smooth' });
                    
                    // Smooth delay before focusing
                    setTimeout(function () {
                        document.getElementById('nombre_completo').focus();
                    }, 800);
                });
            }

            // AJAX Contact Form Submission Handler
            const form = document.getElementById('contact-form');
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnArrow = document.getElementById('btn-svg-arrow');
            const btnSpinner = document.getElementById('btn-spinner');
            const successMsg = document.getElementById('form-success');
            const errorMsg = document.getElementById('form-error');

            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    // Flush alert states
                    successMsg.classList.add('hidden');
                    errorMsg.classList.add('hidden');

                    // Initiate premium lock state
                    submitBtn.disabled = true;
                    btnText.textContent = '<?= addslashes($t['contact']['form']['sending']) ?>';
                    btnArrow.classList.add('hidden');
                    btnSpinner.classList.remove('hidden');

                    try {
                        // Dynamically request v3 reCAPTCHA token if configured
                        <?php if (!empty($config['recaptcha_site_key']) && $config['recaptcha_site_key'] !== 'YOUR_RECAPTCHA_SITE_KEY'): ?>
                        if (typeof grecaptcha !== 'undefined') {
                            const recaptchaToken = await grecaptcha.execute('<?= htmlspecialchars($config['recaptcha_site_key']) ?>', {action: 'contact_form'});
                            document.getElementById('recaptcha-token').value = recaptchaToken;
                        }
                        <?php endif; ?>

                        const formData = new FormData(form);
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            successMsg.classList.remove('hidden');
                            form.reset();
                            // Reset token timestamp for safe sequential entries
                            document.getElementById('form-timestamp').value = Math.floor(Date.now() / 1000);
                        } else {
                            errorMsg.textContent = result.error || '<?= addslashes($t['contact']['form']['error']) ?>';
                            errorMsg.classList.remove('hidden');
                        }
                    } catch (err) {
                        errorMsg.textContent = '<?= addslashes($t['contact']['form']['error']) ?>';
                        errorMsg.classList.remove('hidden');
                    }

                    // Release premium lock state
                    submitBtn.disabled = false;
                    btnText.textContent = '<?= addslashes($t['contact']['form']['submit']) ?>';
                    btnSpinner.classList.add('hidden');
                    btnArrow.classList.remove('hidden');
                });
            }
        })();
    </script>
</body>

</html>