<?php
/**
 * IntuiFy Admin — Lead Management
 * Pipeline: new → contacted → qualified → converted → lost
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';

$pageTitle = 'Leads';
$breadcrumb = 'Gestione leads';
$sb = getSupabase();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// UPDATE STATUS (quick action)
if ($action === 'status' && $id && isset($_GET['status'])) {
    $newStatus = $_GET['status'];
    $validStatuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];
    if (in_array($newStatus, $validStatuses)) {
        $sb->update('leads', $id, ['status' => $newStatus]);
        $message = 'Status aggiornato.';
        $messageType = 'success';
    }
    $action = 'list';
}

// DELETE
if ($action === 'delete' && $id) {
    $sb->delete('leads', $id);
    $message = 'Lead eliminato.';
    $messageType = 'success';
    $action = 'list';
}

// CONVERT to Client
if ($action === 'convert' && $id) {
    $lead = $sb->find('leads', $id);
    if ($lead) {
        $clientData = [
            'company_name' => $lead['company'] ?: $lead['name'],
            'contact_person' => $lead['name'],
            'email' => $lead['email'],
            'phone' => $lead['phone'],
        ];
        $newClient = $sb->insert('clients', $clientData);
        if ($newClient) {
            $sb->update('leads', $id, [
                'status' => 'converted',
                'converted_client_id' => $newClient['id'],
            ]);
            $message = 'Lead convertito in cliente!';
            $messageType = 'success';
        }
    }
    $action = 'list';
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'company' => trim($_POST['company'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
        'source' => $_POST['source'] ?? 'other',
        'status' => $_POST['status'] ?? 'new',
    ];

    if (empty($data['name'])) {
        $message = 'Il nome è obbligatorio.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $sb->update('leads', $editId, $data);
            $message = 'Lead aggiornato.';
        } else {
            $sb->insert('leads', $data);
            $message = 'Lead creato.';
        }
        $messageType = 'success';
        $action = 'list';
    }
}

// Load data
$leads = [];
$lead = null;
$filterStatus = $_GET['filter'] ?? '';

if ($action === 'list') {
    $filters = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $filters['status'] = 'eq.' . $filterStatus;
    }
    $leads = $sb->select('leads', [
        'select' => '*',
        'order' => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $lead = $sb->find('leads', $id);
}

$statusLabels = [
    'new' => 'Nuovo',
    'contacted' => 'Contattato',
    'qualified' => 'Qualificato',
    'converted' => 'Convertito',
    'lost' => 'Perso',
];

$sourceLabels = [
    'landing_form' => 'Landing Page',
    'email' => 'Email',
    'referral' => 'Referral',
    'other' => 'Altro',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Leads — IntuiFy Admin</title>
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
                <!-- Pipeline filter tabs -->
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?filter=all" class="btn <?= !$filterStatus || $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutti</a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <a href="?filter=<?= $key ?>" class="btn <?= $filterStatus === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                            <span class="pipeline-dot pipeline-<?= $key ?>"></span>
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Leads (<?= count($leads) ?>)</h3>
                        <a href="?action=new" class="btn btn-primary btn-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Nuovo Lead
                        </a>
                    </div>

                    <?php if (empty($leads)): ?>
                        <div class="empty-state">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
                            <p>Nessun lead. I leads arriveranno dal form della landing o puoi aggiungerli manualmente.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Azienda</th>
                                        <th>Fonte</th>
                                        <th>Data</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $l): ?>
                                        <tr>
                                            <td><span class="badge badge-<?= $l['status'] ?>"><?= $statusLabels[$l['status']] ?? $l['status'] ?></span></td>
                                            <td class="font-semibold"><?= htmlspecialchars($l['name']) ?></td>
                                            <td>
                                                <?php if ($l['email']): ?>
                                                    <a href="mailto:<?= htmlspecialchars($l['email']) ?>" class="text-indigo-400 hover:text-indigo-300 text-xs"><?= htmlspecialchars($l['email']) ?></a>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($l['company'] ?? '—') ?></td>
                                            <td><span class="text-xs text-slate-500"><?= $sourceLabels[$l['source']] ?? $l['source'] ?></span></td>
                                            <td class="text-xs text-slate-500 font-mono"><?= date('d/m/Y', strtotime($l['created_at'])) ?></td>
                                            <td>
                                                <div class="flex items-center gap-1 flex-wrap">
                                                    <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                                    <?php if ($l['status'] !== 'converted'): ?>
                                                        <a href="?action=convert&id=<?= $l['id'] ?>" class="btn btn-sm" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.2)" onclick="return confirm('Convertire questo lead in cliente?')">→ Cliente</a>
                                                    <?php endif; ?>
                                                    <a href="?action=delete&id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')">×</a>
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
                        <h3 class="card-title"><?= $action === 'edit' ? 'Modifica Lead' : 'Nuovo Lead' ?></h3>
                        <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                    </div>

                    <form method="POST">
                        <?php if ($lead): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($lead['id']) ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($lead['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Telefono</label>
                                <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($lead['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Azienda</label>
                                <input type="text" name="company" class="form-input" value="<?= htmlspecialchars($lead['company'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fonte</label>
                                <select name="source" class="form-select">
                                    <?php foreach ($sourceLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($lead['source'] ?? 'other') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($lead['status'] ?? 'new') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Messaggio</label>
                                <textarea name="message" class="form-textarea"><?= htmlspecialchars($lead['message'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-white/[0.06]">
                            <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva' : 'Crea Lead' ?></button>
                            <a href="?action=list" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
