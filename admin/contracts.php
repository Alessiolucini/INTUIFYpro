<?php
/**
 * IntuiFy Admin — Contract Management
 * CRUD + AI Generation + PDF export with IntuiFy branded header, clauses, and IBAN.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/openai.php';

$pageTitle = 'Contratti';
$breadcrumb = 'Gestione contratti';
$sb     = getSupabase();
$ai     = getOpenAI();
$config = require dirname(__DIR__) . '/config.php';

$action = $_GET['action'] ?? 'list';
$id     = $_GET['id'] ?? '';
$message     = '';
$messageType = '';

// ─── DELETE ──────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $sb->delete('contracts', $id);
    $message     = 'Contratto eliminato.';
    $messageType = 'success';
    $action      = 'list';
}

// ─── GENERATE PDF ────────────────────────────────────────────────────────────
if ($action === 'pdf' && $id) {
    $contract = $sb->find('contracts', $id);
    if ($contract) {
        // Decode JSON clauses if stored as string
        if (isset($contract['clauses']) && is_string($contract['clauses'])) {
            $contract['clauses'] = json_decode($contract['clauses'], true);
        }
        $client = $sb->find('clients', $contract['client_id']);
        require_once __DIR__ . '/includes/pdf.php';
        generateContractPDF($contract, $client, $config);
        exit;
    }
}

// ─── Helper: generate contract number ────────────────────────────────────────
function nextContractNumber(object $sb, array $config): string
{
    $year   = date('Y');
    $prefix = $config['contract_prefix'] ?? 'CTR';
    $existing = $sb->select('contracts', [
        'select'  => 'contract_number',
        'filters' => ['contract_number' => 'like.' . $prefix . '-' . $year . '-%'],
        'order'   => 'contract_number.desc',
        'limit'   => 1,
    ]);
    $lastNum = 0;
    if (!empty($existing)) {
        $parts   = explode('-', $existing[0]['contract_number']);
        $lastNum = (int) end($parts);
    }
    return $prefix . '-' . $year . '-' . str_pad((string)($lastNum + 1), 3, '0', STR_PAD_LEFT);
}

// ─── AI GENERATE (POST — generate and preview) ───────────────────────────────
$aiGenerated = null;   // holds structured AI result for preview
$aiError     = '';

if ($action === 'ai-generate' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $clientId   = trim($_POST['client_id'] ?? '');
    $userPrompt = trim($_POST['user_description'] ?? '');

    if (empty($clientId) || empty($userPrompt)) {
        $message     = 'Seleziona un cliente e inserisci la descrizione.';
        $messageType = 'error';
    } else {
        $clientInfo   = $sb->find('clients', $clientId) ?? [];
        $contractNum  = nextContractNumber($sb, $config);
        $aiGenerated  = $ai->generateContract($userPrompt, $config, $clientInfo, $contractNum);

        if (!$aiGenerated) {
            $aiError = 'L\'AI non ha potuto generare il contratto. Verifica che la chiave OpenAI sia configurata e riprova.';
        }
    }
}

// ─── AI GENERATE (POST — save after preview) ─────────────────────────────────
if ($action === 'ai-generate' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai'])) {
    $clientId    = trim($_POST['client_id'] ?? '');
    $contractNum = trim($_POST['contract_number'] ?? nextContractNumber($sb, $config));

    $clausesRaw  = $_POST['clauses_json'] ?? '[]';
    $clauses     = json_decode($clausesRaw, true);

    $data = [
        'contract_number' => $contractNum,
        'client_id'       => $clientId,
        'product_id'      => $_POST['product_id'] ?: null,
        'title'           => trim($_POST['title'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'amount'          => (float) ($_POST['amount'] ?? 0),
        'currency'        => 'EUR',
        'start_date'      => $_POST['start_date'] ?: null,
        'end_date'        => $_POST['end_date'] ?: null,
        'status'          => 'draft',
        'payment_terms'   => trim($_POST['payment_terms'] ?? ''),
        'clauses'         => json_encode($clauses, JSON_UNESCAPED_UNICODE),
        'ai_generated'    => true,
    ];

    if (empty($data['title']) || empty($data['client_id'])) {
        $message     = 'Titolo e cliente sono obbligatori.';
        $messageType = 'error';
    } else {
        $result = $sb->insert('contracts', $data);
        if ($result === null) {
            $errDetail   = $sb->getLastError() ?? 'Risposta vuota dal server.';
            $message     = 'Errore salvataggio: ' . $errDetail;
            $messageType = 'error';
        } else {
            $message     = 'Contratto generato e salvato! Ora puoi scaricarlo in PDF.';
            $messageType = 'success';
            // Redirect to PDF immediately
            header('Location: ?action=pdf&id=' . $result['id']);
            exit;
        }
    }
}

// ─── SAVE (manual form) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['generate']) && !isset($_POST['save_ai']) && $action !== 'ai-generate') {
    $contractNumber = '';
    if (empty($_POST['id'])) {
        $contractNumber = nextContractNumber($sb, $config);
    }

    $data = [
        'client_id'   => $_POST['client_id'] ?? '',
        'product_id'  => $_POST['product_id'] ?: null,
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'amount'      => (float) ($_POST['amount'] ?? 0),
        'currency'    => $_POST['currency'] ?? 'EUR',
        'start_date'  => $_POST['start_date'] ?: null,
        'end_date'    => $_POST['end_date'] ?: null,
        'status'      => $_POST['status'] ?? 'draft',
    ];

    if ($contractNumber) {
        $data['contract_number'] = $contractNumber;
    }

    if (empty($data['title']) || empty($data['client_id'])) {
        $message     = 'Titolo e cliente sono obbligatori.';
        $messageType = 'error';
    } else {
        $editId = $_POST['id'] ?? '';
        if ($editId) {
            $result = $sb->update('contracts', $editId, $data);
            if ($result === null && $sb->getLastError()) {
                $message     = 'Errore aggiornamento: ' . $sb->getLastError();
                $messageType = 'error';
            } else {
                $message     = 'Contratto aggiornato.';
                $messageType = 'success';
                $action      = 'list';
            }
        } else {
            $result = $sb->insert('contracts', $data);
            if ($result === null) {
                $message     = 'Errore salvataggio: ' . ($sb->getLastError() ?? 'Risposta vuota.');
                $messageType = 'error';
            } else {
                $message     = 'Contratto creato.';
                $messageType = 'success';
                $action      = 'list';
            }
        }
    }
}

// ─── Load data ───────────────────────────────────────────────────────────────
$contracts = [];
$contract  = null;
$clients   = [];
$products  = [];

if ($action === 'list') {
    $filterStatus = $_GET['filter'] ?? '';
    $filters = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $filters['status'] = 'eq.' . $filterStatus;
    }
    $contracts = $sb->select('contracts', [
        'select'  => '*,clients(company_name)',
        'order'   => 'created_at.desc',
        'filters' => $filters,
    ]);
} elseif ($action === 'edit' && $id) {
    $contract = $sb->find('contracts', $id);
    $clients  = $sb->select('clients',  ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name',         'order' => 'name.asc']);
} elseif ($action === 'new' || $action === 'ai-generate') {
    $clients  = $sb->select('clients',  ['select' => 'id,company_name', 'order' => 'company_name.asc']);
    $products = $sb->select('products', ['select' => 'id,name',         'order' => 'name.asc']);
}

$statusLabels = [
    'draft'     => 'Bozza',
    'sent'      => 'Inviato',
    'signed'    => 'Firmato',
    'expired'   => 'Scaduto',
    'cancelled' => 'Annullato',
];
$cycleLabels = [
    'monthly'    => 'Mensile',
    'quarterly'  => 'Trimestrale',
    'semiannual' => 'Semestrale',
    'annual'     => 'Annuale',
    'one_time'   => 'Una Tantum',
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
                        <div class="flex items-center gap-2">
                            <a href="?action=ai-generate" class="btn btn-sm" style="background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(168,85,247,0.2));color:#a78bfa;border:1px solid rgba(167,139,250,0.3)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                Genera con AI
                            </a>
                            <a href="?action=new" class="btn btn-primary btn-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                Nuovo Contratto
                            </a>
                        </div>
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
                                            <td class="font-mono text-xs">
                                                <?= htmlspecialchars($c['contract_number']) ?>
                                                <?php if ($c['ai_generated'] ?? false): ?>
                                                    <span class="ml-1 text-purple-400" title="Generato da AI">✦</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-semibold"><?= htmlspecialchars($c['title']) ?></td>
                                            <td><?= htmlspecialchars($c['clients']['company_name'] ?? '—') ?></td>
                                            <td class="font-mono">€<?= number_format((float)$c['amount'], 2, ',', '.') ?></td>
                                            <td class="text-xs"><?= $c['start_date'] ? date('d/m/Y', strtotime($c['start_date'])) : '—' ?></td>
                                            <td class="text-xs"><?= $c['end_date']   ? date('d/m/Y', strtotime($c['end_date']))   : '—' ?></td>
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

            <?php elseif ($action === 'ai-generate'): ?>
                <!-- ══════════════════════════════════════════════════════════
                     AI CONTRACT GENERATOR
                ══════════════════════════════════════════════════════════ -->
                <div class="max-w-4xl">
                    <div class="card mb-6">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title flex items-center gap-2">
                                    <span style="color:#a78bfa">✦</span> Genera Contratto con AI
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">Descrivi il contratto e l'AI genererà un documento completo con clausole, IBAN e spazi firma.</p>
                            </div>
                            <a href="?action=list" class="btn btn-secondary btn-sm">← Indietro</a>
                        </div>

                        <?php if ($aiError): ?>
                            <div class="toast toast-error mb-4"><?= htmlspecialchars($aiError) ?></div>
                        <?php endif; ?>

                        <!-- Step 1: Input form -->
                        <form method="POST" class="p-6" id="generateForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-group">
                                    <label class="form-label">Cliente *</label>
                                    <select name="client_id" id="clientSelect" class="form-select" required>
                                        <option value="">Seleziona cliente...</option>
                                        <?php foreach ($clients as $cl): ?>
                                            <option value="<?= $cl['id'] ?>" <?= ($_POST['client_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prodotto (opzionale)</label>
                                    <select name="product_id" class="form-select">
                                        <option value="">Nessuno</option>
                                        <?php foreach ($products as $pr): ?>
                                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label class="form-label">Descrivi il contratto *</label>
                                <textarea name="user_description" class="form-textarea" style="min-height:9rem" required
                                    placeholder="Es: Contratto di abbonamento mensile per il servizio SaaS LingoBite. Importo 299€/mese. Durata 12 mesi a partire da settembre 2026. Il cliente ha diritto ad assistenza tecnica 5×8 e aggiornamenti inclusi. Preavviso di recesso 30 giorni."><?= htmlspecialchars($_POST['user_description'] ?? '') ?></textarea>
                                <p class="text-xs text-slate-500 mt-1">Includi: tipo di servizio, importo, durata, condizioni particolari, SLA, modalità di pagamento desiderate…</p>
                            </div>

                            <button type="submit" name="generate" value="1" class="btn btn-primary" id="generateBtn">
                                <span id="generateLabel">✦ Genera Contratto</span>
                                <span id="generateSpinner" class="hidden">⏳ Generazione in corso…</span>
                            </button>
                        </form>
                    </div>

                    <?php if ($aiGenerated): ?>
                    <!-- ─── Step 2: Preview & Save ──────────────────────────── -->
                    <?php
                        $previewClauses = $aiGenerated['clauses'] ?? [];
                        $previewTitle   = htmlspecialchars($aiGenerated['title'] ?? '');
                        $previewAmount  = $aiGenerated['amount'] ?? 0;
                        $previewCycle   = $aiGenerated['billing_cycle'] ?? 'monthly';
                        $previewStart   = $aiGenerated['start_date'] ?? date('Y-m-d');
                        $previewEnd     = $aiGenerated['end_date'] ?? '';
                        $previewPayment = $aiGenerated['payment_terms'] ?? '';
                        $clausesJson    = json_encode($previewClauses, JSON_UNESCAPED_UNICODE);
                        $contractNum    = nextContractNumber($sb, $config);
                    ?>
                    <div class="card" style="border-color:rgba(167,139,250,0.25)">
                        <div class="card-header" style="background:rgba(139,92,246,0.05)">
                            <h3 class="card-title text-purple-400">✦ Contratto Generato — Anteprima e Salvataggio</h3>
                        </div>

                        <form method="POST" class="p-6">
                            <input type="hidden" name="save_ai" value="1">
                            <input type="hidden" name="client_id" value="<?= htmlspecialchars($_POST['client_id'] ?? '') ?>">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($_POST['product_id'] ?? '') ?>">
                            <input type="hidden" name="contract_number" value="<?= htmlspecialchars($contractNum) ?>">
                            <input type="hidden" name="clauses_json" id="clausesJson" value="<?= htmlspecialchars($clausesJson) ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div class="form-group md:col-span-2">
                                    <label class="form-label">Titolo Contratto</label>
                                    <input type="text" name="title" class="form-input" value="<?= $previewTitle ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">N° Contratto</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($contractNum) ?>" readonly style="opacity:0.6">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Importo (€)</label>
                                    <input type="number" name="amount" step="0.01" class="form-input" value="<?= $previewAmount ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Inizio</label>
                                    <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($previewStart) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Fine</label>
                                    <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($previewEnd) ?>">
                                </div>
                                <div class="form-group md:col-span-2">
                                    <label class="form-label">Descrizione / Note brevi</label>
                                    <input type="text" name="description" class="form-input" placeholder="Descrizione breve (opzionale)">
                                </div>
                                <div class="form-group md:col-span-2">
                                    <label class="form-label">Condizioni di Pagamento & IBAN</label>
                                    <textarea name="payment_terms" class="form-textarea" style="min-height:5rem"><?= htmlspecialchars($previewPayment) ?></textarea>
                                </div>
                            </div>

                            <!-- Clausole preview -->
                            <div class="mb-6">
                                <h4 class="text-sm font-semibold text-slate-300 mb-3">Clausole generate (<?= count($previewClauses) ?>)</h4>
                                <div class="space-y-3" id="clausesContainer">
                                    <?php foreach ($previewClauses as $i => $clause): ?>
                                        <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4" data-clause="<?= $i ?>">
                                            <div class="flex items-start justify-between gap-3 mb-2">
                                                <p class="text-xs font-bold text-indigo-400">Art. <?= $clause['number'] ?> — <?= htmlspecialchars($clause['title']) ?></p>
                                            </div>
                                            <p class="text-xs text-slate-400 leading-relaxed"><?= nl2br(htmlspecialchars($clause['text'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-white/[0.06]">
                                <button type="submit" class="btn btn-primary">
                                    💾 Salva e Scarica PDF
                                </button>
                                <a href="?action=ai-generate" class="btn btn-secondary">Rigenera</a>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Manual form -->
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

    <script>
    // Show spinner on generate click
    const generateForm = document.getElementById('generateForm');
    if (generateForm) {
        generateForm.addEventListener('submit', function(e) {
            const btn = document.getElementById('generateBtn');
            const label = document.getElementById('generateLabel');
            const spinner = document.getElementById('generateSpinner');
            if (btn && label && spinner) {
                btn.disabled = true;
                label.classList.add('hidden');
                spinner.classList.remove('hidden');
            }
        });
    }
    </script>
</body>
</html>
