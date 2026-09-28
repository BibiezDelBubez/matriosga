<?php
/** @var bool $configured @var ?string $error @var ?array $stats @var ?array $server */
$tools = array_filter(setting('menu', []), static fn (array $m) => $m['home']);
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-gauge-high"></i> Dashboard</h1>
        <p class="lead">Esplora struttura e dati del database SGA: tabelle, chiavi, relazioni, valori.</p>
    </div>
</div>

<?php if ($env['error'] + $env['warn'] > 0): ?>
    <?= $this->partial('partials/alert', [
        'type'    => $env['error'] ? 'danger' : 'warning',
        'message' => 'Ambiente server: ' . ($env['error'] ? '<strong>' . $env['error'] . ' problemi</strong> ' : '')
            . ($env['warn'] ? $env['warn'] . ' miglioramenti consigliati ' : '')
            . '— <a class="alert-link" href="' . e(url('/settings/environment')) . '">vedi cosa fare</a>',
    ]) ?>
<?php endif; ?>

<?php if (!$configured): ?>
    <?= $this->partial('partials/alert', ['type' => 'warning', 'message' => 'Nessuna connessione configurata. <a href="' . e(url('/settings')) . '" class="alert-link">Apri Impostazioni</a> per collegarti al database SGA.']) ?>
<?php elseif ($error !== null): ?>
    <?= $this->partial('partials/alert', ['type' => 'danger', 'message' => '<strong>Impossibile leggere il database.</strong><br>' . e($error) . '<br><a href="' . e(url('/settings')) . '" class="alert-link">Controlla le impostazioni</a>']) ?>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-4 align-items-center">
            <div><div class="stat-label text-secondary small">Server</div><div class="ident fw-semibold"><?= e($server['server_name']) ?> <?= $this->partial('partials/copy', ['text' => $server['server_name']]) ?></div></div>
            <div><div class="stat-label text-secondary small">Database</div><div class="ident fw-semibold"><?= e($server['database_name']) ?> <?= $this->partial('partials/copy', ['text' => $server['database_name']]) ?></div></div>
            <div><div class="stat-label text-secondary small">Versione</div><div><?= e($server['version']) ?></div></div>
            <div><div class="stat-label text-secondary small">Collation</div><div class="ident"><?= e($server['collation']) ?></div></div>
            <div class="ms-auto text-secondary small"><i class="fa-regular fa-clock"></i> Metadata del <?= e($cachedAt) ?></div>
        </div>
    </div>

    <?php // Numero grande = ciò che serve davvero (tabelle con dati, non copie); il totale del database resta sotto, in piccolo. ?>
    <div class="row g-3 mb-2">
        <?php foreach ([
            ['Tabelle con dati', 'tables', 'fa-table', url('/tables')],
            ['Viste', 'views', 'fa-eye', url('/tables')],
            ['Colonne', 'columns', 'fa-table-columns', null],
            ['Chiavi primarie', 'pks', 'fa-key', null],
            ['Foreign key', 'fks', 'fa-link', url('/relations')],
        ] as [$label, $key, $icon, $link]): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <<?= $link ? 'a href="' . e($link) . '"' : 'div' ?> class="card stat-card h-100 text-decoration-none text-reset">
                    <div class="d-flex justify-content-between">
                        <div class="stat-label"><?= e($label) ?></div>
                        <i class="fa-solid <?= e($icon) ?> stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= e(fmt_int($useful[$key]['useful'])) ?></div>
                    <?php if ($useful[$key]['useful'] !== $useful[$key]['total']): ?>
                        <div class="small text-secondary">su <?= e(fmt_int($useful[$key]['total'])) ?> nel database</div>
                    <?php endif; ?>
                </<?= $link ? 'a' : 'div' ?>>
            </div>
        <?php endforeach; ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100 stat-muted">
                <div class="d-flex justify-content-between">
                    <div class="stat-label">Tabelle nascoste</div>
                    <i class="fa-regular fa-eye-slash stat-icon"></i>
                </div>
                <div class="stat-value"><?= e(fmt_int($noise['hidden'])) ?></div>
                <div class="small text-secondary"><?= e(fmt_int($noise['empty'])) ?> vuote, <?= e(fmt_int($noise['copies'])) ?> copie (molte copie sono anche vuote)</div>
            </div>
        </div>
    </div>
    <p class="small text-secondary mb-4"><i class="fa-solid fa-circle-info"></i>
        I numeri grandi contano solo le tabelle <strong>con dati</strong>: le tabelle vuote e le copie di sicurezza (riconosciute dal nome, es. <span class="ident">Save_…</span>, <span class="ident">XXBeforeRepair_…</span>)
        sono nascoste in tutte le pagine. Per vederle usa l'interruttore «Mostra anche tabelle vuote e copie» o cambia il default in <a href="<?= e(url('/settings')) ?>">Impostazioni</a>.</p>

    <?php if ($stats['fks'] === 0): ?>
        <?= $this->partial('partials/alert', ['type' => 'info', 'message' => 'Il database non ha foreign key dichiarate: per collegare le tabelle usa le <strong>relazioni candidate</strong> (Relazioni / Percorso).']) ?>
    <?php endif; ?>

    <div class="mb-4 small">
        <span class="text-secondary me-2">Schemi:</span>
        <?php foreach ($stats['schemas'] as $schema => $count): ?>
            <span class="badge badge-type me-1"><?= e($schema) ?> · <?= e(fmt_int($count)) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($tools as $tool): ?>
        <div class="col-sm-6 col-xl-3">
            <?= $this->partial('partials/tool_card', ['tool' => $tool]) ?>
        </div>
    <?php endforeach; ?>
</div>
