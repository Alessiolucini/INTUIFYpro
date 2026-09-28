<?php
/**
 * IntuiFy Admin — Server Control Center (read-only MVP)
 *
 * The page is a shell: assets/server-control.js loads JSON from
 * api/server-control.php and refreshes it. The browser never talks to
 * Prometheus, Docker or Dokploy (spec §2, §55).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$config = require dirname(__DIR__) . '/config.php';

$views = [
    'overview'   => 'Overview',
    'server'     => 'Server',
    'containers' => 'Container',
    'domains'    => 'Domini & SSL',
    'alerts'     => 'Alert',
];
$view = (string) ($_GET['view'] ?? 'overview');
if (!isset($views[$view])) {
    $view = 'overview';
}

$serverName = (string) ($config['monitoring_server_name'] ?? 'INTUIFY SERVER');
$serverEnv  = (string) ($config['monitoring_server_env'] ?? 'Production');

$pageTitle  = 'Server Control';
$breadcrumb = htmlspecialchars($serverName) . ' · ' . $views[$view];

$asset = fn(string $path) => $path . '?v=' . (@filemtime(dirname(__DIR__) . $path) ?: time());
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Server Control — IntuiFy Admin</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <link rel="stylesheet" href="<?= $asset('/admin/assets/server-control.css') ?>">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <main class="p-6" id="scc" data-view="<?= $view ?>">
            <div class="scc-head">
                <div>
                    <div class="scc-eyebrow">IntuiFy Server Control</div>
                    <div class="scc-server-name">
                        <span><?= htmlspecialchars($serverName) ?></span>
                        <span class="scc-env"><?= htmlspecialchars($serverEnv) ?></span>
                        <span id="scc-status"><span class="scc-badge scc-unknown"><span class="scc-icon" aria-hidden="true">?</span>Caricamento…</span></span>
                    </div>
                </div>
                <div class="scc-meta" aria-live="polite">
                    <span id="scc-updated">In attesa dei dati…</span>
                    <span id="scc-stale" class="scc-stale" hidden>⚠ Monitoring data stale</span>
                </div>
            </div>

            <nav class="scc-tabs" aria-label="Sezioni Server Control">
                <?php foreach ($views as $key => $label): ?>
                    <a href="?view=<?= $key ?>" class="scc-tab <?= $key === $view ? 'is-active' : '' ?>" <?= $key === $view ? 'aria-current="page"' : '' ?>><?= $label ?></a>
                <?php endforeach; ?>
            </nav>

            <div id="scc-unavailable" class="scc-banner" role="status" hidden></div>

            <div id="scc-body">
                <div class="card"><p class="scc-empty">Caricamento dati dal monitoring…</p></div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="<?= $asset('/admin/assets/server-control.js') ?>"></script>
</body>
</html>
