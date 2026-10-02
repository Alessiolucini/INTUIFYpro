<?php
/**
 * IntuiFy — Projects shown in the "Proyectos que puedes ver" section.
 *
 * Rules (from the site brief):
 * - Never invent clients, testimonials, results or downloads.
 * - Client names/logos only when authorised: keep `confirmed => false` until then.
 *   Unconfirmed projects are shown ONLY with SITE_PREVIEW=1 (see config.php).
 * - `status`: operativo | publicado | en_desarrollo (the real state, verified).
 * - `kind`:   own (Producto propio) | client (Proyecto para cliente).
 *
 * Verified on 2026-10-02: Auterio (auterio.net live), BUBBLO (App Store + Google Play
 * + bubblo.es), Eco Andratx (App Store + Google Play + ecoandratx.es).
 * Confirmed by the owner on 2026-10-02: Auterio operativo, Eco Andratx producto propio,
 * Aquatrópolis client project (shop in development).
 */

return [
    [
        'id'        => 'auterio',
        'name'      => 'Auterio',
        'kind'      => 'own',
        'status'    => 'operativo',
        'confirmed' => true,
        'media'     => [
            ['src' => 'assets/projects/screens/auterio-web.jpg', 'type' => 'web'],
        ],
        'links' => ['web' => 'https://auterio.net/'],
        'text' => [
            'es' => [
                'tag'      => 'Software para concesionarios',
                'need'     => 'Los concesionarios gestionan stock, ventas, administración y logística con herramientas separadas.',
                'solution' => 'Una plataforma web que reúne todo el ciclo comercial, del primer contacto a la entrega del vehículo.',
                'features' => ['Stock de vehículos', 'CRM y seguimiento comercial', 'Proformas y documentación', 'Subastas B2B entre profesionales', 'Logística y reacondicionamiento', 'Control financiero e indicadores'],
                'alt'      => 'Página de Auterio con panel de datos demostrativos',
            ],
            'it' => [
                'tag'      => 'Software per concessionarie',
                'need'     => 'Le concessionarie gestiscono stock, vendite, amministrazione e logistica con strumenti separati.',
                'solution' => 'Una piattaforma web che riunisce tutto il ciclo commerciale, dal primo contatto alla consegna del veicolo.',
                'features' => ['Stock dei veicoli', 'CRM e follow-up commerciale', 'Proforma e documenti', 'Aste B2B tra professionisti', 'Logistica e ricondizionamento', 'Controllo finanziario e indicatori'],
                'alt'      => 'Pagina di Auterio con pannello di dati dimostrativi',
            ],
            'en' => [
                'tag'      => 'Software for car dealerships',
                'need'     => 'Dealerships manage stock, sales, admin and logistics with separate tools.',
                'solution' => 'A web platform that brings the whole sales cycle together, from the first lead to vehicle delivery.',
                'features' => ['Vehicle stock', 'CRM and sales follow-up', 'Pro-forma invoices and documents', 'B2B auctions between dealers', 'Logistics and reconditioning', 'Financial control and KPIs'],
                'alt'      => 'Auterio page with a demo data dashboard',
            ],
        ],
    ],
    [
        'id'        => 'bubblo',
        'name'      => 'BUBBLO',
        'kind'      => 'own',
        'status'    => 'publicado',
        'confirmed' => true,
        'media'     => [
            ['src' => 'assets/projects/screens/bubblo-app-panel.jpg', 'type' => 'phone'],
            ['src' => 'assets/projects/screens/bubblo-app-parametros.jpg', 'type' => 'phone'],
        ],
        'links' => [
            'web'         => 'https://bubblo.es/',
            'app_store'   => 'https://apps.apple.com/es/app/bubblo-aquarium-platform/id6804181492',
            'google_play' => 'https://play.google.com/store/apps/details?id=es.bubblo.app',
        ],
        'text' => [
            'es' => [
                'tag'      => 'App iOS y Android · Acuariofilia',
                'need'     => 'Los aficionados a los acuarios anotan parámetros, mantenimiento y equipos entre notas, hojas de cálculo y varias apps.',
                'solution' => 'Una app que reúne todo el acuario en un solo lugar, con avisos y un asistente de IA que conoce sus datos.',
                'features' => ['Registro de parámetros con tendencias y avisos', 'Ficha y compatibilidad de los habitantes', 'Mantenimiento programado con recordatorios', 'Guía del ciclado paso a paso', 'Catálogo de equipos', 'Asistente BUBBLO AI'],
                'alt'      => 'Pantallas de la app BUBBLO: panel del acuario y parámetros',
            ],
            'it' => [
                'tag'      => 'App iOS e Android · Acquariofilia',
                'need'     => 'Gli appassionati di acquari annotano parametri, manutenzione e attrezzature tra note, fogli di calcolo e varie app.',
                'solution' => "Un'app che riunisce tutto l'acquario in un solo posto, con avvisi e un assistente IA che conosce i suoi dati.",
                'features' => ['Registro dei parametri con andamenti e avvisi', 'Schede e compatibilità degli abitanti', 'Manutenzione programmata con promemoria', 'Guida al ciclo, passo per passo', 'Catalogo delle attrezzature', 'Assistente BUBBLO AI'],
                'alt'      => "Schermate dell'app BUBBLO: pannello dell'acquario e parametri",
            ],
            'en' => [
                'tag'      => 'iOS and Android app · Aquariums',
                'need'     => 'Aquarium keepers track parameters, maintenance and equipment across notes, spreadsheets and several apps.',
                'solution' => 'One app for the whole aquarium, with alerts and an AI assistant that knows its data.',
                'features' => ['Parameter log with trends and alerts', 'Livestock profiles and compatibility', 'Scheduled maintenance with reminders', 'Step-by-step cycling guide', 'Equipment catalogue', 'BUBBLO AI assistant'],
                'alt'      => 'BUBBLO app screens: aquarium dashboard and parameters',
            ],
        ],
    ],
    [
        'id'        => 'ecoandratx',
        'name'      => 'Eco Andratx',
        'kind'      => 'own',
        'status'    => 'publicado',
        'confirmed' => true,
        'media'     => [
            ['src' => 'assets/projects/screens/ecoandratx-app-chat.jpg', 'type' => 'phone'],
            ['src' => 'assets/projects/screens/ecoandratx-web.jpg', 'type' => 'web'],
        ],
        'links' => [
            'web'         => 'https://ecoandratx.es/',
            'app_store'   => 'https://apps.apple.com/es/app/eco-andratx/id6782368668',
            'google_play' => 'https://play.google.com/store/apps/details?id=com.intuify.ecoandratx',
        ],
        'text' => [
            'es' => [
                'tag'      => 'App iOS y Android · Reciclaje',
                'need'     => 'Saber dónde tirar cada residuo y cuándo pasa la recogida en Andratx no siempre es sencillo.',
                'solution' => 'Una guía de reciclaje en app y web, con escáner de residuos por IA y asistente en varios idiomas.',
                'features' => ['Escáner de residuos con la cámara (IA)', 'Calendario de recogida y recordatorios', 'Mapa de puntos limpios y contenedores', 'Guía de reciclaje', 'Aviso de incidencias en contenedores', 'Chat de ayuda en 5 idiomas'],
                'alt'      => 'App Eco Andratx: asistente de reciclaje EcoBot',
            ],
            'it' => [
                'tag'      => 'App iOS e Android · Riciclo',
                'need'     => 'Sapere dove buttare ogni rifiuto e quando passa la raccolta ad Andratx non è sempre semplice.',
                'solution' => 'Una guida al riciclo su app e web, con scanner dei rifiuti basato su IA e assistente in più lingue.',
                'features' => ['Scanner dei rifiuti con la fotocamera (IA)', 'Calendario della raccolta e promemoria', 'Mappa di isole ecologiche e contenitori', 'Guida al riciclo', 'Segnalazione di problemi ai contenitori', 'Chat di aiuto in 5 lingue'],
                'alt'      => "App Eco Andratx: assistente al riciclo EcoBot",
            ],
            'en' => [
                'tag'      => 'iOS and Android app · Recycling',
                'need'     => 'Knowing where each item goes and when collection days are in Andratx is not always easy.',
                'solution' => 'A recycling guide on app and web, with an AI waste scanner and a multilingual assistant.',
                'features' => ['AI waste scanner using the camera', 'Collection calendar and reminders', 'Map of recycling points and bins', 'Recycling guide', 'Report issues with bins', 'Help chat in 5 languages'],
                'alt'      => 'Eco Andratx app: EcoBot recycling assistant',
            ],
        ],
    ],

    [
        'id'        => 'aquatropolis',
        'name'      => 'Aquatrópolis',
        'kind'      => 'client',
        'status'    => 'en_desarrollo',
        'confirmed' => true, // confirmed by the owner on 2026-10-02 (online shop not launched yet)
        'media'     => [
            ['src' => 'assets/projects/screens/aquatropolis-web.jpg', 'type' => 'web'],
        ],
        'links' => [],
        'text' => [
            'es' => [
                'tag'      => 'E-commerce · Tienda de acuariofilia',
                'need'     => 'Una tienda de acuarios y terrarios en Palma que quiere vender online.',
                'solution' => 'Tienda online con catálogo, pedidos y panel de administración.',
                'features' => ['Catálogo de productos', 'Pedidos y pagos online', 'Panel de administración'],
                'alt'      => 'Página provisional de Aquatrópolis',
            ],
            'it' => [
                'tag'      => 'E-commerce · Negozio di acquariofilia',
                'need'     => 'Un negozio di acquari e terrari a Palma che vuole vendere online.',
                'solution' => 'Negozio online con catalogo, ordini e pannello di amministrazione.',
                'features' => ['Catalogo prodotti', 'Ordini e pagamenti online', 'Pannello di amministrazione'],
                'alt'      => 'Pagina provvisoria di Aquatrópolis',
            ],
            'en' => [
                'tag'      => 'E-commerce · Aquarium shop',
                'need'     => 'An aquarium and terrarium shop in Palma that wants to sell online.',
                'solution' => 'Online shop with catalogue, orders and an admin panel.',
                'features' => ['Product catalogue', 'Orders and online payments', 'Admin panel'],
                'alt'      => 'Aquatrópolis temporary page',
            ],
        ],
    ],
];
