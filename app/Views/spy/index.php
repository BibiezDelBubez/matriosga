<?php /** Spia modifiche: i tre stati (pronta / in corso / risultati) sono gestiti da js/spy.js. */ ?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-user-secret"></i> Spia modifiche</h1>
        <p class="lead">Dove salva il gestionale una certa operazione? Avvia la spia, fai l'operazione nel gestionale, ferma la spia: vedi quali tabelle sono cambiate.</p>
    </div>
</div>

<div class="row g-3 mb-3 small">
    <?php foreach ([
        ['1', 'fa-play', 'Premi «Inizia»', 'Matriosga annota lo stato attuale di tutte le tabelle.'],
        ['2', 'fa-keyboard', 'Fai l\'operazione nel gestionale', 'Es. registra una fattura, modifica un cliente. Meglio una sola operazione.'],
        ['3', 'fa-stop', 'Torna qui e premi «Fine»', 'Vedi le tabelle con righe nuove, modificate o tolte.'],
    ] as [$n, $icon, $title, $text]): ?>
        <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex gap-3">
            <div class="spy-step"><?= e($n) ?></div>
            <div><div class="fw-semibold"><i class="fa-solid <?= e($icon) ?> text-secondary"></i> <?= e($title) ?></div><div class="text-secondary"><?= e($text) ?></div></div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<!-- Stato: pronta -->
<div class="card d-none" id="spy-idle">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="spy-user">Il tuo utente nel gestionale <span class="text-secondary">(facoltativo)</span></label>
                <input class="form-control ident" id="spy-user" placeholder="es. ROSSI" autocomplete="off">
                <div class="form-text">Il database è condiviso: indicandolo, dove possibile vedi solo le modifiche fatte da te.</div>
            </div>
            <div class="col-lg-3 d-grid">
                <button class="btn btn-success btn-lg" type="button" id="spy-start"><i class="fa-solid fa-play"></i> Inizia</button>
            </div>
        </div>
    </div>
</div>

<!-- Stato: in corso -->
<div class="card border-success d-none" id="spy-running">
    <div class="card-body text-center py-4">
        <div class="spy-pulse mb-2"><i class="fa-solid fa-user-secret"></i></div>
        <h2 class="h5">Spia attiva da <span id="spy-elapsed">0:00</span></h2>
        <p class="text-secondary mb-3">Iniziata alle <span id="spy-since" class="ident"></span> (ora del server)<span id="spy-who"></span>.<br>
            <strong>Ora vai nel gestionale e fai l'operazione.</strong> Poi torna qui e premi «Fine».</p>
        <button class="btn btn-danger btn-lg" type="button" id="spy-stop"><i class="fa-solid fa-stop"></i> Fine</button>
        <button class="btn btn-link text-secondary" type="button" data-spy-reset>Annulla</button>
    </div>
</div>

<!-- Stato: risultati -->
<div class="d-none" id="spy-results">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <div id="spy-period" class="text-secondary small"></div>
        <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-spy-reset><i class="fa-solid fa-rotate-left"></i> Nuova spia</button>
    </div>
    <div class="alert alert-warning py-2 small"><i class="fa-solid fa-users"></i>
        Il database è condiviso: nell'intervallo possono comparire anche modifiche fatte da altri utenti o da processi automatici.
        Per un risultato pulito fai una sola operazione, in un momento tranquillo, e tieni la spia attiva il meno possibile.</div>

    <div class="card mb-3">
        <div class="card-header">Righe nuove o modificate <span class="text-secondary fw-normal small">(tabelle con data/ora di inserimento-modifica o rowversion)</span></div>
        <div class="card-body py-2 small" id="scan-status"></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 sortable" id="scan-table">
                <thead><tr><th data-sort>Tabella</th><th data-sort="num" class="text-end">Nuove</th><th data-sort="num" class="text-end">Modificate</th><th>Come</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Righe aggiunte o tolte <span class="text-secondary fw-normal small">(conteggio righe prima/dopo, vale per tutte le tabelle)</span></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 sortable" id="count-table">
                <thead><tr><th data-sort>Tabella</th><th data-sort="num" class="text-end">Prima</th><th data-sort="num" class="text-end">Dopo</th><th data-sort="num" class="text-end">Differenza</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="spy-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title ident" id="spy-modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="spy-modal-body"></div>
        </div>
    </div>
</div>
