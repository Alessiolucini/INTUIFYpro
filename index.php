<?php
/**
 * IntuiFy - Futuristic Dark Landing Page
 * 
 * DESIGN: Dark futuristic with neon accents, glassmorphism, animated gradients.
 * BRAND: Fast tech studio that builds and ships apps to Apple Store & Google Play.
 * STACK: PHP 8.0+, Tailwind CSS (CDN), vanilla JS, PHPMailer, reCAPTCHA v3.
 */

declare(strict_types=1);

session_start();

// Load configuration
$config = require __DIR__ . '/config.php';

// PHPMailer (for contact form SMTP)
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';

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

    // Send email via PHPMailer SMTP
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom($config['mail_from'], $config['mail_from_name']);
        $mail->addAddress($config['mail_to']);
        $mail->addReplyTo($email, $nombre);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "IntuiFy - Nuovo contatto da {$nombre} ({$empresa})";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #6366F1, #8B5CF6); padding: 24px 32px; border-radius: 12px 12px 0 0;'>
                    <h2 style='color: #ffffff; margin: 0; font-size: 20px;'>📩 Nuovo messaggio dal sito IntuiFy</h2>
                </div>
                <div style='background: #f8fafc; padding: 32px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155; width: 120px;'>Nome</td>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #475569;'>{$nombre}</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155;'>Azienda</td>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #475569;'>{$empresa}</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155;'>Email</td>
                            <td style='padding: 12px 0; border-bottom: 1px solid #e2e8f0;'><a href='mailto:{$email}' style='color: #6366F1;'>{$email}</a></td>
                        </tr>
                        <tr>
                            <td style='padding: 12px 0; font-weight: bold; color: #334155; vertical-align: top;'>Messaggio</td>
                            <td style='padding: 12px 0; color: #475569; line-height: 1.6;'>" . nl2br(htmlspecialchars($mensaje)) . "</td>
                        </tr>
                    </table>
                    <div style='margin-top: 24px; padding: 16px; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;'>
                        <p style='margin: 0; font-size: 12px; color: #94a3b8;'>
                            📅 Inviato il: " . date('d/m/Y H:i:s') . " | 🌐 Lingua: {$currentLang}
                        </p>
                    </div>
                </div>
            </div>";
        $mail->AltBody = "Nuovo contatto IntuiFy\n\nNome: {$nombre}\nAzienda: {$empresa}\nEmail: {$email}\nMessaggio: {$mensaje}\nData: " . date('d/m/Y H:i:s');

        $mail->send();
        
        // Save lead to Supabase for admin panel tracking
        try {
            require_once __DIR__ . '/admin/includes/supabase.php';
            $sb = getSupabase();
            $sb->insert('leads', [
                'name' => $nombre,
                'email' => $email,
                'company' => $empresa,
                'message' => $mensaje,
                'source' => 'landing_form',
                'status' => 'new',
            ]);
        } catch (\Throwable $e) {
            // Don't fail the form submission if Supabase is unavailable
            error_log("Lead save to Supabase failed: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true]);
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
    }
    exit;
}

// Service icons
$serviceIcons = [
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 .364l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h2a2.5 2.5 0 002.5-2.5V8.5a.5.5 0 01.5-.5h.5A2.5 2.5 0 0021 5.5v-.5M12 21a9 9 0 100-18 9 9 0 000 18z"/>'
];

