# storage/ — dati locali di questa installazione

Cartella scritta da Matriosga. **Non raggiungibile dal browser** (`.htaccess`) e **esclusa da git** (`.gitignore`).

| File / cartella | Cosa contiene | Segreto? | Se lo cancello |
|---|---|---|---|
| `settings.json` | Connessione (server, database, utente, timeout) e limiti scelti in Impostazioni. **Niente password.** | no | Si torna ai default: reinserire la connessione |
| `secrets.json` | Solo la password del database, **cifrata** | sì | Reinserire la password in Impostazioni |
| `app.key` | Chiave casuale che cifra/decifra `secrets.json` (creata al primo salvataggio della password) | **sì** | La password salvata non è più leggibile: reinserirla |
| `cache/<codice>/` | Copia della **struttura** del database (tabelle, colonne, chiavi, relazioni candidate). Nessun dato delle tabelle. Una cartella per server+database | no | Si ricrea da sola (circa 1 minuto su SGA) |
| `logs/app-AAAA-MM-GG.log` | Errori, query lente, aggiornamenti metadata. Mai password. Tenuti 30 giorni | no | Nessun problema |
| `settings.example.json` | Esempio di `settings.json`, versionato | no | — |
| `.htaccess`, `.gitignore`, `.gitkeep` | Protezione e struttura cartelle | no | Non cancellare |

## Sicurezza della password

`app.key` e `secrets.json` insieme permettono di leggere la password. Per default stanno entrambi qui:
la cifratura protegge solo se qualcuno vede o riceve `secrets.json` da solo.

Più sicuro: spostare `app.key` **fuori dalla cartella del progetto** (es. `C:\MatriosgaChiave\app.key`)
e indicarlo in `config/app.php` → `security.key_file`. Istruzioni e controllo in
**Impostazioni → Ambiente server**. In ogni caso conviene un utente SQL con sola lettura (`db_datareader`).

## Spostare Matriosga su un'altra macchina

- Per portare la connessione: copiare `settings.json`, `secrets.json` **e** `app.key` (dove si trova).
- Oppure copiare solo `settings.json` e reinserire la password.
- `cache/` e `logs/` non servono: si ricreano.
