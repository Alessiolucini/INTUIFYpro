<?php
/**
 * IntuiFy - Futuristic Dark Header Component
 * Floating dark glass navbar with neon glow, responsive, i18n-ready.
 */
?>
<header id="main-header" class="fixed top-0 left-0 right-0 z-50 mt-4 md:mt-5 mx-auto w-[92%] max-w-6xl transition-all duration-700 ease-[cubic-bezier(0.32,0.72,0,1)]">
    <div class="relative w-full rounded-full bg-[#0d0d14]/80 backdrop-blur-2xl border border-white/[0.06] shadow-2xl shadow-black/40 px-4 md:px-6 py-2.5 md:py-3 transition-all duration-500">
        
        <nav class="flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="#inicio"
                class="flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 rounded-full transition-transform duration-300 hover:scale-102"
                aria-label="IntuiFy - <?= $t['nav']['inicio'] ?>">
                <img src="logo/intuifylogo.svg?v=<?= time() ?>" alt="IntuiFy" class="h-5 md:h-6 w-auto invert brightness-200" width="96" height="28">
            </a>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center gap-1 bg-white/[0.04] p-1 rounded-full border border-white/[0.06]">
                <a href="#inicio" data-section="inicio"
                    class="nav-link px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition-all duration-500 rounded-full focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-400">
                    <?= $t['nav']['inicio'] ?>
                </a>
                <a href="#portfolio" data-section="portfolio"
                    class="nav-link px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition-all duration-500 rounded-full focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-400">
                    <?= $t['nav']['portfolio'] ?>
                </a>
                <a href="#servicio" data-section="servicio"
                    class="nav-link px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition-all duration-500 rounded-full focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-400">
                    <?= $t['nav']['servicios'] ?>
                </a>
                <a href="#contacto" data-section="contacto"
                    class="nav-link px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition-all duration-500 rounded-full focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-400">
                    <?= $t['nav']['contacto'] ?>
                </a>
            </div>

            <!-- Language Switcher + CTA -->
            <div class="flex items-center gap-3">
                <!-- Segmented Language Picker -->
                <div class="hidden sm:flex items-center bg-white/[0.04] p-0.5 rounded-full border border-white/[0.06]">
                    <a href="?lang=es"
                        class="px-2.5 py-1 text-[10px] font-bold rounded-full <?= $currentLang === 'es' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300"
                        aria-label="Español">ES</a>
                    <a href="?lang=it"
                        class="px-2.5 py-1 text-[10px] font-bold rounded-full <?= $currentLang === 'it' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300"
                        aria-label="Italiano">IT</a>
                    <a href="?lang=en"
                        class="px-2.5 py-1 text-[10px] font-bold rounded-full <?= $currentLang === 'en' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300"
                        aria-label="English">EN</a>
                </div>

                <!-- Primary CTA -->
                <a href="#contacto"
                    class="group hidden sm:inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] rounded-full transition-all duration-500 shadow-lg shadow-indigo-500/20">
                    <span><?= $t['nav']['cta'] ?></span>
                    <span class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center transition-transform duration-500 group-hover:translate-x-0.5">
                        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </span>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" type="button"
                    class="md:hidden relative w-9 h-9 flex flex-col items-center justify-center rounded-full bg-white/[0.06] hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    aria-expanded="false" aria-controls="mobile-menu" aria-label="Toggle menu">
                    <span id="hamburger-line-1" class="w-4 h-0.5 bg-white rounded transition-transform duration-500 translate-y-[-3px]"></span>
                    <span id="hamburger-line-2" class="w-4 h-0.5 bg-white rounded transition-transform duration-500 translate-y-[3px]"></span>
                </button>
            </div>
        </nav>

        <!-- Mobile Menu -->
        <div id="mobile-menu"
            class="md:hidden absolute top-[110%] left-0 right-0 w-full bg-[#0d0d14]/95 backdrop-blur-2xl border border-white/[0.06] rounded-3xl p-6 shadow-2xl transition-all duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] opacity-0 pointer-events-none translate-y-4 scale-95 z-40">
            <div class="flex flex-col gap-4">
                <a href="#inicio" data-section="inicio"
                    class="mobile-nav-link block px-4 py-3 text-base font-semibold text-slate-300 hover:text-white hover:bg-white/5 rounded-2xl transition-all duration-300">
                    <?= $t['nav']['inicio'] ?>
                </a>
                <a href="#portfolio" data-section="portfolio"
                    class="mobile-nav-link block px-4 py-3 text-base font-semibold text-slate-300 hover:text-white hover:bg-white/5 rounded-2xl transition-all duration-300">
                    <?= $t['nav']['portfolio'] ?>
                </a>
                <a href="#servicio" data-section="servicio"
                    class="mobile-nav-link block px-4 py-3 text-base font-semibold text-slate-300 hover:text-white hover:bg-white/5 rounded-2xl transition-all duration-300">
                    <?= $t['nav']['servicios'] ?>
                </a>
                <a href="#contacto" data-section="contacto"
                    class="mobile-nav-link block px-4 py-3 text-base font-semibold text-slate-300 hover:text-white hover:bg-white/5 rounded-2xl transition-all duration-300">
                    <?= $t['nav']['contacto'] ?>
                </a>

                <!-- Mobile Language Picker -->
                <div class="flex items-center justify-between px-4 py-3 border-t border-white/[0.06] mt-2">
                    <span class="text-xs font-bold text-slate-500">Language:</span>
                    <div class="flex items-center bg-white/[0.04] p-0.5 rounded-full border border-white/[0.06]">
                        <a href="?lang=es"
                            class="px-3 py-1 text-xs font-bold rounded-full <?= $currentLang === 'es' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300">ES</a>
                        <a href="?lang=it"
                            class="px-3 py-1 text-xs font-bold rounded-full <?= $currentLang === 'it' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300">IT</a>
                        <a href="?lang=en"
                            class="px-3 py-1 text-xs font-bold rounded-full <?= $currentLang === 'en' ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-white' ?> transition-all duration-300">EN</a>
                    </div>
                </div>

                <!-- Mobile CTA -->
                <a href="#contacto"
                    class="block w-full text-center py-3.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-full transition-colors mt-2 shadow-lg shadow-indigo-500/20">
                    <?= $t['nav']['cta'] ?>
                </a>
            </div>
        </div>
    </div>
</header>