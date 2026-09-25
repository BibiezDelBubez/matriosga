<?php
/** @var string $value @var string $mode @var int $maxRows @var array<string,string> $modes */
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-magnifying-glass"></i> Cerca valore</h1>
        <p class="lead">In quali tabelle e colonne compare un valore? Cerca solo nelle colonne compatibili, tabella per tabella.</p>
    </div>
</div>

<form class="card mb-3" id="search-form" autocomplete="off"
      data-batch="<?= e(setting('limits.search_batch_tables')) ?>" data-max-hits="<?= e(setting('limits.search_max_hits')) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-7">
                <label class="form-label" for="value">Valore</label>
                <input class="form-control form-control-lg ident" id="value" name="value" value="<?= e($value) ?>" required autofocus
                       placeholder="es. un nome file, una partita IVA, un codice documento…">
            </div>
            <div class="col-lg-4">
                <?= $this->partial('partials/mode_buttons', ['modes' => $modes, 'mode' => $mode]) ?>
            </div>
            <div class="col-lg-1 d-grid">
                <button class="btn btn-primary btn-lg" type="submit" id="btn-search" title="Cerca"><i class="fa-solid fa-magnifying-glass"></i></button>
                <button class="btn btn-danger btn-lg d-none" type="button" id="btn-stop" title="Ferma"><i class="fa-solid fa-stop"></i></button>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-2 small">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="numbers" name="numbers" value="1">
                <label class="form-check-label" for="numbers">Anche colonne numeriche <span class="text-secondary">(solo «uguale a»)</span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="views" name="views" value="1">
                <label class="form-check-label" for="views">Anche nelle viste</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="force" name="force" value="1">
                <label class="form-check-label" for="force">Anche tabelle oltre <?= e(fmt_int($maxRows)) ?> righe <span class="text-secondary">(lento)</span></label>
            </div>
        </div>
        <div class="small text-secondary mt-2"><i class="fa-solid fa-circle-info"></i>
            Maiuscole/minuscole indifferenti. «Uguale a» è la modalità più veloce (può usare gli indici); «contiene» legge tutte le righe.</div>
    </div>
</form>

<div id="search-status" class="d-none mb-3">
    <div class="d-flex justify-content-between small mb-1">
        <span id="status-text"></span><span id="status-time" class="text-secondary"></span>
    </div>
    <div class="progress" style="height: 6px"><div class="progress-bar" id="status-bar" style="width: 0%"></div></div>
    <div id="status-notes" class="mt-2"></div>
</div>

<div class="card d-none" id="results-card">
    <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center border-bottom">
        <div id="results-summary" class="fw-semibold"></div>
        <div class="flex-grow-1" style="min-width: 220px">
            <?= $this->partial('partials/filter_box', ['target' => '#results', 'placeholder' => 'Filtra nei risultati…']) ?>
        </div>
    </div>
    <div class="table-responsive scroll-y">
        <table class="table table-sm table-hover table-sticky sortable mb-0" id="results">
            <thead>
            <tr>
                <th data-sort>Tabella</th>
                <th data-sort>Colonna</th>
                <th data-sort>Tipo</th>
                <th data-sort="num" class="text-end">Occorrenze</th>
                <th data-sort="num" class="text-end">Righe tabella</th>
                <th></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<div id="results-empty" class="empty-state d-none"><i class="fa-solid fa-magnifying-glass"></i>Nessuna occorrenza trovata.</div>

<div class="modal fade" id="rows-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title ident" id="rows-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rows-body"></div>
        </div>
    </div>
</div>