// Project showcase logos and colors
$projectLogos = [
    ['file' => 'assets/projects/auterio.png', 'type' => 'png', 'glow' => '#3b82f6'],
    ['file' => 'assets/projects/orqesia.svg', 'type' => 'svg', 'glow' => '#8b5cf6'],
    ['file' => 'assets/projects/ecoandratx.png', 'type' => 'png', 'glow' => '#22c55e'],
    ['file' => 'assets/projects/lingobite.svg', 'type' => 'svg', 'glow' => '#f97316']
];
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['meta']['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($t['meta']['description']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://intuify.com/">
    <link rel="icon" href="logo/intuifylogo.svg" type="image/svg+xml">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Space Grotesk', 'sans-serif'],
                        body: ['Inter', 'sans-serif']
                    },
                    colors: {
                        accent: '#6366f1',
                        surface: '#0d0d14',
                        'surface-light': '#13131d'
                    }
                }
            }
        }
    </script>

    <?php if (!empty($config['recaptcha_site_key']) && $config['recaptcha_site_key'] !== 'YOUR_RECAPTCHA_SITE_KEY'): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= $config['recaptcha_site_key'] ?>"></script>
    <?php endif; ?>

    <style>
        *, *::before, *::after { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: 'Space Grotesk', sans-serif; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #06060a; }
        ::-webkit-scrollbar-thumb { background: #2a2a3d; border-radius: 3px; }

        /* Animated gradient background */
        .hero-gradient {
            background: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(139, 92, 246, 0.08), transparent),
                        radial-gradient(ellipse 60% 40% at 20% 80%, rgba(59, 130, 246, 0.06), transparent);
        }

        /* Grid pattern overlay */
        .grid-pattern {
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                             linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Glow card effect */
        .glow-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.5s cubic-bezier(0.32, 0.72, 0, 1);
        }
        .glow-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 0 40px -10px rgba(99, 102, 241, 0.15);
            transform: translateY(-4px);
        }

        /* Reveal animation */
        .reveal-element {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.32, 0.72, 0, 1),
                        transform 0.8s cubic-bezier(0.32, 0.72, 0, 1);
        }
        .reveal-element.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Shimmer effect for stats */
        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.04), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #818cf8, #6366f1, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Process timeline connector */
        .timeline-connector {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3));
        }

        /* Pulse glow */
        .pulse-glow {
            animation: pulseGlow 2s ease-in-out infinite alternate;
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 20px rgba(99,102,241,0.2); }
            100% { box-shadow: 0 0 40px rgba(99,102,241,0.4); }
        }

        /* Nav active state */
        .nav-link-active {
            background: rgba(255,255,255,0.08) !important;
            color: #fff !important;
        }

        /* Counter animation */
        .counter { font-variant-numeric: tabular-nums; }

        /* Form inputs */
        .form-input {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            color: #f0f0f5;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: rgba(99,102,241,0.5);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
            outline: none;
        }
        .form-input::placeholder { color: #4a4a5a; }

        /* Hide reCAPTCHA floating badge */
        .grecaptcha-badge { visibility: hidden !important; }
    </style>
</head>

<body class="bg-[#08080e] text-slate-300 font-body antialiased overflow-x-hidden">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <!-- ================================================================
             HERO SECTION
             ================================================================ -->
        <section id="inicio" class="relative min-h-screen flex items-center justify-center hero-gradient overflow-hidden">
            <!-- Grid overlay -->
            <div class="absolute inset-0 grid-pattern opacity-60"></div>
            
            <!-- Floating orbs -->
            <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-indigo-500/10 rounded-full blur-[100px] animate-pulse"></div>
            <div class="absolute bottom-1/3 right-1/4 w-96 h-96 bg-purple-500/8 rounded-full blur-[120px] animate-pulse" style="animation-delay: 1s"></div>

            <div class="relative max-w-5xl mx-auto px-6 text-center z-10 pt-32 pb-20">
                
                <!-- Store badges -->
                <div class="reveal-element flex items-center justify-center gap-3 mb-10">
                    <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.04] border border-white/[0.06] backdrop-blur-sm">
                        <svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                        <span class="text-xs font-semibold text-slate-400">App Store</span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.04] border border-white/[0.06] backdrop-blur-sm">
                        <svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-1.4l2.584 1.496c.906.524.906 1.37 0 1.894l-2.177 1.26-2.536-2.536 2.13-2.115zM5.864 2.658L16.8 8.99l-2.302 2.302-8.635-8.634z"/></svg>
                        <span class="text-xs font-semibold text-slate-400">Google Play</span>
                    </div>
                </div>

                <!-- Main Heading -->
                <h1 class="reveal-element font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.08] mb-8">
                    <?= htmlspecialchars($t['hero']['title']) ?>
                </h1>

                <!-- Subtitle -->
                <p class="reveal-element text-lg md:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed mb-12">
                    <?= htmlspecialchars($t['hero']['subtitle']) ?>
                </p>

                <!-- CTAs -->
                <div class="reveal-element flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
                    <a href="#contacto"
                        class="group inline-flex items-center gap-3 px-8 py-4 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-all duration-500 shadow-xl shadow-indigo-500/25 pulse-glow">
                        <span><?= htmlspecialchars($t['hero']['cta_primary']) ?></span>
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                    <a href="#portfolio"
                        class="inline-flex items-center gap-2 px-8 py-4 text-sm font-bold text-slate-300 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.08] hover:border-white/[0.12] rounded-full transition-all duration-500">
                        <?= htmlspecialchars($t['hero']['cta_secondary']) ?>
                    </a>
                </div>

                <!-- Stats -->
                <div class="reveal-element grid grid-cols-3 gap-6 max-w-lg mx-auto">
                    <div class="text-center">
                        <div class="font-display text-3xl md:text-4xl font-bold text-white counter" data-target="20">0</div>
                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($t['hero']['stats']['projects']) ?></div>
                    </div>
                    <div class="text-center border-x border-white/[0.06]">
                        <div class="font-display text-3xl md:text-4xl font-bold gradient-text counter" data-target="5" data-prefix="<">0</div>
                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($t['hero']['stats']['speed']) ?></div>
                    </div>
                    <div class="text-center">
                        <div class="font-display text-3xl md:text-4xl font-bold text-white counter" data-target="12">0</div>
                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($t['hero']['stats']['stores']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Bottom fade -->
            <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#08080e] to-transparent"></div>
        </section>

        <!-- ================================================================
             SHOWCASE / PORTFOLIO SECTION
             ================================================================ -->
        <section id="portfolio" class="relative py-28 bg-[#08080e]">
            <div class="max-w-6xl mx-auto px-6">
                <!-- Section header -->
                <div class="text-center mb-16">
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-bold text-white tracking-tight mb-5">
                        <?= htmlspecialchars($t['showcase']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-400 max-w-2xl mx-auto">
                        <?= htmlspecialchars($t['showcase']['subtitle']) ?>
                    </p>
                </div>

                <!-- Project grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($t['showcase']['items'] as $i => $project): 
                        $logo = $projectLogos[$i];
                    ?>
                        <article class="reveal-element glow-card rounded-3xl p-8 md:p-10 flex flex-col justify-between gap-6 group" style="--glow: <?= $logo['glow'] ?>">
                            <div>
                                <!-- Tag -->
                                <span class="inline-block px-3 py-1 text-[10px] uppercase tracking-widest font-bold rounded-full mb-6"
                                      style="color: <?= $logo['glow'] ?>; background: <?= $logo['glow'] ?>15; border: 1px solid <?= $logo['glow'] ?>20">
                                    <?= htmlspecialchars($project['tag']) ?>
                                </span>
                                <!-- Logo -->
                                <div class="h-10 md:h-12 mb-5 flex items-center">
                                    <img src="<?= $logo['file'] ?>" 
                                         alt="<?= htmlspecialchars($project['name']) ?>" 
                                         class="h-full w-auto object-contain <?= $logo['type'] === 'svg' ? 'brightness-200' : '' ?>"
                                         loading="lazy">
                                </div>
                                <!-- Description -->
                                <p class="text-sm text-slate-400 leading-relaxed">
                                    <?= htmlspecialchars($project['description']) ?>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             PROCESS / TIMELINE SECTION
             ================================================================ -->
        <section class="relative py-28 bg-[#0a0a12]">
            <!-- Gradient accent -->
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>
            
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-bold text-white tracking-tight mb-5">
                        <?= htmlspecialchars($t['process']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-400 max-w-2xl mx-auto">
                        <?= htmlspecialchars($t['process']['subtitle']) ?>
                    </p>
                </div>

                <!-- Timeline steps -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">
                    <!-- Connector line (desktop) -->
                    <div class="hidden md:block absolute top-[4.5rem] left-[12%] right-[12%] h-px timeline-connector"></div>

                    <?php foreach ($t['process']['steps'] as $i => $step): ?>
                        <div class="reveal-element text-center relative" style="transition-delay: <?= $i * 0.1 ?>s">
                            <!-- Step number circle -->
                            <div class="relative mx-auto w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mb-6 z-10">
                                <span class="font-display text-lg font-bold gradient-text"><?= $i + 1 ?></span>
                            </div>
                            <!-- Day badge -->
                            <div class="inline-block px-3 py-1 text-[10px] uppercase tracking-widest font-bold text-indigo-400 bg-indigo-500/10 rounded-full mb-3">
                                <?= htmlspecialchars($step['day']) ?>
                            </div>
                            <h3 class="font-display text-lg font-bold text-white mb-2">
                                <?= htmlspecialchars($step['title']) ?>
                            </h3>
                            <p class="text-sm text-slate-500">
                                <?= htmlspecialchars($step['description']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             SERVICES SECTION
             ================================================================ -->
        <section id="servicio" class="relative py-28 bg-[#08080e]">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-bold text-white tracking-tight mb-5">
                        <?= htmlspecialchars($t['services']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-400 max-w-2xl mx-auto">
                        <?= htmlspecialchars($t['services']['subtitle']) ?>
                    </p>
                </div>

                <?php
                    // Accent colors per service card
                    $cardAccents = [
                        ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/20', 'text' => 'text-indigo-400', 'dot' => 'bg-indigo-500', 'glow' => 'rgba(99,102,241,0.15)'],
                        ['bg' => 'bg-cyan-500/10', 'border' => 'border-cyan-500/20', 'text' => 'text-cyan-400', 'dot' => 'bg-cyan-500', 'glow' => 'rgba(6,182,212,0.15)'],
                        ['bg' => 'bg-fuchsia-500/10', 'border' => 'border-fuchsia-500/20', 'text' => 'text-fuchsia-400', 'dot' => 'bg-fuchsia-500', 'glow' => 'rgba(217,70,239,0.15)'],
                        ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/20', 'text' => 'text-amber-400', 'dot' => 'bg-amber-500', 'glow' => 'rgba(245,158,11,0.15)'],
                        ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-400', 'dot' => 'bg-emerald-500', 'glow' => 'rgba(16,185,129,0.15)']
                    ];
                    // Grid span: first 3 = 4 cols, last 2 = 6 cols
                    $colSpans = ['md:col-span-4', 'md:col-span-4', 'md:col-span-4', 'md:col-span-6', 'md:col-span-6'];
                ?>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <?php foreach ($t['services']['items'] as $i => $srv): 
                        $accent = $cardAccents[$i] ?? $cardAccents[0];
                        $span = $colSpans[$i] ?? 'md:col-span-4';
                    ?>
                        <article class="reveal-element <?= $span ?> group glow-card rounded-3xl p-7 md:p-8 flex flex-col gap-5 relative overflow-hidden" style="transition-delay: <?= $i * 0.08 ?>s">
                            <!-- Top gradient line on hover -->
                            <div class="absolute top-0 left-0 right-0 h-px opacity-0 group-hover:opacity-100 transition-opacity duration-500" style="background: linear-gradient(90deg, transparent, <?= str_replace('/10', '', str_replace('bg-', '', $accent['glow'])) ?>, transparent)"></div>
                            
                            <!-- Icon -->
                            <div class="inline-flex items-center justify-center w-12 h-12 <?= $accent['bg'] ?> <?= $accent['border'] ?> border <?= $accent['text'] ?> rounded-2xl transition-transform duration-500 group-hover:scale-110">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <?= $serviceIcons[$i] ?? $serviceIcons[0] ?>
                                </svg>
                            </div>
                            <!-- Content -->
                            <div>
                                <span class="text-[10px] uppercase font-extrabold tracking-widest <?= $accent['text'] ?> mb-1 block">
                                    <?= htmlspecialchars($srv['description']) ?>
                                </span>
                                <h3 class="font-display text-xl font-bold text-white">
                                    <?= htmlspecialchars($srv['title']) ?>
                                </h3>
                            </div>
                            <ul class="flex flex-col gap-2.5">
                                <?php foreach ($srv['features'] as $feat): ?>
                                    <li class="flex items-start gap-3 text-sm text-slate-500 group-hover:text-slate-400 transition-colors">
                                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full <?= $accent['dot'] ?> flex-shrink-0"></span>
                                        <span><?= htmlspecialchars($feat) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             BENEFITS SECTION
             ================================================================ -->
        <section id="beneficios" class="relative py-28 bg-[#0a0a12]">
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>

            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="reveal-element font-display text-3xl md:text-5xl font-bold text-white tracking-tight mb-5">
                        <?= htmlspecialchars($t['benefits']['title']) ?>
                    </h2>
                    <p class="reveal-element text-slate-400 max-w-2xl mx-auto">
                        <?= htmlspecialchars($t['benefits']['subtitle']) ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    $benefitIcons = ['⚡', '🏗️', '📱', '🔧', '💎', '🛡️'];
                    foreach ($t['benefits']['items'] as $i => $benefit): ?>
                        <div class="reveal-element glow-card rounded-3xl p-7 md:p-8">
                            <div class="text-3xl mb-4"><?= $benefitIcons[$i] ?? '✨' ?></div>
                            <h3 class="font-display text-lg font-bold text-white mb-2">
                                <?= htmlspecialchars($benefit['title']) ?>
                            </h3>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                <?= htmlspecialchars($benefit['description']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             CTA SECTION
             ================================================================ -->
        <section class="relative py-28 bg-[#08080e] overflow-hidden">
            <div class="absolute inset-0 hero-gradient"></div>
            <div class="absolute inset-0 grid-pattern opacity-40"></div>
            
            <div class="relative max-w-3xl mx-auto px-6 text-center z-10">
                <h2 class="reveal-element font-display text-3xl md:text-5xl font-bold text-white tracking-tight mb-6">
                    <?= htmlspecialchars($t['cta_section']['title']) ?>
                </h2>
                <p class="reveal-element text-slate-400 mb-10 text-lg">
                    <?= htmlspecialchars($t['cta_section']['subtitle']) ?>
                </p>
                <a href="#contacto"
                    class="reveal-element group inline-flex items-center gap-3 px-10 py-5 text-base font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-all duration-500 shadow-xl shadow-indigo-500/25 pulse-glow">
                    <span><?= htmlspecialchars($t['cta_section']['cta']) ?></span>
                    <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>
        </section>

        <!-- ================================================================
             CONTACT SECTION
             ================================================================ -->
        <section id="contacto" class="relative py-28 bg-[#0a0a12]">
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>

            <div class="max-w-6xl mx-auto px-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
                    <!-- Left: Info -->
                    <div class="reveal-element">
                        <h2 class="font-display text-3xl md:text-4xl font-bold text-white tracking-tight mb-5">
                            <?= htmlspecialchars($t['contact']['title']) ?>
                        </h2>
                        <p class="text-slate-400 mb-10 leading-relaxed">
                            <?= htmlspecialchars($t['contact']['subtitle']) ?>
                        </p>

                        <h3 class="text-sm font-bold text-white uppercase tracking-widest mb-5">
                            <?= htmlspecialchars($t['contact']['reasons_title']) ?>
                        </h3>
                        <ul class="flex flex-col gap-4">
                            <?php foreach ($t['contact']['reasons'] as $reason): ?>
                                <li class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-3 h-3 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm text-slate-400"><?= htmlspecialchars($reason) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Right: Form -->
                    <div class="reveal-element">
                        <form id="contact-form" class="glow-card rounded-3xl p-8 md:p-10 flex flex-col gap-5">
                            <input type="hidden" name="ajax_submit" value="1">
                            <input type="hidden" name="_timestamp" value="<?= time() ?>">
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">
                            <!-- Honeypot -->
                            <div class="absolute opacity-0 pointer-events-none" aria-hidden="true">
                                <input type="text" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($t['contact']['form']['name']) ?></label>
                                    <input type="text" name="nombre_completo" required
                                        class="form-input w-full px-4 py-3 rounded-xl text-sm"
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['name_placeholder']) ?>">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($t['contact']['form']['company']) ?></label>
                                    <input type="text" name="empresa" required
                                        class="form-input w-full px-4 py-3 rounded-xl text-sm"
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['company_placeholder']) ?>">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($t['contact']['form']['email']) ?></label>
                                <input type="email" name="email" required
                                    class="form-input w-full px-4 py-3 rounded-xl text-sm"
                                    placeholder="<?= htmlspecialchars($t['contact']['form']['email_placeholder']) ?>">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2"><?= htmlspecialchars($t['contact']['form']['message']) ?></label>
                                <textarea name="mensaje" required rows="4"
                                    class="form-input w-full px-4 py-3 rounded-xl text-sm resize-none"
                                    placeholder="<?= htmlspecialchars($t['contact']['form']['message_placeholder']) ?>"></textarea>
                            </div>

                            <button type="submit" id="submit-btn"
                                class="group w-full flex items-center justify-center gap-3 px-8 py-4 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-all duration-500 shadow-lg shadow-indigo-500/20 mt-2">
                                <span id="submit-text"><?= htmlspecialchars($t['contact']['form']['submit']) ?></span>
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>

                            <!-- Response message -->
                            <div id="form-response" class="hidden text-center text-sm py-3 rounded-xl"></div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- ================================================================
         JAVASCRIPT
         ================================================================ -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {

        // ---- IntersectionObserver Reveal ----
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal-element').forEach(el => observer.observe(el));

        // ---- Counter animation ----
        const counters = document.querySelectorAll('.counter');
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.dataset.target);
                    const prefix = el.dataset.prefix || '';
                    let current = 0;
                    const duration = 2000;
                    const step = target / (duration / 16);

                    const timer = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        el.textContent = prefix + Math.floor(current) + '+';
                    }, 16);

                    counterObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(c => counterObserver.observe(c));

        // ---- Mobile menu ----
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const line1 = document.getElementById('hamburger-line-1');
        const line2 = document.getElementById('hamburger-line-2');
        let menuOpen = false;

        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', () => {
                menuOpen = !menuOpen;
                menuBtn.setAttribute('aria-expanded', menuOpen);
                if (menuOpen) {
                    mobileMenu.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4', 'scale-95');
                    mobileMenu.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0', 'scale-100');
                    line1.style.transform = 'rotate(45deg) translateY(0)';
                    line2.style.transform = 'rotate(-45deg) translateY(0)';
                } else {
                    mobileMenu.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4', 'scale-95');
                    mobileMenu.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0', 'scale-100');
                    line1.style.transform = 'translateY(-3px)';
                    line2.style.transform = 'translateY(3px)';
                }
            });

            // Close on link click
            mobileMenu.querySelectorAll('a[href^="#"]').forEach(a => {
                a.addEventListener('click', () => {
                    menuOpen = false;
                    menuBtn.setAttribute('aria-expanded', 'false');
                    mobileMenu.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4', 'scale-95');
                    mobileMenu.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0', 'scale-100');
                    line1.style.transform = 'translateY(-3px)';
                    line2.style.transform = 'translateY(3px)';
                });
            });
        }

        // ---- Active nav highlighting ----
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
        const sections = document.querySelectorAll('section[id]');

        const navObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    navLinks.forEach(link => {
                        link.classList.toggle('nav-link-active', link.dataset.section === id);
                    });
                }
            });
        }, { threshold: 0.3, rootMargin: '-80px 0px -50% 0px' });

        sections.forEach(s => navObserver.observe(s));

        // ---- Header scroll behavior ----
        const header = document.getElementById('main-header');
        let lastScrollY = 0;

        window.addEventListener('scroll', () => {
            const scrollY = window.scrollY;
            if (scrollY > 100) {
                header.style.marginTop = '0.5rem';
            } else {
                header.style.marginTop = '';
            }
            lastScrollY = scrollY;
        }, { passive: true });

        // ---- Contact Form AJAX ----
        const form = document.getElementById('contact-form');
        const submitBtn = document.getElementById('submit-btn');
        const submitText = document.getElementById('submit-text');
        const formResponse = document.getElementById('form-response');

        const texts = {
            submit: <?= json_encode($t['contact']['form']['submit']) ?>,
            sending: <?= json_encode($t['contact']['form']['sending']) ?>,
            success: <?= json_encode($t['contact']['form']['success']) ?>,
            error: <?= json_encode($t['contact']['form']['error']) ?>
        };

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                submitText.textContent = texts.sending;

                <?php if (!empty($config['recaptcha_site_key']) && $config['recaptcha_site_key'] !== 'YOUR_RECAPTCHA_SITE_KEY'): ?>
                try {
                    const token = await grecaptcha.execute('<?= $config['recaptcha_site_key'] ?>', { action: 'submit' });
                    document.getElementById('recaptcha_token').value = token;
                } catch (err) {
                    console.error('reCAPTCHA error:', err);
                }
                <?php endif; ?>

                try {
                    const formData = new FormData(form);
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    formResponse.classList.remove('hidden');
                    if (data.success) {
                        formResponse.textContent = texts.success;
                        formResponse.className = 'text-center text-sm py-3 px-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400';
                        form.reset();
                    } else {
                        formResponse.textContent = data.error || texts.error;
                        formResponse.className = 'text-center text-sm py-3 px-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400';
                    }
                } catch (err) {
                    formResponse.classList.remove('hidden');
                    formResponse.textContent = texts.error;
                    formResponse.className = 'text-center text-sm py-3 px-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400';
                }

                submitBtn.disabled = false;
                submitText.textContent = texts.submit;
            });
        }

        // ---- Smooth scroll ----
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const target = document.querySelector(anchor.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    });
    </script>

</body>
</html>