# Roadmap — passi piccoli e ripristinabili

Legenda: `[x]` fatto e verificato (`php -l` + prova), `[~]` in corso, `[ ]` da fare.
Se una sessione si interrompe: riprendere dal primo passo non spuntato.

## Fase 0 — Progetto
- [x] 0.1 Analisi requisiti, `CLAUDE.md`, `docs/ARCHITETTURA.md`, questa roadmap

## Fase 1 — Fondamenta
- [x] 1.1 Skeleton: `.htaccess`, `public/index.php`, Core (Autoload, App, Router, Request, Response, View, Controller, HttpException), helpers
- [x] 1.2 Asset locali in `public/assets/vendor/` + `config/assets.php` + layout con sidebar + `app.css`/`app.js`
- [x] 1.3 `Settings` (storage/settings.json, cifratura password) + `Logger`
- [x] 1.4 `DatabaseService` (PDO_SQLSRV, timeout, read-only guard)
- [x] 1.5 Pagina Impostazioni: form connessione + "Testa connessione" (AJAX) + limiti
- [x] 1.6 `MetadataService` + `MetadataCache` + `Catalog`/`Table`/`Column` (Catalog testato con snapshot finto; **query sys.* da verificare sul server SGA reale**)
- [x] 1.7 Dashboard (conteggi, schemi, pulsanti) + "Aggiorna metadata"
- [x] 1.8 README (installazione XAMPP, estensioni, librerie, primo utilizzo)

## Extra
- [x] E.1 Landing page `/` con logo (`public/assets/matriosga.svg`) e ricerca istantanea fra le funzioni (dashboard spostata su `/dashboard`)
- [x] E.2 Landing: azioni rapide dal testo digitato (valore / nome tabella / nome colonna); Invio senza funzione corrispondente → tabelle

## Fase 2 — Esplorazione struttura
- [x] 2.1 Lista tabelle/viste con filtro istantaneo (testo + tipo + schema), colonne ordinabili
- [x] 2.2 Dettaglio tabella: info, colonne (tipo, len, prec, scala, null, identity, default, PK, FK, indice), filtro colonne
- [x] 2.3 Dettaglio tabella: PK (anche composte, ordine), FK uscenti/entranti (composte, ON DELETE/UPDATE, Copia JOIN), indici
- [x] 2.4 `QueryTextService`: SQL + Power Query (navigazione e query nativa), anche solo con colonne selezionate
- [x] 2.5 Ricerca colonne (contiene/uguale/inizia/termina, filtro tipo dati, viste sì/no)
- [x] 2.6 Pagina Relazioni (FK dichiarate, filtrabili, Copia JOIN; scheda "Candidate" segnaposto per Fase 3)
- [x] 2.7 Extra: scheda "Anteprima dati" (TOP N via `TableDataService`, riusabile in Fase 3)
- [x] 2.8 Prestazioni su SGA reale (8.614 tabelle, 128.518 colonne, 24.972 FK): cache divisa core/columns/tables-shard,
      elenco tabelle reso lato client (max 300 righe nel DOM), Relazioni con ricerca lato server + tabelle hub,
      FK entranti limitate a 50 nel dettaglio. /tables 16s→0,6s, /relations 58s→0,3s

## Conoscenza del DB SGA (dal DB reale, 25/09/2026)
- Database SGA di produzione (connessione in Impostazioni), solo schema dbo, 8.582 tabelle + 32 viste.
- PK quasi sempre composte e con `SOCIETA` in testa: (SOCIETA, CODICE) ×1861, (SOCIETA, PROGRESSIVO) ×445,
  (SOCIETA, ANNO, FILIALE, NUMERO) ×376. 381 tabelle senza PK.
- 20.574 FK su 24.972 sono composte; 19.855 iniziano con SOCIETA→SOCIETA.
- Hub (FK entranti): BaSocieta 3.981, BaFiliali 935, BaCliFor 885, BaUnitaMisura 729, BaValute 687, BaNazioni 560.
  → nel **percorso** vanno escluse/penalizzate come passaggi intermedi.
