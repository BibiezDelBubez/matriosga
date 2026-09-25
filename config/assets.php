<?php
declare(strict_types=1);

/*
 * Librerie CSS/JS locali, percorsi relativi a public/assets/.
 * Per cambiare versione: sostituire i file in public/assets/vendor/ oppure
 * modificare i percorsi qui. Nessun altro file contiene questi percorsi.
 * VIETATO usare URL remoti (CDN): l'app deve funzionare offline.
 */
return [
    // Caricati in ogni pagina (in quest'ordine)
    'css' => [
        'vendor/bootstrap/css/bootstrap.min.css',
        'vendor/fontawesome/css/all.min.css',
        'css/app.css',
    ],
    'js' => [
        'vendor/bootstrap/js/bootstrap.bundle.js',
        'js/app.js',
    ],

    // Caricati solo dalle pagine che li richiedono (chiave => file)
    'optional' => [
        'cytoscape' => 'vendor/cytoscape/cytoscape.min.js',
    ],
];
