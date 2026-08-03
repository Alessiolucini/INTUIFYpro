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

// ─── AI GENERATE (POST — save after preview is now the only POST for ai-generate) ─
$aiError = '';

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
                     AI CONTRACT GENERATOR — form + AJAX fetch
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

                        <!-- Error banner (shown by JS) -->
                        <div id="aiErrorBanner" class="hidden mx-6 mb-4 p-3 rounded-lg text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171"></div>

                        <!-- STEP 1: Descrizione base -->
                        <div class="p-6" id="step1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-group">
                                    <label class="form-label">Cliente *</label>
                                    <select id="ai_client_id" class="form-select" required>
                                        <option value="">Seleziona cliente...</option>
                                        <?php foreach ($clients as $cl): ?>
                                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prodotto (opzionale)</label>
                                    <select id="ai_product_id" class="form-select">
                                        <option value="">Nessuno</option>
                                        <?php foreach ($products as $pr): ?>
                                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group mb-5">
                                <label class="form-label">Descrivi il contratto *</label>
                                <textarea id="ai_description" class="form-textarea" style="min-height:8rem" required
                                    placeholder="Es: Contratto fornitura software CRM SaaS per il cliente Rossi Srl. Abbonamento mensile 500€ + setup una tantum 1.000€. Durata minima 2 anni. Il servizio include assistenza telefonica lun-ven 9-18. Pagamenti entro 5 giorni dall'inizio mese. Foro di Milano."></textarea>
                                <p class="text-xs text-slate-500 mt-1">Scrivi tutto quello che sai: importo, durata, servizi inclusi, SLA, condizioni speciali. L'AI ti farà domande per completare i dettagli mancanti.</p>
                            </div>
                            <button type="button" onclick="loadQuestions()" class="btn btn-primary" id="analyzeBtn">
                                <span id="analyzeLabel">🔍 Analizza e prepara le domande</span>
                                <span id="analyzeSpinner" class="hidden">⏳ Analisi in corso…</span>
                            </button>
                        </div>

                        <!-- STEP 2: Domande AI -->
                        <div id="step2" class="hidden p-6 border-t border-white/[0.06]">
                            <div class="flex items-center gap-2 mb-4">
                                <span style="color:#a78bfa;font-size:1.1rem">✦</span>
                                <h4 class="text-sm font-semibold text-slate-200">Domande del consulente</h4>
                                <span class="text-xs text-slate-500">Rispondi per ottenere un contratto più preciso</span>
                            </div>
                            <div id="questionsContainer" class="space-y-4 mb-5"></div>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="generateWithAI()" class="btn btn-primary" id="generateBtn">
                                    <span id="generateLabel">✦ Redigi il Contratto</span>
                                    <span id="generateSpinner" class="hidden">⏳ Redazione in corso… (60–90 sec)</span>
                                </button>
                                <button type="button" onclick="generateWithAI(true)" class="btn btn-secondary btn-sm">Salta domande e genera subito</button>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Anteprima + Salva -->
                    <div id="step3" class="hidden">
                        <div class="card" style="border-color:rgba(167,139,250,0.25)">
                            <div class="card-header" style="background:rgba(139,92,246,0.05)">
                                <h3 class="card-title text-purple-400">✦ Contratto Redatto — Anteprima e Salvataggio</h3>
                                <button type="button" onclick="resetFlow()" class="btn btn-secondary btn-sm">Rigenera</button>
                            </div>
                            <form method="POST" action="?action=ai-generate" class="p-6" id="saveForm">
                                <input type="hidden" name="save_ai" value="1">
                                <input type="hidden" name="client_id"       id="save_client_id">
                                <input type="hidden" name="product_id"      id="save_product_id">
                                <input type="hidden" name="contract_number" id="save_contract_number">
                                <input type="hidden" name="clauses_json"    id="save_clauses_json">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div class="form-group md:col-span-2">
                                        <label class="form-label">Titolo Contratto</label>
                                        <input type="text" name="title" id="save_title" class="form-input" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">N° Contratto</label>
                                        <input type="text" id="save_num_display" class="form-input" readonly style="opacity:0.6">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Importo (€)</label>
                                        <input type="number" name="amount" id="save_amount" step="0.01" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data Inizio</label>
                                        <input type="date" name="start_date" id="save_start" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data Fine</label>
                                        <input type="date" name="end_date" id="save_end" class="form-input">
                                    </div>
                                    <div class="form-group md:col-span-2">
                                        <label class="form-label">Condizioni di Pagamento & IBAN</label>
                                        <textarea name="payment_terms" id="save_payment" class="form-textarea" style="min-height:5rem"></textarea>
                                    </div>
                                    <div class="form-group md:col-span-2">
                                        <label class="form-label">Note interne (non nel PDF)</label>
                                        <input type="text" name="description" class="form-input" placeholder="Opzionale">
                                    </div>
                                </div>
                                <!-- Clausole -->
                                <div class="mb-6">
                                    <h4 class="text-sm font-semibold text-slate-300 mb-3" id="clauseCount">Articoli</h4>
                                    <div class="space-y-3" id="clausesContainer"></div>
                                </div>
                                <div class="flex items-center gap-3 pt-4 border-t border-white/[0.06]">
                                    <button type="submit" class="btn btn-primary">💾 Salva e Scarica PDF</button>
                                    <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                </div>
                            </form>
                        </div>
                    </div>
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
    // ── State ────────────────────────────────────────────────────────────────
    let _questions = [];   // [{id, question, type, placeholder, options, required}]

    // ── Step 1 → 2: Load clarifying questions ────────────────────────────────
    async function loadQuestions() {
        const clientId    = document.getElementById('ai_client_id')?.value;
        const description = document.getElementById('ai_description')?.value?.trim();
        if (!clientId)                      { alert('Seleziona un cliente');                        return; }
        if (!description || description.length < 30) { alert('Descrivi il contratto (almeno 30 caratteri)'); return; }

        setBusy('analyzeBtn', 'analyzeLabel', 'analyzeSpinner', true);
        hideError();

        try {
            const fd = new FormData();
            fd.append('client_id',   clientId);
            fd.append('description', description);
            const res  = await fetch('/admin/api/contract-questions.php', { method:'POST', body:fd, signal: AbortSignal.timeout(50000) });
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || `Errore HTTP ${res.status}`);

            _questions = data.questions || [];
            renderQuestions(_questions);
            document.getElementById('step2').classList.remove('hidden');
            document.getElementById('step2').scrollIntoView({ behavior:'smooth', block:'start' });
        } catch(err) {
            showError(err.message);
        } finally {
            setBusy('analyzeBtn', 'analyzeLabel', 'analyzeSpinner', false);
        }
    }

    function renderQuestions(questions) {
        const wrap = document.getElementById('questionsContainer');
        wrap.innerHTML = '';
        questions.forEach(q => {
            const div = document.createElement('div');
            div.className = 'form-group';
            let inputHtml = '';
            if (q.type === 'textarea') {
                inputHtml = `<textarea id="qa_${q.id}" class="form-textarea" style="min-height:5rem" placeholder="${escHtml(q.placeholder||'')}"></textarea>`;
            } else if (q.type === 'select' && q.options?.length) {
                const opts = q.options.map(o => `<option value="${escHtml(o)}">${escHtml(o)}</option>`).join('');
                inputHtml = `<select id="qa_${q.id}" class="form-select"><option value="">Seleziona…</option>${opts}</select>`;
            } else {
                const t = q.type === 'number' ? 'number' : 'text';
                inputHtml = `<input type="${t}" id="qa_${q.id}" class="form-input" placeholder="${escHtml(q.placeholder||'')}">`;
            }
            div.innerHTML = `
                <label class="form-label" style="color:#c4b5fd">
                    ${escHtml(q.question)} ${q.required ? '<span style="color:#f87171">*</span>' : '<span style="color:#64748b;font-weight:normal">(opzionale)</span>'}
                </label>
                ${inputHtml}`;
            wrap.appendChild(div);
        });
    }

    // ── Step 2 → 3: Generate the full professional contract ───────────────────
    async function generateWithAI(skipQuestions = false) {
        const clientId    = document.getElementById('ai_client_id')?.value;
        const productId   = document.getElementById('ai_product_id')?.value;
        const description = document.getElementById('ai_description')?.value?.trim();

        // Collect Q&A answers
        const answers = {};
        if (!skipQuestions) {
            _questions.forEach(q => {
                const el = document.getElementById('qa_' + q.id);
                if (el) answers[q.question] = el.value.trim();
            });
        }

        setBusy('generateBtn', 'generateLabel', 'generateSpinner', true);
        hideError();

        try {
            const fd = new FormData();
            fd.append('client_id',   clientId);
            fd.append('description', description);
            fd.append('answers',     JSON.stringify(answers));
            const res  = await fetch('/admin/api/generate-contract.php', { method:'POST', body:fd, signal: AbortSignal.timeout(150000) });
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || `Errore HTTP ${res.status}`);

            // Populate save form
            document.getElementById('save_client_id').value    = clientId;
            document.getElementById('save_product_id').value   = productId || '';
            document.getElementById('save_contract_number').value = data.contract_number || '';
            document.getElementById('save_num_display').value    = data.contract_number || '';
            document.getElementById('save_title').value          = data.title || '';
            document.getElementById('save_amount').value         = data.amount || '';
            document.getElementById('save_start').value          = data.start_date || '';
            document.getElementById('save_end').value            = data.end_date   || '';
            document.getElementById('save_payment').value        = data.payment_terms || '';
            document.getElementById('save_clauses_json').value   = JSON.stringify(data.clauses || []);

            // Render clauses
            const container = document.getElementById('clausesContainer');
            container.innerHTML = '';
            const clauses = data.clauses || [];
            document.getElementById('clauseCount').textContent = `Articoli del contratto (${clauses.length})`;
            clauses.forEach(c => {
                const div = document.createElement('div');
                div.className = 'rounded-xl p-4';
                div.style.cssText = 'border:1px solid rgba(99,102,241,0.2);background:rgba(99,102,241,0.03)';
                div.innerHTML = `
                    <p class="text-xs font-bold mb-2" style="color:#818cf8;text-transform:uppercase;letter-spacing:.05em">
                        Art. ${c.number} — ${escHtml(c.title)}
                    </p>
                    <p class="text-xs leading-relaxed" style="color:#94a3b8;white-space:pre-line">${escHtml(c.text)}</p>`;
                container.appendChild(div);
            });

            document.getElementById('step3').classList.remove('hidden');
            document.getElementById('step3').scrollIntoView({ behavior:'smooth', block:'start' });
        } catch(err) {
            showError(err.message);
        } finally {
            setBusy('generateBtn', 'generateLabel', 'generateSpinner', false);
        }
    }

    function resetFlow() {
        document.getElementById('step2').classList.add('hidden');
        document.getElementById('step3').classList.add('hidden');
        document.getElementById('step1').scrollIntoView({ behavior:'smooth' });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function setBusy(btnId, labelId, spinnerId, busy) {
        const btn = document.getElementById(btnId);
        const lbl = document.getElementById(labelId);
        const sp  = document.getElementById(spinnerId);
        if (btn) btn.disabled = busy;
        if (lbl) lbl.classList.toggle('hidden', busy);
        if (sp)  sp.classList.toggle('hidden', !busy);
    }
    function showError(msg) {
        const b = document.getElementById('aiErrorBanner');
        if (b) { b.textContent = '✗ ' + msg; b.classList.remove('hidden'); }
    }
    function hideError() {
        const b = document.getElementById('aiErrorBanner');
        if (b) b.classList.add('hidden');
    }
    function escHtml(str) {
        return String(str||'')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    </script>
</body>
</html>

