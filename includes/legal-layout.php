<?php
/**
 * IntuiFy — shared layout for legal pages (privacy/cookies, legal notice).
 * Same header, footer and visual identity as the landing, without the WebGL scene.
 *
 * Usage: renderLegalPage($docs) where $docs = ['es' => [...], 'it' => [...], 'en' => [...]],
 * each with: meta_title, meta_description, title, updated, sections[] (id?, title, html, list[]?).
 */

declare(strict_types=1);

function renderLegalPage(array $docs): void
{
    session_start();
    $config = require dirname(__DIR__) . '/config.php';

    $langs = ['es', 'it', 'en'];
    $lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? null);
    if (!is_string($lang) || !in_array($lang, $langs, true)) {
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $lang = str_starts_with($accept, 'it') ? 'it' : (str_starts_with($accept, 'en') ? 'en' : 'es');
    }
    $_SESSION['lang'] = $lang;

    $currentLang = $lang;
    $t = json_decode((string) file_get_contents(dirname(__DIR__) . "/i18n/{$lang}.json"), true);
    $doc = $docs[$lang];
    $isPreview = !empty($config['site_preview']);
    $navBase = '/';
    $whatsappNumber = (string) ($config['whatsapp_number'] ?? '');
    $whatsappUrl = $whatsappNumber !== '' ? 'https://wa.me/' . $whatsappNumber : '';
    $self = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $asset = function (string $path): string {
        $full = dirname(__DIR__) . '/' . $path;
        return '/' . $path . '?v=' . (is_file($full) ? filemtime($full) : time());
    };
    ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc['meta_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($doc['meta_description']) ?>">
    <meta name="robots" content="<?= $isPreview ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="canonical" href="https://intuify.net<?= htmlspecialchars($self) ?>?lang=<?= $lang ?>">
    <?php foreach ($langs as $l): ?>
    <link rel="alternate" hreflang="<?= $l ?>" href="https://intuify.net<?= htmlspecialchars($self) ?>?lang=<?= $l ?>">
    <?php endforeach; ?>
    <link rel="icon" href="/logo/intuifylogo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        *, *::before, *::after { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: 'Space Grotesk', sans-serif; }
        .nav-link-active { background: rgba(255,255,255,0.08) !important; color: #fff !important; }
    </style>
    <link rel="stylesheet" href="<?= $asset('assets/css/nova.css') ?>">
    <link rel="stylesheet" href="<?= $asset('assets/css/site.css') ?>">
</head>
<body class="text-slate-300 antialiased nova-static">
    <div id="nova-fallback" aria-hidden="true" style="opacity:1"></div>
    <?php if ($isPreview): ?>
        <div class="preview-banner" role="status"><?= htmlspecialchars($t['preview']['banner']) ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/header.php'; ?>

    <main class="legal">
        <h1><?= htmlspecialchars($doc['title']) ?></h1>
        <p class="updated"><?= htmlspecialchars($doc['updated']) ?></p>
        <?php if ($isPreview && !empty($doc['review_note'])): ?>
            <p class="legal-review"><?= htmlspecialchars($doc['review_note']) ?></p>
        <?php endif; ?>
        <?php foreach ($doc['sections'] as $s): ?>
            <section<?= !empty($s['id']) ? ' id="' . htmlspecialchars($s['id']) . '"' : '' ?>>
                <h2><?= htmlspecialchars($s['title']) ?></h2>
                <?php if (!empty($s['html'])): ?><p><?= $s['html'] ?></p><?php endif; ?>
                <?php if (!empty($s['list'])): ?>
                    <ul><?php foreach ($s['list'] as $li): ?><li><?= $li ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($s['after'])): ?><p class="mt-3"><?= $s['after'] ?></p><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
    <script>
        const btn = document.getElementById('mobile-menu-btn'), menu = document.getElementById('mobile-menu');
        btn?.addEventListener('click', () => { const open = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(open)); menu.classList.toggle('is-open', open); });
    </script>
</body>
</html>
<?php
}
