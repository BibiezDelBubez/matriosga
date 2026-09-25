<?php
/** @var array $c connessione senza password, $limits, $limitDefaults, $saved */
$limitLabels = [
    'search_batch_tables'   => ['Ricerca valore: tabelle per lotto', 'Quante tabelle interrogare per ogni chiamata (risultati progressivi).'],
    'search_max_table_rows' => ['Ricerca valore: righe max per tabella', 'Tabelle più grandi vengono saltate (si possono forzare).'],
    'search_max_hits'       => ['Ricerca valore: max colonne trovate', 'La ricerca si ferma dopo questo numero di colonne con risultati.'],
    'preview_rows'          => ['Righe di anteprima', 'Righe mostrate aprendo un risultato o l\'anteprima di una tabella.'],
    'page_size'             => ['Righe per pagina', 'Risultati per pagina in Colonne e Relazioni (meno righe = pagine più veloci).'],
    'analysis_top_values'   => ['Analisi: valori più frequenti', 'Quanti valori distinti mostrare.'],
    'analysis_sample_rows'  => ['Analisi: campione righe', '0 = tutta la tabella; es. 1000000 = solo le prime N righe.'],
    'path_max_depth'        => ['Percorso: profondità massima', 'Numero massimo di salti fra tabelle.'],
    'path_max_results'      => ['Percorso: risultati massimi', 'Quanti percorsi alternativi mostrare.'],
    'graph_max_nodes'       => ['Grafo: nodi massimi', 'Limite di tabelle disegnate nel grafo.'],
];
$check = static fn (string $name, string $label, bool $on, string $help = '') => sprintf(
    '<div class="form-check form-switch mb-2"><input type="hidden" name="connection[%1$s]" value="0"><input class="form-check-input" type="checkbox" role="switch" id="c_%1$s" name="connection[%1$s]" value="1"%3$s><label class="form-check-label" for="c_%1$s">%2$s</label>%4$s</div>',
    e($name), e($label), $on ? ' checked' : '', $help !== '' ? '<div class="form-text mt-0">' . e($help) . '</div>' : ''
);
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-gear"></i> Impostazioni</h1>
        <p class="lead">Connessione al database SGA (sola lettura) e limiti di sicurezza/performance.</p>
    </div>
</div>

<?= $this->partial('settings/_nav', ['current' => 'connection']) ?>

<?php if ($saved): ?>
    <?= $this->partial('partials/alert', ['type' => 'success', 'message' => 'Impostazioni salvate.']) ?>
<?php endif; ?>

<form method="post" action="<?= e(url('/settings')) ?>" id="settings-form" autocomplete="off">
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><i class="fa-solid fa-database"></i> Connessione database</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="c_server">Server</label>
                            <input class="form-control ident" id="c_server" name="connection[server]" value="<?= e($c['server']) ?>" placeholder="SRVSQL01\ISTANZA oppure 192.168.1.10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="c_port">Porta</label>
                            <input class="form-control" id="c_port" name="connection[port]" value="<?= e($c['port']) ?>" placeholder="1433 (opzionale)">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="c_database">Database</label>
                            <input class="form-control ident" id="c_database" name="connection[database]" value="<?= e($c['database']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="c_schema">Schema predefinito</label>
                            <input class="form-control ident" id="c_schema" name="connection[schema]" value="<?= e($c['schema']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="c_auth">Autenticazione</label>
                            <select class="form-select" id="c_auth" name="connection[auth]">
                                <option value="sql"<?= $c['auth'] === 'sql' ? ' selected' : '' ?>>SQL Server</option>
                                <option value="windows"<?= $c['auth'] === 'windows' ? ' selected' : '' ?>>Windows</option>
                            </select>
                        </div>
                        <div class="col-md-4 auth-sql">
                            <label class="form-label" for="c_username">Utente</label>
                            <input class="form-control" id="c_username" name="connection[username]" value="<?= e($c['username']) ?>">
                        </div>
                        <div class="col-md-4 auth-sql">
                            <label class="form-label" for="c_password">Password</label>
                            <input class="form-control" type="password" id="c_password" name="password" value="" autocomplete="new-password"
                                   placeholder="<?= $c['has_password'] ? '•••••• (invariata)' : '' ?>">
                            <?php if ($c['has_password']): ?>
                                <div class="form-check mt-1 small">
                                    <input class="form-check-input" type="checkbox" id="clear_password" name="clear_password" value="1">
                                    <label class="form-check-label" for="clear_password">Cancella password salvata</label>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 auth-windows small text-secondary">
                            <i class="fa-solid fa-circle-info"></i> Con autenticazione Windows viene usato l'account con cui gira Apache
                            (se Apache è avviato come servizio è l'account di sistema: in quel caso usare un login SQL).
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="c_login_timeout">Timeout connessione (s)</label>
                            <input class="form-control" type="number" min="1" id="c_login_timeout" name="connection[login_timeout]" value="<?= e($c['login_timeout']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="c_query_timeout">Timeout query (s)</label>
                            <input class="form-control" type="number" min="1" id="c_query_timeout" name="connection[query_timeout]" value="<?= e($c['query_timeout']) ?>">
                        </div>
                        <div class="col-12">
                            <?= $check('encrypt', 'Connessione cifrata (Encrypt)', (bool) $c['encrypt']) ?>
                            <?= $check('trust_server_certificate', 'Accetta certificato del server (TrustServerCertificate)', (bool) $c['trust_server_certificate'], 'Necessario con ODBC Driver 18 se il server non ha un certificato valido.') ?>
                            <?= $check('read_uncommitted', 'Letture senza lock (READ UNCOMMITTED)', (bool) $c['read_uncommitted'], 'Consigliato: non blocca il gestionale mentre si esplorano i dati.') ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-primary" type="button" id="btn-test"><i class="fa-solid fa-plug"></i> Testa connessione</button>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
                </div>
            </div>
            <div id="test-result" class="mt-3"></div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><i class="fa-solid fa-sliders"></i> Limiti</div>
                <div class="card-body">
                    <?php foreach ($limitLabels as $key => [$label, $help]): ?>
                        <div class="mb-3">
                            <label class="form-label mb-1" for="l_<?= e($key) ?>"><?= e($label) ?></label>
                            <input class="form-control form-control-sm" type="number" min="0" id="l_<?= e($key) ?>" name="limits[<?= e($key) ?>]" value="<?= e($limits[$key]) ?>">
                            <div class="form-text"><?= e($help) ?> Default: <?= e(fmt_int($limitDefaults[$key])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</form>

