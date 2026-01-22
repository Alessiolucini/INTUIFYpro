<?php
/**
 * IntuiFy - Premium Landing Page
 * PHP 8+ with Tailwind CSS via CDN
 * 
 * PALETTE (derived from IntuiFy brand):
 * --primary:    #0F172A  (dark blue tech - backgrounds)
 * --accent:     #6366F1  (indigo vibrant - CTAs, links, focus)
 * --secondary:  #8B5CF6  (purple - badges, gradient accent)
 * --text-light: #F8FAFC  (text on dark)
 * --text-dark:  #1E293B  (text on light)
 * --bg-light:   #F8FAFC  (light sections)
 * --bg-dark:    #0F172A  (dark sections)
 */

declare(strict_types=1);

// ============================================================================
// LANGUAGE DETECTION & I18N
// ============================================================================

session_start();

// Rate limiting for form (simple session-based)
if (!isset($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}

// Detect language
function detectLanguage(): string
{
    // Priority 1: Query string override
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'it', 'en'])) {
        $_SESSION['lang'] = $_GET['lang'];
        return $_GET['lang'];
    }

    // Priority 2: Session
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }

    // Priority 3: Browser Accept-Language
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
        // Bot detected - silently accept but don't process
        echo json_encode(['success' => true]);
        exit;
    }

    // Anti-spam: Time trap (form must take > 3 seconds)
    $formTime = intval($_POST['_timestamp'] ?? 0);
    if (time() - $formTime < 3) {
        echo json_encode(['success' => false, 'error' => 'Please wait a moment before submitting.']);
        exit;
    }

    // Anti-spam: Rate limiting (max 3 submissions per 5 minutes)
    $now = time();
    $_SESSION['form_submissions'] = array_filter(
        $_SESSION['form_submissions'],
        fn($ts) => $now - $ts < 300
    );

    if (count($_SESSION['form_submissions']) >= 3) {
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait.']);
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

    // Send to n8n webhook
    $webhookUrl = 'https://n8n-n8n-60a08c-72-60-34-31.traefik.me/webhook/73129db0-a899-412d-b0f3-0a32fac8b692';

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
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for testing
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log for debugging (remove in production)
    error_log("Webhook Response: HTTP $httpCode - $response - Error: $curlError");

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        $errorMsg = $curlError ? $curlError : 'Server error. Please try again.';
        echo json_encode(['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode]);
    }
    exit;
}

// Service icons (inline SVG paths for performance)
$serviceIcons = [
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'
];

// Benefit icons
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
    <title>
        <?= htmlspecialchars($t['meta']['title']) ?>
    </title>
    <meta name="description" content="<?= htmlspecialchars($t['meta']['description']) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0F172A',
                        accent: '#6366F1',
                        secondary: '#8B5CF6',
                        'text-light': '#F8FAFC',
                        'text-dark': '#1E293B',
                        'bg-light': '#F8FAFC',
                        'bg-dark': '#0F172A'
                    },
                    fontFamily: {
                        display: ['Outfit', 'sans-serif'],
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        /* CSS Variables for palette */
        :root {
            --primary: #0F172A;
            --accent: #6366F1;
            --secondary: #8B5CF6;
            --text-light: #F8FAFC;
            --text-dark: #1E293B;
            --bg-light: #F8FAFC;
            --bg-dark: #0F172A;
        }

        /* Smooth scroll offset for fixed header */
        html {
            scroll-padding-top: 5rem;
        }

        /* Focus ring for accessibility */
        .focus-ring {
            @apply focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-primary;
        }

        /* Active nav link */
        .nav-link.active {
            color: var(--accent);
        }

        /* Card hover effect */
        .card-hover {
            transition: transform 200ms ease, box-shadow 200ms ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(99, 102, 241, 0.15);
        }

        /* Gradient border effect */
        .gradient-border {
            position: relative;
        }

        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            padding: 1px;
            border-radius: inherit;
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* Subtle animation */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        /* Background pattern */
        .bg-grid {
            background-image:
                linear-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }
    </style>
</head>

<body class="bg-primary text-text-light font-sans antialiased">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <!-- ================================================================
             HERO SECTION #inicio
             ================================================================ -->
        <section id="inicio" class="relative min-h-screen flex items-center justify-center overflow-hidden bg-grid">
            <!-- Background decorations -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
                <div class="absolute top-1/4 -left-32 w-96 h-96 bg-accent/20 rounded-full blur-3xl animate-float"></div>
                <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-secondary/20 rounded-full blur-3xl animate-float"
                    style="animation-delay: 3s;"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 md:py-40 text-center">
                <!-- Badge -->
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-accent/10 border border-accent/20 rounded-full mb-8">
                    <span class="w-2 h-2 bg-accent rounded-full animate-pulse"></span>
                    <span class="text-sm font-medium text-accent">
                        <?= htmlspecialchars($t['hero']['badge']) ?>
                    </span>
                </div>

                <!-- H1 -->
                <h1
                    class="font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-text-light leading-tight mb-6">
                    <?= htmlspecialchars($t['hero']['title']) ?>
                </h1>

                <!-- Subtitle -->
                <p class="max-w-2xl mx-auto text-lg md:text-xl text-text-light/70 leading-relaxed mb-10">
                    <?= htmlspecialchars($t['hero']['subtitle']) ?>
                </p>

                <!-- CTAs -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="#contacto"
                        class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-accent to-secondary rounded-xl hover:opacity-90 transition-opacity duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-primary shadow-xl shadow-accent/25">
                        <?= htmlspecialchars($t['hero']['cta_primary']) ?>
                        <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                    <a href="#servicio"
                        class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-text-light border border-white/20 rounded-xl hover:bg-white/5 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        <?= htmlspecialchars($t['hero']['cta_secondary']) ?>
                    </a>
                </div>
            </div>

            <!-- Scroll indicator -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce" aria-hidden="true">
                <svg class="w-6 h-6 text-text-light/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            </div>
        </section>

        <!-- ================================================================
             SERVICES SECTION #servicio
             ================================================================ -->
        <section id="servicio" class="relative py-24 md:py-32 bg-bg-light">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section header -->
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-bold text-text-dark mb-4">
                        <?= htmlspecialchars($t['services']['title']) ?>
                    </h2>
                    <p class="max-w-2xl mx-auto text-lg text-text-dark/60">
                        <?= htmlspecialchars($t['services']['subtitle']) ?>
                    </p>
                </div>

                <!-- Services grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php foreach ($t['services']['items'] as $i => $service): ?>
                        <article class="group bg-white rounded-2xl p-6 lg:p-8 shadow-sm border border-gray-100 card-hover">
                            <!-- Icon -->
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 bg-accent/10 text-accent rounded-xl mb-5 group-hover:bg-accent group-hover:text-white transition-colors duration-200">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    aria-hidden="true">
                                    <?= $serviceIcons[$i] ?? $serviceIcons[0] ?>
                                </svg>
                            </div>

                            <!-- Title -->
                            <h3 class="font-display text-xl font-bold text-text-dark mb-2">
                                <?= htmlspecialchars($service['title']) ?>
                            </h3>

                            <!-- Description -->
                            <p class="text-sm text-accent font-medium mb-4">
                                <?= htmlspecialchars($service['description']) ?>
                            </p>

                            <!-- Features list -->
                            <ul class="space-y-2">
                                <?php foreach ($service['features'] as $feature): ?>
                                    <li class="flex items-start gap-2 text-sm text-text-dark/70">
                                        <svg class="w-4 h-4 text-accent mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        <?= htmlspecialchars($feature) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             BENEFITS SECTION #beneficios
             ================================================================ -->
        <section id="beneficios" class="relative py-24 md:py-32 bg-primary">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section header -->
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-bold text-text-light mb-4">
                        <?= htmlspecialchars($t['benefits']['title']) ?>
                    </h2>
                    <p class="max-w-2xl mx-auto text-lg text-text-light/60">
                        <?= htmlspecialchars($t['benefits']['subtitle']) ?>
                    </p>
                </div>

                <!-- Benefits grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php foreach ($t['benefits']['items'] as $i => $benefit): ?>
                        <article
                            class="group bg-white/5 backdrop-blur-sm rounded-2xl p-6 lg:p-8 border border-white/10 card-hover">
                            <!-- Icon -->
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 bg-accent/20 text-accent rounded-xl mb-5">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    aria-hidden="true">
                                    <?= $benefitIcons[$i] ?? $benefitIcons[0] ?>
                                </svg>
                            </div>

                            <!-- Title -->
                            <h3 class="font-display text-lg font-bold text-text-light mb-3">
                                <?= htmlspecialchars($benefit['title']) ?>
                            </h3>

                            <!-- Description -->
                            <p class="text-sm text-text-light/60 leading-relaxed">
                                <?= htmlspecialchars($benefit['description']) ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             TESTIMONIALS SECTION #testimonios
             ================================================================ -->
        <section id="testimonios" class="relative py-24 md:py-32 bg-bg-light">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section header -->
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-bold text-text-dark mb-4">
                        <?= htmlspecialchars($t['testimonials']['title']) ?>
                    </h2>
                    <p class="max-w-2xl mx-auto text-lg text-text-dark/60">
                        <?= htmlspecialchars($t['testimonials']['subtitle']) ?>
                    </p>
                </div>

                <!-- Testimonials grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                    <?php foreach ($t['testimonials']['items'] as $testimonial): ?>
                        <article class="bg-white rounded-2xl p-6 lg:p-8 shadow-sm border border-gray-100">
                            <!-- Quote -->
                            <blockquote class="relative">
                                <svg class="absolute -top-2 -left-2 w-8 h-8 text-accent/20" fill="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path
                                        d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                                </svg>
                                <p class="relative text-text-dark/80 leading-relaxed pl-6">
                                    "
                                    <?= htmlspecialchars($testimonial['quote']) ?>"
                                </p>
                            </blockquote>

                            <!-- Author -->
                            <div class="mt-6 flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-gradient-to-br from-accent to-secondary rounded-full flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($testimonial['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-text-dark">
                                        <?= htmlspecialchars($testimonial['name']) ?>
                                    </p>
                                    <p class="text-sm text-text-dark/60">
                                        <?= htmlspecialchars($testimonial['role']) ?> –
                                        <?= htmlspecialchars($testimonial['company']) ?>
                                    </p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             DEMO CTA SECTION #demo
             ================================================================ -->
        <section id="demo"
            class="relative py-20 md:py-28 bg-gradient-to-br from-accent via-accent to-secondary overflow-hidden">
            <!-- Background decoration -->
            <div class="absolute inset-0 opacity-10" aria-hidden="true">
                <div class="absolute inset-0 bg-grid"></div>
            </div>

            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-4">
                    <?= htmlspecialchars($t['demo']['title']) ?>
                </h2>
                <p class="text-lg md:text-xl text-white/80 mb-8 max-w-2xl mx-auto">
                    <?= htmlspecialchars($t['demo']['subtitle']) ?>
                </p>
                <button id="demo-cta-btn" type="button"
                    class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-accent bg-white rounded-xl hover:bg-gray-50 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-accent shadow-xl">
                    <?= htmlspecialchars($t['demo']['cta']) ?>
                    <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </button>
            </div>
        </section>

        <!-- ================================================================
             CONTACT SECTION #contacto
             ================================================================ -->
        <section id="contacto" class="relative py-24 md:py-32 bg-primary">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Left column: Why choose us -->
                    <div>
                        <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-bold text-text-light mb-4">
                            <?= htmlspecialchars($t['contact']['title']) ?>
                        </h2>
                        <p class="text-lg text-text-light/60 mb-8">
                            <?= htmlspecialchars($t['contact']['subtitle']) ?>
                        </p>

                        <h3 class="font-semibold text-lg text-text-light mb-4">
                            <?= htmlspecialchars($t['contact']['reasons_title']) ?>
                        </h3>

                        <ul class="space-y-4 mb-8">
                            <?php foreach ($t['contact']['reasons'] as $reason): ?>
                                <li class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 w-6 h-6 bg-accent/20 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-text-light/80">
                                        <?= htmlspecialchars($reason) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Email -->
                        <a href="mailto:info@intuify.net"
                            class="inline-flex items-center gap-2 text-accent hover:text-secondary transition-colors duration-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            info@intuify.net
                        </a>
                    </div>

                    <!-- Right column: Form -->
                    <div class="relative">
                        <div class="gradient-border bg-white/5 backdrop-blur-sm rounded-2xl p-6 md:p-8">
                            <form id="contact-form" class="space-y-5" novalidate>
                                <!-- Honeypot (hidden) -->
                                <div class="hidden" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                                </div>

                                <!-- Time trap -->
                                <input type="hidden" name="_timestamp" id="form-timestamp" value="">
                                <input type="hidden" name="ajax_submit" value="1">

                                <!-- Name -->
                                <div>
                                    <label for="nombre_completo" class="block text-sm font-medium text-text-light mb-2">
                                        <?= htmlspecialchars($t['contact']['form']['name']) ?> <span
                                            class="text-accent">*</span>
                                    </label>
                                    <input type="text" id="nombre_completo" name="nombre_completo" required
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['name_placeholder']) ?>"
                                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-text-light placeholder-text-light/40 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all duration-200">
                                </div>

                                <!-- Company -->
                                <div>
                                    <label for="empresa" class="block text-sm font-medium text-text-light mb-2">
                                        <?= htmlspecialchars($t['contact']['form']['company']) ?> <span
                                            class="text-accent">*</span>
                                    </label>
                                    <input type="text" id="empresa" name="empresa" required
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['company_placeholder']) ?>"
                                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-text-light placeholder-text-light/40 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all duration-200">
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-text-light mb-2">
                                        <?= htmlspecialchars($t['contact']['form']['email']) ?> <span
                                            class="text-accent">*</span>
                                    </label>
                                    <input type="email" id="email" name="email" required
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['email_placeholder']) ?>"
                                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-text-light placeholder-text-light/40 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all duration-200">
                                </div>

                                <!-- Message -->
                                <div>
                                    <label for="mensaje" class="block text-sm font-medium text-text-light mb-2">
                                        <?= htmlspecialchars($t['contact']['form']['message']) ?> <span
                                            class="text-accent">*</span>
                                    </label>
                                    <textarea id="mensaje" name="mensaje" rows="4" required
                                        placeholder="<?= htmlspecialchars($t['contact']['form']['message_placeholder']) ?>"
                                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-text-light placeholder-text-light/40 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all duration-200 resize-none"></textarea>
                                </div>

                                <!-- Submit -->
                                <button type="submit" id="submit-btn"
                                    class="w-full inline-flex items-center justify-center px-6 py-4 text-base font-semibold text-white bg-gradient-to-r from-accent to-secondary rounded-xl hover:opacity-90 transition-opacity duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-primary disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span id="btn-text">
                                        <?= htmlspecialchars($t['contact']['form']['submit']) ?>
                                    </span>
                                    <svg id="btn-spinner" class="hidden w-5 h-5 ml-2 animate-spin" fill="none"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </button>

                                <!-- Status messages -->
                                <div id="form-success"
                                    class="hidden p-4 bg-green-500/20 border border-green-500/30 rounded-xl text-green-400 text-center">
                                    <?= htmlspecialchars($t['contact']['form']['success']) ?>
                                </div>
                                <div id="form-error"
                                    class="hidden p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-red-400 text-center">
                                    <?= htmlspecialchars($t['contact']['form']['error']) ?>
                                </div>
                            </form>
                        </div>
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
            (function () {
                'use strict';

                // Set form timestamp for time-trap
                document.getElementById('form-timestamp').value = Math.floor(Date.now() / 1000);

                // Mobile menu toggle
                const menuBtn = document.getElementById('mobile-menu-btn');
                const mobileMenu = document.getElementById('mobile-menu');
                const iconOpen = document.getElementById('menu-icon-open');
                const iconClose = document.getElementById('menu-icon-close');

                if (menuBtn && mobileMenu) {
                    menuBtn.addEventListener('click', function () {
                        const isOpen = !mobileMenu.classList.contains('hidden');
                        mobileMenu.classList.toggle('hidden');
                        iconOpen.classList.toggle('hidden');
                        iconClose.classList.toggle('hidden');
                        menuBtn.setAttribute('aria-expanded', !isOpen);
                    });

                    // Close menu on link click
                    mobileMenu.querySelectorAll('a').forEach(function (link) {
                        link.addEventListener('click', function () {
                            mobileMenu.classList.add('hidden');
                            iconOpen.classList.remove('hidden');
                            iconClose.classList.add('hidden');
                            menuBtn.setAttribute('aria-expanded', 'false');
                        });
                    });
                }

                // Active section highlighting with IntersectionObserver
                const sections = document.querySelectorAll('section[id]');
                const navLinks = document.querySelectorAll('.nav-link');

                const observerOptions = {
                    root: null,
                    rootMargin: '-20% 0px -60% 0px',
                    threshold: 0
                };

                const observer = new IntersectionObserver(function (entries) {
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
                    observer.observe(section);
                });

                // Demo CTA button - scroll to contact and prefill message
                const demoCta = document.getElementById('demo-cta-btn');
                const mensajeField = document.getElementById('mensaje');

                if (demoCta && mensajeField) {
                    demoCta.addEventListener('click', function () {
                        const demoMessage = {
                            'es': 'Solicito demo gratuita',
                            'it': 'Richiedo demo gratuita',
                            'en': 'I request a free demo'
                        };
                        const lang = document.documentElement.lang || 'en';
                        mensajeField.value = demoMessage[lang] || demoMessage['en'];
                        document.getElementById('contacto').scrollIntoView({ behavior: 'smooth' });
                        setTimeout(function () {
                            document.getElementById('nombre_completo').focus();
                        }, 500);
                    });
                }

                // Form submission
                const form = document.getElementById('contact-form');
                const submitBtn = document.getElementById('submit-btn');
                const btnText = document.getElementById('btn-text');
                const btnSpinner = document.getElementById('btn-spinner');
                const successMsg = document.getElementById('form-success');
                const errorMsg = document.getElementById('form-error');

                if (form) {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        // Hide previous messages
                        successMsg.classList.add('hidden');
                        errorMsg.classList.add('hidden');

                        // Show loading
                        submitBtn.disabled = true;
                        btnText.textContent = '<?= addslashes($t['contact']['form']['sending']) ?>';
                        btnSpinner.classList.remove('hidden');

                        try {
                            const formData = new FormData(form);
                            const response = await fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            });

                            const result = await response.json();

                            if (result.success) {
                                successMsg.classList.remove('hidden');
                                form.reset();
                                document.getElementById('form-timestamp').value = Math.floor(Date.now() / 1000);
                            } else {
                                errorMsg.textContent = result.error || '<?= addslashes($t['contact']['form']['error']) ?>';
                                errorMsg.classList.remove('hidden');
                            }
                        } catch (err) {
                            errorMsg.classList.remove('hidden');
                        }

                        // Reset button
                        submitBtn.disabled = false;
                        btnText.textContent = '<?= addslashes($t['contact']['form']['submit']) ?>';
                        btnSpinner.classList.add('hidden');
                    });
                }
            })();
    </script>
</body>

</html>