<?php
/**
 * @var string $query @var string $mode @var string $category @var bool $views @var ?array $result @var ?App\Helpers\Pager $pager
 * @var array<string,string> $modes @var array<string,string> $categories @var int $maxMatches
 */
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-table-columns"></i> Ricerca colonne</h1>
        <p class="lead">In quali tabelle esiste una colonna? Cerca per nome (maiuscole/minuscole indifferenti).</p>
    </div>
</div>

<form class="card mb-3" method="get" action="<?= e(url('/columns')) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="q">Nome colonna</label>
                <input class="form-control form-control-lg ident" id="q" name="q" value="<?= e($query) ?>" placeholder="es. NOME, ID_CLIENTE, COD_ART" autofocus required>
            </div>
            <div class="col-lg-4">
                <?= $this->partial('partials/mode_buttons', ['modes' => $modes, 'mode' => $mode]) ?>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="cat">Tipo dati</label>
                <select class="form-select" id="cat" name="cat">
                    <option value="">Tutti</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $category === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 d-grid">
                <button class="btn btn-primary btn-lg" type="submit" title="Cerca"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="views" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="views" name="views" value="1"<?= $views ? ' checked' : '' ?>>
            <label class="form-check-label small" for="views">Includi le viste</label>
        </div>
    </div>
</form>

<?php if ($result === null): ?>
    <div class="empty-state"><i class="fa-solid fa-table-columns"></i>Scrivi il nome (o parte del nome) di una colonna.</div>
<?php elseif ($result['total'] === 0): ?>
    <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i>Nessuna colonna trovata per «<?= e($query) ?>».
        <?php if ($mode !== 'contains'): ?><br><a href="<?= e(url('/columns', ['q' => $query, 'mode' => 'contains'])) ?>">Riprova con «contiene»</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div><strong><?= e(fmt_int($result['total'])) ?></strong> colonne in <strong><?= e(fmt_int($result['tables'])) ?></strong> tabelle/viste
                <?php if ($result['truncated']): ?><span class="badge text-bg-warning ms-1">oltre <?= e(fmt_int($maxMatches)) ?>: restringi la ricerca</span><?php endif; ?>
            </div>
            <div class="flex-grow-1" style="min-width: 240px">
                <?= $this->partial('partials/filter_box', ['target' => '#column-results', 'placeholder' => 'Filtra in questa pagina (tabella, tipo…)']) ?>
            </div>
        </div>
        <div class="table-responsive scroll-y">
            <table class="table table-sm table-hover table-sticky sortable mb-0" id="column-results">
                <thead>
                <tr>
                    <th data-sort>Tabella</th>
                    <th data-sort>Colonna</th>
                    <th data-sort>Tipo</th>
                    <th class="text-center">Null</th>
                    <th>Chiavi</th>
                    <th data-sort="num" class="text-end">Righe tabella</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $r): ?>
                    <tr>
                        <td data-value="<?= e($r['table']) ?>"><?= $this->partial('partials/table_link', ['full' => $r['table'], 'view' => $r['view']]) ?></td>
                        <td class="text-nowrap" data-value="<?= e($r['column']) ?>"><span class="ident fw-semibold"><?= e($r['column']) ?></span><?= $this->partial('partials/copy', ['text' => $r['column']]) ?></td>
                        <td data-value="<?= e($r['type']) ?>"><span class="badge badge-type"><?= e($r['type']) ?></span></td>
                        <td class="text-center"><?= $r['nullable'] ? '<i class="fa-solid fa-check text-secondary"></i>' : '' ?></td>
                        <td>
                            <?php if ($r['pk']): ?><span class="badge badge-pk"><i class="fa-solid fa-key"></i> PK</span><?php endif; ?>
                            <?php foreach ($r['fk'] as $target): ?><span class="badge badge-fk ident">FK → <?= e($target) ?></span><?php endforeach; ?>
                        </td>
                        <td class="text-end" data-value="<?= e($r['rows'] ?? -1) ?>"><?= e(fmt_int($r['rows'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="empty-state d-none" data-filter-empty="#column-results"><i class="fa-solid fa-filter"></i>Nessun risultato corrisponde al filtro.</div>
        </div>
        <?= $this->partial('partials/pager', ['pager' => $pager, 'path' => '/columns',
            'query' => ['q' => $query, 'mode' => $mode, 'cat' => $category, 'views' => $views ? null : 0]]) ?>
    </div>
<?php endif; ?>
