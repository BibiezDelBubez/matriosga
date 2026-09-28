<?php
/**
 * Scheda chiavi: PK, FK in uscita, FK in entrata (solo le prime: $fksIn).
 * @var App\Models\Table $table @var array $joinsOut @var array $joinsIn @var list<array> $fksIn @var int $fksInTotal
 * @var array $candOut @var array $candIn candidate {rows, total, joins} @var array $noise Controller::noiseFilter()
 */
$pk = $table->pk();
$fksOut = $table->fksOut();
?>
<div class="card-body">
    <form method="get" action="<?= e(url('/tables/show')) ?>" class="d-flex justify-content-end mb-2">
        <input type="hidden" name="t" value="<?= e($table->fullName) ?>">
        <input type="hidden" name="tab" value="keys">
        <?= $this->partial('partials/show_all', ['noise' => $noise, 'id' => 'keys-all', 'submit' => true]) ?>
    </form>
    <h2 class="section-title"><i class="fa-solid fa-key"></i> Chiave primaria</h2>
    <?php if ($pk): ?>
        <div class="pk-box mb-4">
            <div class="mb-2">
                <span class="badge badge-pk">PRIMARY KEY</span>
                <span class="ident"><?= e($pk['name']) ?></span><?= $this->partial('partials/copy', ['text' => $pk['name']]) ?>
                <span class="small text-secondary ms-2"><?= e(str_replace('_', ' ', strtolower($pk['type']))) ?><?= count($pk['columns']) > 1 ? ' · chiave composta da ' . count($pk['columns']) . ' colonne' : '' ?></span>
            </div>
            <ol class="pk-cols mb-0">
                <?php foreach ($pk['columns'] as $name): ?>
                    <?php $col = $table->column($name); ?>
                    <li><span class="ident fw-semibold"><?= e($name) ?></span><?= $this->partial('partials/copy', ['text' => $name]) ?>
                        <?php if ($col): ?><span class="badge badge-type ms-1"><?= e($col->typeLabel()) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if (count($pk['columns']) > 1): ?>
                <button type="button" class="btn btn-sm btn-link px-0 mt-2" data-copy="<?= e(implode(', ', $pk['columns'])) ?>"><i class="fa-regular fa-copy"></i> Copia colonne della PK</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-4"><i class="fa-solid fa-circle-info"></i>
            <?= $table->isView ? 'Le viste non hanno chiave primaria.' : 'Nessuna chiave primaria dichiarata (tabella heap). Controlla gli indici univoci nella scheda Indici.' ?>
        </p>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-6">
            <h2 class="section-title"><i class="fa-solid fa-arrow-right-from-bracket"></i> Questa tabella punta a <span class="badge text-bg-light border"><?= count($fksOut) ?></span></h2>
            <?php foreach ($fksOut as $i => $fk): ?>
                <?= $this->partial('partials/fk_card', ['fk' => $fk, 'join' => $joinsOut[$i]]) ?>
            <?php endforeach; ?>
            <?php if (!$fksOut): ?><p class="text-secondary small">Nessuna foreign key dichiarata in uscita.</p><?php endif; ?>
        </div>
        <div class="col-xl-6">
            <h2 class="section-title"><i class="fa-solid fa-arrow-right-to-bracket"></i> Tabelle che puntano a questa <span class="badge text-bg-light border"><?= e(fmt_int($fksInTotal)) ?></span></h2>
            <?php if ($fksInTotal > count($fksIn)): ?>
                <div class="alert alert-info py-2 small">
                    <i class="fa-solid fa-circle-nodes"></i> Tabella molto referenziata: qui le prime <?= count($fksIn) ?>.
                    <a href="<?= e(url('/relations', ['to' => $table->fullName])) ?>" class="alert-link">Cerca fra tutte le <?= e(fmt_int($fksInTotal)) ?> in Relazioni</a>
                </div>
            <?php endif; ?>
            <?php foreach ($fksIn as $i => $fk): ?>
                <?= $this->partial('partials/fk_card', ['fk' => $fk, 'join' => $joinsIn[$i]]) ?>
            <?php endforeach; ?>
            <?php if (!$fksIn): ?><p class="text-secondary small">Nessuna foreign key dichiarata in entrata.</p><?php endif; ?>
        </div>
    </div>

    <h2 class="section-title mt-4"><span class="badge badge-cand">?</span> Relazioni candidate
        <span class="small fw-normal text-secondary">— dedotte dai nomi delle colonne, <strong>non sono FK</strong>: verificale sui dati</span></h2>
    <?php foreach (['from' => ['Questa tabella potrebbe puntare a', $candOut], 'to' => ['Potrebbero puntare a questa', $candIn]] as $side => [$label, $set]): ?>
        <h3 class="h6 mt-3"><?= e($label) ?> <span class="badge text-bg-light border"><?= e(fmt_int($set['total'])) ?></span>
            <?php if ($set['total'] > count($set['rows'])): ?>
                <a class="small fw-normal ms-2" href="<?= e(url('/relations', ['kind' => 'candidates', 'hubs' => 1, $side => $table->fullName])) ?>">vedi tutte</a>
            <?php endif; ?>
        </h3>
        <?php if ($set['rows']): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                    <?php foreach ($set['rows'] as $i => $rel): ?>
                        <?= $this->partial('partials/relation_row', ['rel' => $rel, 'join' => $set['joins'][$i]]) ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="small text-secondary">Nessuna.</p>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
