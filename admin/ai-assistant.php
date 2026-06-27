<?php
/**
 * IntuiFy Admin — AI Product Expert Assistant
 * Interactive chat with knowledge of all IntuiFy products.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/openai.php';

$pageTitle = 'AI Assistente';
$breadcrumb = 'Esperto Prodotti';
$sb = getSupabase();

// Load products from DB for context
$products = $sb->select('products', ['select' => '*', 'order' => 'name.asc']) ?: [];

// Build product knowledge string
$productKnowledge = "";
foreach ($products as $p) {
    $productKnowledge .= "- **{$p['name']}** ({$p['type']}): {$p['description']}";
    if (!empty($p['url'])) $productKnowledge .= " — URL: {$p['url']}";
    $productKnowledge .= " [Status: {$p['status']}]\n";
}

if (empty($productKnowledge)) {
    $productKnowledge = "- **Auterio**: Piattaforma AI-powered per il settore automotive (gestione concessionarie, preventivi, CRM)\n";
    $productKnowledge .= "- **LingoBite**: App di apprendimento linguistico con AI (micro-lezioni, gamification, podcast AI)\n";
    $productKnowledge .= "- **Orqesia**: Piattaforma di gestione orchestrale ed eventi musicali\n";
    $productKnowledge .= "- **Eco Andratx**: Progetto di sostenibilità ambientale digitale\n";
}

$systemPrompt = <<<PROMPT
Sei l'Esperto Prodotti di IntuiFy, uno studio tecnologico specializzato nello sviluppo di software innovativo e soluzioni AI.

## Informazioni Aziendali
- **Ragione Sociale**: Intuify Ventures SL
- **CIF**: B88769526
- **Sede**: Calle Mussol 5 2Pta. B
- **Email**: info@intuify.net
- **Sito**: https://intuify.net

## I Nostri Prodotti (dal database)
{$productKnowledge}

## Servizi Offerti
- Sviluppo app iOS/Android (Apple Store & Google Play)
- Piattaforme SaaS e AAAS (AI-as-a-Service)
- Siti web e e-commerce
- Integrazioni AI e automazione
- Consulenza tecnologica

## Il Tuo Ruolo
Sei un esperto commerciale e tecnico. Puoi:
1. Spiegare in dettaglio ogni prodotto
2. Suggerire quale prodotto proporre a un potenziale cliente
3. Generare pitch di vendita personalizzati
4. Rispondere a FAQ tecniche
5. Confrontare i nostri prodotti con competitor
6. Suggerire strategie di pricing e upselling

Rispondi sempre in italiano, in modo professionale ma amichevole.
Usa formattazione Markdown per le risposte (grassetto, elenchi, headers).
PROMPT;

// Initialize chat history in session
if (!isset($_SESSION['ai_chat'])) {
    $_SESSION['ai_chat'] = [];
}

// Handle AJAX chat message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_message'])) {
    header('Content-Type: application/json');
    
    $userMessage = trim($_POST['ai_message']);
    if (empty($userMessage)) {
        echo json_encode(['error' => 'Messaggio vuoto.']);
        exit;
    }
    
    // Add user message to history
    $_SESSION['ai_chat'][] = ['role' => 'user', 'content' => $userMessage];
    
    // Keep last 20 messages for context
    if (count($_SESSION['ai_chat']) > 20) {
        $_SESSION['ai_chat'] = array_slice($_SESSION['ai_chat'], -20);
    }
    
    $ai = getOpenAI();
    $reply = $ai->chatMultiTurn($systemPrompt, $_SESSION['ai_chat'], 0.7);
    
    if ($reply) {
        $_SESSION['ai_chat'][] = ['role' => 'assistant', 'content' => $reply];
        echo json_encode(['success' => true, 'reply' => $reply]);
    } else {
        echo json_encode(['error' => 'Errore comunicazione AI. Controlla la chiave OpenAI.']);
    }
    exit;
}

// Handle clear chat
if (isset($_GET['clear'])) {
    $_SESSION['ai_chat'] = [];
    header('Location: /admin/ai-assistant.php');
    exit;
}

$chatHistory = $_SESSION['ai_chat'] ?? [];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>AI Assistente — IntuiFy Admin</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <!-- Markdown rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .chat-container { height: calc(100vh - 220px); }
        .chat-bubble { max-width: 85%; }
        .chat-bubble-user { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.2); }
        .chat-bubble-ai { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); }
        .chat-bubble-ai h1, .chat-bubble-ai h2, .chat-bubble-ai h3 { color: #c7d2fe; margin: 12px 0 6px; font-weight: 700; }
        .chat-bubble-ai h1 { font-size: 1.1em; } .chat-bubble-ai h2 { font-size: 1em; } .chat-bubble-ai h3 { font-size: 0.95em; }
        .chat-bubble-ai ul, .chat-bubble-ai ol { padding-left: 20px; margin: 8px 0; }
        .chat-bubble-ai li { margin: 4px 0; }
        .chat-bubble-ai strong { color: #a5b4fc; }
        .chat-bubble-ai code { background: rgba(99,102,241,0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        .chat-bubble-ai pre { background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; overflow-x: auto; margin: 8px 0; }
        .chat-bubble-ai pre code { background: none; padding: 0; }
        .chat-bubble-ai p { margin: 6px 0; }
        .typing-indicator span { animation: bounce 1.4s infinite ease-in-out both; }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%,80%,100%{transform:scale(0)} 40%{transform:scale(1)} }
    </style>
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <main class="p-6 flex flex-col" style="height: calc(100vh - 64px);">
            <!-- Chat Header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center text-xl">🤖</div>
                    <div>
                        <h2 class="text-white font-bold text-lg">Esperto Prodotti IntuiFy</h2>
                        <p class="text-xs text-slate-500">Conosce tutti i prodotti • Genera pitch • FAQ tecniche</p>
                    </div>
                </div>
                <a href="?clear=1" class="btn btn-secondary btn-sm">🗑️ Nuova Chat</a>
            </div>

            <!-- Chat Messages -->
            <div id="chat-messages" class="chat-container flex-1 overflow-y-auto space-y-4 p-4 rounded-xl bg-black/20 border border-white/[0.04]">
                <?php if (empty($chatHistory)): ?>
                    <div id="welcome-msg" class="text-center py-12">
                        <p class="text-4xl mb-4">🤖</p>
                        <h3 class="text-white font-bold text-lg mb-2">Ciao! Sono l'Esperto Prodotti IntuiFy</h3>
                        <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">Posso aiutarti con informazioni sui prodotti, generare pitch di vendita, rispondere a FAQ tecniche e molto altro.</p>
                        <div class="flex flex-wrap gap-2 justify-center">
                            <button onclick="sendQuick('Fammi un riassunto di tutti i nostri prodotti')" class="px-3 py-1.5 rounded-lg bg-white/[0.04] border border-white/[0.08] text-xs text-slate-300 hover:bg-white/[0.08] transition-colors">📋 Riassunto prodotti</button>
                            <button onclick="sendQuick('Genera un pitch di vendita per Auterio')" class="px-3 py-1.5 rounded-lg bg-white/[0.04] border border-white/[0.08] text-xs text-slate-300 hover:bg-white/[0.08] transition-colors">🎯 Pitch Auterio</button>
                            <button onclick="sendQuick('Quale prodotto consiglieresti a un ristorante?')" class="px-3 py-1.5 rounded-lg bg-white/[0.04] border border-white/[0.08] text-xs text-slate-300 hover:bg-white/[0.08] transition-colors">💡 Consiglio prodotto</button>
                            <button onclick="sendQuick('Quali sono i vantaggi di LingoBite rispetto a Duolingo?')" class="px-3 py-1.5 rounded-lg bg-white/[0.04] border border-white/[0.08] text-xs text-slate-300 hover:bg-white/[0.08] transition-colors">🆚 Confronto LingoBite</button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($chatHistory as $msg): ?>
                        <div class="flex <?= $msg['role'] === 'user' ? 'justify-end' : 'justify-start' ?>">
                            <div class="chat-bubble <?= $msg['role'] === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai' ?> rounded-2xl px-4 py-3 text-sm text-slate-200">
                                <?php if ($msg['role'] === 'user'): ?>
                                    <?= htmlspecialchars($msg['content']) ?>
                                <?php else: ?>
                                    <div class="ai-md"><?= $msg['content'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Chat Input -->
            <form id="chat-form" class="mt-4 flex gap-3">
                <input type="text" id="chat-input" class="form-input flex-1 !rounded-xl" placeholder="Chiedi qualcosa sui prodotti IntuiFy..." autocomplete="off" autofocus>
                <button type="submit" id="send-btn" class="btn btn-primary px-6 !rounded-xl">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                </button>
            </form>
        </main>
    </div>

    <script>
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');

    // Auto-scroll to bottom
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    scrollToBottom();

    // Render markdown
    function renderMd(text) {
        if (typeof marked !== 'undefined') {
            return marked.parse(text);
        }
        return text.replace(/\n/g, '<br>');
    }

    // Add message to chat
    function addMessage(role, content) {
        const welcome = document.getElementById('welcome-msg');
        if (welcome) welcome.remove();

        const div = document.createElement('div');
        div.className = 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble ' + (role === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai') + ' rounded-2xl px-4 py-3 text-sm text-slate-200';
        
        if (role === 'user') {
            bubble.textContent = content;
        } else {
            const mdDiv = document.createElement('div');
            mdDiv.className = 'ai-md';
            mdDiv.innerHTML = renderMd(content);
            bubble.appendChild(mdDiv);
        }
        
        div.appendChild(bubble);
        chatMessages.appendChild(div);
        scrollToBottom();
    }

    // Show typing indicator
    function showTyping() {
        const div = document.createElement('div');
        div.id = 'typing';
        div.className = 'flex justify-start';
        div.innerHTML = '<div class="chat-bubble chat-bubble-ai rounded-2xl px-4 py-3 text-sm"><div class="typing-indicator flex gap-1"><span class="w-2 h-2 bg-indigo-400 rounded-full inline-block"></span><span class="w-2 h-2 bg-indigo-400 rounded-full inline-block"></span><span class="w-2 h-2 bg-indigo-400 rounded-full inline-block"></span></div></div>';
        chatMessages.appendChild(div);
        scrollToBottom();
    }

    function hideTyping() {
        document.getElementById('typing')?.remove();
    }

    // Send message
    async function sendMessage(text) {
        if (!text.trim()) return;
        
        addMessage('user', text);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;
        showTyping();

        try {
            const formData = new FormData();
            formData.append('ai_message', text);
            
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            
            hideTyping();
            
            if (data.reply) {
                addMessage('assistant', data.reply);
            } else if (data.error) {
                addMessage('assistant', '❌ ' + data.error);
            }
        } catch (e) {
            hideTyping();
            addMessage('assistant', '❌ Errore di connessione.');
        }
        
        chatInput.disabled = false;
        sendBtn.disabled = false;
        chatInput.focus();
    }

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage(chatInput.value);
    });

    function sendQuick(text) {
        chatInput.value = text;
        sendMessage(text);
    }
    </script>
</body>
</html>
