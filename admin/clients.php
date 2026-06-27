<?php
/**
 * IntuiFy Admin — Client Management
 * Full CRUD with client card (company, contact, phone, email, VAT, products)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Clienti';
$breadcrumb = 'Gestione clienti';
$sb = getSupabase();
$config = require dirname(__DIR__) . '/config.php';

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// DELETE
if ($action === 'delete' && $id) {
    if ($sb->delete('clients', $id)) {
        $message = 'Cliente eliminato.';
        $messageType = 'success';
    } else {
        $message = 'Errore durante l\'eliminazione.';
        $messageType = 'error';
    }
    $action = 'list';
}

// SAVE (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'vat_number' => trim($_POST['vat_number'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if (empty($data['company_name'])) {
        $message = 'Il nome azienda è obbligatorio.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('clients', $editId, $data);
            $message = 'Cliente aggiornato.';
        } else {
            $sb->insert('clients', $data);
            $message = 'Cliente creato.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$clients = [];
$client = null;
$clientProducts = [];
$allProducts = [];

if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $filters = [];
    if ($search) {
        $filters['company_name'] = 'ilike.*' . $search . '*';
    }
    $clients = $sb->select('clients', [
        'select' => '*',
        'order' => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $client = $sb->find('clients', $id);
    $clientProducts = $sb->select('client_products', [
        'select' => '*,products(name,type)',
        'filters' => ['client_id' => 'eq.' . $id],
    ]);
    $allProducts = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
} elseif ($action === 'new') {
    $allProducts = $sb->select('products', ['select' => 'id,name', 'order' => 'name.asc']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Clienti — IntuiFy Admin</title>
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
                <!-- LIST VIEW -->
                <div class="card">
                    <div class="card-header">
                        <div class="flex items-center gap-4">
                            <h3 class="card-title">Tutti i Clienti (<?= count($clients) ?>)</h3>
                            <form method="GET" class="flex items-center gap-2">
                                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Cerca azienda..." class="form-input !w-48 !py-1.5 !text-xs">
                                <button type="submit" class="btn btn-secondary btn-sm">Cerca</button>
                            </form>
                        </div>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Cliente
                        </a>
                    </div>

                    <?php if (empty($clients)): ?>
                        <div class="empty-state">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m4.5-18v18m4.5-18v18m4.5-18v18"/></svg>
                            <p>Nessun cliente ancora. Crea il primo!</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Azienda</th>
                                        <th>Referente</th>
                                        <th>Email</th>
                                        <th>Telefono</th>
                                        <th>P.IVA</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $c): ?>
                                        <tr>
                                            <td class="font-semibold"><?= htmlspecialchars($c['company_name']) ?></td>
                                            <td><?= htmlspecialchars($c['contact_person'] ?? '—') ?></td>
                                            <td>
                                                <?php if ($c['email']): ?>
                                                    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="text-indigo-400 hover:text-indigo-300"><?= htmlspecialchars($c['email']) ?></a>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                            <td class="font-mono text-xs"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                                            <td class="font-mono text-xs"><?= htmlspecialchars($c['vat_number'] ?? '—') ?></td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare questo cliente?')">Elimina</a>
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
                <!-- FORM VIEW -->
                <div class="card max-w-3xl">
                    <div class="card-header">
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Cliente' : 'Nuovo Cliente' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($client): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($client['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Azienda *</label>
                                <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($client['company_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Persona di Riferimento</label>
                                <input type="text" name="contact_person" class="form-input" value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Telefono</label>
                                <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">P.IVA / CIF</label>
                                <input type="text" name="vat_number" class="form-input" value="<?= htmlspecialchars($client['vat_number'] ?? '') ?>">
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Indirizzo</label>
                                <input type="text" name="address" class="form-input" value="<?= htmlspecialchars($client['address'] ?? '') ?>">
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Note</label>
                                <textarea name="notes" class="form-textarea"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary">
                                <?= $action === 'edit' ? 'Salva Modifiche' : 'Crea Cliente' ?>
                            </button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>

                    <?php if ($action === 'edit' && !empty($clientProducts)): ?>
                        <div class="mt-8 pt-6 border-t border-white/[0.06]">
                            <h4 class="text-sm font-bold text-white mb-3">Prodotti Associati</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($clientProducts as $cp): ?>
                                    <span class="badge badge-active"><?= htmlspecialchars($cp['products']['name'] ?? 'N/A') ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
