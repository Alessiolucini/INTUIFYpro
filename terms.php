<?php
/**
 * IntuiFy — Legal notice and terms of use (es / it / en), as required by LSSI art. 10.
 * Company details come from config.php. Registry data still to be provided by the owner.
 * To be reviewed by a legal advisor.
 */

declare(strict_types=1);

require __DIR__ . '/includes/legal-layout.php';

$cfg = require __DIR__ . '/config.php';
$vat = htmlspecialchars((string) ($cfg['company_vat'] ?? ''));
$address = htmlspecialchars((string) ($cfg['company_address'] ?? ''));
$mail = '<a href="mailto:info@intuify.net">info@intuify.net</a>';
$privacy = fn(string $lang, string $label) => "<a href=\"/privacy.php?lang={$lang}\">{$label}</a>";

renderLegalPage([
    'es' => [
        'meta_title'       => 'Aviso legal y condiciones de uso | IntuiFy',
        'meta_description' => 'Datos del titular de intuify.net y condiciones de uso del sitio web de IntuiFy Ventures, S.L.',
        'title'            => 'Aviso legal y condiciones de uso',
        'updated'          => 'Última actualización: 2 de octubre de 2026',
        'review_note'      => 'Pendiente: confirmar domicilio social completo y datos de inscripción en el Registro Mercantil, y revisión por un asesor legal.',
        'sections' => [
            ['title' => '1. Titular del sitio web',
             'list'  => [
                 '<strong>Titular:</strong> IntuiFy Ventures, S.L.',
                 "<strong>CIF:</strong> {$vat}",
                 "<strong>Domicilio:</strong> {$address}",
                 "<strong>Email:</strong> {$mail}",
                 '<strong>Actividad:</strong> desarrollo de software, aplicaciones y soluciones digitales para empresas.',
             ]],
            ['title' => '2. Objeto',
             'html'  => 'Este sitio presenta los servicios de IntuiFy (webs y e-commerce, apps iOS y Android, software empresarial e inteligencia artificial), algunos de los proyectos que hemos desarrollado y un formulario para solicitar información o presupuesto.'],
            ['title' => '3. Información, presupuestos y contratación',
             'html'  => 'La información publicada es orientativa y no constituye una oferta vinculante. Funcionalidades, plazos, precios y condiciones de cada proyecto se definen en la propuesta o el contrato que acordemos por escrito con cada cliente.'],
            ['title' => '4. Uso del sitio',
             'html'  => 'Te comprometes a utilizar el sitio de forma lícita y a no enviar a través del formulario contenidos falsos, ofensivos o que vulneren derechos de terceros, ni a intentar acceder a áreas restringidas.'],
            ['title' => '5. Propiedad intelectual e industrial',
             'html'  => 'Los textos, diseño, logotipos y código de este sitio, así como los productos propios presentados (Auterio, BUBBLO, Eco Andratx), pertenecen a IntuiFy Ventures, S.L. o se utilizan con autorización. Las marcas de terceros, incluidas App Store y Google Play, pertenecen a sus titulares. No está permitida su reproducción sin autorización.'],
            ['title' => '6. Enlaces externos',
             'html'  => 'El sitio incluye enlaces a webs de proyectos y a tiendas de aplicaciones. No somos responsables de los contenidos ni de las políticas de sitios de terceros.'],
            ['title' => '7. Responsabilidad',
             'html'  => 'Trabajamos para que la información sea correcta y el sitio esté disponible, pero no podemos garantizar la ausencia de errores o interrupciones. IntuiFy no responde de los daños derivados del uso del sitio, salvo en los casos previstos por la ley.'],
            ['title' => '8. Protección de datos y cookies',
             'html'  => 'El tratamiento de los datos personales y el uso de cookies se describen en la ' . $privacy('es', 'Política de privacidad y cookies') . '.'],
            ['title' => '9. Legislación aplicable',
             'html'  => 'Este aviso legal se rige por la legislación española. Para cualquier controversia serán competentes los juzgados y tribunales que correspondan conforme a la normativa aplicable.'],
        ],
    ],
    'it' => [
        'meta_title'       => 'Note legali e condizioni d\'uso | IntuiFy',
        'meta_description' => 'Dati del titolare di intuify.net e condizioni d\'uso del sito di IntuiFy Ventures, S.L.',
        'title'            => 'Note legali e condizioni d\'uso',
        'updated'          => 'Ultimo aggiornamento: 2 ottobre 2026',
        'review_note'      => 'Da completare: sede legale completa e dati di iscrizione al Registro Mercantil, e revisione da parte di un consulente legale.',
        'sections' => [
            ['title' => '1. Titolare del sito',
             'list'  => [
                 '<strong>Titolare:</strong> IntuiFy Ventures, S.L.',
                 "<strong>CIF:</strong> {$vat}",
                 "<strong>Sede:</strong> {$address}",
                 "<strong>Email:</strong> {$mail}",
                 '<strong>Attività:</strong> sviluppo di software, app e soluzioni digitali per le aziende.',
             ]],
            ['title' => '2. Oggetto',
             'html'  => 'Questo sito presenta i servizi di IntuiFy (siti ed e-commerce, app iOS e Android, software aziendale e intelligenza artificiale), alcuni progetti che abbiamo sviluppato e un modulo per chiedere informazioni o un preventivo.'],
            ['title' => '3. Informazioni, preventivi e contratti',
             'html'  => 'Le informazioni pubblicate sono indicative e non costituiscono un\'offerta vincolante. Funzionalità, tempi, prezzi e condizioni di ogni progetto sono definiti nella proposta o nel contratto concordati per iscritto con ciascun cliente.'],
            ['title' => '4. Uso del sito',
             'html'  => 'Ti impegni a usare il sito in modo lecito, a non inviare tramite il modulo contenuti falsi, offensivi o lesivi dei diritti di terzi e a non tentare di accedere ad aree riservate.'],
            ['title' => '5. Proprietà intellettuale e industriale',
             'html'  => 'Testi, grafica, loghi e codice del sito, così come i prodotti propri presentati (Auterio, BUBBLO, Eco Andratx), appartengono a IntuiFy Ventures, S.L. o sono usati con autorizzazione. I marchi di terzi, inclusi App Store e Google Play, appartengono ai rispettivi titolari. Non ne è consentita la riproduzione senza autorizzazione.'],
            ['title' => '6. Link esterni',
             'html'  => 'Il sito contiene link ai siti dei progetti e agli store delle app. Non siamo responsabili dei contenuti né delle politiche dei siti di terzi.'],
            ['title' => '7. Responsabilità',
             'html'  => 'Ci impegniamo affinché le informazioni siano corrette e il sito sia disponibile, ma non possiamo garantire l\'assenza di errori o interruzioni. IntuiFy non risponde dei danni derivanti dall\'uso del sito, salvo nei casi previsti dalla legge.'],
            ['title' => '8. Protezione dei dati e cookie',
             'html'  => 'Il trattamento dei dati personali e l\'uso dei cookie sono descritti nell\'' . $privacy('it', 'Informativa privacy e cookie') . '.'],
            ['title' => '9. Legge applicabile',
             'html'  => 'Queste note legali sono regolate dalla legge spagnola. Per qualsiasi controversia saranno competenti i giudici individuati secondo la normativa applicabile.'],
        ],
    ],
    'en' => [
        'meta_title'       => 'Legal notice and terms of use | IntuiFy',
        'meta_description' => 'Owner details of intuify.net and terms of use of the IntuiFy Ventures, S.L. website.',
        'title'            => 'Legal notice and terms of use',
        'updated'          => 'Last updated: 2 October 2026',
        'review_note'      => 'Pending: full registered address and Commercial Registry details, and review by a legal advisor.',
        'sections' => [
            ['title' => '1. Website owner',
             'list'  => [
                 '<strong>Owner:</strong> IntuiFy Ventures, S.L.',
                 "<strong>Tax ID (CIF):</strong> {$vat}",
                 "<strong>Address:</strong> {$address}",
                 "<strong>Email:</strong> {$mail}",
                 '<strong>Activity:</strong> development of software, apps and digital solutions for businesses.',
             ]],
            ['title' => '2. Purpose',
             'html'  => 'This site presents IntuiFy\'s services (websites and e-commerce, iOS and Android apps, business software and artificial intelligence), some of the projects we have built and a form to request information or a quote.'],
            ['title' => '3. Information, quotes and contracts',
             'html'  => 'The information published is for guidance only and is not a binding offer. Features, timelines, prices and terms of each project are set out in the proposal or contract agreed in writing with each client.'],
            ['title' => '4. Use of the site',
             'html'  => 'You agree to use the site lawfully, not to send false, offensive or infringing content through the form, and not to attempt to access restricted areas.'],
            ['title' => '5. Intellectual and industrial property',
             'html'  => 'The texts, design, logos and code of this site, as well as the own products shown (Auterio, BUBBLO, Eco Andratx), belong to IntuiFy Ventures, S.L. or are used with permission. Third-party trademarks, including App Store and Google Play, belong to their owners. Reproduction without permission is not allowed.'],
            ['title' => '6. External links',
             'html'  => 'The site links to project websites and app stores. We are not responsible for the content or policies of third-party sites.'],
            ['title' => '7. Liability',
             'html'  => 'We work to keep the information accurate and the site available, but we cannot guarantee it will be free of errors or interruptions. IntuiFy is not liable for damage arising from use of the site, except where the law provides otherwise.'],
            ['title' => '8. Data protection and cookies',
             'html'  => 'How we process personal data and use cookies is described in our ' . $privacy('en', 'Privacy and cookie policy') . '.'],
            ['title' => '9. Governing law',
             'html'  => 'This legal notice is governed by Spanish law. Any dispute will be heard by the courts that have jurisdiction under the applicable rules.'],
        ],
    ],
]);
