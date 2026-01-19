<?php
/**
 * IntuiFy - Header Component
 * Fixed header with backdrop-blur, desktop nav + mobile hamburger
 * Active section highlighting via IntersectionObserver (JS in index.php)
 */
?>
<header id="main-header" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
    <div class="absolute inset-0 bg-primary/80 backdrop-blur-xl border-b border-white/5"></div>
    <nav class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 md:h-20">
            <!-- Logo -->
            <a href="#inicio" class="flex-shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-primary rounded-lg" aria-label="IntuiFy - <?= $t['nav']['inicio'] ?>">
                <img 
                    src="assets/logo.png" 
                    alt="IntuiFy" 
                    class="h-8 md:h-10 w-auto"
                    width="120"
                    height="40"
                >
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-1">
                <a href="#inicio" data-section="inicio" class="nav-link px-4 py-2 text-sm font-medium text-text-light/70 hover:text-text-light transition-colors duration-200 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <?= $t['nav']['inicio'] ?>
                </a>
                <a href="#servicio" data-section="servicio" class="nav-link px-4 py-2 text-sm font-medium text-text-light/70 hover:text-text-light transition-colors duration-200 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <?= $t['nav']['servicios'] ?>
                </a>
                <a href="#beneficios" data-section="beneficios" class="nav-link px-4 py-2 text-sm font-medium text-text-light/70 hover:text-text-light transition-colors duration-200 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <?= $t['nav']['beneficios'] ?>
                </a>
                <a href="#testimonios" data-section="testimonios" class="nav-link px-4 py-2 text-sm font-medium text-text-light/70 hover:text-text-light transition-colors duration-200 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <?= $t['nav']['testimonios'] ?>
                </a>
                <a href="#contacto" data-section="contacto" class="nav-link px-4 py-2 text-sm font-medium text-text-light/70 hover:text-text-light transition-colors duration-200 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <?= $t['nav']['contacto'] ?>
                </a>
            </div>

            <!-- CTA + Mobile Menu Button -->
            <div class="flex items-center gap-3">
                <!-- Language Switcher -->
                <div class="hidden sm:flex items-center gap-1 mr-2">
                    <a href="?lang=es" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'es' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors" aria-label="Español">ES</a>
                    <a href="?lang=it" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'it' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors" aria-label="Italiano">IT</a>
                    <a href="?lang=en" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'en' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors" aria-label="English">EN</a>
                </div>

                <!-- CTA Button -->
                <a 
                    href="#contacto" 
                    class="hidden sm:inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-accent to-secondary rounded-xl hover:opacity-90 transition-opacity duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-primary shadow-lg shadow-accent/25"
                >
                    <?= $t['nav']['cta'] ?>
                </a>

                <!-- Mobile Menu Button -->
                <button 
                    id="mobile-menu-btn"
                    type="button"
                    class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-text-light/70 hover:text-text-light hover:bg-white/5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                    aria-label="Toggle menu"
                >
                    <svg id="menu-icon-open" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="menu-icon-close" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden absolute top-full left-0 right-0 bg-primary/95 backdrop-blur-xl border-b border-white/5">
            <div class="px-4 py-4 space-y-1">
                <a href="#inicio" data-section="inicio" class="nav-link block px-4 py-3 text-base font-medium text-text-light/70 hover:text-text-light hover:bg-white/5 rounded-lg transition-colors">
                    <?= $t['nav']['inicio'] ?>
                </a>
                <a href="#servicio" data-section="servicio" class="nav-link block px-4 py-3 text-base font-medium text-text-light/70 hover:text-text-light hover:bg-white/5 rounded-lg transition-colors">
                    <?= $t['nav']['servicios'] ?>
                </a>
                <a href="#beneficios" data-section="beneficios" class="nav-link block px-4 py-3 text-base font-medium text-text-light/70 hover:text-text-light hover:bg-white/5 rounded-lg transition-colors">
                    <?= $t['nav']['beneficios'] ?>
                </a>
                <a href="#testimonios" data-section="testimonios" class="nav-link block px-4 py-3 text-base font-medium text-text-light/70 hover:text-text-light hover:bg-white/5 rounded-lg transition-colors">
                    <?= $t['nav']['testimonios'] ?>
                </a>
                <a href="#contacto" data-section="contacto" class="nav-link block px-4 py-3 text-base font-medium text-text-light/70 hover:text-text-light hover:bg-white/5 rounded-lg transition-colors">
                    <?= $t['nav']['contacto'] ?>
                </a>
                
                <!-- Language Switcher Mobile -->
                <div class="flex items-center gap-2 px-4 py-3 border-t border-white/5 mt-2">
                    <span class="text-xs text-text-light/50 mr-2">Lang:</span>
                    <a href="?lang=es" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'es' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors">ES</a>
                    <a href="?lang=it" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'it' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors">IT</a>
                    <a href="?lang=en" class="px-2 py-1 text-xs font-medium rounded <?= $currentLang === 'en' ? 'bg-accent text-white' : 'text-text-light/60 hover:text-text-light' ?> transition-colors">EN</a>
                </div>

                <a 
                    href="#contacto" 
                    class="block mx-4 mt-4 px-5 py-3 text-center text-sm font-semibold text-white bg-gradient-to-r from-accent to-secondary rounded-xl shadow-lg shadow-accent/25"
                >
                    <?= $t['nav']['cta'] ?>
                </a>
            </div>
        </div>
    </nav>
</header>
