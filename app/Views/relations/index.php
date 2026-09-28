<?php
/**
 * @var string $kind declared|candidates @var string $query @var ?string $from @var ?string $to @var bool $hubs
 * @var ?array $result @var array<int,string> $joins @var ?App\Helpers\Pager $pager @var int $total @var array<string,int> $hubList
 * @var array $noise Controller::noiseFilter()
 */
$candidates = $kind === 'candidates';
$keep = ['q' => $query, 'from' => $from, 'to' => $to, 'all' => $noise['all'] ? 1 : null];
$tabUrl = static fn (string $k) => url('/relations', ['kind' => $k] + $keep);
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-link"></i> Relazioni</h1>
        <p class="lead">Da quale colonna a quale tabella/colonna. Le FK <strong>dichiarate</strong> sono certe; le <strong>candidate</strong> sono dedotte e vanno verificate.</p>
    </div>
</div>

<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link<?= $candidates ? '' : ' active' ?>" href="<?= e($tabUrl('declared')) ?>">
        <span class="badge badge-fk">FK</span> Dichiarate <span class="badge text-bg-light border"><?= e(fmt_int($total)) ?></span></a></li>
    <li class="nav-item"><a class="nav-link<?= $candidates ? ' active' : '' ?>" href="<?= e($tabUrl('candidates')) ?>">
        <span class="badge badge-cand">?</span> Candidate</a></li>
</ul>
<div class="card border-top-0 rounded-top-0">
    <form class="card-body border-bottom" method="get" action="<?= e(url('/relations')) ?>">
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="q">Cerca</label>
                <input class="form-control ident" id="q" name="q" value="<?= e($query) ?>" placeholder="tabella, colonna o nome FK (es. doctes clifor)" autofocus>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="from">Da tabella (esatta)</label>
                <input class="form-control ident" id="from" name="from" value="<?= e($from ?? '') ?>" placeholder="la tabella che punta">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="to">A tabella (esatta)</label>
                <input class="form-control ident" id="to" name="to" value="<?= e($to ?? '') ?>" placeholder="la tabella puntata">
            </div>
            <div class="col-lg-1 d-grid">
                <button class="btn btn-primary" type="submit" title="Cerca"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-2">
            <?php if ($candidates): ?>
                <div class="form-check form-switch small mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="hubs" name="hubs" value="1"<?= $hubs ? ' checked' : '' ?> data-autosubmit>
                    <label class="form-check-label" for="hubs">Includi le candidate verso tabelle hub (società, filiali, lingue…)</label>
                </div>
            <?php endif; ?>
            <?= $this->partial('partials/show_all', ['noise' => $noise, 'submit' => true]) ?>
        </div>
    </form>

    <?php if ($candidates): ?>
        <div class="card-body py-2 small text-secondary border-bottom">
            <i class="fa-solid fa-circle-info"></i> Una candidata nasce quando una tabella ha le <strong>stesse colonne</strong> che in altre tabelle sono FK dichiarate verso una certa tabella
            (la % indica quanto è coerente questa regola). Non è una FK: usa <strong>Verifica</strong> per controllare i dati su un campione.
        </div>
    <?php endif; ?>

    <?php if (!$candidates && $total === 0): ?>
        <div class="empty-state"><i class="fa-solid fa-link-slash"></i>Il database non ha foreign key dichiarate: usa la scheda <strong>Candidate</strong>.</div>
    <?php elseif ($result === null): ?>
        <div class="card-body">
            <h2 class="section-title"><i class="fa-solid fa-circle-nodes"></i> Tabelle più referenziate</h2>
            <p class="small text-secondary">Tabelle "centrali" (società, filiali, anagrafiche…): moltissime altre tabelle puntano a loro. Clicca per vedere chi le usa.</p>
            <div class="hub-grid">
                <?php foreach ($hubList as $name => $count): ?>
                    <a class="hub-item" href="<?= e(url('/relations', ['to' => $name])) ?>">
                        <span class="ident"><?= e($name) ?></span><span class="badge text-bg-light border"><?= e(fmt_int($count)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($result['total'] === 0): ?>
        <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i>Nessuna relazione trovata.</div>
    <?php else: ?>
        <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center border-bottom">
            <div><strong><?= e(fmt_int($result['total'])) ?></strong> relazioni <?= $candidates ? 'candidate' : 'dichiarate' ?></div>
            <div class="flex-grow-1" style="min-width: 240px">
                <?= $this->partial('partials/filter_box', ['target' => '#rel-list', 'placeholder' => 'Filtra in questa pagina…']) ?>
            </div>
        </div>
        <div class="table-responsive scroll-y">
            <table class="table table-sm table-hover table-sticky sortable mb-0" id="rel-list">
                <thead>
                <tr>
                    <th data-sort>Da (tabella.colonna)</th>
                    <th></th>
                    <th data-sort>A (tabella.colonna)</th>
                    <?php if ($candidates): ?>
                        <th data-sort="num">Affidabilità</th><th>Perché</th>
                    <?php else: ?>
                        <th data-sort>Nome FK</th><th>Regole</th>
                    <?php endif; ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $i => $rel): ?>
                    <?= $this->partial('partials/relation_row', ['rel' => $rel, 'join' => $joins[$i]]) ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="empty-state d-none" data-filter-empty="#rel-list"><i class="fa-solid fa-filter"></i>Nessuna relazione corrisponde al filtro.</div>
        </div>
        <?= $this->partial('partials/pager', ['pager' => $pager, 'path' => '/relations',
            'query' => ['kind' => $kind, 'hubs' => $hubs ? 1 : null] + $keep]) ?>
    <?php endif; ?>
</div>
