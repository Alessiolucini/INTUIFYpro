<?php
/**
 * IntuiFy - Terms of Service Page
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

// Terms of Service content per language
$terms = [
    'en' => [
        'title' => 'Terms of Service',
        'meta_title' => 'Terms of Service – IntuiFy Ventures',
        'meta_description' => 'Terms of Service for IntuiFy Ventures S.L. Read the terms governing the use of our website and services.',
        'last_updated' => 'Last updated: June 2, 2026',
        'sections' => [
            [
                'title' => '1. Introduction',
                'content' => 'These Terms of Service ("Terms") govern your access to and use of the website and services provided by <strong>IntuiFy Ventures S.L.</strong> ("IntuiFy", "we", "us", or "our"). By accessing or using our website at <a href="https://intuify.net" class="text-accent hover:underline">intuify.net</a>, you agree to be bound by these Terms. If you do not agree, please do not use our website.'
            ],
            [
                'title' => '2. Company Information',
                'content' => '<strong>IntuiFy Ventures S.L.</strong> is a holding company registered under Spanish law. For any inquiries, you may contact us at <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '3. Services Description',
                'content' => 'IntuiFy Ventures S.L. operates as a parent holding company coordinating specialized subsidiaries in the following sectors:',
                'list' => [
                    'Custom software development and IT solutions.',
                    'Data processing, AI platforms, and intelligent analytics.',
                    'Strategic marketing, advertising, and campaign management.',
                    'Additive manufacturing and 3D printing services.',
                    'Real estate development, construction, and brokerage.',
                    'Travel organization, tour operations, and global tourism.'
                ],
                'extra' => 'The specific terms for each subsidiary\'s services may be governed by separate agreements.'
            ],
            [
                'title' => '4. Intellectual Property',
                'content' => 'All content on this website — including but not limited to text, graphics, logos, images, software, and design — is the property of IntuiFy Ventures S.L. or its licensors and is protected by applicable intellectual property laws. You may not reproduce, distribute, modify, or create derivative works from any content without our prior written consent.'
            ],
            [
                'title' => '5. Use of the Website',
                'content' => 'When using our website, you agree to:',
                'list' => [
                    'Use the website only for lawful purposes and in accordance with these Terms.',
                    'Not attempt to gain unauthorized access to any part of the website or its systems.',
                    'Not use the website to transmit any harmful, offensive, or illegal content.',
                    'Not interfere with or disrupt the website\'s functionality or security.',
                    'Provide accurate and truthful information when submitting the contact form.'
                ]
            ],
            [
                'title' => '6. Contact Form Submissions',
                'content' => 'By submitting information through our contact form, you confirm that the information provided is accurate and that you consent to being contacted by IntuiFy or its subsidiaries regarding your inquiry. Contact form submissions are subject to our <a href="/privacy" class="text-accent hover:underline">Privacy Policy</a>.'
            ],
            [
                'title' => '7. Third-Party Links',
                'content' => 'Our website may contain links to third-party websites or services. We are not responsible for the content, privacy practices, or availability of these external sites. Your use of third-party services is governed by their respective terms and policies.'
            ],
            [
                'title' => '8. Disclaimer of Warranties',
                'content' => 'Our website is provided on an "as is" and "as available" basis. To the fullest extent permitted by law, IntuiFy disclaims all warranties, express or implied, including but not limited to implied warranties of merchantability, fitness for a particular purpose, and non-infringement. We do not guarantee that the website will be uninterrupted, error-free, or secure.'
            ],
            [
                'title' => '9. Limitation of Liability',
                'content' => 'To the maximum extent permitted by applicable law, IntuiFy Ventures S.L. shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of the website, regardless of the basis of the claim. Our total liability shall not exceed €100.'
            ],
            [
                'title' => '10. Indemnification',
                'content' => 'You agree to indemnify and hold harmless IntuiFy Ventures S.L., its subsidiaries, affiliates, officers, and employees from any claims, damages, losses, or expenses (including reasonable legal fees) arising from your use of the website or violation of these Terms.'
            ],
            [
                'title' => '11. Modifications',
                'content' => 'We reserve the right to modify these Terms at any time. Changes will be effective upon posting on this page with an updated date. Your continued use of the website after modifications constitutes acceptance of the revised Terms.'
            ],
            [
                'title' => '12. Governing Law & Jurisdiction',
                'content' => 'These Terms are governed by and construed in accordance with the laws of Spain. Any disputes arising from these Terms shall be subject to the exclusive jurisdiction of the courts of Spain.'
            ],
            [
                'title' => '13. Severability',
                'content' => 'If any provision of these Terms is found to be invalid or unenforceable by a court of competent jurisdiction, the remaining provisions shall remain in full force and effect.'
            ],
            [
                'title' => '14. Contact',
                'content' => 'For any questions regarding these Terms, please contact us at <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ]
        ]
    ],
    'es' => [
        'title' => 'Términos de Servicio',
        'meta_title' => 'Términos de Servicio – IntuiFy Ventures',
        'meta_description' => 'Términos de Servicio de IntuiFy Ventures S.L. Lea los términos que rigen el uso de nuestro sitio web y servicios.',
        'last_updated' => 'Última actualización: 2 de junio de 2026',
        'sections' => [
            [
                'title' => '1. Introducción',
                'content' => 'Estos Términos de Servicio ("Términos") rigen su acceso y uso del sitio web y los servicios proporcionados por <strong>IntuiFy Ventures S.L.</strong> Al acceder o utilizar nuestro sitio web en <a href="https://intuify.net" class="text-accent hover:underline">intuify.net</a>, usted acepta quedar vinculado por estos Términos. Si no está de acuerdo, por favor no utilice nuestro sitio web.'
            ],
            [
                'title' => '2. Información de la Empresa',
                'content' => '<strong>IntuiFy Ventures S.L.</strong> es una sociedad holding registrada bajo la legislación española. Para cualquier consulta, puede contactarnos en <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '3. Descripción de los Servicios',
                'content' => 'IntuiFy Ventures S.L. opera como sociedad holding matriz coordinando filiales especializadas en los siguientes sectores:',
                'list' => [
                    'Desarrollo de software a medida y soluciones informáticas.',
                    'Procesamiento de datos, plataformas de IA y análisis inteligente.',
                    'Marketing estratégico, publicidad y gestión de campañas.',
                    'Fabricación aditiva y servicios de impresión 3D.',
                    'Promoción inmobiliaria, construcción e intermediación.',
                    'Organización de viajes, operaciones turísticas y turismo global.'
                ],
                'extra' => 'Los términos específicos de los servicios de cada filial pueden estar regidos por acuerdos separados.'
            ],
            [
                'title' => '4. Propiedad Intelectual',
                'content' => 'Todo el contenido de este sitio web — incluyendo textos, gráficos, logotipos, imágenes, software y diseño — es propiedad de IntuiFy Ventures S.L. o sus licenciantes y está protegido por las leyes de propiedad intelectual aplicables. No puede reproducir, distribuir, modificar o crear obras derivadas sin nuestro consentimiento previo por escrito.'
            ],
            [
                'title' => '5. Uso del Sitio Web',
                'content' => 'Al utilizar nuestro sitio web, usted se compromete a:',
                'list' => [
                    'Utilizar el sitio web solo para fines lícitos y de acuerdo con estos Términos.',
                    'No intentar obtener acceso no autorizado a ninguna parte del sitio web.',
                    'No utilizar el sitio web para transmitir contenido dañino, ofensivo o ilegal.',
                    'No interferir con la funcionalidad o seguridad del sitio web.',
                    'Proporcionar información precisa y veraz al enviar el formulario de contacto.'
                ]
            ],
            [
                'title' => '6. Envíos del Formulario de Contacto',
                'content' => 'Al enviar información a través de nuestro formulario de contacto, usted confirma que la información es precisa y que consiente ser contactado por IntuiFy o sus filiales. Los envíos están sujetos a nuestra <a href="/privacy" class="text-accent hover:underline">Política de Privacidad</a>.'
            ],
            [
                'title' => '7. Enlaces a Terceros',
                'content' => 'Nuestro sitio web puede contener enlaces a sitios web o servicios de terceros. No somos responsables del contenido, las prácticas de privacidad o la disponibilidad de estos sitios externos.'
            ],
            [
                'title' => '8. Exclusión de Garantías',
                'content' => 'Nuestro sitio web se proporciona "tal cual" y "según disponibilidad". En la máxima medida permitida por la ley, IntuiFy excluye todas las garantías, expresas o implícitas. No garantizamos que el sitio web sea ininterrumpido, libre de errores o seguro.'
            ],
            [
                'title' => '9. Limitación de Responsabilidad',
                'content' => 'En la máxima medida permitida por la ley aplicable, IntuiFy Ventures S.L. no será responsable de daños indirectos, incidentales, especiales, consecuentes o punitivos derivados del uso del sitio web. Nuestra responsabilidad total no excederá 100€.'
            ],
            [
                'title' => '10. Indemnización',
                'content' => 'Usted acepta indemnizar y mantener indemne a IntuiFy Ventures S.L., sus filiales, directivos y empleados de cualquier reclamación, daño, pérdida o gasto derivado de su uso del sitio web o violación de estos Términos.'
            ],
            [
                'title' => '11. Modificaciones',
                'content' => 'Nos reservamos el derecho de modificar estos Términos en cualquier momento. Los cambios serán efectivos tras su publicación en esta página. El uso continuado del sitio web después de las modificaciones constituye la aceptación de los Términos revisados.'
            ],
            [
                'title' => '12. Ley Aplicable y Jurisdicción',
                'content' => 'Estos Términos se rigen por las leyes de España. Cualquier disputa derivada de estos Términos estará sujeta a la jurisdicción exclusiva de los tribunales de España.'
            ],
            [
                'title' => '13. Divisibilidad',
                'content' => 'Si alguna disposición de estos Términos se considera inválida o inaplicable, las disposiciones restantes permanecerán en pleno vigor y efecto.'
            ],
            [
                'title' => '14. Contacto',
                'content' => 'Para cualquier pregunta sobre estos Términos, contáctenos en <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ]
        ]
    ],
    'it' => [
        'title' => 'Termini di Servizio',
        'meta_title' => 'Termini di Servizio – IntuiFy Ventures',
        'meta_description' => 'Termini di Servizio di IntuiFy Ventures S.L. Leggi i termini che regolano l\'utilizzo del nostro sito web e dei nostri servizi.',
        'last_updated' => 'Ultimo aggiornamento: 2 giugno 2026',
        'sections' => [
            [
                'title' => '1. Introduzione',
                'content' => 'I presenti Termini di Servizio ("Termini") regolano l\'accesso e l\'utilizzo del sito web e dei servizi forniti da <strong>IntuiFy Ventures S.L.</strong> Accedendo o utilizzando il nostro sito web su <a href="https://intuify.net" class="text-accent hover:underline">intuify.net</a>, accetti di essere vincolato da questi Termini. Se non sei d\'accordo, ti preghiamo di non utilizzare il nostro sito web.'
            ],
            [
                'title' => '2. Informazioni sull\'Azienda',
                'content' => '<strong>IntuiFy Ventures S.L.</strong> è una società holding registrata ai sensi della legge spagnola. Per qualsiasi richiesta, puoi contattarci all\'indirizzo <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ],
            [
                'title' => '3. Descrizione dei Servizi',
                'content' => 'IntuiFy Ventures S.L. opera come holding capogruppo coordinando società controllate specializzate nei seguenti settori:',
                'list' => [
                    'Sviluppo software personalizzato e soluzioni IT.',
                    'Elaborazione dati, piattaforme IA e analisi intelligente.',
                    'Marketing strategico, pubblicità e gestione campagne.',
                    'Produzione additiva e servizi di stampa 3D.',
                    'Sviluppo immobiliare, costruzione e intermediazione.',
                    'Organizzazione viaggi, tour operator e turismo globale.'
                ],
                'extra' => 'I termini specifici per i servizi di ciascuna società controllata possono essere regolati da accordi separati.'
            ],
            [
                'title' => '4. Proprietà Intellettuale',
                'content' => 'Tutti i contenuti di questo sito web — inclusi testi, grafica, loghi, immagini, software e design — sono di proprietà di IntuiFy Ventures S.L. o dei suoi licenzianti e sono protetti dalle leggi sulla proprietà intellettuale applicabili. Non è consentito riprodurre, distribuire, modificare o creare opere derivate senza il nostro previo consenso scritto.'
            ],
            [
                'title' => '5. Utilizzo del Sito Web',
                'content' => 'Utilizzando il nostro sito web, ti impegni a:',
                'list' => [
                    'Utilizzare il sito web solo per scopi leciti e in conformità con questi Termini.',
                    'Non tentare di ottenere accesso non autorizzato a qualsiasi parte del sito web.',
                    'Non utilizzare il sito web per trasmettere contenuti dannosi, offensivi o illegali.',
                    'Non interferire con la funzionalità o la sicurezza del sito web.',
                    'Fornire informazioni accurate e veritiere quando invii il modulo di contatto.'
                ]
            ],
            [
                'title' => '6. Invio del Modulo di Contatto',
                'content' => 'Inviando informazioni tramite il nostro modulo di contatto, confermi che le informazioni fornite sono accurate e acconsenti a essere contattato da IntuiFy o dalle sue controllate. Gli invii sono soggetti alla nostra <a href="/privacy" class="text-accent hover:underline">Informativa sulla Privacy</a>.'
            ],
            [
                'title' => '7. Link a Terze Parti',
                'content' => 'Il nostro sito web può contenere link a siti web o servizi di terze parti. Non siamo responsabili per il contenuto, le pratiche sulla privacy o la disponibilità di questi siti esterni.'
            ],
            [
                'title' => '8. Esclusione di Garanzie',
                'content' => 'Il nostro sito web è fornito "così com\'è" e "come disponibile". Nella misura massima consentita dalla legge, IntuiFy declina tutte le garanzie, espresse o implicite. Non garantiamo che il sito web sarà ininterrotto, privo di errori o sicuro.'
            ],
            [
                'title' => '9. Limitazione di Responsabilità',
                'content' => 'Nella misura massima consentita dalla legge applicabile, IntuiFy Ventures S.L. non sarà responsabile per danni indiretti, incidentali, speciali, consequenziali o punitivi derivanti dall\'uso del sito web. La nostra responsabilità totale non supererà 100€.'
            ],
            [
                'title' => '10. Manleva',
                'content' => 'Accetti di manlevare e tenere indenne IntuiFy Ventures S.L., le sue controllate, dirigenti e dipendenti da qualsiasi reclamo, danno, perdita o spesa derivante dal tuo utilizzo del sito web o dalla violazione di questi Termini.'
            ],
            [
                'title' => '11. Modifiche',
                'content' => 'Ci riserviamo il diritto di modificare questi Termini in qualsiasi momento. Le modifiche saranno effettive al momento della pubblicazione su questa pagina. L\'uso continuato del sito web dopo le modifiche costituisce accettazione dei Termini aggiornati.'
            ],
            [
                'title' => '12. Legge Applicabile e Foro Competente',
                'content' => 'Questi Termini sono regolati dalla legge spagnola. Qualsiasi controversia derivante da questi Termini sarà soggetta alla giurisdizione esclusiva dei tribunali della Spagna.'
            ],
            [
                'title' => '13. Clausola di Salvaguardia',
                'content' => 'Se una disposizione di questi Termini dovesse essere ritenuta invalida o inapplicabile, le restanti disposizioni rimarranno pienamente valide ed efficaci.'
            ],
            [
                'title' => '14. Contatti',
                'content' => 'Per qualsiasi domanda su questi Termini, contattaci all\'indirizzo <a href="mailto:info@intuify.net" class="text-accent hover:underline">info@intuify.net</a>.'
            ]
        ]
    ]
];

$tos = $terms[$currentLang];
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tos['meta_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($tos['meta_description']) ?>">

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
                    <?= htmlspecialchars($tos['title']) ?>
                </h1>
                <p class="text-sm text-slate-400 font-medium"><?= htmlspecialchars($tos['last_updated']) ?></p>
            </div>

            <!-- Content Sections -->
            <div class="space-y-12">
                <?php foreach ($tos['sections'] as $section): ?>
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
                <a href="/privacy" class="text-slate-400 text-[10px] uppercase font-bold tracking-widest hover:text-slate-600 transition-colors">Privacy Policy</a>
                <a href="/terms" class="text-accent text-[10px] uppercase font-bold tracking-widest hover:text-indigo-800 transition-colors">Terms of Service</a>
            </div>
        </div>
    </footer>

</body>
</html>
