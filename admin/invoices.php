<?php
/**
 * IntuiFy Admin — Invoice Management
 * CRUD + PDF generation with dynamic line items
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Fatture';
$breadcrumb = 'Fatture emesse';
$sb = getSupabase();
$config = require dirname(__DIR__) . '/config.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('invoices', $id);
    $message = 'Fattura eliminata.';
    $messageType = 'success';
    $action = 'list';
}

// GENERATE PDF
if ($action === 'pdf' && $id) {
    $invoice = $sb->find('invoices', $id);
    if ($invoice) {
        $client = $sb->find('clients', $invoice['client_id']);
        require_once __DIR__ . '/includes/pdf.php';
        generateInvoicePDF($invoice, $client, $config);
        exit;
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-generate invoice number
    $invoiceNumber = '';
    if (empty($_POST['id'])) {
        $year = date('Y');
        $prefix = $config['invoice_prefix'] ?? 'INV';
        $existing = $sb->select('invoices', [
            'select' => 'invoice_number',
            'filters' => ['invoice_number' => 'like.' . $prefix . '-' . $year . '-%'],
            'order' => 'invoice_number.desc',
            'limit' => 1,
        ]);
        $lastNum = 0;
        if (!empty($existing)) {
            $parts = explode('-', $existing[0]['invoice_number']);
            $lastNum = (int) end($parts);
        }
        $invoiceNumber = $prefix . '-' . $year . '-' . str_pad((string)($lastNum + 1), 3, '0', STR_PAD_LEFT);
    }

    // Parse line items from form
    $items = [];
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_qty'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    
    for ($i = 0; $i < count($descriptions); $i++) {
        $desc = trim($descriptions[$i] ?? '');
        $qty = (float) ($quantities[$i] ?? 0);
        $price = (float) ($prices[$i] ?? 0);
        if ($desc && $qty > 0 && $price > 0) {
            $items[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => round($qty * $price, 2),
            ];
        }
    }

    $subtotal = array_sum(array_column($items, 'total'));
    $taxRate = (float) ($_POST['tax_rate'] ?? 21);
    $taxAmount = round($subtotal * $taxRate / 100, 2);
    $total = $subtotal + $taxAmount;

    $data = [
        'client_id' => $_POST['client_id'] ?? '',
        'contract_id' => $_POST['contract_id'] ?: null,
        'items' => json_encode($items),
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total' => $total,
        'currency' => $_POST['currency'] ?? 'EUR',
        'issue_date' => $_POST['issue_date'] ?: date('Y-m-d'),
        'due_date' => $_POST['due_date'] ?: null,
        'status' => $_POST['status'] ?? 'draft',
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if ($invoiceNumber) {
        $data['invoice_number'] = $invoiceNumber;
    }

    if (empty($data['client_id'])) {
        $message = 'Il cliente è obbligatorio.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('invoices', $editId, $data);
            $message = 'Fattura aggiornata.';
        } else {
            $sb->insert('invoices', $data);
            $message = 'Fattura creata.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$invoices = [];
$invoice = null;
$clients = [];
$contracts = [];

if ($action === 'list') {
    $filterStatus = $_GET['filter'] ?? '';
    $filters = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $filters['status'] = 'eq.' . $filterStatus;
    }
    $invoices = $sb->select('invoices', [
        'select' => '*,clients(company_name)',
        'order' => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif (in_array($action, ['edit', 'new'])) {
    if ($action === 'edit' && $id) {
        $invoice = $sb->find('invoices', $id);
    }
    $clients = $sb->select('clients', ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $contracts = $sb->select('contracts', ['select' => 'id,contract_number,title', 'order' => 'created_at.desc']);
}

$invoiceItems = [];
if ($invoice && isset($invoice['items'])) {
    $invoiceItems = is_string($invoice['items']) ? json_decode($invoice['items'], true) : $invoice['items'];
}
if (empty($invoiceItems)) {
    $invoiceItems = [['description' => '', 'quantity' => 1, 'unit_price' => 0, 'total' => 0]];
}

$statusLabels = [
    'draft' => 'Bozza',
    'sent' => 'Inviata',
    'paid' => 'Pagata',
    'overdue' => 'Scaduta',
    'cancelled' => 'Annullata',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Fatture — IntuiFy Admin</title>
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
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?filter=all" class="btn <?= empty($_GET['filter']) || $_GET['filter'] === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutte</a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <a href="?filter=<?= $key ?>" class="btn <?= ($_GET['filter'] ?? '') === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Fatture (<?= count($invoices) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuova Fattura
                        </a>
                    </div>

                    <?php if (empty($invoices)): ?>
                        <div class="empty-state"><p>Nessuna fattura ancora.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Cliente</th>
                                        <th>Imponibile</th>
                                        <th>IVA</th>
                                        <th>Totale</th>
                                        <th>Emissione</th>
                                        <th>Scadenza</th>
                                        <th>Status</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr>
                                            <td class="font-mono text-xs"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                            <td><?= htmlspecialchars($inv['clients']['company_name'] ?? '—') ?></td>
                                            <td class="font-mono text-xs">€<?= number_format((float)$inv['subtotal'], 2, ',', '.') ?></td>
                                            <td class="font-mono text-xs"><?= number_format((float)$inv['tax_rate'], 0) ?>%</td>
                                            <td class="font-mono font-semibold">€<?= number_format((float)$inv['total'], 2, ',', '.') ?></td>
                                            <td class="text-xs"><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
                                            <td class="text-xs"><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></td>
                                            <td><span class="badge badge-<?= $inv['status'] ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=pdf&id=<?= $inv['id'] ?>" class="btn btn-sm" style="background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.2)" target="_blank">PDF</a>
                                                    <a href="?action=delete&id=<?= $inv['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')">×</a>
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
                <div class="card max-w-4xl">
                    <div class="card-header">
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Fattura' : 'Nuova Fattura' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST" id="invoice-form">
                        <?php if ($invoice): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($invoice['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="form-group">
                                <label class="form-label">Cliente *</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Seleziona...</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= $cl['id'] ?>" <?= ($invoice['client_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Emissione</label>
                                <input type="date" name="issue_date" class="form-input" value="<?= htmlspecialchars($invoice['issue_date'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data Scadenza</label>
                                <input type="date" name="due_date" class="form-input" value="<?= htmlspecialchars($invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contratto (opzionale)</label>
                                <select name="contract_id" class="form-select">
                                    <option value="">Nessuno</option>
                                    <?php foreach ($contracts as $ct): ?>
                                        <option value="<?= $ct['id'] ?>" <?= ($invoice['contract_id'] ?? '') === $ct['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ct['contract_number'] . ' — ' . $ct['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">IVA %</label>
                                <input type="number" name="tax_rate" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($invoice['tax_rate'] ?? '21')) ?>" id="tax-rate">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($invoice['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Line Items -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <label class="form-label !mb-0">Righe Fattura</label>
                                <button type="button" id="add-item" class="btn btn-secondary btn-sm">+ Aggiungi Riga</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="admin-table" id="items-table">
                                    <thead>
                                        <tr>
                                            <th class="w-1/2">Descrizione</th>
                                            <th>Qtà</th>
                                            <th>Prezzo Unit.</th>
                                            <th>Totale</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body">
                                        <?php foreach ($invoiceItems as $i => $item): ?>
                                            <tr class="item-row">
                                                <td><input type="text" name="item_description[]" class="form-input !py-1.5 !text-sm" value="<?= htmlspecialchars($item['description'] ?? '') ?>" placeholder="Descrizione servizio..."></td>
                                                <td><input type="number" name="item_qty[]" step="0.01" min="0" class="form-input !py-1.5 !text-sm !w-20 item-qty" value="<?= $item['quantity'] ?? 1 ?>"></td>
                                                <td><input type="number" name="item_price[]" step="0.01" min="0" class="form-input !py-1.5 !text-sm !w-28 item-price" value="<?= $item['unit_price'] ?? 0 ?>"></td>
                                                <td class="item-total font-mono text-sm text-white">€<?= number_format((float)($item['total'] ?? 0), 2, ',', '.') ?></td>
                                                <td><button type="button" class="remove-item text-red-400 hover:text-red-300 text-lg font-bold">×</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Totals -->
                            <div class="flex justify-end mt-4">
                                <div class="w-64 space-y-2">
                                    <div class="flex justify-between text-sm"><span class="text-slate-400">Imponibile:</span><span class="font-mono text-white" id="subtotal">€0,00</span></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-400">IVA (<span id="tax-rate-display">21</span>%):</span><span class="font-mono text-white" id="tax-amount">€0,00</span></div>
                                    <div class="flex justify-between text-base font-bold border-t border-white/[0.06] pt-2"><span class="text-white">Totale:</span><span class="font-mono text-indigo-400" id="total">€0,00</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Note</label>
                            <textarea name="notes" class="form-textarea"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Crea Fattura' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>

                <script>
                function calcTotals() {
                    let subtotal = 0;
                    document.querySelectorAll('.item-row').forEach(row => {
                        const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
                        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
                        const lineTotal = qty * price;
                        subtotal += lineTotal;
                        const td = row.querySelector('.item-total');
                        if (td) td.textContent = '€' + lineTotal.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
                    });
                    const taxRate = parseFloat(document.getElementById('tax-rate')?.value || 21);
                    const taxAmount = subtotal * taxRate / 100;
                    const total = subtotal + taxAmount;
                    document.getElementById('subtotal').textContent = '€' + subtotal.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
                    document.getElementById('tax-amount').textContent = '€' + taxAmount.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
                    document.getElementById('total').textContent = '€' + total.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
                    document.getElementById('tax-rate-display').textContent = taxRate;
                }

                document.getElementById('add-item')?.addEventListener('click', () => {
                    const row = document.createElement('tr');
                    row.className = 'item-row';
                    row.innerHTML = `
                        <td><input type="text" name="item_description[]" class="form-input !py-1.5 !text-sm" placeholder="Descrizione..."></td>
                        <td><input type="number" name="item_qty[]" step="0.01" min="0" class="form-input !py-1.5 !text-sm !w-20 item-qty" value="1"></td>
                        <td><input type="number" name="item_price[]" step="0.01" min="0" class="form-input !py-1.5 !text-sm !w-28 item-price" value="0"></td>
                        <td class="item-total font-mono text-sm text-white">€0,00</td>
                        <td><button type="button" class="remove-item text-red-400 hover:text-red-300 text-lg font-bold">×</button></td>
                    `;
                    document.getElementById('items-body').appendChild(row);
                    bindEvents();
                    calcTotals();
                });

                function bindEvents() {
                    document.querySelectorAll('.item-qty, .item-price, #tax-rate').forEach(el => {
                        el.removeEventListener('input', calcTotals);
                        el.addEventListener('input', calcTotals);
                    });
                    document.querySelectorAll('.remove-item').forEach(btn => {
                        btn.onclick = () => { btn.closest('tr')?.remove(); calcTotals(); };
                    });
                }

                bindEvents();
                calcTotals();
                </script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
