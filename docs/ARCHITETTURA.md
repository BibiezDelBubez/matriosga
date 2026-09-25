# Matriosga — Architettura

## 1. Architettura definitiva

App PHP 8.2 senza framework e senza Composer (zero installazioni), MVC con front controller unico.

```
Browser ──► Apache (.htaccess) ──► public/index.php ──► Router ──► Controller
                                                                     │
                                  View (layout + partials) ◄─────────┤
                                                                     ▼
                                                              Services ──► DatabaseService ──► SQL Server (read-only)
                                                                     │
                                                              MetadataCache (storage/cache/*.json)
```

- **Pagine** renderizzate server-side (veloci, semplici).
- **Operazioni lunghe** (ricerca valore, analisi colonna, verifica relazioni candidate) via
  endpoint JSON `/api/...` chiamati da JS vanilla, **a lotti**, per mostrare risultati
  progressivi e non bloccare l'interfaccia.

## 2. Struttura cartelle

```
Matriosga/
├── .htaccess                 # rimanda tutto a public/ (app/, config/, storage/ non raggiungibili)
├── CLAUDE.md                 # regole per agenti AI / sviluppatori
├── README.md
├── app/
│   ├── Core/                 # Autoload, App, Router, Request, Response, View, Controller, Settings, Logger, HttpException
│   ├── Controllers/          # uno per area funzionale
│   ├── Models/               # Catalog, Table, Column (oggetti di dominio sui metadata)
│   ├── Services/             # DatabaseService, MetadataService, MetadataCache, ... (tutta la logica e l'SQL)
│   ├── Helpers/              # Sql (quoting), functions.php (e(), url(), asset())
│   └── Views/
│       ├── layout/           # main.php (sidebar, topbar)
│       ├── partials/         # componenti riusabili
│       └── <area>/           # viste per area
├── config/
│   ├── app.php               # default applicativi (versionato)
│   └── assets.php            # percorsi librerie CSS/JS locali (sostituibili)
├── docs/                     # ARCHITETTURA.md, ROADMAP.md
├── public/
│   ├── index.php             # front controller
│   ├── .htaccess
│   └── assets/
│       ├── css/app.css
│       ├── js/app.js (+ un file per pagina complessa)
│       └── vendor/           # bootstrap/, fontawesome/, cytoscape/  ← librerie dell'utente
├── routes/web.php
└── storage/                  # NON versionato
    ├── settings.json         # connessione e limiti (senza segreti)
    ├── secrets.json          # password DB cifrata
    ├── app.key               # chiave di cifratura (spostabile fuori dal progetto)
    ├── README.md             # cosa contiene ogni file
    ├── cache/                # metadata_<server>_<db>.json
    └── logs/                 # app-YYYY-MM-DD.log
```

## 3. Librerie locali

| Libreria | Versione | Posizione | Origine |
|---|---|---|---|
| Bootstrap (css + bundle js) | 5.3.8 | `public/assets/vendor/bootstrap/` | copia locale da `htdocs/Quack_ERP` |
| Font Awesome | 7.2 (Pro, licenza utente) | `public/assets/vendor/fontawesome/` | copia locale da `htdocs/Quack_ERP` |
| Cytoscape.js | 3.33.1 (MIT) | `public/assets/vendor/cytoscape/` | copia locale da `node_modules` già su disco |

Tutti i percorsi sono in `config/assets.php`: per cambiare versione basta sostituire il file o
cambiare il percorso lì. Nessun codice applicativo contiene percorsi di librerie.

## 4. Schema MVC

- **Controller**: `DashboardController`, `SettingsController`, `TablesController`,
  `ColumnsController`, `RelationsController`, `PathController`, `SearchController`,
  `GraphController`, `AnalysisController`, `CompareController`, `MetadataController`.
- **Model**: `Catalog` (snapshot in memoria di schemi/tabelle/viste/colonne/PK/FK/indici con
  lookup O(1)), `Table`, `Column`. I Model non fanno query: le fa `MetadataService`.
- **View**: PHP puro con `e()` per l'escape. Layout unico + partials.

## 5. Servizi principali

| Servizio | Responsabilità |
|---|---|
| `DatabaseService` | unica connessione PDO_SQLSRV (lazy); timeout; guard read-only; misura durata e logga |
| `MetadataService` | legge `sys.*` in poche query set-based e costruisce il `Catalog` |
| `MetadataCache` | salva/carica lo snapshot JSON; "Aggiorna metadata" forza rilettura |
| `QueryTextService` | genera SQL leggibile da copiare e snippet Power Query M |
| `ColumnSearchService` | ricerca colonne per nome (solo metadata, istantanea) |
| `RelationshipService` | FK dichiarate + relazioni **candidate** (euristica metadata + verifica dati opzionale) |
| `PathFinderService` | percorsi tra tabelle (BFS limitata in profondità) |
| `ValueSearchService` | ricerca valore globale a lotti |
| `ColumnAnalysisService` | profilazione colonna (conteggi, distinti, min/max, top valori) |
| `TableCompareService` | confronto struttura e possibili collegamenti fra due tabelle |
| `GraphService` | converte Catalog/relazioni in elementi Cytoscape (vicinato entro N livelli) |

