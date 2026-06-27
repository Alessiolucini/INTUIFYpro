<?php
/**
 * IntuiFy Admin — Expense Tracking
 * Upload expense invoices + categorize spending
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Spese';
$breadcrumb = 'Gestione spese';
$sb = getSupabase();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('expenses', $id);
    $message = 'Spesa eliminata.';
    $messageType = 'success';
    $action = 'list';
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'description' => trim($_POST['description'] ?? ''),
        'vendor' => trim($_POST['vendor'] ?? ''),
        'amount' => (float) ($_POST['amount'] ?? 0),
        'currency' => $_POST['currency'] ?? 'EUR',
        'category' => $_POST['category'] ?? 'other',
        'date' => $_POST['date'] ?: date('Y-m-d'),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    // Handle file upload
    if (isset($_FILES['expense_file']) && $_FILES['expense_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['expense_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed) && $file['size'] <= 10 * 1024 * 1024) {
            $filename = 'expense_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $mimeTypes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            $url = $sb->uploadFile('expenses', $filename, $file['tmp_name'], $mimeTypes[$ext] ?? 'application/octet-stream');
            if ($url) {
                $data['file_url'] = $url;
            }
        }
    }

    if (empty($data['description'])) {
        $message = 'La descrizione è obbligatoria.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('expenses', $editId, $data);
            $message = 'Spesa aggiornata.';
        } else {
            $sb->insert('expenses', $data);
            $message = 'Spesa registrata.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$expenses = [];
$expense = null;
$filterCat = $_GET['cat'] ?? '';

if ($action === 'list') {
    $filters = [];
    if ($filterCat && $filterCat !== 'all') {
        $filters['category'] = 'eq.' . $filterCat;
    }
    $expenses = $sb->select('expenses', [
        'select' => '*',
        'order' => 'date.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $expense = $sb->find('expenses', $id);
}

$categoryLabels = [
    'hosting' => '🖥️ Hosting',
    'software' => '💻 Software',
    'marketing' => '📣 Marketing',
    'legal' => '⚖️ Legale',
    'design' => '🎨 Design',
    'hardware' => '🔧 Hardware',
    'office' => '🏢 Ufficio',
    'other' => '📦 Altro',
];

$totalExpenses = array_sum(array_column($expenses, 'amount'));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Spese — IntuiFy Admin</title>
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
                <!-- Total banner -->
                <div class="kpi-card mb-6 inline-flex items-center gap-4">
                    <span class="text-sm text-slate-400">Totale spese visualizzate:</span>
                    <span class="text-xl font-bold text-red-400 font-mono">€<?= number_format($totalExpenses, 2, ',', '.') ?></span>
                </div>

                <!-- Category filters -->
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?cat=all" class="btn <?= empty($filterCat) || $filterCat === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutte</a>
                    <?php foreach ($categoryLabels as $key => $label): ?>
                        <a href="?cat=<?= $key ?>" class="btn <?= $filterCat === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Spese (<?= count($expenses) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuova Spesa
                        </a>
                    </div>

                    <?php if (empty($expenses)): ?>
                        <div class="empty-state"><p>Nessuna spesa registrata.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrizione</th>
                                        <th>Fornitore</th>
                                        <th>Categoria</th>
                                        <th>Importo</th>
                                        <th>File</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $e): ?>
                                        <tr>
                                            <td class="font-mono text-xs"><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                            <td class="font-semibold"><?= htmlspecialchars($e['description']) ?></td>
                                            <td><?= htmlspecialchars($e['vendor'] ?? '—') ?></td>
                                            <td><span class="text-xs"><?= $categoryLabels[$e['category']] ?? $e['category'] ?></span></td>
                                            <td class="font-mono font-semibold text-red-400">€<?= number_format((float)$e['amount'], 2, ',', '.') ?></td>
                                            <td>
                                                <?php if ($e['file_url']): ?>
                                                    <a href="<?= htmlspecialchars($e['file_url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 text-xs">📎 Vedi</a>
                                                <?php else: ?>
                                                    <span class="text-slate-600 text-xs">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $e['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=delete&id=<?= $e['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')">×</a>
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
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Spesa' : 'Nuova Spesa' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($expense): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($expense['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Descrizione *</label>
                                <input type="text" name="description" class="form-input" value="<?= htmlspecialchars($expense['description'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fornitore</label>
                                <input type="text" name="vendor" class="form-input" value="<?= htmlspecialchars($expense['vendor'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Importo (€) *</label>
                                <input type="number" name="amount" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($expense['amount'] ?? '')) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Categoria</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($categoryLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($expense['category'] ?? 'other') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data</label>
                                <input type="date" name="date" class="form-input" value="<?= htmlspecialchars($expense['date'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">File Fattura (PDF, JPG, PNG — max 10MB)</label>
                                <input type="file" name="expense_file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-input !py-2">
                                <?php if (!empty($expense['file_url'])): ?>
                                    <p class="text-xs text-slate-500 mt-1">File attuale: <a href="<?= htmlspecialchars($expense['file_url']) ?>" target="_blank" class="text-indigo-400">📎 Visualizza</a></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Note</label>
                                <textarea name="notes" class="form-textarea"><?= htmlspecialchars($expense['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Registra Spesa' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
