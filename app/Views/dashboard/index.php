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

    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Tabelle', $stats['tables'], 'fa-table'],
            ['Viste', $stats['views'], 'fa-eye'],
            ['Colonne', $stats['columns'], 'fa-table-columns'],
            ['Chiavi primarie', $stats['pks'], 'fa-key'],
            ['Foreign key', $stats['fks'], 'fa-link'],
            ['Indici', $stats['indexes'], 'fa-bolt'],
        ] as [$label, $value, $icon]): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card stat-card h-100">
                    <div class="d-flex justify-content-between">
                        <div class="stat-label"><?= e($label) ?></div>
                        <i class="fa-solid <?= e($icon) ?> stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= e(fmt_int($value)) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

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
