<?php
declare(strict_types=1);

/*
 * Valori di default (versionati). Le sezioni "connection" e "limits" possono essere
 * sovrascritte da Impostazioni, che salva in storage/settings.json (non versionato).
 * Il tipo di ogni default (bool/int/string) è usato per validare i valori salvati.
 */
return [
    'app' => [
        'name'    => 'Matriosga',
        'version' => '0.1.0',
        'debug'   => true,
    ],

    'security' => [
        // Chiave che cifra la password del DB (storage/secrets.json). Vuoto = storage/app.key.
        // Più sicuro: un percorso FUORI dalla cartella del progetto, es. 'C:\\MatriosgaChiave\\app.key'
        // (spostarci il file app.key esistente, altrimenti la password va reinserita).
        // In alternativa: variabile d'ambiente MATRIOSGA_KEY_FILE.
        'key_file' => '',
    ],

    'connection' => [
        'server'                   => '',     // es. SRVSQL01 oppure SRVSQL01\ISTANZA oppure 192.168.1.10
        'port'                     => '',     // vuoto = porta di default / istanza nominata
        'database'                 => '',
        'auth'                     => 'sql',  // sql | windows
        'username'                 => '',
        'password'                 => '',     // mai qui: salvata cifrata in storage/secrets.json
        'schema'                   => 'dbo',
        'login_timeout'            => 10,     // secondi
        'query_timeout'            => 30,     // secondi
        'encrypt'                  => true,   // ODBC Driver 18 cifra per default
        'trust_server_certificate' => true,   // tipico in LAN senza certificato valido
        'read_uncommitted'         => true,   // non blocca il gestionale durante le letture
    ],

    // Tabelle "rumore" nascoste di default in tutte le pagine (ogni pagina ha l'interruttore per mostrarle).
    'filters' => [
        'hide_empty'  => true,   // 0 righe (su SGA circa 9 tabelle su 10)
        'hide_copies' => true,   // copie di sicurezza riconosciute dal nome (Save_…, …_SAVE_2019_…, XXBeforeRepair_…)
    ],

    'limits' => [
        'search_batch_tables'   => 20,        // tabelle per chiamata AJAX nella ricerca valore
        'search_max_table_rows' => 5000000,   // tabelle più grandi saltate (forzabile)
        'search_max_hits'       => 500,       // stop dopo N colonne con risultati
        'preview_rows'          => 50,        // righe mostrate per un risultato
        'page_size'             => 100,       // righe per pagina in Colonne e Relazioni
        'analysis_top_values'   => 50,        // valori distinti mostrati in analisi colonna
        'analysis_sample_rows'  => 0,         // 0 = tutta la tabella; altrimenti TOP N righe
        'path_max_depth'        => 4,         // salti massimi nel percorso tra tabelle
        'path_max_results'      => 10,        // percorsi massimi mostrati
        'graph_max_nodes'       => 150,       // nodi massimi nel grafo
    ],

    /*
     * Funzioni dell'app: unica fonte per sidebar, dashboard e ricerca della landing page.
     * home      = mostrata come pulsante grande in dashboard
     * ready     = funzione già implementata (false = "in arrivo", non cliccabile)
     * keywords  = sinonimi usati dalla barra di ricerca della landing
     * questions = domande tipiche a cui risponde (anche queste ricercabili)
     */
    'menu' => [
        [
            'path' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'home' => false, 'ready' => true,
            'desc' => 'Panoramica: server, database, conteggi di tabelle, colonne, PK e FK',
            'keywords' => ['panoramica', 'riepilogo', 'statistiche', 'conteggi', 'quante tabelle', 'server', 'versione', 'schemi', 'metadata', 'aggiorna'],
            'questions' => ['Quante tabelle, viste e colonne ci sono?', 'A quale server e database sono collegato?'],
        ],
        [
            'path' => '/search', 'label' => 'Cerca valore', 'icon' => 'fa-magnifying-glass', 'home' => true, 'ready' => true,
            'desc' => 'In quali tabelle/colonne compare un valore',
            'keywords' => ['valore', 'dato', 'trova', 'ovunque', 'tutto il db', 'occorrenze', 'testo', 'stringa', 'codice', 'nome file', 'xml', 'guid', 'numero documento', 'contiene', 'dove compare'],
            'questions' => ['In quali tabelle/colonne compare un determinato valore?', 'Dove è salvato questo codice o nome file?'],
        ],
        [
            'path' => '/tables', 'label' => 'Tabelle', 'icon' => 'fa-table-list', 'home' => true, 'ready' => true,
            'desc' => 'Colonne, PK, FK, indici di una tabella',
            'keywords' => ['tabella', 'viste', 'esplora', 'struttura', 'colonne', 'tipi', 'chiave primaria', 'primary key', 'pk', 'indici', 'righe', 'default', 'identity', 'nullable', 'power query', 'sql'],
            'questions' => ['Quali sono le chiavi primarie di questa tabella?', 'Com\'è fatta questa tabella (colonne e tipi)?', 'Quante righe ha?'],
        ],
        [
            'path' => '/columns', 'label' => 'Colonne', 'icon' => 'fa-table-columns', 'home' => true, 'ready' => true,
            'desc' => 'In quali tabelle esiste una colonna',
            'keywords' => ['colonna', 'campo', 'field', 'nome campo', 'dove esiste', 'cerca colonna', 'attributo'],
            'questions' => ['In quali tabelle esiste una determinata colonna?', 'Quali tabelle hanno un campo chiamato NOME?'],
        ],
        [
            'path' => '/relations', 'label' => 'Relazioni', 'icon' => 'fa-link', 'home' => true, 'ready' => true,
            'desc' => 'FK dichiarate e relazioni candidate',
            'keywords' => ['relazioni', 'foreign key', 'fk', 'chiavi esterne', 'collegamenti', 'riferimenti', 'punta a', 'candidate', 'non dichiarate', 'join', 'lookup'],
            'questions' => ['Quali sono le chiavi esterne e a cosa si collegano?', 'Quali colonne potrebbero essere collegate anche senza FK?'],
        ],
        [
            'path' => '/path', 'label' => 'Percorso', 'icon' => 'fa-route', 'home' => true, 'ready' => true,
            'desc' => 'Come collegare la tabella A alla B',
            'keywords' => ['percorso', 'collegare', 'collegate', 'cammino', 'da a', 'join', 'legame', 'come arrivo', 'catena', 'passaggi'],
            'questions' => ['Queste due tabelle sono collegate? Da cosa?', 'Esiste un percorso tra la tabella A e la tabella B?'],
        ],
        [
            'path' => '/queries', 'label' => 'Query di SGA', 'icon' => 'fa-scroll', 'home' => true, 'ready' => true,
            'desc' => 'Come SGA stesso usa una tabella o colonna',
            'keywords' => ['query', 'vista', 'viste', 'funzione', 'funzioni', 'trigger', 'procedura', 'codice', 'logica', 'calcolo', 'come usa', 'come calcola', 'esempio', 'sql di sga', 'zucchetti'],
            'questions' => ['Come usa SGA questa tabella?', 'Quali query del gestionale collegano queste tabelle?', 'Come calcola SGA questo valore?'],
        ],
        [
            'path' => '/graph', 'label' => 'Grafo', 'icon' => 'fa-diagram-project', 'home' => true, 'ready' => true,
            'desc' => 'Mappa interattiva delle relazioni',
            'keywords' => ['grafo', 'mappa', 'diagramma', 'er', 'schema visivo', 'disegno', 'visualizza', 'vicini', 'rete'],
            'questions' => ['Come è strutturata una determinata parte del database?', 'Quali tabelle stanno intorno a questa?'],
        ],
        [
            'path' => '/analysis', 'label' => 'Analisi', 'icon' => 'fa-chart-column', 'home' => true, 'ready' => true,
            'desc' => 'Valori, NULL, distinti di una colonna',
            'keywords' => ['analisi', 'profilo', 'profiling', 'distinti', 'null', 'frequenza', 'frequenti', 'minimo', 'massimo', 'media', 'distribuzione', 'statistiche colonna', 'lunghezza'],
            'questions' => ['Quali sono i valori più frequenti di una colonna?', 'Quanti valori distinti o NULL contiene?'],
        ],
        [
            'path' => '/settings', 'label' => 'Impostazioni', 'icon' => 'fa-gear', 'home' => false, 'ready' => true,
            'desc' => 'Connessione al database SGA e limiti',
            'keywords' => ['impostazioni', 'configurazione', 'connessione', 'server', 'password', 'utente', 'login', 'timeout', 'limiti', 'test connessione'],
            'questions' => ['Come collego Matriosga al database?'],
        ],
    ],
];
