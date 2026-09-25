<?php /** @var string $table @var string $column */ ?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-chart-column"></i> Analisi colonna</h1>
        <p class="lead">Quanti valori, quanti NULL, quali sono i più frequenti e come sono distribuiti.</p>
    </div>
</div>

<form class="card mb-3" id="an-form" autocomplete="off">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="an-table">Tabella</label>
                <input class="form-control ident" id="an-table" value="<?= e($table) ?>" data-table-picker required placeholder="es. BaCliFor" autofocus>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="an-column">Colonna</label>
                <select class="form-select ident" id="an-column" data-initial="<?= e($column) ?>" required disabled>
                    <option value="">— scegli prima la tabella —</option>
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="an-full">
                    <label class="form-check-label small" for="an-full" title="Sulle tabelle molto grandi l'analisi usa un campione di 1.000.000 di righe">Tutta la tabella <span class="text-secondary">(lento)</span></label>
                </div>
            </div>
            <div class="col-lg-1 d-grid">
                <button class="btn btn-primary" type="submit" id="an-go" title="Analizza"><i class="fa-solid fa-chart-column"></i></button>
            </div>
        </div>
    </div>
</form>

<div id="an-result">
    <div class="empty-state"><i class="fa-solid fa-chart-column"></i>Scegli tabella e colonna. Puoi arrivare qui anche dall'icona <i class="fa-solid fa-chart-column d-inline fs-6"></i> nell'elenco colonne di una tabella.</div>
</div>
