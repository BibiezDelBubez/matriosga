<?php
/**
 * @var App\Models\Table $table @var list<string> $selected @var array $joinsOut @var array $joinsIn @var list<array> $fksIn
 * @var int $fksInTotal @var array $code @var string $tab @var array $noise @var ?string $copyReason
 */
$pk = $table->pk();
$tabs = [
    'columns' => ['Colonne', 'fa-table-columns', count($table->columns())],
    'keys'    => ['Chiavi e relazioni', 'fa-key', fmt_int(count($table->fksOut()) + $fksInTotal + ($pk ? 1 : 0))],
    'indexes' => ['Indici', 'fa-bolt', count($table->indexes())],
    'data'    => ['Anteprima dati', 'fa-eye', null],
    'code'    => ['SQL / Power Query', 'fa-code', null],
];
?>
<div class="page-head">
    <div>
        <h1 class="d-flex align-items-center gap-2 flex-wrap">
            <i class="fa-solid <?= $table->isView ? 'fa-eye' : 'fa-table' ?>"></i>
            <span class="ident"><?= e($table->fullName) ?></span>
            <?= $this->partial('partials/copy', ['text' => $table->name, 'title' => 'Copia nome']) ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($table->quoted()) ?>" title="Copia nome SQL">
                <i class="fa-regular fa-copy"></i> <?= e($table->quoted()) ?>
            </button>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/graph', ['t' => $table->fullName])) ?>"><i class="fa-solid fa-diagram-project"></i> Grafo</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/queries', ['q' => $table->name])) ?>" title="Viste, funzioni e trigger di SGA che usano questa tabella"><i class="fa-solid fa-scroll"></i> Come la usa SGA</a>
        </h1>
        <div class="d-flex flex-wrap gap-2 mt-2 small">
            <span class="badge <?= $table->isView ? 'text-bg-info' : 'text-bg-primary' ?>"><?= $table->isView ? 'Vista' : 'Tabella' ?></span>
            <?php if ($copyReason): ?><span class="badge text-bg-warning" title="<?= e($copyReason) ?>">probabile copia di sicurezza</span><?php endif; ?>
            <?php if ($table->rows === 0): ?><span class="badge text-bg-secondary">vuota</span><?php endif; ?>
            <span class="badge badge-type">schema <?= e($table->schema) ?></span>
            <?php if ($table->rows !== null): ?><span class="badge badge-type" title="Stima da sys.partitions">≈ <?= e(fmt_int($table->rows)) ?> righe</span><?php endif; ?>
            <span class="badge badge-type"><?= e(count($table->columns())) ?> colonne</span>
            <?php if ($pk): ?><span class="badge badge-pk"><i class="fa-solid fa-key"></i> PK <?= e(implode(', ', $pk['columns'])) ?></span><?php endif; ?>
            <?php if ($table->modified): ?><span class="text-secondary">modificata il <?= e($table->modified) ?></span><?php endif; ?>
        </div>
        <?php if ($table->description): ?><p class="lead mt-2"><?= e($table->description) ?></p><?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <?php foreach ($tabs as $key => [$label, $icon, $count]): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?= $tab === $key ? ' active' : '' ?>" id="tab-<?= e($key) ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= e($key) ?>" type="button" role="tab">
                <i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?>
                <?php if ($count !== null): ?><span class="badge text-bg-light border ms-1"><?= e($count) ?></span><?php endif; ?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    <?php foreach (array_keys($tabs) as $key): ?>
        <div class="tab-pane fade<?= $tab === $key ? ' show active' : '' ?>" id="pane-<?= e($key) ?>" role="tabpanel">
            <?= $this->partial('tables/_' . $key, compact('table', 'selected', 'joinsOut', 'joinsIn', 'fksIn', 'fksInTotal', 'code', 'candOut', 'candIn', 'noise')) ?>
        </div>
    <?php endforeach; ?>
</div>