- Convenzione nomi: colonne FK = RUOLO_CLIFOR + RUOLO → BaCliFor(SOCIETA, CLIENTE_FORNITORE, CODICE).
  → le **relazioni candidate** si possono imparare dai nomi colonna delle FK dichiarate.
- 773 tabelle senza alcuna FK. Refresh metadata ≈ 55 s.

## Fase 3 — Ricerca e percorsi
- [x] 3.1 `ValueSearchService` + API a lotti (plan/run/rows; 1 scansione per tabella; lotti da ~6 s; parametri in CTE `p`)
- [x] 3.2 UI ricerca valore progressiva (2 richieste parallele, Stop, filtro risultati) + modale righe con SQL/Power Query.
      Tempi su SGA: «uguale a» ~15 s (482 tabelle), «contiene» valore corto ~155 s (781 tabelle, le ultime sono le più grandi)
- [x] 3.3 `RelationshipService`: relazioni candidate + verifica dati (campione 10.000 righe, % corrispondenze).
      Strategie: 'learned' (combinazioni di colonne apprese dalle FK dichiarate, coerenza ≥50%) e 'name' (PK singola).
      Su SGA: 14.605 candidate (11.445 escludendo hub >300 FK entranti), 513/773 tabelle isolate coperte, calcolo 1,4 s
      (cache `derived-candidates.json`). Visibili in Relazioni → Candidate e nel dettaglio tabella (scheda Chiavi)
- [x] 3.4 `PathFinderService` + UI percorso: FK / FK + candidate (affidabilità minima), hub esclusi come passaggi
      (attivabili), «Evita», SQL JOIN a catena + Power Query per ogni percorso, suggerimenti se nessun percorso. ~0,5-0,9 s

## Fase 4 — Visualizzazione e analisi
- [x] 4.1 `GraphService` + API grafo + pagina Cytoscape: vicinato 1-3 livelli (max graph_max_nodes), hub nascosti e mai espansi,
      archi raggruppati per coppia (×N), pannello nodo/arco (Apri, Espandi, Riparti da qui, Evidenzia, Nascondi, JOIN, Verifica),
      trova nodo, evidenzia percorso (aStar), 3 layout, PNG. Link da dettaglio tabella e da ogni percorso. API 0,15-0,3 s
- [x] 4.2 `ColumnAnalysisService` + pagina analisi: righe, NULL, distinti (+ "possibile chiave"), vuoti, min/max, media,
      lunghezze, valori più frequenti (NULL compreso, link "cerca questo valore"), distribuzione (anno / 10 fasce / lunghezza),
      SQL + Power Query. Tabelle > soglia: campione 1.000.000 righe (AppLog 31M righe: 2,4 s). Icona analisi nell'elenco colonne
- [-] 4.3 ~~Confronta due tabelle~~ — **sospesa su richiesta dell'utente (25/09/2026): non serve per ora.** Rimossa dal menu.

## Fase 5 — Rifinitura
- [x] 5.0 Impostazioni → **Ambiente server** (`EnvironmentService`): controlli automatici (PHP, pdo_sqlsrv, ODBC, OPcache,
      memory_limit, mod_deflate, mod_rewrite, storage) con istruzioni + checklist per installare su un'altra VM; avviso in dashboard
- [x] 5.0b Paginazione (`Helpers\Pager` + `partials/pager`) in Colonne e Relazioni, `limits.page_size` configurabile;
      compressione (mod_deflate se attivo) e cache asset 30 giorni in `public/.htaccess`
- [x] 5.0c Autocompletamento proprio (menu sotto il campo) al posto di `<datalist>` (copriva il testo digitato)
- [x] 5.0d Revisione MVC/DRY: niente logica/servizi nelle viste, niente JS inline, `QueryTextService::joins()`,
      `RelationshipService::forTable()`, partial fk_rules/mode_buttons, cols_a/cols_b calcolati una volta nel PathFinder;
      elenco tabelle con "Mostra altre 300"
