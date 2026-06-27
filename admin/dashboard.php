<?php
/**
 * IntuiFy Admin — Dashboard KPI
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Dashboard';
$breadcrumb = 'Panoramica generale';

$sb = getSupabase();

// Fetch KPIs via RPC
$kpis = $sb->rpc('get_dashboard_kpis');
if (is_string($kpis)) {
    $kpis = json_decode($kpis, true);
}
$kpis = $kpis ?? [
    'total_revenue' => 0,
    'total_expenses' => 0,
    'pending_invoices' => 0,
    'new_leads_month' => 0,
    'active_contracts' => 0,
    'expiring_domains' => 0,
    'total_clients' => 0,
    'total_products' => 0,
];

$profit = ($kpis['total_revenue'] ?? 0) - ($kpis['total_expenses'] ?? 0);

// Fetch recent leads
$recentLeads = $sb->select('leads', [
    'select' => '*',
    'order' => 'created_at.desc',
    'limit' => 5,
]);

// Fetch overdue invoices
$overdueInvoices = $sb->select('invoices', [
    'select' => '*,clients(company_name)',
    'filters' => ['status' => 'in.(sent,overdue)'],
    'order' => 'due_date.asc',
    'limit' => 5,
]);

// Fetch expiring domains
$expiringDomains = $sb->select('domains', [
    'order' => 'expiry_date.asc',
    'limit' => 5,
    'filters' => ['expiry_date' => 'gte.' . date('Y-m-d')],
]);

// Monthly revenue data for chart
$monthlyRevenue = $sb->rpc('get_monthly_revenue', ['months_back' => 12]) ?? [];
$monthlyExpenses = $sb->rpc('get_monthly_expenses', ['months_back' => 12]) ?? [];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard — IntuiFy Admin</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <main class="p-6 space-y-6">
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Revenue -->
                <div class="kpi-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value text-emerald-400">€<?= number_format((float)($kpis['total_revenue'] ?? 0), 0, ',', '.') ?></div>
                    <div class="kpi-label">Totale Incassato</div>
                </div>

                <!-- Expenses -->
                <div class="kpi-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-red-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value text-red-400">€<?= number_format((float)($kpis['total_expenses'] ?? 0), 0, ',', '.') ?></div>
                    <div class="kpi-label">Totale Spese</div>
                </div>

                <!-- Profit -->
                <div class="kpi-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value <?= $profit >= 0 ? 'text-indigo-400' : 'text-red-400' ?>">€<?= number_format($profit, 0, ',', '.') ?></div>
                    <div class="kpi-label">Profitto Netto</div>
                </div>

                <!-- Leads this month -->
                <div class="kpi-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value text-amber-400"><?= (int)($kpis['new_leads_month'] ?? 0) ?></div>
                    <div class="kpi-label">Leads Questo Mese</div>
                </div>
            </div>

            <!-- Second row of smaller KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="kpi-card">
                    <div class="kpi-value text-lg"><?= (int)($kpis['active_contracts'] ?? 0) ?></div>
                    <div class="kpi-label">Contratti Attivi</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value text-lg">€<?= number_format((float)($kpis['pending_invoices'] ?? 0), 0, ',', '.') ?></div>
                    <div class="kpi-label">Fatture in Attesa</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value text-lg"><?= (int)($kpis['total_clients'] ?? 0) ?></div>
                    <div class="kpi-label">Clienti Totali</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value text-lg <?= ($kpis['expiring_domains'] ?? 0) > 0 ? 'text-warning' : '' ?>"><?= (int)($kpis['expiring_domains'] ?? 0) ?></div>
                    <div class="kpi-label">Domini in Scadenza</div>
                </div>
            </div>

            <!-- Charts & Tables -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Revenue/Expenses Chart -->
                <div class="card lg:col-span-2">
                    <div class="card-header">
                        <h3 class="card-title">Entrate vs Uscite (12 mesi)</h3>
                    </div>
                    <canvas id="revenueChart" height="300"></canvas>
                </div>

                <!-- Recent Leads -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Leads Recenti</h3>
                        <a href="/admin/leads.php" class="btn btn-secondary btn-sm">Tutti →</a>
                    </div>
                    <?php if (empty($recentLeads)): ?>
                        <div class="empty-state">
                            <p>Nessun lead ancora</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recentLeads as $lead): ?>
                                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-white/[0.04] transition-colors">
                                    <span class="pipeline-dot pipeline-<?= $lead['status'] ?>"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($lead['name'] ?? 'N/A') ?></p>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($lead['email'] ?? '') ?></p>
                                    </div>
                                    <span class="text-[10px] text-slate-600"><?= date('d/m', strtotime($lead['created_at'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Overdue Invoices -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Fatture da Incassare</h3>
                        <a href="/admin/invoices.php" class="btn btn-secondary btn-sm">Tutte →</a>
                    </div>
                    <?php if (empty($overdueInvoices)): ?>
                        <div class="empty-state">
                            <p>Nessuna fattura in attesa</p>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Cliente</th>
                                    <th>Importo</th>
                                    <th>Scadenza</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueInvoices as $inv): ?>
                                    <tr>
                                        <td class="font-mono text-xs"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                        <td><?= htmlspecialchars($inv['clients']['company_name'] ?? '—') ?></td>
                                        <td class="font-mono">€<?= number_format((float)$inv['total'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php
                                            $due = $inv['due_date'] ? strtotime($inv['due_date']) : null;
                                            $isOverdue = $due && $due < time();
                                            ?>
                                            <span class="<?= $isOverdue ? 'text-danger font-semibold' : '' ?>">
                                                <?= $due ? date('d/m/Y', $due) : '—' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Expiring Domains -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Domini — Prossime Scadenze</h3>
                        <a href="/admin/domains.php" class="btn btn-secondary btn-sm">Tutti →</a>
                    </div>
                    <?php if (empty($expiringDomains)): ?>
                        <div class="empty-state">
                            <p>Nessun dominio registrato</p>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Dominio</th>
                                    <th>Scadenza</th>
                                    <th>Rinnovo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiringDomains as $dom): ?>
                                    <?php
                                    $exp = $dom['expiry_date'] ? strtotime($dom['expiry_date']) : null;
                                    $daysLeft = $exp ? (int)ceil(($exp - time()) / 86400) : null;
                                    ?>
                                    <tr>
                                        <td class="font-medium"><?= htmlspecialchars($dom['domain_name']) ?></td>
                                        <td>
                                            <span class="<?= $daysLeft !== null && $daysLeft < 30 ? 'text-danger font-semibold' : ($daysLeft !== null && $daysLeft < 60 ? 'text-warning' : '') ?>">
                                                <?= $exp ? date('d/m/Y', $exp) : '—' ?>
                                            </span>
                                            <?php if ($daysLeft !== null && $daysLeft < 60): ?>
                                                <span class="text-xs text-slate-500 ml-1">(<?= $daysLeft ?>g)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $dom['auto_renew'] ? '✅ Auto' : '⚠️ Manuale' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Revenue vs Expenses Chart
    const revData = <?= json_encode($monthlyRevenue ?: []) ?>;
    const expData = <?= json_encode($monthlyExpenses ?: []) ?>;
    
    // Merge all months
    const allMonths = new Set();
    (Array.isArray(revData) ? revData : []).forEach(r => allMonths.add(r.month));
    (Array.isArray(expData) ? expData : []).forEach(e => allMonths.add(e.month));
    const months = [...allMonths].sort();
    
    // If no data, show last 6 months
    if (months.length === 0) {
        const now = new Date();
        for (let i = 5; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            months.push(d.toISOString().slice(0, 7));
        }
    }
    
    const revMap = {};
    const expMap = {};
    (Array.isArray(revData) ? revData : []).forEach(r => revMap[r.month] = parseFloat(r.total));
    (Array.isArray(expData) ? expData : []).forEach(e => expMap[e.month] = parseFloat(e.total));
    
    const labels = months.map(m => {
        const [y, mo] = m.split('-');
        const names = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
        return names[parseInt(mo) - 1] + ' ' + y.slice(2);
    });
    
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Entrate',
                    data: months.map(m => revMap[m] || 0),
                    backgroundColor: 'rgba(34, 197, 94, 0.3)',
                    borderColor: 'rgba(34, 197, 94, 0.8)',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Uscite',
                    data: months.map(m => expMap[m] || 0),
                    backgroundColor: 'rgba(239, 68, 68, 0.3)',
                    borderColor: 'rgba(239, 68, 68, 0.8)',
                    borderWidth: 1,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#64748b', font: { size: 11, family: 'Inter' } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#475569', font: { size: 10 } }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#475569', font: { size: 10 }, callback: v => '€' + v.toLocaleString() }
                }
            }
        }
    });
    </script>
</body>
</html>
