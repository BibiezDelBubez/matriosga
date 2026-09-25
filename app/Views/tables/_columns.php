<?php
/** Scheda colonne. @var App\Models\Table $table @var list<string> $selected */
$pkCols = $table->pkColumns();
?>
<form method="get" action="<?= e(url('/tables/show')) ?>">
    <input type="hidden" name="t" value="<?= e($table->fullName) ?>">
    <input type="hidden" name="tab" value="code">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center border-bottom">
        <div class="flex-grow-1" style="min-width: 240px">
            <?= $this->partial('partials/filter_box', ['target' => '#columns-list', 'placeholder' => 'Filtra colonne per nome, tipo, descrizione…']) ?>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary" title="Genera SELECT e Power Query solo con le colonne spuntate">
            <i class="fa-solid fa-wand-magic-sparkles"></i> SQL/Power Query con colonne selezionate
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e(implode(', ', array_map(static fn ($c) => $c->name, $table->columns()))) ?>">
            <i class="fa-regular fa-copy"></i> Copia elenco colonne
        </button>
    </div>
    <div class="table-responsive scroll-y">
        <table class="table table-sm table-hover table-sticky sortable mb-0" id="columns-list">
            <thead>
            <tr>
                <th><input class="form-check-input" type="checkbox" data-check-all="cols[]" title="Seleziona tutte"></th>
                <th data-sort="num">#</th>
                <th data-sort>Colonna</th>
                <th data-sort>Tipo</th>
                <th class="text-center">Null</th>
                <th>Chiavi</th>
                <th>Default</th>
                <th>Indici</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($table->columns() as $col): ?>
                <?php $pkPos = array_search($col->name, $pkCols, true); ?>
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="cols[]" value="<?= e($col->name) ?>"<?= in_array($col->name, $selected, true) ? ' checked' : '' ?>></td>
                    <td class="text-secondary"><?= e($col->id) ?></td>
                    <td class="text-nowrap" data-value="<?= e($col->name) ?>">
                        <span class="ident fw-semibold"><?= e($col->name) ?></span><?= $this->partial('partials/copy', ['text' => $col->name]) ?>
                        <?php if (!in_array($col->category(), ['binary', 'other'], true)): ?>
                            <a class="btn-copy" href="<?= e(url('/analysis', ['t' => $table->fullName, 'c' => $col->name])) ?>" title="Analizza valori"><i class="fa-solid fa-chart-column"></i></a>
                        <?php endif; ?>
                    </td>
                    <td data-value="<?= e($col->type) ?>">
                        <span class="badge badge-type"><?= e($col->typeLabel()) ?></span>
                        <?php if ($col->userType): ?><span class="small text-secondary ident"><?= e($col->userType) ?></span><?php endif; ?>
                    </td>
                    <td class="text-center"><?= $col->nullable ? '<i class="fa-solid fa-check text-secondary" title="Ammette NULL"></i>' : '<span class="small text-secondary">NOT NULL</span>' ?></td>
                    <td>
                        <?php if ($pkPos !== false): ?>
                            <span class="badge badge-pk"><i class="fa-solid fa-key"></i> PK<?= count($pkCols) > 1 ? ' ' . ($pkPos + 1) . '/' . count($pkCols) : '' ?></span>
                        <?php endif; ?>
                        <?php if ($col->identity): ?><span class="badge text-bg-light border">IDENTITY</span><?php endif; ?>
                        <?php if ($col->computed): ?><span class="badge text-bg-light border">calcolata</span><?php endif; ?>
                        <?php foreach ($col->fks() as $fk): ?>
                            <?php $target = $fk['to_cols'][array_search($col->name, $fk['from_cols'], true)]; ?>
                            <span class="badge badge-fk text-nowrap" title="<?= e($fk['name']) ?>">
                                FK → <a class="ident" href="<?= e(url('/tables/show', ['t' => $fk['to']])) ?>"><?= e($fk['to'] . '.' . $target) ?></a>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td class="small ident text-secondary"><?= e($col->default ?? '') ?></td>
                    <td>
                        <?php foreach ($col->indexNames() as $idx): ?>
                            <span class="badge badge-idx" title="<?= e($idx) ?>"><i class="fa-solid fa-bolt"></i></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="small text-secondary"><?= e($col->description ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="empty-state d-none" data-filter-empty="#columns-list"><i class="fa-solid fa-magnifying-glass"></i>Nessuna colonna corrisponde al filtro.</div>
    </div>
</form>
