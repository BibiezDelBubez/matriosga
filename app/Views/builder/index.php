<?php
/** @var array<string,string> $operators QueryBuilderService::OPERATORS @var array $noise Controller::noiseFilter() */
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-screwdriver-wrench"></i> Costruttore di query</h1>
        <p class="lead">Aggiungi le tabelle che ti servono: Matriosga le collega da sola. Spunta le colonne e copia SQL o Power Query.</p>
    </div>
    <button class="btn btn-outline-secondary" type="button" id="b-reset"><i class="fa-solid fa-eraser"></i> Ricomincia</button>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><span class="step-num">1</span> Tabelle</div>
            <div class="card-body">
                <div class="input-group">
                    <input class="form-control ident" id="b-table" data-table-picker placeholder="Scrivi il nome di una tabella…" autocomplete="off">
                    <button class="btn btn-primary" type="button" id="b-add"><i class="fa-solid fa-plus"></i> Aggiungi</button>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-2 small">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="b-cand">
                        <label class="form-check-label" for="b-cand">Usa anche relazioni <span class="badge badge-cand">candidate</span></label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="b-hubs">
                        <label class="form-check-label" for="b-hubs">Passa da tabelle hub</label>
                    </div>
                    <?= $this->partial('partials/show_all', ['noise' => $noise, 'id' => 'b-all']) ?>
                </div>
                <div class="form-text">La prima tabella è quella principale. Le altre vengono collegate col percorso più corto;
                    se servono tabelle in mezzo vengono aggiunte da sole.</div>
            </div>
            <div id="b-tables"></div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><span class="step-num">2</span> Filtri <span class="text-secondary fw-normal small ms-1">(facoltativi)</span>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" id="b-add-filter"><i class="fa-solid fa-plus"></i> Filtro</button></div>
            <div class="card-body" id="b-filters"><div class="text-secondary small">Nessun filtro: vengono tutte le righe.</div></div>
        </div>

        <div class="card">
            <div class="card-header"><span class="step-num">3</span> Risultato</div>
            <div class="card-body">
                <ul class="nav nav-pills nav-sm mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#b-pane-pq" type="button">Power Query</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#b-pane-sql" type="button">SQL</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#b-pane-preview" type="button" id="b-tab-preview">Anteprima dati</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="b-pane-pq"><div class="b-code" data-kind="pq"></div></div>
                    <div class="tab-pane fade" id="b-pane-sql"><div class="b-code" data-kind="sql"></div></div>
                    <div class="tab-pane fade" id="b-pane-preview">
                        <button class="btn btn-sm btn-outline-primary mb-2" type="button" id="b-run"><i class="fa-solid fa-play"></i> Mostra le prime 50 righe</button>
                        <div id="b-preview"></div>
                    </div>
                </div>
                <div id="b-empty" class="empty-state py-3"><i class="fa-solid fa-screwdriver-wrench"></i>Aggiungi una tabella e spunta almeno una colonna.</div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="b-operators"><?= json_encode($operators, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