## 6. Strategia SQL Server

- Driver **PDO_SQLSRV** (già installato in XAMPP insieme a ODBC Driver 18). PDO dà eccezioni e
  parametri uniformi; `sqlsrv` non serve.
- ODBC Driver 18 cifra per default: opzioni `Encrypt` e `TrustServerCertificate` configurabili
  (default: Encrypt=yes, TrustServerCertificate=yes, tipico per server in LAN senza certificato).
- Autenticazione SQL Server (utente/password) o **Windows** (utente/password vuoti → integrated;
  usa l'identità con cui gira Apache: se Apache è servizio, è l'account macchina).
- Timeout: `LoginTimeout` in DSN, `PDO::SQLSRV_ATTR_QUERY_TIMEOUT` per statement.
- Isolamento: `SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED` (configurabile) per non
  creare lock sul gestionale in produzione.
- Guard read-only: `DatabaseService` rifiuta testo che non inizia con `SELECT`/`WITH`/`SET TRANSACTION`
  o che contiene `;` fuori da stringhe. Consigliato comunque un login SQL con solo `db_datareader`.
- Righe per tabella da `sys.partitions` (index_id 0/1): approssimate ma istantanee, senza `COUNT(*)`.

## 7. Cache metadata

- Snapshot unico per server+database in `storage/cache/metadata_<hash>.json`.
- Contiene solo **struttura** (nessun dato delle tabelle).
- Caricata una volta per request (json_decode di pochi MB: decine di ms).
- Invalidazione: pulsante **Aggiorna metadata** (dashboard/topbar) e automatica al cambio di connessione.
- La data dello snapshot è mostrata in topbar.

## 8. Strategia grafo

- **Cytoscape.js** (scelto su vis-network): API per vicinati (`closedNeighborhood`),
  cammini (`aStar`/`dijkstra`), layout multipli (`cose`, `breadthfirst`, `concentric`),
  stili per archi tratteggiati (candidati) vs pieni (FK), buone prestazioni su centinaia di nodi.
- Non si disegna mai l'intero DB di default: si parte da una tabella e si espande entro **N livelli**
  (default 1–2), con limite nodi. Il server restituisce i soli elementi necessari (`/api/graph`).
- Funzioni: zoom/pan/drag, click nodo (pannello dettagli + link a Esplora), click arco (colonne),
  evidenzia percorso, nascondi nodo, cerca nodo, reset layout, espandi nodo.

## 9. Strategia ricerca valore globale

1. Dal Catalog si selezionano le **colonne candidate** in base a tipo e valore:
   - stringhe (`char/varchar/nchar/nvarchar/text/ntext`) con lunghezza max ≥ lunghezza valore
     (per "uguale"); `text/ntext` solo con `LIKE`;
   - `uniqueidentifier` solo se il valore è un GUID valido (uguaglianza);
   - numerici solo se il valore è numerico (uguaglianza), opzionale.
2. Una query **per tabella** che conta le occorrenze di tutte le colonne candidate in una sola
   scansione: `SELECT SUM(CASE WHEN [c1] LIKE @p THEN 1 ELSE 0 END) AS c1, ... FROM t WHERE [c1] LIKE @p OR ...`.
3. Il browser chiama `/api/search/value` a **lotti di tabelle** (es. 20): barra di avanzamento,
   risultati progressivi, pulsante **Stop**.
4. Limiti configurabili: tabelle con righe > soglia saltate (segnalate, forzabili), timeout per
   query, numero massimo tabelle.
5. Click su un risultato → modale con le prime N righe trovate (`TOP N ... WHERE col LIKE @p`)
   e SQL/Power Query copiabili.
6. Modalità: contiene / uguale / inizia con / termina con; case-insensitive (collation CI tipica;
   se la collation del DB è CS si applica `COLLATE` CI). Wildcard `% _ [` dell'utente escapate.

## 10. Problemi tecnici noti

- **Windows authentication**: dipende dall'account di Apache. Se non funziona, usare login SQL.
- **Certificato TLS** (ODBC 18): se la connessione fallisce con errore SSL, attivare TrustServerCertificate.
- **DB molto grandi**: ricerca con `LIKE '%x%'` = scansione; mitigata da lotti, soglie righe, timeout,
  e modalità "uguale"/"inizia con" che possono usare indici.
- **Relazioni candidate**: euristiche → possibili falsi positivi; sempre etichettate come
  *candidate* con punteggio, mai come FK. Verifica dati facoltativa e limitata (campionamento).
- **Percorsi**: esplosione combinatoria → profondità massima (default 4) e numero massimo di percorsi.
- **Font Awesome Pro 7**: licenza commerciale dell'utente, non ridistribuire il pacchetto.
- **Permessi**: `sys.*` visibile solo per oggetti su cui l'utente ha permessi; serve almeno `db_datareader`
  (e `VIEW DEFINITION` per vedere tutti i metadata).
