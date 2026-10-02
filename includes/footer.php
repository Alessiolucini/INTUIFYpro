<?php
/**
 * IntuiFy — Footer: company, services, projects, contacts, legal links.
 * Expects $t, $currentLang and $config; uses $projects / $whatsappUrl when the page has them.
 */
$navBase = $navBase ?? '';
$footerProjects = $projects ?? array_values(array_filter(require __DIR__ . '/projects.php', fn($p) => $p['confirmed']));
$footerWhatsapp = $whatsappUrl ?? '';
$assetBase = $navBase === '' ? '' : '/';
?>
<footer class="site-footer">
    <div class="max-w-6xl mx-auto px-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-10 pb-12">
            <div class="lg:col-span-4">
                <img src="<?= $assetBase ?>logo/intuifylogo.svg" alt="IntuiFy" class="h-6 w-auto invert brightness-200 mb-5" width="96" height="28">
                <p class="text-sm text-slate-400 leading-relaxed max-w-sm"><?= htmlspecialchars($t['footer']['tagline']) ?></p>
            </div>
            <div class="lg:col-span-3">
                <h4 class="footer-title"><?= htmlspecialchars($t['footer']['services_title']) ?></h4>
                <ul class="footer-links">
                    <?php foreach ($t['services']['items'] as $srv): ?>
                        <li><a href="<?= $navBase ?>#servicio-<?= htmlspecialchars($srv['id']) ?>"><?= htmlspecialchars($srv['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="lg:col-span-2">
                <h4 class="footer-title"><?= htmlspecialchars($t['footer']['projects_title']) ?></h4>
                <ul class="footer-links">
                    <?php foreach ($footerProjects as $p): ?>
                        <?php if ($p['confirmed']): ?>
                            <li><a href="<?= $navBase ?>#proyecto-<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['name']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li><a href="<?= $navBase ?>#agencias"><?= htmlspecialchars($t['nav']['agencies']) ?></a></li>
                </ul>
            </div>
            <div class="lg:col-span-3">
                <h4 class="footer-title"><?= htmlspecialchars($t['footer']['contact_title']) ?></h4>
                <ul class="footer-links">
                    <li><a href="mailto:info@intuify.net">info@intuify.net</a></li>
                    <?php if ($footerWhatsapp !== ''): ?>
                        <li><a href="<?= htmlspecialchars($footerWhatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
                    <?php endif; ?>
                    <li><?= htmlspecialchars($t['contact']['location']) ?></li>
                    <li><a href="<?= $navBase ?>#contacto" class="text-indigo-300 hover:text-white"><?= htmlspecialchars($t['nav']['cta']) ?> →</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="text-xs text-slate-500 leading-relaxed">
                <p><?= htmlspecialchars(str_replace('{year}', date('Y'), $t['footer']['copyright'])) ?></p>
                <p>IntuiFy Ventures, S.L. · <?= htmlspecialchars($t['footer']['vat']) ?> <?= htmlspecialchars((string) ($config['company_vat'] ?? '')) ?></p>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-xs" aria-label="Legal">
                <a href="<?= $assetBase ?>privacy.php?lang=<?= $currentLang ?>"><?= htmlspecialchars($t['footer']['legal']['privacy']) ?></a>
                <a href="<?= $assetBase ?>terms.php?lang=<?= $currentLang ?>"><?= htmlspecialchars($t['footer']['legal']['legal_notice']) ?></a>
                <a href="<?= $assetBase ?>privacy.php?lang=<?= $currentLang ?>#cookies"><?= htmlspecialchars($t['footer']['legal']['cookies']) ?></a>
            </nav>
        </div>
    </div>
</footer>
