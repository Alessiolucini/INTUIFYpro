<?php
/**
 * IntuiFy — Privacy & cookies policy (es / it / en).
 * Describes what the site really does (contact form, AI reply, hosting, cookies).
 * Keep in sync with index.php + includes/lead-mailer.php. To be reviewed by a legal advisor.
 */

declare(strict_types=1);

require __DIR__ . '/includes/legal-layout.php';

$mail = '<a href="mailto:info@intuify.net">info@intuify.net</a>';

renderLegalPage([
    'es' => [
        'meta_title'       => 'Política de privacidad y cookies | IntuiFy',
        'meta_description' => 'Cómo trata IntuiFy Ventures, S.L. los datos personales recibidos a través de intuify.net y qué cookies utiliza.',
        'title'            => 'Política de privacidad y cookies',
        'updated'          => 'Última actualización: 2 de octubre de 2026',
        'review_note'      => 'Texto preparado a partir del funcionamiento real del sitio: pendiente de revisión por un asesor legal y de confirmar los datos societarios.',
        'sections' => [
            ['title' => '1. Responsable del tratamiento',
             'html'  => "<strong>IntuiFy Ventures, S.L.</strong>, con CIF B88769526. Para cualquier cuestión sobre tus datos puedes escribirnos a {$mail}."],
            ['title' => '2. Qué datos tratamos',
             'list'  => [
                 '<strong>Formulario de contacto:</strong> nombre, empresa (opcional), email, teléfono (opcional), tipo de consulta, descripción del proyecto e idioma de la página.',
                 '<strong>Datos técnicos:</strong> dirección IP y datos del navegador que llegan al servidor, usados para la seguridad del sitio y para limitar envíos abusivos del formulario.',
                 '<strong>Comunicaciones:</strong> los mensajes que intercambiemos por email o WhatsApp si decides escribirnos por esos canales.',
             ]],
            ['title' => '3. Para qué los usamos',
             'list'  => [
                 'Responder a tu consulta, estudiar tu proyecto y, si lo solicitas, preparar una propuesta.',
                 'Enviarte una primera respuesta por email redactada con ayuda de inteligencia artificial a partir de lo que nos has escrito. Nuestro equipo recibe la consulta y la revisa.',
                 'Gestionar la relación comercial en nuestro sistema interno de clientes.',
                 'Proteger el sitio frente a spam y usos indebidos.',
             ]],
            ['title' => '4. Base jurídica',
             'list'  => [
                 'Aplicación de medidas precontractuales a petición tuya (art. 6.1.b RGPD) cuando nos haces una consulta.',
                 'Interés legítimo (art. 6.1.f RGPD) para la seguridad del sitio y la prevención del spam.',
                 'Cumplimiento de obligaciones legales cuando corresponda (art. 6.1.c RGPD).',
             ]],
            ['title' => '5. Quién puede acceder a los datos',
             'html'  => 'No vendemos ni cedemos tus datos. Para prestar el servicio trabajamos con estos proveedores, que actúan como encargados del tratamiento:',
             'list'  => [
                 '<strong>Hostinger</strong>: alojamiento del sitio y de nuestra base de datos en servidores situados en la Unión Europea (Alemania), y servicio de correo electrónico.',
                 '<strong>OpenAI</strong> (Estados Unidos): generación del borrador de la primera respuesta a partir de los datos del formulario. La transferencia internacional se realiza con las garantías previstas en el RGPD.',
                 '<strong>Cloudflare</strong>: servicio de DNS del dominio.',
                 '<strong>Google Fonts, unpkg, jsDelivr y Tailwind CDN</strong>: entrega de tipografías y librerías de la web; al cargarlas, tu navegador comunica su dirección IP a estos servicios.',
                 '<strong>WhatsApp (Meta)</strong>: solo si decides escribirnos por WhatsApp.',
                 'Autoridades públicas, cuando lo exija la ley.',
             ]],
            ['title' => '6. Cuánto tiempo los conservamos',
             'html'  => 'Conservamos las consultas durante un máximo de 24 meses desde el último contacto, salvo que se inicie una relación contractual o la ley exija un plazo mayor. Los registros técnicos del servidor se conservan el tiempo imprescindible para la seguridad del sitio.'],
            ['title' => '7. Tus derechos',
             'html'  => "Puedes ejercer los derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad escribiendo a {$mail}. También puedes presentar una reclamación ante la Agencia Española de Protección de Datos (<a href=\"https://www.aepd.es\" target=\"_blank\" rel=\"noopener\">www.aepd.es</a>)."],
            ['id' => 'cookies', 'title' => '8. Cookies',
             'html'  => 'Este sitio utiliza únicamente una cookie técnica propia, necesaria para su funcionamiento:',
             'list'  => [
                 '<strong>PHPSESSID</strong> (sesión, se elimina al cerrar el navegador): recuerda el idioma elegido y protege el formulario de contacto frente a envíos repetidos.',
             ],
             'after' => 'No utilizamos cookies analíticas, publicitarias ni de seguimiento. Por ser estrictamente necesaria, esta cookie no requiere consentimiento (art. 22.2 LSSI). Si en el futuro incorporamos otras cookies, actualizaremos esta política y solicitaremos tu consentimiento cuando sea necesario.'],
            ['title' => '9. Seguridad',
             'html'  => 'Aplicamos medidas técnicas y organizativas para proteger tus datos: conexión cifrada (HTTPS), acceso restringido a la base de datos y a los paneles internos, y registros de actividad.'],
            ['title' => '10. Cambios en esta política',
             'html'  => 'Podemos actualizar esta política. Publicaremos cualquier cambio en esta página con la fecha de actualización.'],
        ],
    ],
    'it' => [
        'meta_title'       => 'Informativa privacy e cookie | IntuiFy',
        'meta_description' => 'Come IntuiFy Ventures, S.L. tratta i dati personali ricevuti tramite intuify.net e quali cookie utilizza.',
        'title'            => 'Informativa privacy e cookie',
        'updated'          => 'Ultimo aggiornamento: 2 ottobre 2026',
        'review_note'      => 'Testo preparato in base al funzionamento reale del sito: da far revisionare a un consulente legale e da completare con i dati societari confermati.',
        'sections' => [
            ['title' => '1. Titolare del trattamento',
             'html'  => "<strong>IntuiFy Ventures, S.L.</strong>, CIF B88769526. Per qualsiasi domanda sui tuoi dati puoi scriverci a {$mail}."],
            ['title' => '2. Quali dati trattiamo',
             'list'  => [
                 '<strong>Modulo di contatto:</strong> nome, azienda (facoltativa), email, telefono (facoltativo), tipo di richiesta, descrizione del progetto e lingua della pagina.',
                 '<strong>Dati tecnici:</strong> indirizzo IP e dati del browser che arrivano al server, usati per la sicurezza del sito e per limitare gli invii abusivi del modulo.',
                 '<strong>Comunicazioni:</strong> i messaggi che ci scambiamo via email o WhatsApp se scegli di scriverci su questi canali.',
             ]],
            ['title' => '3. Per cosa li usiamo',
             'list'  => [
                 'Rispondere alla tua richiesta, studiare il tuo progetto e, se lo chiedi, preparare una proposta.',
                 "Inviarti una prima risposta via email scritta con l'aiuto dell'intelligenza artificiale a partire da quanto ci hai scritto. Il nostro team riceve la richiesta e la esamina.",
                 'Gestire il rapporto commerciale nel nostro sistema interno dei clienti.',
                 'Proteggere il sito da spam e usi impropri.',
             ]],
            ['title' => '4. Base giuridica',
             'list'  => [
                 'Misure precontrattuali adottate su tua richiesta (art. 6.1.b GDPR) quando ci contatti.',
                 'Legittimo interesse (art. 6.1.f GDPR) per la sicurezza del sito e la prevenzione dello spam.',
                 'Adempimento di obblighi di legge quando previsto (art. 6.1.c GDPR).',
             ]],
            ['title' => '5. Chi può accedere ai dati',
             'html'  => 'Non vendiamo né cediamo i tuoi dati. Per fornire il servizio ci avvaliamo di questi fornitori, che agiscono come responsabili del trattamento:',
             'list'  => [
                 "<strong>Hostinger</strong>: hosting del sito e del nostro database su server situati nell'Unione Europea (Germania) e servizio di posta elettronica.",
                 '<strong>OpenAI</strong> (Stati Uniti): generazione della bozza della prima risposta a partire dai dati del modulo. Il trasferimento internazionale avviene con le garanzie previste dal GDPR.',
                 '<strong>Cloudflare</strong>: servizio DNS del dominio.',
                 '<strong>Google Fonts, unpkg, jsDelivr e Tailwind CDN</strong>: distribuzione di font e librerie del sito; caricandoli, il tuo browser comunica il proprio indirizzo IP a questi servizi.',
                 '<strong>WhatsApp (Meta)</strong>: solo se scegli di scriverci su WhatsApp.',
                 'Autorità pubbliche, quando richiesto dalla legge.',
             ]],
            ['title' => '6. Per quanto tempo li conserviamo',
             'html'  => "Conserviamo le richieste per un massimo di 24 mesi dall'ultimo contatto, salvo l'avvio di un rapporto contrattuale o termini più lunghi previsti dalla legge. I log tecnici del server sono conservati per il tempo strettamente necessario alla sicurezza del sito."],
            ['title' => '7. I tuoi diritti',
             'html'  => "Puoi esercitare i diritti di accesso, rettifica, cancellazione, opposizione, limitazione e portabilità scrivendo a {$mail}. Puoi anche presentare reclamo all'autorità di controllo spagnola, l'Agencia Española de Protección de Datos (<a href=\"https://www.aepd.es\" target=\"_blank\" rel=\"noopener\">www.aepd.es</a>)."],
            ['id' => 'cookies', 'title' => '8. Cookie',
             'html'  => 'Questo sito utilizza soltanto un cookie tecnico proprio, necessario al suo funzionamento:',
             'list'  => [
                 '<strong>PHPSESSID</strong> (di sessione, si cancella alla chiusura del browser): ricorda la lingua scelta e protegge il modulo di contatto dagli invii ripetuti.',
             ],
             'after' => 'Non usiamo cookie analitici, pubblicitari o di tracciamento. Essendo strettamente necessario, questo cookie non richiede consenso. Se in futuro aggiungeremo altri cookie, aggiorneremo questa informativa e chiederemo il consenso quando necessario.'],
            ['title' => '9. Sicurezza',
             'html'  => 'Adottiamo misure tecniche e organizzative per proteggere i tuoi dati: connessione cifrata (HTTPS), accesso limitato al database e ai pannelli interni, registri delle attività.'],
            ['title' => '10. Modifiche',
             'html'  => 'Possiamo aggiornare questa informativa. Pubblicheremo ogni modifica in questa pagina con la data di aggiornamento.'],
        ],
    ],
    'en' => [
        'meta_title'       => 'Privacy and cookie policy | IntuiFy',
        'meta_description' => 'How IntuiFy Ventures, S.L. handles personal data received through intuify.net and which cookies it uses.',
        'title'            => 'Privacy and cookie policy',
        'updated'          => 'Last updated: 2 October 2026',
        'review_note'      => 'Text based on how the site actually works: pending review by a legal advisor and confirmation of company details.',
        'sections' => [
            ['title' => '1. Data controller',
             'html'  => "<strong>IntuiFy Ventures, S.L.</strong>, tax ID (CIF) B88769526. For any question about your data you can write to {$mail}."],
            ['title' => '2. Data we process',
             'list'  => [
                 '<strong>Contact form:</strong> name, company (optional), email, phone (optional), type of enquiry, project description and page language.',
                 '<strong>Technical data:</strong> IP address and browser data received by the server, used for site security and to limit abusive form submissions.',
                 '<strong>Communications:</strong> messages we exchange by email or WhatsApp if you choose to contact us there.',
             ]],
            ['title' => '3. What we use it for',
             'list'  => [
                 'Replying to your enquiry, assessing your project and, if you ask, preparing a proposal.',
                 'Sending you a first email reply drafted with the help of artificial intelligence from what you wrote. Our team receives the enquiry and reviews it.',
                 'Managing the business relationship in our internal client system.',
                 'Protecting the site against spam and misuse.',
             ]],
            ['title' => '4. Legal basis',
             'list'  => [
                 'Steps taken at your request before entering into a contract (Art. 6.1.b GDPR) when you contact us.',
                 'Legitimate interest (Art. 6.1.f GDPR) for site security and spam prevention.',
                 'Compliance with legal obligations where applicable (Art. 6.1.c GDPR).',
             ]],
            ['title' => '5. Who can access the data',
             'html'  => 'We do not sell or share your data. To provide the service we work with these providers, acting as data processors:',
             'list'  => [
                 '<strong>Hostinger</strong>: hosting of the website and our database on servers in the European Union (Germany), and email service.',
                 '<strong>OpenAI</strong> (United States): drafting the first reply from the form data. The international transfer relies on the safeguards provided by the GDPR.',
                 '<strong>Cloudflare</strong>: DNS service for the domain.',
                 '<strong>Google Fonts, unpkg, jsDelivr and Tailwind CDN</strong>: delivery of fonts and website libraries; when loading them, your browser shares its IP address with these services.',
                 '<strong>WhatsApp (Meta)</strong>: only if you choose to message us on WhatsApp.',
                 'Public authorities, when required by law.',
             ]],
            ['title' => '6. How long we keep it',
             'html'  => 'We keep enquiries for up to 24 months from the last contact, unless a contract is entered into or the law requires a longer period. Server technical logs are kept only as long as needed for site security.'],
            ['title' => '7. Your rights',
             'html'  => "You can exercise your rights of access, rectification, erasure, objection, restriction and portability by writing to {$mail}. You can also lodge a complaint with the Spanish Data Protection Agency (<a href=\"https://www.aepd.es\" target=\"_blank\" rel=\"noopener\">www.aepd.es</a>)."],
            ['id' => 'cookies', 'title' => '8. Cookies',
             'html'  => 'This site only uses one first-party technical cookie, needed for it to work:',
             'list'  => [
                 '<strong>PHPSESSID</strong> (session cookie, deleted when you close the browser): remembers the language you chose and protects the contact form against repeated submissions.',
             ],
             'after' => 'We do not use analytics, advertising or tracking cookies. As it is strictly necessary, this cookie does not require consent. If we add other cookies in the future, we will update this policy and ask for your consent where required.'],
            ['title' => '9. Security',
             'html'  => 'We apply technical and organisational measures to protect your data: encrypted connection (HTTPS), restricted access to the database and internal panels, and activity logs.'],
            ['title' => '10. Changes',
             'html'  => 'We may update this policy. Any change will be published on this page with its update date.'],
        ],
    ],
]);
