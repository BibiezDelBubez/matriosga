# Matriosga — regole di progetto (per agenti AI e sviluppatori)

Matriosga è una web app PHP **locale e offline** (XAMPP) per esplorare struttura e dati
di un database **SGA (Sima/Zucchetti) su SQL Server**, a supporto di chi sviluppa report
Power BI / Power Query. Deve essere **veloce, semplice, a prova di errore**.

Documenti da leggere prima di lavorare:
- `docs/ARCHITETTURA.md` — architettura e strategie tecniche (fonte di verità)
- `docs/ROADMAP.md` — avanzamento a passi piccoli. **Aggiornarlo dopo ogni passo completato.**

## Regole non negoziabili

1. **Read-only verso SGA.** Mai INSERT/UPDATE/DELETE/DDL. Tutte le query passano da
   `DatabaseService`, che accetta solo `SELECT`/`WITH` e rifiuta statement multipli.
2. **Offline.** Nessun URL `http(s)://` verso CDN, font, API. Tutti gli asset sono in
   `public/assets/vendor/` e vengono referenziati **solo** tramite `config/assets.php`.
3. **Niente SQL arbitrario dall'utente.** Identificatori (schema/tabella/colonna) validati
   contro il catalogo metadata (`Catalog`) e quotati con `Sql::quoteIdent()`. Valori sempre
   come parametri PDO.
4. **Password mai** in codice, log, output HTML o JSON. La config locale sta in
   `storage/settings.json` (senza segreti); la password sta cifrata in `storage/secrets.json`
   con la chiave `app.key` (percorso configurabile: `config/app.php` → `security.key_file`). Vedi `storage/README.md`.
5. **MVC reale:** Controller sottili (leggono input, chiamano Service, rendono View).
   SQL solo nei Service. HTML solo nelle View/partials. Nessuna logica nelle View oltre a cicli/if.
   Le View non chiamano `App::get()`, `$_SERVER`, `ini_get()` né costanti dei Service: tutto arriva dal controller.
   Niente `<script>` inline né `onclick=`: JS in `public/assets/js/` (comune in app.js, per pagina in <pagina>.js).
6. **Paginazione/limiti ovunque una lista può crescere** (SGA: 8.614 tabelle, 25.000 FK): `Pager` lato server,
   oppure resa lato client a blocchi (elenco tabelle), oppure limite + link "vedi tutte".

## DRY — dove sta ogni cosa (non duplicare!)

| Cosa | Unico punto |
|---|---|
| Connessione PDO | `App\Services\DatabaseService` (lazy, una per request) |
| Lettura cataloghi sys.* | `App\Services\MetadataService` |
| Cache metadata | `App\Services\MetadataCache` (file JSON in `storage/cache/`) |
| Accesso a tabelle/colonne/PK/FK in memoria | `App\Models\Catalog` (+ `Table`, `Column`) |
| Quoting/escape identificatori e LIKE | `App\Helpers\Sql` |
| Generazione SQL "da copiare" e Power Query M | `App\Services\QueryTextService` |
| Settings (lettura/scrittura, cifratura password) | `App\Core\Settings` |
| Log | `App\Core\Logger` (mai password) |
| Escape HTML | helper `e()` in `app/Helpers/functions.php` |
| Componenti HTML ripetuti (copia, badge tipo, tabella risultati) | `app/Views/partials/` |
| Funzioni JS comuni (fetch JSON, copia, toast, escape) | `public/assets/js/app.js` (`Matriosga.*`) |
| Routing | `routes/web.php` |
| Elenco funzioni (sidebar, dashboard, ricerca landing: label, icona, keywords, domande) | `config/app.php` → `menu`. **Quando una funzione è completata impostare `'ready' => true`** |
| Card di una funzione | `app/Views/partials/tool_card.php` |
| Link a tabella / riferimento `tabella.colonna` / card FK / blocco codice copiabile / filtro | `partials/table_link`, `col_ref`, `fk_card`, `code_block`, `filter_box` |
| Filtro istantaneo, ordinamento tabelle, "seleziona tutte" | attributi `data-filter-table`, `th[data-sort]`, `data-check-all` gestiti in `app.js` |
| Griglia dati da JSON (anteprima, righe trovate) | `Matriosga.renderDataTable()` in `app.js` |
| Lettura righe dati (TOP N, filtro LIKE) | `App\Services\TableDataService` |
| Modalità di confronto testo (contiene/uguale/...) | `App\Helpers\Sql::MATCH_MODES` |
| Ricerca valore (colonne compatibili, predicati, CTE parametri) | `App\Services\ValueSearchService` |
| FK dichiarate, relazioni candidate, hub, verifica dati | `App\Services\RelationshipService` (candidate MAI presentate come FK) |
| Percorsi tra tabelle | `App\Services\PathFinderService`; SQL a catena in `QueryTextService::joinPath()` |
| Dati calcolati dai metadata (cache che si rigenera con "Aggiorna metadata") | `MetadataCache::derived()` |
| Archi del grafo relazioni (FK + candidate) e FK entranti | `RelationshipService::edges()`, `inDegree()` (usati da Percorso e Grafo) |
| Grafo Cytoscape (vicinato, archi raggruppati) | `App\Services\GraphService` + `public/assets/js/graph.js` |
| Profilo colonna (statistiche, top valori, distribuzione) | `App\Services\ColumnAnalysisService` |
| Paginazione | `App\Helpers\Pager` + `partials/pager.php` (dimensione: `limits.page_size`) |
| Controlli ambiente server + istruzioni installazione | `App\Services\EnvironmentService` (pagina Impostazioni → Ambiente server) |
| Tabelle vuote / copie di sicurezza da nascondere | `App\Services\TableFilterService` + `Controller::noiseFilter()` + `partials/show_all.php` (parametro `?all=1`). **Ogni nuova lista di tabelle/relazioni deve usarlo** |
| Query di SGA (viste, funzioni, trigger nel DB) | `App\Services\SqlModuleService` (serve permesso VIEW DEFINITION) |
| Spia modifiche (conteggi prima/dopo, rowversion, colonne data/utente configurabili) | `App\Services\SpyService`; nomi colonne in `config/app.php` → `spy` |
| Costruttore di query (spec → SQL validato, anteprima) | `App\Services\QueryBuilderService`; collegamento tabelle `PathFinderService::connect()`; esecuzione `TableDataService::run()` |

