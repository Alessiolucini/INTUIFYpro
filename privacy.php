<?php
/**
 * IntuiFy - Privacy Policy Page
 * Premium Soft Structuralism design, multilingue (es/it/en)
 */

declare(strict_types=1);

session_start();

// Detect language
function detectLanguage(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'it', 'en'])) {
        $_SESSION['lang'] = $_GET['lang'];
        return $_GET['lang'];
    }
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    if (str_starts_with($acceptLang, 'es')) return 'es';
    if (str_starts_with($acceptLang, 'it')) return 'it';
    return 'en';
}

$currentLang = detectLanguage();

// Load main translations (for nav/footer)
$i18nFile = __DIR__ . "/i18n/{$currentLang}.json";
if (!file_exists($i18nFile)) {
    $i18nFile = __DIR__ . "/i18n/en.json";
}
$t = json_decode(file_get_contents($i18nFile), true);

// Privacy Policy content per language
$privacy = [
    'en' => [
        'title' => 'Privacy Policy',
        'meta_title' => 'Privacy Policy – IntuiFy Ventures',
        'meta_description' => 'Privacy Policy of IntuiFy Ventures S.L. Learn how we collect, use, and protect your personal data.',
        'last_updated' => 'Last updated: June 2, 2026',
        'sections' => [
            [
                'title' => '1. Data Controller',
                'content' => 'The data controller is <strong>IntuiFy Ventures S.L.</strong> For any inquiries regarding your personal data, you can contact us at <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '2. Data We Collect',
                'content' => 'We collect the following categories of personal data:',
                'list' => [
                    '<strong>Contact form data:</strong> full name, company name, email address, and message content when you submit our contact form.',
                    '<strong>Technical data:</strong> IP address, browser type, operating system, and browsing behavior collected automatically through cookies and server logs.',
                    '<strong>Communication data:</strong> records of correspondence when you contact us via email.'
                ]
            ],
            [
                'title' => '3. Purpose of Processing',
                'content' => 'We process your personal data for the following purposes:',
                'list' => [
                    'Responding to your inquiries and contact form submissions.',
                    'Providing and improving our services.',
                    'Ensuring the security and functionality of our website.',
                    'Complying with legal obligations.',
                    'Protecting against spam and abuse (via Google reCAPTCHA v3).'
                ]
            ],
            [
                'title' => '4. Legal Basis',
                'content' => 'We process your data based on the following legal grounds under GDPR:',
                'list' => [
                    '<strong>Consent (Art. 6(1)(a) GDPR):</strong> when you voluntarily submit the contact form.',
                    '<strong>Legitimate interest (Art. 6(1)(f) GDPR):</strong> for website security, analytics, and fraud prevention.',
                    '<strong>Legal obligation (Art. 6(1)(c) GDPR):</strong> to comply with applicable laws and regulations.'
                ]
            ],
            [
                'title' => '5. Cookies & Third-Party Services',
                'content' => 'Our website uses the following third-party services:',
                'list' => [
                    '<strong>Google reCAPTCHA v3:</strong> to protect our contact form from spam and abuse. This service may collect your IP address and browsing data. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-accent hover:underline">Google\'s Privacy Policy</a>.',
                    '<strong>Google Fonts:</strong> to serve web fonts. Your IP address may be transmitted to Google servers. See <a href="https://developers.google.com/fonts/faq/privacy" target="_blank" rel="noopener" class="text-accent hover:underline">Google Fonts Privacy</a>.',
                    '<strong>Tailwind CSS CDN:</strong> for styling. Served via a CDN which may log basic access data.'
                ]
            ],
            [
                'title' => '6. Data Retention',
                'content' => 'We retain your personal data only for as long as necessary to fulfill the purposes described above, or as required by law. Contact form submissions are stored for a maximum of 24 months unless a longer retention period is required for legal or contractual purposes.'
            ],
            [
                'title' => '7. Data Sharing',
                'content' => 'We do not sell, trade, or rent your personal data to third parties. We may share your data with:',
                'list' => [
                    'Service providers who assist in operating our website (hosting, email delivery).',
                    'Authorities, when required by applicable law or regulation.',
                    'Subsidiaries within the IntuiFy Ventures group, solely for legitimate business purposes.'
                ]
            ],
            [
                'title' => '8. Your Rights',
                'content' => 'Under the GDPR, you have the following rights regarding your personal data:',
                'list' => [
                    '<strong>Right of access:</strong> obtain confirmation and a copy of your personal data.',
                    '<strong>Right to rectification:</strong> request correction of inaccurate data.',
                    '<strong>Right to erasure:</strong> request deletion of your data ("right to be forgotten").',
                    '<strong>Right to restrict processing:</strong> request limitation of how we use your data.',
                    '<strong>Right to data portability:</strong> receive your data in a machine-readable format.',
                    '<strong>Right to object:</strong> object to processing based on legitimate interests.',
                    '<strong>Right to withdraw consent:</strong> withdraw consent at any time without affecting prior processing.'
                ],
                'extra' => 'To exercise any of these rights, contact us at <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>. You also have the right to lodge a complaint with a supervisory authority.'
            ],
            [
                'title' => '9. Data Security',
                'content' => 'We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. These include SSL/TLS encryption, secure server infrastructure, and access controls.'
            ],
            [
                'title' => '10. Changes to This Policy',
                'content' => 'We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated "last updated" date. We encourage you to review this page periodically.'
            ]
        ]
    ],
    'es' => [
        'title' => 'Política de Privacidad',
        'meta_title' => 'Política de Privacidad – IntuiFy Ventures',
        'meta_description' => 'Política de Privacidad de IntuiFy Ventures S.L. Conozca cómo recopilamos, utilizamos y protegemos sus datos personales.',
        'last_updated' => 'Última actualización: 2 de junio de 2026',
        'sections' => [
            [
                'title' => '1. Responsable del Tratamiento',
                'content' => 'El responsable del tratamiento es <strong>IntuiFy Ventures S.L.</strong> Para cualquier consulta sobre sus datos personales, puede contactarnos en <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '2. Datos que Recopilamos',
                'content' => 'Recopilamos las siguientes categorías de datos personales:',
                'list' => [
                    '<strong>Datos del formulario de contacto:</strong> nombre completo, empresa, dirección de correo electrónico y contenido del mensaje.',
                    '<strong>Datos técnicos:</strong> dirección IP, tipo de navegador, sistema operativo y comportamiento de navegación recogidos automáticamente.',
                    '<strong>Datos de comunicación:</strong> registros de correspondencia cuando nos contacta por correo electrónico.'
                ]
            ],
            [
                'title' => '3. Finalidad del Tratamiento',
                'content' => 'Tratamos sus datos personales para las siguientes finalidades:',
                'list' => [
                    'Responder a sus consultas y envíos del formulario de contacto.',
                    'Proporcionar y mejorar nuestros servicios.',
                    'Garantizar la seguridad y funcionalidad de nuestro sitio web.',
                    'Cumplir con obligaciones legales.',
                    'Proteger contra spam y abuso (mediante Google reCAPTCHA v3).'
                ]
            ],
            [
                'title' => '4. Base Legal',
                'content' => 'Tratamos sus datos según las siguientes bases legales del RGPD:',
                'list' => [
                    '<strong>Consentimiento (Art. 6(1)(a) RGPD):</strong> al enviar voluntariamente el formulario de contacto.',
                    '<strong>Interés legítimo (Art. 6(1)(f) RGPD):</strong> para seguridad del sitio web y prevención de fraude.',
                    '<strong>Obligación legal (Art. 6(1)(c) RGPD):</strong> para cumplir con leyes y regulaciones aplicables.'
                ]
            ],
            [
                'title' => '5. Cookies y Servicios de Terceros',
                'content' => 'Nuestro sitio web utiliza los siguientes servicios de terceros:',
                'list' => [
                    '<strong>Google reCAPTCHA v3:</strong> para proteger nuestro formulario de contacto contra spam. Ver <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-accent hover:underline">Política de Privacidad de Google</a>.',
                    '<strong>Google Fonts:</strong> para servir tipografías web. Su dirección IP puede ser transmitida a servidores de Google.',
                    '<strong>Tailwind CSS CDN:</strong> para estilos. Servido a través de un CDN que puede registrar datos de acceso básicos.'
                ]
            ],
            [
                'title' => '6. Conservación de Datos',
                'content' => 'Conservamos sus datos personales solo durante el tiempo necesario para cumplir con las finalidades descritas, o según lo requiera la ley. Los envíos del formulario de contacto se almacenan durante un máximo de 24 meses.'
            ],
            [
                'title' => '7. Cesión de Datos',
                'content' => 'No vendemos, comerciamos ni alquilamos sus datos personales. Podemos compartir sus datos con:',
                'list' => [
                    'Proveedores de servicios que nos asisten en la operación del sitio web.',
                    'Autoridades, cuando lo requiera la legislación aplicable.',
                    'Filiales dentro del grupo IntuiFy Ventures, únicamente para fines comerciales legítimos.'
                ]
            ],
            [
                'title' => '8. Sus Derechos',
                'content' => 'Bajo el RGPD, usted tiene los siguientes derechos:',
                'list' => [
                    '<strong>Derecho de acceso:</strong> obtener confirmación y copia de sus datos.',
                    '<strong>Derecho de rectificación:</strong> solicitar la corrección de datos inexactos.',
                    '<strong>Derecho de supresión:</strong> solicitar la eliminación de sus datos.',
                    '<strong>Derecho a la limitación:</strong> solicitar la limitación del tratamiento.',
                    '<strong>Derecho a la portabilidad:</strong> recibir sus datos en formato legible por máquina.',
                    '<strong>Derecho de oposición:</strong> oponerse al tratamiento basado en interés legítimo.',
                    '<strong>Derecho a retirar el consentimiento:</strong> retirar su consentimiento en cualquier momento.'
                ],
                'extra' => 'Para ejercer cualquiera de estos derechos, contáctenos en <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>. También tiene derecho a presentar una reclamación ante la autoridad de control competente.'
            ],
            [
                'title' => '9. Seguridad de los Datos',
                'content' => 'Implementamos medidas técnicas y organizativas apropiadas para proteger sus datos personales contra acceso no autorizado, alteración, divulgación o destrucción. Esto incluye cifrado SSL/TLS e infraestructura de servidor segura.'
            ],
            [
                'title' => '10. Cambios en esta Política',
                'content' => 'Podemos actualizar esta Política de Privacidad periódicamente. Cualquier cambio se publicará en esta página con una fecha de "última actualización" revisada.'
            ]
        ]
    ],
    'it' => [
        'title' => 'Informativa sulla Privacy',
        'meta_title' => 'Informativa sulla Privacy – IntuiFy Ventures',
        'meta_description' => 'Informativa sulla Privacy di IntuiFy Ventures S.L. Scopri come raccogliamo, utilizziamo e proteggiamo i tuoi dati personali.',
        'last_updated' => 'Ultimo aggiornamento: 2 giugno 2026',
        'sections' => [
            [
                'title' => '1. Titolare del Trattamento',
                'content' => 'Il Titolare del trattamento è <strong>IntuiFy Ventures S.L.</strong> Per qualsiasi richiesta relativa ai tuoi dati personali, puoi contattarci all\'indirizzo <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '2. Dati che Raccogliamo',
                'content' => 'Raccogliamo le seguenti categorie di dati personali:',
                'list' => [
                    '<strong>Dati del modulo di contatto:</strong> nome completo, azienda, indirizzo email e contenuto del messaggio.',
                    '<strong>Dati tecnici:</strong> indirizzo IP, tipo di browser, sistema operativo e comportamento di navigazione raccolti automaticamente.',
                    '<strong>Dati di comunicazione:</strong> registri della corrispondenza quando ci contatti via email.'
                ]
            ],
            [
                'title' => '3. Finalità del Trattamento',
                'content' => 'Trattiamo i tuoi dati personali per le seguenti finalità:',
                'list' => [
                    'Rispondere alle tue richieste e invii del modulo di contatto.',
                    'Fornire e migliorare i nostri servizi.',
                    'Garantire la sicurezza e la funzionalità del nostro sito web.',
                    'Adempiere agli obblighi di legge.',
                    'Proteggere da spam e abusi (tramite Google reCAPTCHA v3).'
                ]
            ],
            [
                'title' => '4. Base Giuridica',
                'content' => 'Trattiamo i tuoi dati in base alle seguenti basi giuridiche del GDPR:',
                'list' => [
                    '<strong>Consenso (Art. 6(1)(a) GDPR):</strong> quando invii volontariamente il modulo di contatto.',
                    '<strong>Interesse legittimo (Art. 6(1)(f) GDPR):</strong> per la sicurezza del sito web e la prevenzione delle frodi.',
                    '<strong>Obbligo legale (Art. 6(1)(c) GDPR):</strong> per adempiere a leggi e regolamenti applicabili.'
                ]
            ],
            [
                'title' => '5. Cookie e Servizi di Terze Parti',
                'content' => 'Il nostro sito utilizza i seguenti servizi di terze parti:',
                'list' => [
                    '<strong>Google reCAPTCHA v3:</strong> per proteggere il modulo di contatto dallo spam. Vedi <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-accent hover:underline">Privacy Policy di Google</a>.',
                    '<strong>Google Fonts:</strong> per servire i font web. Il tuo indirizzo IP potrebbe essere trasmesso ai server di Google.',
                    '<strong>Tailwind CSS CDN:</strong> per lo stile. Servito tramite un CDN che potrebbe registrare dati di accesso di base.'
                ]
            ],
            [
                'title' => '6. Conservazione dei Dati',
                'content' => 'Conserviamo i tuoi dati personali solo per il tempo necessario a perseguire le finalità descritte, o come richiesto dalla legge. I dati del modulo di contatto vengono conservati per un massimo di 24 mesi.'
            ],
            [
                'title' => '7. Condivisione dei Dati',
                'content' => 'Non vendiamo, scambiamo o affittiamo i tuoi dati personali. Possiamo condividere i tuoi dati con:',
                'list' => [
                    'Fornitori di servizi che ci assistono nella gestione del sito web.',
                    'Autorità, quando richiesto dalla legge applicabile.',
                    'Società del gruppo IntuiFy Ventures, esclusivamente per finalità aziendali legittime.'
                ]
            ],
            [
                'title' => '8. I Tuoi Diritti',
                'content' => 'Ai sensi del GDPR, hai i seguenti diritti:',
                'list' => [
                    '<strong>Diritto di accesso:</strong> ottenere conferma e copia dei tuoi dati personali.',
                    '<strong>Diritto di rettifica:</strong> richiedere la correzione di dati inesatti.',
                    '<strong>Diritto alla cancellazione:</strong> richiedere l\'eliminazione dei tuoi dati ("diritto all\'oblio").',
                    '<strong>Diritto alla limitazione:</strong> richiedere la limitazione del trattamento.',
                    '<strong>Diritto alla portabilità:</strong> ricevere i tuoi dati in formato leggibile da dispositivo automatico.',
                    '<strong>Diritto di opposizione:</strong> opporsi al trattamento basato sull\'interesse legittimo.',
                    '<strong>Diritto di revocare il consenso:</strong> revocare il consenso in qualsiasi momento.'
                ],
                'extra' => 'Per esercitare uno qualsiasi di questi diritti, contattaci all\'indirizzo <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>. Hai inoltre il diritto di presentare reclamo all\'autorità di controllo competente (Garante per la Protezione dei Dati Personali).'
            ],
            [
                'title' => '9. Sicurezza dei Dati',
                'content' => 'Adottiamo misure tecniche e organizzative adeguate per proteggere i tuoi dati personali da accessi non autorizzati, alterazione, divulgazione o distruzione. Queste includono crittografia SSL/TLS e infrastruttura server sicura.'
            ],
            [
                'title' => '10. Modifiche a questa Informativa',
                'content' => 'Potremmo aggiornare questa Informativa periodicamente. Eventuali modifiche saranno pubblicate su questa pagina con una data di "ultimo aggiornamento" aggiornata.'
            ]
        ]
    ]
];

