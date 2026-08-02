<?php
/**
 * IntuiFy Admin — Subscription Management
 * CRUD for managing client subscriptions (plans, billing cycles, renewals).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Abbonamenti';
$breadcrumb = 'Gestione abbonamenti';
$sb = getSupabase();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('subscriptions', $id);
    $message = 'Abbonamento eliminato.';
    $messageType = 'success';
    $action = 'list';
}

// TOGGLE auto_renew
if ($action === 'toggle-renew' && $id) {
    $sub = $sb->find('subscriptions', $id);
    if ($sub) {
        $sb->update('subscriptions', $id, ['auto_renew' => !$sub['auto_renew']]);
        $message = 'Rinnovo automatico ' . (!$sub['auto_renew'] ? 'attivato' : 'disattivato') . '.';
        $messageType = 'success';
    }
    $action = 'list';
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_id'     => $_POST['client_id'] ?? '',
        'product_id'    => $_POST['product_id'] ?: null,
        'plan_name'     => trim($_POST['plan_name'] ?? ''),
        'amount'        => (float) ($_POST['amount'] ?? 0),
        'currency'      => $_POST['currency'] ?? 'EUR',
        'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
        'start_date'    => $_POST['start_date'] ?: null,
        'end_date'      => $_POST['end_date'] ?: null,
        'next_billing'  => $_POST['next_billing'] ?: null,
        'auto_renew'    => isset($_POST['auto_renew']),
        'status'        => $_POST['status'] ?? 'active',
        'notes'         => trim($_POST['notes'] ?? ''),
    ];

    $validUuid = fn(string $s) => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s);

    if (empty($data['plan_name'])) {
        $message = 'Il nome del piano è obbligatorio.';
        $messageType = 'error';
    } elseif (empty($data['client_id']) || !$validUuid($data['client_id'])) {
        $message = 'Seleziona un cliente valido.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $result = $sb->update('subscriptions', $editId, $data);
            if ($result === null && $sb->getLastError()) {
                $message = 'Errore aggiornamento: ' . $sb->getLastError();
                $messageType = 'error';
            } else {
                $message = 'Abbonamento aggiornato.';
                $messageType = 'success';
                $action = 'list';
            }
        } else {
            $result = $sb->insert('subscriptions', $data);
            if ($result === null) {
                $errDetail = $sb->getLastError() ?? 'Risposta vuota dal server.';
                $message = 'Errore salvataggio abbonamento: ' . $errDetail;
                $messageType = 'error';
                // Stay on form — reload clients/products for re-display
                $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
                $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
            } else {
                $message = 'Abbonamento creato con successo.';
                $messageType = 'success';
                $action = 'list';
            }
        }
    }
}

// Load data
$subscriptions = [];
$subscription = null;
$clients = [];
$products = [];

if ($action === 'list') {
    $filterStatus = $_GET['filter'] ?? '';
    $filters = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $filters['status'] = 'eq.' . $filterStatus;
    }
    $subscriptions = $sb->select('subscriptions', [
        'select' => '*,clients(company_name),products(name)',
        'order'  => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $subscription = $sb->find('subscriptions', $id);
    $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
} elseif ($action === 'new') {
    $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
}

$statusLabels = [
    'active'    => 'Attivo',
    'trial'     => 'Prova',
    'paused'    => 'In Pausa',
    'cancelled' => 'Annullato',
    'expired'   => 'Scaduto',
];

$cycleLabels = [
    'monthly'   => 'Mensile',
    'quarterly' => 'Trimestrale',
    'semiannual'=> 'Semestrale',
    'annual'    => 'Annuale',
    'one_time'  => 'Una Tantum',
];

// Stats for KPI cards
$totalActive = 0;
$totalMRR = 0.0;
$expiringCount = 0;
$trialCount = 0;
if ($action === 'list') {
    foreach ($subscriptions as $s) {
        if ($s['status'] === 'active') {
            $totalActive++;
            // Normalize to monthly
            $amt = (float)($s['amount'] ?? 0);
            switch ($s['billing_cycle'] ?? 'monthly') {
                case 'quarterly':   $totalMRR += $amt / 3; break;
                case 'semiannual':  $totalMRR += $amt / 6; break;
                case 'annual':      $totalMRR += $amt / 12; break;
                case 'one_time':    break;
                default:            $totalMRR += $amt;
            }
        }
        if ($s['status'] === 'trial') $trialCount++;
        if ($s['end_date'] ?? false) {
            $daysLeft = (int)ceil((strtotime($s['end_date']) - time()) / 86400);
            if ($daysLeft >= 0 && $daysLeft <= 30 && in_array($s['status'], ['active', 'trial'])) {
                $expiringCount++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Abbonamenti — IntuiFy Admin</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <main class="p-6">
            <?php if ($message): ?>
                <div class="toast toast-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- KPI Summary -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="kpi-card">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-value text-lg text-emerald-400"><?= $totalActive ?></div>
                        <div class="kpi-label">Attivi</div>
                    </div>
                    <div class="kpi-card">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-value text-lg text-indigo-400">€<?= number_format($totalMRR, 0, ',', '.') ?></div>
                        <div class="kpi-label">MRR Stimato</div>
                    </div>
                    <div class="kpi-card">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-value text-lg text-amber-400"><?= $trialCount ?></div>
                        <div class="kpi-label">In Prova</div>
                    </div>
                    <div class="kpi-card">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-9 h-9 rounded-xl bg-red-500/15 flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-value text-lg <?= $expiringCount > 0 ? 'text-red-400' : '' ?>"><?= $expiringCount ?></div>
                        <div class="kpi-label">In Scadenza (30g)</div>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?filter=all" class="btn <?= empty($_GET['filter']) || $_GET['filter'] === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutti</a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <a href="?filter=<?= $key ?>" class="btn <?= ($_GET['filter'] ?? '') === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Abbonamenti (<?= count($subscriptions) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Abbonamento
                        </a>
                    </div>

                    <?php if (empty($subscriptions)): ?>
                        <div class="empty-state"><p>Nessun abbonamento ancora.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Piano</th>
                                        <th>Cliente</th>
                                        <th>Prodotto</th>
                                        <th>Importo</th>
                                        <th>Ciclo</th>
                                        <th>Prossimo Addebito</th>
                                        <th>Rinnovo</th>
                                        <th>Status</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $s): ?>
                                        <?php
                                        $nextBilling = $s['next_billing'] ? strtotime($s['next_billing']) : null;
                                        $billingDaysLeft = $nextBilling ? (int)ceil(($nextBilling - time()) / 86400) : null;
                                        ?>
                                        <tr>
                                            <td class="font-semibold"><?= htmlspecialchars($s['plan_name']) ?></td>
                                            <td><?= htmlspecialchars($s['clients']['company_name'] ?? '—') ?></td>
                                            <td class="text-xs"><?= htmlspecialchars($s['products']['name'] ?? '—') ?></td>
                                            <td class="font-mono">€<?= number_format((float)$s['amount'], 2, ',', '.') ?></td>
                                            <td class="text-xs"><?= $cycleLabels[$s['billing_cycle']] ?? $s['billing_cycle'] ?></td>
                                            <td class="text-xs">
                                                <?php if ($nextBilling): ?>
                                                    <span class="<?= $billingDaysLeft !== null && $billingDaysLeft <= 7 ? 'text-warning font-semibold' : '' ?>">
                                                        <?= date('d/m/Y', $nextBilling) ?>
                                                    </span>
                                                    <?php if ($billingDaysLeft !== null && $billingDaysLeft <= 14): ?>
                                                        <span class="text-xs text-slate-500 ml-1">(<?= $billingDaysLeft ?>g)</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=toggle-renew&id=<?= $s['id'] ?>" class="text-xs hover:underline" title="Clicca per cambiare">
                                                    <?= $s['auto_renew'] ? '✅ Auto' : '⚠️ Manuale' ?>
                                                </a>
                                            </td>
                                            <td><span class="badge badge-<?= $s['status'] ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare questo abbonamento?')">×</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <div class="card max-w-3xl">
                    <div class="card-header">
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Abbonamento' : 'Nuovo Abbonamento' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($subscription): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($subscription['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Nome Piano *</label>
                                <input type="text" name="plan_name" class="form-input" placeholder="Es. Piano Pro, Starter Mensile…" value="<?= htmlspecialchars($subscription['plan_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cliente *</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Seleziona cliente...</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= $cl['id'] ?>" <?= ($subscription['client_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prodotto (opzionale)</label>
                                <select name="product_id" class="form-select">
                                    <option value="">Nessuno</option>
                                    <?php foreach ($products as $pr): ?>
                                        <option value="<?= $pr['id'] ?>" <?= ($subscription['product_id'] ?? '') === $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Importo (€)</label>
                                <input type="number" name="amount" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($subscription['amount'] ?? '0')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ciclo di Fatturazione</label>
                                <select name="billing_cycle" class="form-select">
                                    <?php foreach ($cycleLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($subscription['billing_cycle'] ?? 'monthly') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($subscription['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Inizio</label>
                                <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($subscription['start_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Fine</label>
                                <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($subscription['end_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prossimo Addebito</label>
                                <input type="date" name="next_billing" class="form-input" value="<?= htmlspecialchars($subscription['next_billing'] ?? '') ?>">
                            </div>
                            <div class="form-group flex items-end">
                                <label class="flex items-center gap-3 cursor-pointer py-2">
                                    <input type="checkbox" name="auto_renew" class="w-4 h-4 accent-indigo-500" <?= ($subscription['auto_renew'] ?? false) ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-300">Rinnovo automatico</span>
                                </label>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Note</label>
                                <textarea name="notes" class="form-textarea" placeholder="Note interne sull'abbonamento..."><?= htmlspecialchars($subscription['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Crea Abbonamento' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
