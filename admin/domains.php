<?php
/**
 * IntuiFy Admin — Domain Management
 * Track purchased domains with renewal dates and costs
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Domini';
$breadcrumb = 'Gestione domini';
$sb = getSupabase();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('domains', $id);
    $message = 'Dominio eliminato.';
    $messageType = 'success';
    $action = 'list';
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'domain_name' => trim($_POST['domain_name'] ?? ''),
        'registrar' => trim($_POST['registrar'] ?? ''),
        'purchase_date' => $_POST['purchase_date'] ?: null,
        'expiry_date' => $_POST['expiry_date'] ?: null,
        'auto_renew' => isset($_POST['auto_renew']),
        'annual_cost' => (float) ($_POST['annual_cost'] ?? 0),
        'associated_product_id' => $_POST['associated_product_id'] ?: null,
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if (empty($data['domain_name'])) {
        $message = 'Il nome dominio è obbligatorio.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('domains', $editId, $data);
            $message = 'Dominio aggiornato.';
        } else {
            $sb->insert('domains', $data);
            $message = 'Dominio aggiunto.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$domains = [];
$domain = null;
$products = [];

if ($action === 'list') {
    $domains = $sb->select('domains', [
        'select' => '*,products(name)',
        'order' => 'expiry_date.asc',
    ]);
} elseif (in_array($action, ['edit', 'new'])) {
    if ($action === 'edit' && $id) {
        $domain = $sb->find('domains', $id);
    }
    $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
}

$totalAnnualCost = array_sum(array_column($domains, 'annual_cost'));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Domini — IntuiFy Admin</title>
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
                <!-- Summary -->
                <div class="flex items-center gap-6 mb-6">
                    <div class="kpi-card inline-flex items-center gap-4">
                        <span class="text-sm text-slate-400">Domini totali:</span>
                        <span class="text-xl font-bold text-white"><?= count($domains) ?></span>
                    </div>
                    <div class="kpi-card inline-flex items-center gap-4">
                        <span class="text-sm text-slate-400">Costo annuale:</span>
                        <span class="text-xl font-bold text-amber-400 font-mono">€<?= number_format($totalAnnualCost, 2, ',', '.') ?></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Domini Registrati</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Dominio
                        </a>
                    </div>

                    <?php if (empty($domains)): ?>
                        <div class="empty-state"><p>Nessun dominio registrato.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Dominio</th>
                                        <th>Registrar</th>
                                        <th>Prodotto</th>
                                        <th>Acquisto</th>
                                        <th>Scadenza</th>
                                        <th>Rinnovo</th>
                                        <th>Costo/anno</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($domains as $d): ?>
                                        <?php
                                        $exp = $d['expiry_date'] ? strtotime($d['expiry_date']) : null;
                                        $daysLeft = $exp ? (int)ceil(($exp - time()) / 86400) : null;
                                        $isExpired = $daysLeft !== null && $daysLeft < 0;
                                        $isUrgent = $daysLeft !== null && $daysLeft >= 0 && $daysLeft < 30;
                                        $isWarning = $daysLeft !== null && $daysLeft >= 30 && $daysLeft < 60;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="font-semibold"><?= htmlspecialchars($d['domain_name']) ?></span>
                                            </td>
                                            <td class="text-xs"><?= htmlspecialchars($d['registrar'] ?? '—') ?></td>
                                            <td class="text-xs"><?= htmlspecialchars($d['products']['name'] ?? '—') ?></td>
                                            <td class="text-xs font-mono"><?= $d['purchase_date'] ? date('d/m/Y', strtotime($d['purchase_date'])) : '—' ?></td>
                                            <td>
                                                <span class="font-mono text-xs <?= $isExpired ? 'text-danger font-bold' : ($isUrgent ? 'text-danger font-semibold' : ($isWarning ? 'text-warning' : '')) ?>">
                                                    <?= $exp ? date('d/m/Y', $exp) : '—' ?>
                                                </span>
                                                <?php if ($daysLeft !== null): ?>
                                                    <span class="text-[10px] ml-1 <?= $isExpired ? 'text-danger' : ($isUrgent ? 'text-danger' : ($isWarning ? 'text-warning' : 'text-slate-600')) ?>">
                                                        (<?= $isExpired ? 'SCADUTO' : $daysLeft . 'g' ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $d['auto_renew'] ? '<span class="text-success text-xs font-semibold">✓ Auto</span>' : '<span class="text-warning text-xs">Manuale</span>' ?></td>
                                            <td class="font-mono text-xs">€<?= number_format((float)$d['annual_cost'], 2, ',', '.') ?></td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=delete&id=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')">×</a>
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
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Dominio' : 'Nuovo Dominio' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($domain): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($domain['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">Nome Dominio *</label>
                                <input type="text" name="domain_name" class="form-input" value="<?= htmlspecialchars($domain['domain_name'] ?? '') ?>" placeholder="esempio.com" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Registrar</label>
                                <input type="text" name="registrar" class="form-input" value="<?= htmlspecialchars($domain['registrar'] ?? 'Hostinger') ?>" placeholder="Es. Hostinger, Namecheap...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Acquisto</label>
                                <input type="date" name="purchase_date" class="form-input" value="<?= htmlspecialchars($domain['purchase_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Scadenza</label>
                                <input type="date" name="expiry_date" class="form-input" value="<?= htmlspecialchars($domain['expiry_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Costo Annuale (€)</label>
                                <input type="number" name="annual_cost" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($domain['annual_cost'] ?? '')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prodotto Associato</label>
                                <select name="associated_product_id" class="form-select">
                                    <option value="">Nessuno</option>
                                    <?php foreach ($products as $pr): ?>
                                        <option value="<?= $pr['id'] ?>" <?= ($domain['associated_product_id'] ?? '') === $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group flex items-center gap-3 pt-6">
                                <input type="checkbox" name="auto_renew" id="auto_renew" class="w-4 h-4 rounded" <?= !empty($domain['auto_renew']) ? 'checked' : '' ?>>
                                <label for="auto_renew" class="text-sm text-slate-300 cursor-pointer">Rinnovo automatico</label>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Note</label>
                                <textarea name="notes" class="form-textarea"><?= htmlspecialchars($domain['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Aggiungi Dominio' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