**Generico SQL Server**: le funzioni non devono dipendere da convenzioni di SGA. Solo meccanismi standard
(sys.*, FK, rowversion, conteggi); se serve una convenzione di nomi, renderla configurabile in `config/app.php`.
| Ricerca/evidenziazione testo | PHP `App\Helpers\Text` (regex parola intera, highlight); JS `Matriosga.highlight()` |
| Riga relazione (FK o candidata) con Verifica + Copia JOIN | `partials/relation_row.php`; pulsante `[data-verify]` gestito in `app.js` |
| Campo con autocompletamento tabelle | attributo `data-table-picker` (app.js, API `/api/tables/names`) |
| Autocompletamento generico (menu sotto il campo, mai `<datalist>`) | `Matriosga.autocomplete(input, getItems)` |
| JOIN per una lista di relazioni | `QueryTextService::joins()` (mai cicli di `join()` nei controller) |
| Relazioni di una tabella (FK entranti limitate, candidate) | `RelationshipService::forTable()` |
| Regole FK (ON DELETE/UPDATE, disabilitata) / pulsanti modalità | `partials/fk_rules.php`, `partials/mode_buttons.php` |
| Invio form al cambio / scambio valori di due campi | attributi `data-autosubmit`, `data-swap="#a,#b"` (app.js) |
| Blocco codice / link tabella generati in JS | `Matriosga.codeBlock()`, `Matriosga.tableLink()` (stessa resa dei partial PHP) |

Tabelle "hub" (FK entranti > `RelationshipService::HUB_IN`, es. BaSocieta): escluse di default da candidate e passaggi dei percorsi.
| Categorie tipi SQL (testo, intero, data...) | `App\Models\Column` (`categoryOf`, `CATEGORY_LABELS`) |
| Tag CSS/JS nelle pagine | `partials/assets_css.php`, `partials/assets_js.php` (usati da entrambi i layout) |
| Percorsi librerie CSS/JS | `config/assets.php` |

Prima di scrivere una nuova query o funzione, **cercare se esiste già** nei punti sopra.

## Convenzioni

- PHP 8.2, `declare(strict_types=1);`, namespace `App\` → `app/` (autoloader PSR-4 in `app/Core/Autoload.php`, niente Composer).
- Nomi tabelle completi sempre nel formato `schema.tabella` (chiave del Catalog).
- Risposte AJAX: `Response::json(['ok'=>bool, 'data'=>..., 'error'=>...])`.
- UI in italiano. Bootstrap 5 + Font Awesome locali. JS vanilla.
- Query sui dati: `READ UNCOMMITTED` (configurabile) per non bloccare il gestionale; sempre timeout e `TOP`.
- File UTF-8 **senza BOM**. Non riscrivere file con PowerShell 5 (`Set-Content`): aggiunge BOM e corrompe gli accenti.
- Ogni fase deve lasciare l'app funzionante. Dopo ogni passo: `php -l` sui file toccati e spunta in `docs/ROADMAP.md`.
