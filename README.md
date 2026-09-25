# Matriosga

Esploratore **offline** della struttura e dei dati di un database **SGA (Sima / Zucchetti)** su
SQL Server, pensato per chi costruisce report Power BI / Power Query.
Sola lettura: non modifica mai il database.

## 1. Prerequisiti

- XAMPP con **PHP 8.1+** (testato con PHP 8.2.12)
- Estensione **pdo_sqlsrv** per la tua versione di PHP (Microsoft Drivers for PHP for SQL Server)
- **Microsoft ODBC Driver 17 o 18 for SQL Server** (64 bit)
- Nessuna connessione Internet necessaria; MySQL non serve

### Estensione pdo_sqlsrv

1. Copiare `php_pdo_sqlsrv_82_ts.dll` (versione adatta al proprio PHP: `82` = PHP 8.2, `ts` = thread safe, come XAMPP) in `xampp\php\ext\`.
2. In `xampp\php\php.ini` aggiungere:
   ```ini
   extension=php_pdo_sqlsrv_82_ts.dll
   ```
3. Riavviare Apache. Verifica: `php -m` deve elencare `pdo_sqlsrv`.

Se manca il driver ODBC, "Testa connessione" mostra un errore esplicito.

## 2. Installazione

Copiare la cartella del progetto in `C:\xampp\htdocs\` (es. `C:\xampp\htdocs\matriosga`),
avviare Apache e aprire `http://localhost/matriosga/`
(se Apache usa un'altra porta, es. 8080: `http://localhost:8080/matriosga/`).

Serve `mod_rewrite` attivo e `AllowOverride All` su htdocs (default di XAMPP).
La cartella `storage/` deve essere scrivibile da Apache.

> Sviluppo: invece di copiare si può creare una junction
> `mklink /J C:\xampp\htdocs\matriosga C:\percorso\Matriosga`.

## 3. Librerie CSS/JS locali

Tutte le librerie stanno in `public/assets/vendor/` e sono referenziate **solo** da
`config/assets.php`. Per aggiornarne una: sostituire il file (stesso nome) oppure cambiare il percorso in `config/assets.php`.

| Libreria | Cartella | File usati |
|---|---|---|
| Bootstrap 5 | `vendor/bootstrap/` | `css/bootstrap.min.css`, `js/bootstrap.bundle.js` |
| Font Awesome | `vendor/fontawesome/` | `css/all.min.css` + `webfonts/*.woff2` (la struttura `css/` + `webfonts/` va mantenuta) |
| Cytoscape.js | `vendor/cytoscape/` | `cytoscape.min.js` (grafo relazioni) |

Nessun CDN, nessun font remoto: l'app funziona senza Internet.

## 4. Configurazione database

Menu **Impostazioni → Connessione database**: server (`NOME`, `NOME\ISTANZA` o IP), porta opzionale,
database, autenticazione SQL o Windows, schema predefinito, timeout.

- I valori sono salvati in `storage/settings.json` (senza password; esempio in `storage/settings.example.json`).
- La password sta a parte in `storage/secrets.json`, cifrata con la chiave `app.key`; non viene mai mostrata né loggata.
  Consigliato spostare `app.key` fuori dal progetto (`config/app.php` → `security.key_file`). Dettagli in `storage/README.md`.
- **Consigliato**: un login SQL dedicato con solo `db_datareader` (+ `VIEW DEFINITION` per vedere tutti i metadata).
- **ODBC Driver 18** cifra per default: se il server non ha un certificato valido lasciare attivo *TrustServerCertificate*.
- **Autenticazione Windows**: usa l'account con cui gira Apache. Se Apache è avviato come servizio è l'account di sistema, quindi conviene un login SQL.
- *READ UNCOMMITTED* (default attivo) evita di bloccare il gestionale durante le letture.

## 5. Test connessione

Nella stessa pagina, **Testa connessione** prova i valori del form (anche prima di salvarli) e
mostra server, database, utente, versione SQL Server e collation, oppure l'errore dettagliato.

## 6. Primo utilizzo

1. Salvare la connessione.
2. Aprire la **Dashboard**: al primo accesso Matriosga legge la struttura (tabelle, colonne, PK, FK, indici) dai cataloghi `sys.*` e la salva in `storage/cache/`.
3. Quando la struttura del DB cambia, premere **Aggiorna metadata** (in alto a destra).

## 7. Controllo dell'ambiente e installazione su un'altra macchina

Menu **Impostazioni → Ambiente server** controlla da solo PHP, driver SQL Server, OPcache, memoria,
compressione, permessi di `storage` e, per ogni voce da sistemare, mostra i passi e le righe da copiare
nei file di configurazione (con il percorso esatto di `php.ini` sulla macchina in uso). La Dashboard
avvisa se qualcosa non va.

Per spostare Matriosga su un'altra VM:

1. Installare XAMPP (PHP 8.2, 64 bit) e *Microsoft ODBC Driver 18 for SQL Server* (x64).
2. Copiare la cartella in `xampp\htdocs\` (`storage\cache` può restare vuota).
3. Per portare la connessione copiare `storage\settings.json`, `storage\secrets.json` **e** `app.key`; altrimenti copiare solo `settings.json` e reinserire la password.
4. Avviare Apache, aprire Impostazioni → Ambiente server e sistemare prima le voci rosse, poi le gialle.
5. Testa connessione (il server SQL deve essere raggiungibile dalla nuova VM), poi aprire la Dashboard.

Consigliati per la velocità: **OPcache** (in `php.ini`) e **mod_deflate** (in `httpd.conf`).

## Struttura del progetto

Vedi `docs/ARCHITETTURA.md`. Avanzamento lavori in `docs/ROADMAP.md`. Regole di sviluppo in `CLAUDE.md`.

Log tecnici: `storage/logs/app-AAAA-MM-GG.log` (errori, query lente, aggiornamenti metadata; mai password).
