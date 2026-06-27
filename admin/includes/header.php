<?php
/**
 * IntuiFy Admin — Header Component
 * Top bar with page title, breadcrumb, and quick actions.
 */
?>
<header class="sticky top-0 z-20 bg-[#08080f]/80 backdrop-blur-xl border-b border-white/[0.04]">
    <div class="flex items-center justify-between h-16 px-6">
        <!-- Page title & breadcrumb -->
        <div>
            <h1 class="text-lg font-bold text-white"><?= $pageTitle ?? 'Dashboard' ?></h1>
            <?php if (isset($breadcrumb)): ?>
                <p class="text-xs text-slate-500 mt-0.5"><?= $breadcrumb ?></p>
            <?php endif; ?>
        </div>

        <!-- Right actions -->
        <div class="flex items-center gap-3">
            <!-- Back to site -->
            <a href="/" target="_blank" class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-white bg-white/[0.04] hover:bg-white/[0.08] rounded-lg border border-white/[0.06] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
                Sito
            </a>
            
            <!-- Date/time -->
            <span class="hidden md:inline text-xs text-slate-500 font-mono">
                <?= date('d/m/Y') ?>
            </span>
        </div>
    </div>
</header>