$p = $privacy[$currentLang];
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['meta_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($p['meta_description']) ?>">

    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0F172A',
                        accent: '#6366F1',
                        secondary: '#8B5CF6',
                        'slate-bg': '#FAFBFD',
                        'bezel-outer': 'rgba(15, 23, 42, 0.04)',
                        'bezel-border': 'rgba(15, 23, 42, 0.08)'
                    },
                    fontFamily: {
                        display: ['Outfit', 'sans-serif'],
                        sans: ['Plus Jakarta Sans', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        html { scroll-padding-top: 6.5rem; background-color: #FAFBFD; }
        .premium-transition { transition: all 700ms cubic-bezier(0.32, 0.72, 0, 1); }
    </style>
</head>

<body class="bg-slate-bg text-slate-600 font-sans antialiased overflow-x-hidden">

    <!-- Minimal Header -->
    <header class="fixed top-0 left-0 right-0 z-50 mt-4 md:mt-5 mx-auto w-[92%] max-w-6xl">
        <div class="relative w-full rounded-full bg-white/70 backdrop-blur-xl border border-slate-200/40 shadow-xl shadow-slate-100/40 px-4 md:px-6 py-2.5 md:py-3">
            <nav class="flex items-center justify-between">
                <a href="/"
                    class="flex items-center focus:outline-none rounded-full transition-transform duration-300 hover:scale-102"
                    aria-label="IntuiFy - Home">
                    <img src="assets/logo.svg" alt="IntuiFy" class="h-6 md:h-7 w-auto" width="96" height="28">
                </a>
                <a href="/"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-full premium-transition shadow-md shadow-slate-900/10">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span><?= $currentLang === 'es' ? 'Volver al inicio' : ($currentLang === 'it' ? 'Torna alla home' : 'Back to Home') ?></span>
                </a>
            </nav>
        </div>
    </header>

    <main class="w-full pt-32 pb-24">
        <div class="max-w-4xl mx-auto px-6">
            <!-- Page Title -->
            <div class="mb-16">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-50 border border-slate-200/40 rounded-full mb-4">
                    <span class="text-[9px] uppercase tracking-[0.25em] font-bold text-slate-400">Legal</span>
                </div>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-4">
                    <?= htmlspecialchars($p['title']) ?>
                </h1>
                <p class="text-sm text-slate-400 font-medium"><?= htmlspecialchars($p['last_updated']) ?></p>
            </div>

            <!-- Content Sections -->
            <div class="space-y-12">
                <?php foreach ($p['sections'] as $section): ?>
                    <section class="p-6 md:p-8 rounded-[2rem] bg-white border border-slate-200/40 shadow-sm">
                        <h2 class="font-display text-xl font-bold text-slate-900 mb-4"><?= $section['title'] ?></h2>
                        <p class="text-sm text-slate-600 leading-relaxed mb-4"><?= $section['content'] ?></p>
                        <?php if (!empty($section['list'])): ?>
                            <ul class="space-y-3 ml-1">
                                <?php foreach ($section['list'] as $item): ?>
                                    <li class="flex items-start gap-3 text-sm text-slate-600 leading-relaxed">
                                        <span class="mt-2 w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                                        <span><?= $item ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($section['extra'])): ?>
                            <p class="text-sm text-slate-600 leading-relaxed mt-4"><?= $section['extra'] ?></p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative bg-slate-50/70 border-t border-slate-200/40 py-12">
        <div class="relative max-w-4xl mx-auto px-6 text-center">
            <div class="flex flex-col gap-1">
                <p class="text-slate-400 text-xs font-medium">
                    <?= str_replace('{year}', date('Y'), $t['footer']['copyright']) ?>
                </p>
                <p class="text-slate-400/80 text-[10px] font-bold tracking-wider uppercase">
                    IntuiFy Ventures S.L.
                </p>
            </div>
            <div class="flex items-center justify-center gap-6 mt-4">
                <a href="/privacy" class="text-accent text-[10px] uppercase font-bold tracking-widest hover:text-indigo-800 transition-colors">Privacy Policy</a>
                <a href="/terms" class="text-slate-400 text-[10px] uppercase font-bold tracking-widest hover:text-slate-600 transition-colors">Terms of Service</a>
            </div>
        </div>
    </footer>

</body>
</html>
