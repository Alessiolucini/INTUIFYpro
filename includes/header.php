<?php
/**
 * IntuiFy — Header: floating glass navbar, language picker, quote CTA, mobile menu.
 * Expects $t (i18n) and $currentLang. On pages other than the home, $navBase = '/' so
 * anchors point back to the landing (e.g. "/#servicios").
 */
$navBase = $navBase ?? '';
$navItems = [
    'servicios' => $t['nav']['services'],
    'proyectos' => $t['nav']['projects'],
    'metodo'    => $t['nav']['method'],
    'agencias'  => $t['nav']['agencies'],
    'empresa'   => $t['nav']['company'],
    'contacto'  => $t['nav']['contact'],
];
$langLabels = ['es' => 'Español', 'it' => 'Italiano', 'en' => 'English'];
?>
<header id="main-header" class="site-header">
    <div class="site-header-bar">
        <nav class="flex items-center justify-between gap-3" aria-label="Principal">
            <a href="<?= $navBase ?>#inicio" class="flex items-center rounded-full focus-visible:ring-2 focus-visible:ring-indigo-400 shrink-0" aria-label="IntuiFy">
                <img src="<?= $navBase === '' ? '' : '/' ?>logo/intuifylogo.svg" alt="IntuiFy" class="h-5 md:h-6 w-auto invert brightness-200" width="96" height="28">
            </a>

            <div class="hidden lg:flex items-center gap-1 bg-white/[0.04] p-1 rounded-full border border-white/[0.06]">
                <?php foreach ($navItems as $id => $label): ?>
                    <a href="<?= $navBase ?>#<?= $id ?>" data-section="<?= $id ?>" class="nav-link px-3.5 py-2 text-xs font-semibold text-slate-300 hover:text-white rounded-full transition-colors"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="hidden sm:flex items-center bg-white/[0.04] p-0.5 rounded-full border border-white/[0.06]" role="group" aria-label="<?= htmlspecialchars($t['nav']['language']) ?>">
                    <?php foreach ($langLabels as $code => $name): ?>
                        <a href="?lang=<?= $code ?>" lang="<?= $code ?>" hreflang="<?= $code ?>" aria-label="<?= $name ?>" <?= $currentLang === $code ? 'aria-current="true"' : '' ?>
                           class="px-2.5 py-1 text-[10px] font-bold rounded-full transition-colors <?= $currentLang === $code ? 'bg-white/15 text-white' : 'text-slate-400 hover:text-white' ?>"><?= strtoupper($code) ?></a>
                    <?php endforeach; ?>
                </div>
                <a href="<?= $navBase ?>#contacto" class="hidden sm:inline-flex items-center px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-colors shadow-lg shadow-indigo-500/20">
                    <?= htmlspecialchars($t['nav']['cta']) ?>
                </a>
                <button id="mobile-menu-btn" type="button" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-full bg-white/[0.07] hover:bg-white/10 transition-colors"
                        aria-expanded="false" aria-controls="mobile-menu" aria-label="<?= htmlspecialchars($t['nav']['menu']) ?>">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </nav>

        <div id="mobile-menu" class="mobile-menu lg:hidden">
            <div class="flex flex-col gap-1">
                <?php foreach ($navItems as $id => $label): ?>
                    <a href="<?= $navBase ?>#<?= $id ?>" data-section="<?= $id ?>" class="mobile-nav-link block px-4 py-3 text-base font-semibold text-slate-200 hover:text-white hover:bg-white/5 rounded-2xl"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
                <div class="flex items-center justify-between px-4 py-3 border-t border-white/[0.08] mt-2">
                    <span class="text-xs font-bold text-slate-400"><?= htmlspecialchars($t['nav']['language']) ?></span>
                    <div class="flex items-center bg-white/[0.04] p-0.5 rounded-full border border-white/[0.06]">
                        <?php foreach ($langLabels as $code => $name): ?>
                            <a href="?lang=<?= $code ?>" lang="<?= $code ?>" aria-label="<?= $name ?>" class="px-3 py-1.5 text-xs font-bold rounded-full <?= $currentLang === $code ? 'bg-white/15 text-white' : 'text-slate-400 hover:text-white' ?>"><?= strtoupper($code) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="<?= $navBase ?>#contacto" class="block w-full text-center py-3.5 mt-1 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-colors"><?= htmlspecialchars($t['nav']['cta']) ?></a>
            </div>
        </div>
    </div>
</header>
