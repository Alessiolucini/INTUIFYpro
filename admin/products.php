<?php
/**
 * IntuiFy Admin — Product Suite Management
 * Manage products: Auterio, LingoBite, Orqesia, Eco Andratx + new ones
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Prodotti';
$breadcrumb = 'Suite prodotti';
$sb = getSupabase();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('products', $id);
    $message = 'Prodotto eliminato.';
    $messageType = 'success';
    $action = 'list';
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'saas',
        'url' => trim($_POST['url'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
    ];

    if (empty($data['name'])) {
        $message = 'Il nome prodotto è obbligatorio.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('products', $editId, $data);
            $message = 'Prodotto aggiornato.';
        } else {
            $sb->insert('products', $data);
            $message = 'Prodotto creato.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$products = [];
$product = null;
$productClients = [];

if ($action === 'list') {
    $products = $sb->select('products', [
        'select' => '*',
        'order' => 'created_at.asc',
    ]);
} elseif ($action === 'edit' && $id) {
    $product = $sb->find('products', $id);
    $productClients = $sb->select('client_products', [
        'select' => '*,clients(company_name,contact_person,email)',
        'filters' => ['product_id' => 'eq.' . $id],
    ]);
}

$typeLabels = [
    'saas' => 'SaaS',
    'aaas' => 'AaaS',
    'website' => 'Website',
    'app' => 'App',
    'other' => 'Altro',
];

$typeColors = [
    'saas' => 'bg-indigo-500/15 text-indigo-400',
    'aaas' => 'bg-purple-500/15 text-purple-400',
    'website' => 'bg-emerald-500/15 text-emerald-400',
    'app' => 'bg-amber-500/15 text-amber-400',
    'other' => 'bg-slate-500/15 text-slate-400',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Prodotti — IntuiFy Admin</title>
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
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Suite Prodotti (<?= count($products) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Prodotto
                        </a>
                    </div>

                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <p>Nessun prodotto. Esegui la migration SQL per caricare i prodotti iniziali.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($products as $p): ?>
                                <div class="p-5 rounded-xl bg-white/[0.02] border border-white/[0.06] hover:border-white/[0.12] transition-all group">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h4 class="text-base font-bold text-white"><?= htmlspecialchars($p['name']) ?></h4>
                                            <span class="inline-flex items-center px-2 py-0.5 mt-1 text-[10px] font-bold rounded-full uppercase <?= $typeColors[$p['type']] ?? $typeColors['other'] ?>">
                                                <?= $typeLabels[$p['type']] ?? $p['type'] ?>
                                            </span>
                                        </div>
                                        <span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
                                    </div>
                                    
                                    <?php if ($p['description']): ?>
                                        <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($p['url']): ?>
                                        <a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1 mb-3">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                            <?= htmlspecialchars(parse_url($p['url'], PHP_URL_HOST) ?: $p['url']) ?>
                                        </a>
                                    <?php endif; ?>

                                    <div class="flex items-center gap-2 pt-3 border-t border-white/[0.06]">
                                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm flex-1 justify-center">Modifica</a>
                                        <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare questo prodotto?')">×</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <div class="card max-w-2xl">
                    <div class="card-header">
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Prodotto' : 'Nuovo Prodotto' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($product): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($product['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">Nome Prodotto *</label>
                                <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tipo</label>
                                <select name="type" class="form-select">
                                    <?php foreach ($typeLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($product['type'] ?? 'saas') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL Pagina</label>
                                <input type="url" name="url" class="form-input" value="<?= htmlspecialchars($product['url'] ?? '') ?>" placeholder="https://...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Attivo</option>
                                    <option value="development" <?= ($product['status'] ?? '') === 'development' ? 'selected' : '' ?>>In Sviluppo</option>
                                    <option value="archived" <?= ($product['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archiviato</option>
                                </select>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Descrizione</label>
                                <textarea name="description" class="form-textarea"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Crea Prodotto' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>

                    <?php if ($action === 'edit' && !empty($productClients)): ?>
                        <div class="mt-8 pt-6 border-t border-white/[0.06]">
                            <h4 class="text-sm font-bold text-white mb-3">Clienti Associati</h4>
                            <table class="admin-table">
                                <thead><tr><th>Azienda</th><th>Referente</th><th>Email</th></tr></thead>
                                <tbody>
                                    <?php foreach ($productClients as $pc): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pc['clients']['company_name'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($pc['clients']['contact_person'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($pc['clients']['email'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
