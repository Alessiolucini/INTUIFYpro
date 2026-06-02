<?php
/**
 * IntuiFy - Premium Footer Component (Silicon Valley / Apple Style)
 * Soft Structuralism archetype, clean typography, dynamic links
 */
$currentYear = date('Y');
?>
<footer class="relative bg-slate-50/70 border-t border-slate-200/40 py-16 md:py-24 overflow-hidden">
    <!-- Spatial Grid background -->
    <div class="absolute inset-0 pointer-events-none opacity-40" aria-hidden="true">
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:4rem_4rem]"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-6 lg:px-8">
        <!-- Main Footer Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-12 lg:gap-16 pb-12 md:pb-16 border-b border-slate-200/50">
            <!-- Brand Column -->
            <div class="lg:col-span-5 flex flex-col items-start gap-4">
                <a href="#inicio"
                    class="flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded-full transition-transform duration-300 hover:scale-102"
                    aria-label="IntuiFy - <?= $t['nav']['inicio'] ?>">
                    <img src="assets/logo.svg" alt="IntuiFy" class="h-6 md:h-7 w-auto" width="96" height="28">
                </a>
                <p class="text-slate-500 text-sm leading-relaxed max-w-sm">
                    <?= $t['footer']['tagline'] ?>
                </p>
                <div class="mt-4 p-4 rounded-3xl bg-white border border-slate-200/40 shadow-sm flex items-center gap-3">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-indigo-500"></span>
                    </span>
                    <span class="text-xs font-semibold text-slate-600">Silicon Valley Autopilot Standards</span>
                </div>
            </div>

            <!-- Links Sections -->
            <div class="lg:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-8">
                <!-- Column 1: Platform -->
                <div>
                    <h4 class="text-slate-800 font-display font-bold text-xs uppercase tracking-widest mb-4">
                        <?= $t['footer']['nav_title'] ?>
                    </h4>
                    <ul class="flex flex-col gap-2.5">
                        <li>
                            <a href="#inicio"
                                class="text-slate-500 hover:text-slate-900 text-sm font-medium transition-all duration-300 focus:outline-none focus-visible:text-accent">
                                <?= $t['nav']['inicio'] ?>
                            </a>
                        </li>
                        <li>
                            <a href="#servicio"
                                class="text-slate-500 hover:text-slate-900 text-sm font-medium transition-all duration-300 focus:outline-none focus-visible:text-accent">
                                <?= $t['nav']['servicios'] ?>
                            </a>
                        </li>
                        <li>
                            <a href="#beneficios"
                                class="text-slate-500 hover:text-slate-900 text-sm font-medium transition-all duration-300 focus:outline-none focus-visible:text-accent">
                                <?= $t['nav']['beneficios'] ?>
                            </a>
                        </li>

                    </ul>
                </div>

                <!-- Column 2: Capabilities -->
                <div>
                    <h4 class="text-slate-800 font-display font-bold text-xs uppercase tracking-widest mb-4">
                        <?= $t['footer']['services_title'] ?>
                    </h4>
                    <ul class="flex flex-col gap-2.5">
                        <?php foreach (array_slice($t['services']['items'], 0, 4) as $service): ?>
                            <li>
                                <a href="#servicio"
                                    class="text-slate-500 hover:text-slate-900 text-sm font-medium transition-all duration-300 focus:outline-none focus-visible:text-accent truncate block max-w-[150px] sm:max-w-none"
                                    title="<?= htmlspecialchars($service['title']) ?>">
                                    <?= htmlspecialchars($service['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Column 3: Contact -->
                <div class="col-span-2 sm:col-span-1">
                    <h4 class="text-slate-800 font-display font-bold text-xs uppercase tracking-widest mb-4">
                        <?= $t['footer']['contact_title'] ?>
                    </h4>
                    <ul class="flex flex-col gap-3">
                        <li>
                            <a href="mailto:info@intuify.net"
                                class="group inline-flex items-center gap-2 text-slate-500 hover:text-slate-900 text-sm font-medium transition-all duration-300">
                                <span class="w-7 h-7 rounded-full bg-white border border-slate-200/40 flex items-center justify-center shadow-sm group-hover:border-slate-300 transition-colors duration-300">
                                    <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </span>
                                <span class="group-hover:underline decoration-slate-400">info@intuify.net</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom Copyright / Certifications -->
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex flex-col gap-1 text-center sm:text-left">
                <p class="text-slate-400 text-xs font-medium">
                    <?= str_replace('{year}', $currentYear, $t['footer']['copyright']) ?>
                </p>
                <p class="text-slate-400/80 text-[10px] font-bold tracking-wider uppercase">
                    IntuiFy Ventures S.L.
                </p>
            </div>
            <div class="flex items-center gap-6">
                <a href="/privacy" class="text-slate-400 text-[10px] uppercase font-bold tracking-widest hover:text-slate-600 transition-colors duration-300">Privacy Policy</a>
                <a href="/terms" class="text-slate-400 text-[10px] uppercase font-bold tracking-widest hover:text-slate-600 transition-colors duration-300">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>