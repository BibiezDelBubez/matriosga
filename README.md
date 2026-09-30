# Matriosga

**Trova in pochi minuti dove sta un dato nel database del gestionale e come collegarlo, fino alla query pronta per Power BI.**

Pensato per chi fa **analisi e report**, non per programmatori: si parte da una domanda
("dove sta questo dato?", "come collego viaggi e clienti?") e non serve conoscere il database.

- Funziona su **SQL Server** (nato per il gestionale SGA, ma vale per qualsiasi database SQL Server).
- **Legge e basta**: non modifica mai niente nel database.
- Gira **in locale, senza Internet**, su XAMPP.

---

## Cosa ci fai

| Domanda | Funzione |
|---|---|
| In quale tabella c'è questo codice / nome file / P.IVA? | **Cerca valore** |
| Cosa c'è in questa tabella? Quali sono le chiavi? | **Tabelle** |
| In quali tabelle esiste un campo con questo nome? | **Colonne** |
| Come si collega la tabella A alla tabella B? | **Percorso** e **Grafo** |
| Mi dai la query pronta, con le JOIN giuste? | **Costruttore query** |
| Quali valori ci sono in questa colonna? Quanti vuoti? | **Analisi** |
| Dove scrive il gestionale quando faccio un'operazione? | **Spia modifiche** |
| Come usa il gestionale stesso questa tabella? | **Query di SGA** |
| Due tabelle si collegano in un modo che il database non dichiara? | **Relazioni → Definite da te** |

Tabelle vuote e copie di sicurezza (es. `Save_…`, `XXBeforeRepair_…`) sono **nascoste di default**:
vedi solo quelle con dati. Ogni pagina ha l'interruttore «Mostra anche tabelle vuote e copie».

## Come si usa

1. Apri l'indirizzo di Matriosga nel browser (es. `http://192.168.31.32/matriosga/`).
2. **Scrivi nella barra di ricerca quello che ti serve**, anche come domanda: ti porta alla funzione giusta.
   Se scrivi un nome, puoi cercarlo subito come valore, tabella o colonna.
3. Ovunque trovi pulsanti per **copiare** nomi, SQL e Power Query.

---

## Installazione (per chi la mette su un PC o una VM)

1. Installa **XAMPP** (PHP 8.2, 64 bit) e **Microsoft ODBC Driver 18 for SQL Server** (x64).
2. Copia la cartella di Matriosga in `xampp\htdocs\` (es. `xampp\htdocs\matriosga`).
3. Avvia **Apache** e apri `http://localhost/matriosga/` (se Apache usa un'altra porta: `http://localhost:8080/matriosga/`).
4. Vai in **Impostazioni → Ambiente server**: controlla da sola cosa manca (driver PHP per SQL Server,
   permessi, memoria…) e per ogni voce rossa o gialla ti dice **esattamente cosa fare** e cosa copiare.
5. Vai in **Impostazioni → Connessione**, inserisci server e database, premi **Testa connessione**, poi **Salva**.
6. Apri la **Dashboard**: la prima volta legge la struttura del database (circa un minuto).

Quando la struttura del database cambia, premi **Aggiorna metadata** in alto a destra.

**Consigli**
- Usa un utente SQL **di sola lettura** (`db_datareader`). Per la funzione «Query di SGA» serve anche
  `VIEW DEFINITION` (la pagina stessa mostra la riga da girare al DBA).
- Per più velocità attiva **OPcache** (in `php.ini`) e **mod_deflate** (in `httpd.conf`): istruzioni in Ambiente server.

## Dove stanno i dati

Tutto in file nella cartella `storage\`. Nel database non viene scritto niente.

| File | Contenuto | Se lo perdi |
|---|---|---|
| `relations.json` | **le relazioni definite da te** | le perdi: **è l'unico file da salvare davvero** |
| `settings.json` | connessione e impostazioni (senza password) | reinserisci la connessione |
| `secrets.json` + `app.key` | password cifrata + chiave per leggerla | reinserisci la password |
| `cache\` | copia della struttura del database | si ricrea da sola |
| `logs\` | errori e query lente (30 giorni) | nessun problema |

Il lavoro in corso nel **Costruttore query** resta nel browser, non in questa cartella.
Dettagli e sicurezza della password: `storage\README.md`.

## Spostarla su un'altra macchina

- **Copiando la cartella intera**: i dati arrivano con lei. In Impostazioni scrivi il server **esattamente
  come prima** (stesso nome o stesso IP), altrimenti le relazioni definite da te non compaiono.
- **Con `git clone` da GitHub**: `storage\` arriva vuota (i file locali non vanno mai su GitHub).
  Copia a mano dalla vecchia macchina `relations.json`, `settings.json`, `secrets.json` e `app.key`,
  oppure reinserisci la connessione.

In entrambi i casi, dopo: **Impostazioni → Ambiente server** e sistema le voci rosse.

---

## Per chi sviluppa

- Architettura e scelte tecniche: `docs/ARCHITETTURA.md`
- Avanzamento e note: `docs/ROADMAP.md`
- Regole (MVC, DRY, sola lettura, offline): `CLAUDE.md`
- Librerie locali in `public/assets/vendor/` (Bootstrap 5, Font Awesome, Cytoscape.js), referenziate solo da
  `config/assets.php`: per aggiornarne una basta sostituire il file o cambiare il percorso lì.
- Log tecnici: `storage/logs/app-AAAA-MM-GG.log`.
