<?php
/**
 * IntuiFy - Footer Component
 * Premium footer with navigation, services, and contact info
 */
$currentYear = date('Y');
?>
<footer class="bg-primary border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
            <!-- Brand -->
            <div class="lg:col-span-1">
                <a href="#inicio"
                    class="inline-block mb-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded-lg">
                    <img src="assets/logo.png" alt="IntuiFy" class="h-8 w-auto" width="100" height="32">
                </a>
                <p class="text-text-light/60 text-sm leading-relaxed">
                    <?= $t['footer']['tagline'] ?>
                </p>
            </div>

            <!-- Navigation -->
            <div>
                <h4 class="text-text-light font-semibold text-sm uppercase tracking-wider mb-4">
                    <?= $t['footer']['nav_title'] ?>
                </h4>
                <ul class="space-y-2">
                    <li>
                        <a href="#inicio"
                            class="text-text-light/60 hover:text-text-light text-sm transition-colors duration-200">
                            <?= $t['nav']['inicio'] ?>
                        </a>
                    </li>
                    <li>
                        <a href="#servicio"
                            class="text-text-light/60 hover:text-text-light text-sm transition-colors duration-200">
                            <?= $t['nav']['servicios'] ?>
                        </a>
                    </li>
                    <li>
                        <a href="#beneficios"
                            class="text-text-light/60 hover:text-text-light text-sm transition-colors duration-200">
                            <?= $t['nav']['beneficios'] ?>
                        </a>
                    </li>
                    <li>
                        <a href="#testimonios"
                            class="text-text-light/60 hover:text-text-light text-sm transition-colors duration-200">
                            <?= $t['nav']['testimonios'] ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Services -->
            <div>
                <h4 class="text-text-light font-semibold text-sm uppercase tracking-wider mb-4">
                    <?= $t['footer']['services_title'] ?>
                </h4>
                <ul class="space-y-2">
                    <?php foreach (array_slice($t['services']['items'], 0, 4) as $service): ?>
                        <li>
                            <a href="#servicio"
                                class="text-text-light/60 hover:text-text-light text-sm transition-colors duration-200">
                                <?= htmlspecialchars($service['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="text-text-light font-semibold text-sm uppercase tracking-wider mb-4">
                    <?= $t['footer']['contact_title'] ?>
                </h4>
                <ul class="space-y-3">
                    <li>
                        <a href="mailto:info@intuify.net"
                            class="inline-flex items-center gap-2 text-text-light/60 hover:text-accent text-sm transition-colors duration-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            info@intuify.net
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="mt-12 pt-8 border-t border-white/5">
            <p class="text-center text-text-light/40 text-sm">
                <?= str_replace('{year}', $currentYear, $t['footer']['copyright']) ?>
            </p>
        </div>
    </div>
</footer>