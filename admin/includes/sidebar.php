<?php
/**
 * IntuiFy Admin — Sidebar Navigation
 * Dark glassmorphism sidebar matching the landing page aesthetic.
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'chart'],
    ['page' => 'leads',     'label' => 'Leads',     'icon' => 'users-incoming'],
    ['page' => 'clients',   'label' => 'Clienti',   'icon' => 'building'],
    ['page' => 'contracts', 'label' => 'Contratti',  'icon' => 'document'],
    ['page' => 'invoices',  'label' => 'Fatture',    'icon' => 'banknote'],
    ['page' => 'expenses',  'label' => 'Spese',      'icon' => 'receipt'],
    ['page' => 'domains',   'label' => 'Domini',     'icon' => 'globe'],
    ['page' => 'products',  'label' => 'Prodotti',   'icon' => 'cube'],
    ['page' => 'ai-assistant', 'label' => '🤖 AI Assistente', 'icon' => 'sparkle'],
];

$icons = [
    'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
    'users-incoming' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>',
    'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m4.5-18v18m4.5-18v18m4.5-18v18M5.25 6h.008v.008H5.25V6zm0 3h.008v.008H5.25V9zm0 3h.008v.008H5.25V12zm4.5-6h.008v.008H9.75V6zm0 3h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm4.5-6h.008v.008h-.008V6zm0 3h.008v.008h-.008V9zm0 3h.008v.008h-.008V12z"/>',
    'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
    'banknote' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>',
    'receipt' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>',
    'globe' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>',
    'cube' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>',
    'sparkle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>',
];
?>

<!-- Sidebar -->
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-[#0a0a14]/95 backdrop-blur-2xl border-r border-white/[0.06] transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
    
    <!-- Logo -->
    <div class="flex items-center gap-3 px-6 h-16 border-b border-white/[0.06]">
        <img src="/logo/intuifylogo.svg" alt="IntuiFy" class="h-5 w-auto invert brightness-200">
        <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded-full">Admin</span>
    </div>

    <!-- Navigation -->
    <nav class="flex flex-col gap-1 p-3 mt-2">
        <?php foreach ($navItems as $item): ?>
            <a href="/admin/<?= $item['page'] ?>.php"
               class="group flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 <?= $currentPage === $item['page'] ? 'bg-indigo-500/15 text-white border border-indigo-500/20' : 'text-slate-400 hover:text-white hover:bg-white/[0.04]' ?>">
                <svg class="w-5 h-5 flex-shrink-0 <?= $currentPage === $item['page'] ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300' ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <?= $icons[$item['icon']] ?>
                </svg>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom section -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/[0.06]">
        <div class="flex items-center gap-3 px-3 py-2">
            <div class="w-8 h-8 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-300 text-xs font-bold">
                <?= strtoupper(substr(getAdminUser(), 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars(getAdminUser()) ?></p>
                <p class="text-[10px] text-slate-500">Amministratore</p>
            </div>
            <a href="/admin/logout.php" class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Logout">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile sidebar toggle -->
<button id="sidebar-toggle" class="lg:hidden fixed top-4 left-4 z-50 p-2.5 rounded-xl bg-[#12121e] border border-white/[0.08] text-slate-400 hover:text-white transition-colors">
    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
    </svg>
</button>

<!-- Sidebar overlay for mobile -->
<div id="sidebar-overlay" class="lg:hidden fixed inset-0 z-30 bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300"></div>

<script>
(function() {
    const sidebar = document.getElementById('admin-sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    
    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('opacity-0', 'pointer-events-none');
    }
    
    toggle?.addEventListener('click', openSidebar);
    overlay?.addEventListener('click', closeSidebar);
})();
</script>
