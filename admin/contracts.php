<?php
/**
 * IntuiFy Admin — Contract Management
 * CRUD + PDF generation with IntuiFy branded header
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Contratti';
$breadcrumb = 'Gestione contratti';
$sb = getSupabase();
$config = require dirname(__DIR__) . '/config.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('contracts', $id);
    $message = 'Contratto eliminato.';
    $messageType = 'success';
    $action = 'list';
}

// GENERATE PDF
if ($action === 'pdf' && $id) {
    $contract = $sb->find('contracts', $id);
    if ($contract) {
        $client = $sb->find('clients', $contract['client_id']);
        require_once __DIR__ . '/includes/pdf.php';
        generateContractPDF($contract, $client, $config);
        exit;
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-generate contract number
    $contractNumber = '';
    if (empty($_POST['id'])) {
        $year = date('Y');
        $prefix = $config['contract_prefix'] ?? 'CTR';
        $existing = $sb->select('contracts', [
            'select' => 'contract_number',
            'filters' => ['contract_number' => 'like.' . $prefix . '-' . $year . '-%'],
            'order' => 'contract_number.desc',
            'limit' => 1,
        ]);
        $lastNum = 0;
        if (!empty($existing)) {
            $parts = explode('-', $existing[0]['contract_number']);
            $lastNum = (int) end($parts);
        }
        $contractNumber = $prefix . '-' . $year . '-' . str_pad((string)($lastNum + 1), 3, '0', STR_PAD_LEFT);
    }

    $data = [
        'client_id' => $_POST['client_id'] ?? '',
        'product_id' => $_POST['product_id'] ?: null,
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'amount' => (float) ($_POST['amount'] ?? 0),
        'currency' => $_POST['currency'] ?? 'EUR',
        'start_date' => $_POST['start_date'] ?: null,
        'end_date' => $_POST['end_date'] ?: null,
        'status' => $_POST['status'] ?? 'draft',
    ];

    if ($contractNumber) {
        $data['contract_number'] = $contractNumber;
    }

    if (empty($data['title']) || empty($data['client_id'])) {
        $message = 'Titolo e cliente sono obbligatori.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('contracts', $editId, $data);
            $message = 'Contratto aggiornato.';
        } else {
            $sb->insert('contracts', $data);
            $message = 'Contratto creato.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$contracts = [];
$contract = null;
$clients = [];
$products = [];

if ($action === 'list') {
    $filterStatus = $_GET['filter'] ?? '';
    $filters = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $filters['status'] = 'eq.' . $filterStatus;
    }
    $contracts = $sb->select('contracts', [
        'select' => '*,clients(company_name)',
        'order' => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $contract = $sb->find('contracts', $id);
    $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
} elseif ($action === 'new') {
    $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
}

$statusLabels = [
    'draft' => 'Bozza',
    'sent' => 'Inviato',
    'signed' => 'Firmato',
    'expired' => 'Scaduto',
    'cancelled' => 'Annullato',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Contratti — IntuiFy Admin</title>
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
                <!-- Filter tabs -->
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?filter=all" class="btn <?= empty($_GET['filter']) || $_GET['filter'] === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutti</a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <a href="?filter=<?= $key ?>" class="btn <?= ($_GET['filter'] ?? '') === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Contratti (<?= count($contracts) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Contratto
                        </a>
                    </div>

                    <?php if (empty($contracts)): ?>
                        <div class="empty-state"><p>Nessun contratto ancora.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Titolo</th>
                                        <th>Cliente</th>
                                        <th>Importo</th>
                                        <th>Inizio</th>
                                        <th>Fine</th>
                                        <th>Status</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contracts as $c): ?>
                                        <tr>
                                            <td class="font-mono text-xs"><?= htmlspecialchars($c['contract_number']) ?></td>
                                            <td class="font-semibold"><?= htmlspecialchars($c['title']) ?></td>
                                            <td><?= htmlspecialchars($c['clients']['company_name'] ?? '—') ?></td>
                                            <td class="font-mono">€<?= number_format((float)$c['amount'], 2, ',', '.') ?></td>
                                            <td class="text-xs"><?= $c['start_date'] ? date('d/m/Y', strtotime($c['start_date'])) : '—' ?></td>
                                            <td class="text-xs"><?= $c['end_date'] ? date('d/m/Y', strtotime($c['end_date'])) : '—' ?></td>
                                            <td><span class="badge badge-<?= $c['status'] ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span></td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=pdf&id=<?= $c['id'] ?>" class="btn btn-sm" style="background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.2)" target="_blank">PDF</a>
                                                    <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')">×</a>
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
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Contratto' : 'Nuovo Contratto' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($contract): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($contract['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Titolo *</label>
                                <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($contract['title'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cliente *</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Seleziona cliente...</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= $cl['id'] ?>" <?= ($contract['client_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prodotto (opzionale)</label>
                                <select name="product_id" class="form-select">
                                    <option value="">Nessuno</option>
                                    <?php foreach ($products as $pr): ?>
                                        <option value="<?= $pr['id'] ?>" <?= ($contract['product_id'] ?? '') === $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Importo (€)</label>
                                <input type="number" name="amount" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($contract['amount'] ?? '0')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($contract['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Inizio</label>
                                <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($contract['start_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Fine</label>
                                <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($contract['end_date'] ?? '') ?>">
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Descrizione / Condizioni</label>
                                <textarea name="description" class="form-textarea" style="min-height:10rem"><?= htmlspecialchars($contract['description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Crea Contratto' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
