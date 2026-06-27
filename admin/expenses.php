<?php
/**
 * IntuiFy Admin — Expense Tracking
 * Upload expense invoices + categorize spending
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/openai.php';

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

// AI SCAN INVOICE
if ($action === 'ai-scan' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['scan_file'])) {
    header('Content-Type: application/json');
    
    $file = $_FILES['scan_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(['error' => 'Formato non supportato. Usa JPG, PNG o WebP.']);
        exit;
    }
    
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => 'File troppo grande (max 10MB).']);
        exit;
    }
    
    $imageData = base64_encode(file_get_contents($file['tmp_name']));
    $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $mime = $mimeTypes[$ext] ?? 'image/jpeg';
    
    $ai = getOpenAI();
    $prompt = <<<PROMPT
Analizza questa fattura/ricevuta di spesa e estrai i seguenti dati.
Rispondi SOLO con un JSON valido con questi campi:
{
    "description": "descrizione breve della spesa",
    "vendor": "nome del fornitore/azienda",
    "amount": 0.00,
    "currency": "EUR",
    "category": "una tra: hosting, software, marketing, legal, design, hardware, office, other",
    "date": "YYYY-MM-DD",
    "invoice_number": "numero fattura se presente",
    "notes": "eventuali note aggiuntive"
}
Se un campo non è leggibile, usa un valore vuoto o null.
L'importo deve essere un numero (senza simbolo valuta).
PROMPT;

    $result = $ai->visionJson($imageData, $prompt, $mime);
    
    if ($result) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['error' => 'Non sono riuscito a leggere la fattura. Riprova con un\'immagine più chiara.']);
    }
    exit;
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
                        <div class="flex items-center gap-2">
                            <button onclick="document.getElementById('ai-scan-modal').classList.remove('hidden')" class="btn btn-sm" style="background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.2)">
                                🤖 Scansiona Fattura
                            </button>
                            <a href="?action=new" class="btn btn-primary btn-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                Nuova Spesa
                            </a>
                        </div>
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
                            <?php
                                // AI pre-fill from scan
                                $aiDesc = $_GET['ai_description'] ?? '';
                                $aiVendor = $_GET['ai_vendor'] ?? '';
                                $aiAmount = $_GET['ai_amount'] ?? '';
                                $aiCategory = $_GET['ai_category'] ?? '';
                                $aiDate = $_GET['ai_date'] ?? '';
                                $aiNotes = $_GET['ai_notes'] ?? '';
                            ?>
                            <div class="form-group md:col-span-2">
                                <label class="form-label">Descrizione *</label>
                                <input type="text" name="description" class="form-input" value="<?= htmlspecialchars($expense['description'] ?? $aiDesc) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fornitore</label>
                                <input type="text" name="vendor" class="form-input" value="<?= htmlspecialchars($expense['vendor'] ?? $aiVendor) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Importo (€) *</label>
                                <input type="number" name="amount" step="0.01" class="form-input" value="<?= htmlspecialchars((string)($expense['amount'] ?? $aiAmount)) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Categoria</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($categoryLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($expense['category'] ?? $aiCategory ?: 'other') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data</label>
                                <input type="date" name="date" class="form-input" value="<?= htmlspecialchars($expense['date'] ?? ($aiDate ?: date('Y-m-d'))) ?>">
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
                                <textarea name="notes" class="form-textarea"><?= htmlspecialchars($expense['notes'] ?? $aiNotes) ?></textarea>
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

    <!-- AI Scan Invoice Modal -->
    <div id="ai-scan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
        <div class="bg-[#12121e] border border-white/[0.08] rounded-2xl w-full max-w-lg mx-4 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white">🤖 Scansiona Fattura con AI</h3>
                <button onclick="document.getElementById('ai-scan-modal').classList.add('hidden')" class="text-slate-500 hover:text-white text-xl">&times;</button>
            </div>
            
            <div id="scan-dropzone" class="border-2 border-dashed border-white/[0.12] rounded-xl p-8 text-center cursor-pointer hover:border-indigo-500/40 transition-colors">
                <div id="scan-preview" class="hidden mb-4">
                    <img id="scan-preview-img" class="max-h-48 mx-auto rounded-lg" alt="Preview">
                </div>
                <div id="scan-placeholder">
                    <p class="text-3xl mb-2">📸</p>
                    <p class="text-slate-400 text-sm">Trascina qui la foto della fattura</p>
                    <p class="text-slate-600 text-xs mt-1">oppure clicca per selezionare (JPG, PNG, WebP)</p>
                </div>
                <input type="file" id="scan-file-input" class="hidden" accept=".jpg,.jpeg,.png,.webp">
            </div>
            
            <div id="scan-status" class="hidden mt-4 text-center">
                <div class="inline-flex items-center gap-2 text-indigo-400 text-sm">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Analisi in corso con GPT-4o Vision...
                </div>
            </div>
            
            <div id="scan-error" class="hidden mt-4 text-center text-red-400 text-sm"></div>
            
            <div id="scan-result" class="hidden mt-4 space-y-2 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div><span class="text-slate-500">Fornitore:</span> <span id="res-vendor" class="text-white font-medium"></span></div>
                    <div><span class="text-slate-500">Importo:</span> <span id="res-amount" class="text-emerald-400 font-bold font-mono"></span></div>
                    <div><span class="text-slate-500">Data:</span> <span id="res-date" class="text-white"></span></div>
                    <div><span class="text-slate-500">Categoria:</span> <span id="res-category" class="text-white"></span></div>
                    <div class="col-span-2"><span class="text-slate-500">Descrizione:</span> <span id="res-description" class="text-white"></span></div>
                </div>
                <button id="scan-confirm-btn" class="btn btn-primary w-full mt-4">✅ Conferma e crea spesa</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const dropzone = document.getElementById('scan-dropzone');
        const fileInput = document.getElementById('scan-file-input');
        const placeholder = document.getElementById('scan-placeholder');
        const preview = document.getElementById('scan-preview');
        const previewImg = document.getElementById('scan-preview-img');
        const status = document.getElementById('scan-status');
        const errorDiv = document.getElementById('scan-error');
        const resultDiv = document.getElementById('scan-result');
        let scanData = null;

        if (!dropzone) return;

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-indigo-500/60'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-indigo-500/60'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-indigo-500/60');
            if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', () => { if (fileInput.files.length) handleFile(fileInput.files[0]); });

        function handleFile(file) {
            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);

            // Send to AI
            status.classList.remove('hidden');
            errorDiv.classList.add('hidden');
            resultDiv.classList.add('hidden');

            const formData = new FormData();
            formData.append('scan_file', file);

            fetch('?action=ai-scan', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    status.classList.add('hidden');
                    if (data.error) {
                        errorDiv.textContent = data.error;
                        errorDiv.classList.remove('hidden');
                    } else if (data.success && data.data) {
                        scanData = data.data;
                        document.getElementById('res-vendor').textContent = data.data.vendor || '—';
                        document.getElementById('res-amount').textContent = '€' + (parseFloat(data.data.amount) || 0).toFixed(2);
                        document.getElementById('res-date').textContent = data.data.date || '—';
                        document.getElementById('res-category').textContent = data.data.category || '—';
                        document.getElementById('res-description').textContent = data.data.description || '—';
                        resultDiv.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    status.classList.add('hidden');
                    errorDiv.textContent = 'Errore di connessione.';
                    errorDiv.classList.remove('hidden');
                });
        }

        document.getElementById('scan-confirm-btn')?.addEventListener('click', () => {
            if (!scanData) return;
            // Navigate to new expense form with pre-filled data
            const params = new URLSearchParams({
                action: 'new',
                ai_description: scanData.description || '',
                ai_vendor: scanData.vendor || '',
                ai_amount: scanData.amount || '',
                ai_category: scanData.category || 'other',
                ai_date: scanData.date || '',
                ai_notes: scanData.notes || '',
            });
            window.location.href = '?' + params.toString();
        });
    })();
    </script>
</body>
</html>