- [x] 5.0e storage/: password in secrets.json (separata da settings.json), chiave app.key spostabile (security.key_file),
      storage/.gitignore + README, log tenuti 30 giorni. Repository privato GitHub BibiezDelBubez/matriosga (primo push fatto)
- [x] 6.1 Tabelle vuote e copie di sicurezza nascoste di default ovunque (`TableFilterService`, interruttore
      `partials/show_all`, parametro `?all=1`, default in Impostazioni). SGA: visibili 892 su 8.614
- [x] 6.2 "Query di SGA" (`/queries`, `SqlModuleService`): ricerca parola intera in viste/funzioni/trigger, estratti
      evidenziati, codice completo, SQL/Power Query per viste e funzioni tabella; pagina "permesso mancante" con GRANT
      da girare al DBA. Link «Come la usa SGA» nel dettaglio tabella. Testato offline + pagina senza permesso.
      **Da verificare dal vivo** quando il DBA darà `GRANT VIEW DEFINITION TO [sql01]` (336 query, 0 leggibili al 28/09/2026)
- [x] 6.3 Dashboard: numeri grandi = solo tabelle con dati (860 su 8.582), card "Tabelle nascoste"
- [x] 6.4 Spia modifiche (`/spy`, `SpyService`): generica SQL Server. Conteggi prima/dopo su tutte le tabelle (~2 s),
      rowversion (@@DBTS), colonne data/utente configurabili (config spy). Filtro facoltativo per utente.
      Su Sgam2: 342 tabelle controllabili riga per riga in ~9 s. Nessun permesso extra richiesto
- [-] 6.5 Decodifica codici — **sospesa (28/09/2026)**: l'utente vuole funzioni generiche, non basate su come SGA salva le descrizioni
- [x] 6.6 **Costruttore di query** (`/builder`, `QueryBuilderService`, `PathFinderService::connect`, `js/builder.js`):
      aggiungi tabelle → collegamento automatico al gruppo (tabelle ponte aggiunte da sole, fino a 6 percorsi alternativi),
      LEFT/INNER per join, colonne spuntabili, filtri (=, <>, >, <, contiene, inizia, NULL), SQL + Power Query + anteprima 50 righe.
      Dal browser arriva solo una descrizione (spec), l'SQL è costruito e validato sul server. Stato in localStorage.
      Idee future: stessa tabella due volte (es. cliente e fornitore), salvare/ricaricare query con nome
- [ ] 6.7 Idee restanti: Note personali, Recenti. Valutare nomi più generici per "Query di SGA" (es. "Query nel database")
- [ ] 5.1 Controllo "nessun URL esterno" (grep), log durata query, pulizia
- [ ] 5.2 Ottimizzazioni performance su DB reale (es. binding varchar vs nvarchar nei parametri per usare gli indici in modalità "uguale")
- [ ] 5.3 (se OPcache attivo) cache `core` come file PHP (var_export) in memoria condivisa: -120 ms per richiesta

## Note ambiente di sviluppo
- XAMPP in `C:\Users\michel.papetti\Documents\xampp`, Apache su **porta 8080** (la 80 è di IIS).
- Il progetto è collegato con una junction: `xampp\htdocs\matriosga` → `Documents\Matriosga`. URL: `http://localhost:8080/matriosga/`
- PHP 8.2.12 con `pdo_sqlsrv` + ODBC Driver 18 già installati. Nessun SQL Server locale: i test su dati reali vanno fatti sul server SGA.
- OPcache e mod_deflate non attivi in questo XAMPP: l'utente li attiverà seguendo Impostazioni → Ambiente server.
- Lo script demo con dati finti (tools/demo_metadata.php) è stato rimosso: serviva solo prima di avere il DB reale.
- **Encoding**: file UTF-8 senza BOM. **Non modificare file di progetto con PowerShell 5** (`Get-Content`/`Set-Content`): aggiunge BOM
  e rilegge in ANSI, corrompendo gli accenti (è successo a config/app.php). Usare Edit/Write o Bash/PHP.
