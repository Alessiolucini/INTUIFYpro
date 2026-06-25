<?php
/**
 * IntuiFy - Futuristic Dark Footer Component
 */
?>
        <!-- ================================================================
             FOOTER
             ================================================================ -->
        <footer class="relative bg-[#06060a] border-t border-white/[0.04] pt-20 pb-8">
            <div class="max-w-6xl mx-auto px-6">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                    <!-- Brand -->
                    <div class="md:col-span-1">
                        <img src="logo/intuifylogo.svg" alt="IntuiFy" class="h-6 w-auto invert brightness-200 mb-5">
                        <p class="text-slate-500 text-sm leading-relaxed max-w-sm">
                            <?= $t['footer']['tagline'] ?>
                        </p>
                    </div>

                    <!-- Nav Links -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4"><?= $t['footer']['nav_title'] ?></h4>
                        <ul class="flex flex-col gap-3">
                            <li><a href="#inicio" class="text-sm text-slate-500 hover:text-white transition-colors"><?= $t['nav']['inicio'] ?></a></li>
                            <li><a href="#portfolio" class="text-sm text-slate-500 hover:text-white transition-colors"><?= $t['nav']['portfolio'] ?></a></li>
                            <li><a href="#servicio" class="text-sm text-slate-500 hover:text-white transition-colors"><?= $t['nav']['servicios'] ?></a></li>
                            <li><a href="#beneficios" class="text-sm text-slate-500 hover:text-white transition-colors"><?= $t['nav']['beneficios'] ?></a></li>
                        </ul>
                    </div>

                    <!-- Services Links -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4"><?= $t['footer']['services_title'] ?></h4>
                        <ul class="flex flex-col gap-3">
                            <?php foreach (array_slice($t['services']['items'], 0, 4) as $srv): ?>
                                <li class="text-sm text-slate-500"><?= htmlspecialchars($srv['title']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Contact -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4"><?= $t['footer']['contact_title'] ?></h4>
                        <ul class="flex flex-col gap-3">
                            <li><a href="mailto:info@intuify.com" class="text-sm text-slate-500 hover:text-white transition-colors">info@intuify.com</a></li>
                            <li><a href="#contacto" class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors font-semibold"><?= $t['nav']['cta'] ?> →</a></li>
                        </ul>
                        <!-- Store badges small -->
                        <div class="flex items-center gap-3 mt-5">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/[0.04] border border-white/[0.06]">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                                <span class="text-[10px] font-bold text-slate-400">App Store</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/[0.04] border border-white/[0.06]">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-1.4l2.584 1.496c.906.524.906 1.37 0 1.894l-2.177 1.26-2.536-2.536 2.13-2.115zM5.864 2.658L16.8 8.99l-2.302 2.302-8.635-8.634z"/></svg>
                                <span class="text-[10px] font-bold text-slate-400">Google Play</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="border-t border-white/[0.04] pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-xs text-slate-600">
                        <?= str_replace('{year}', date('Y'), $t['footer']['copyright']) ?>
                    </p>
                    <div class="flex items-center gap-6">
                        <a href="privacy.php" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Privacy</a>
                        <a href="terms.php" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Terms</a>
                    </div>
                </div>
            </div>
        </footer>