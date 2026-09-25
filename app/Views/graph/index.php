<?php
/** @var string $table @var int $depth @var bool $cand @var bool $hubs @var string $path @var string $nodes (nodi extra, separati da virgola) */
?>
<div class="page-head mb-2">
    <div>
        <h1><i class="fa-solid fa-diagram-project"></i> Grafo relazioni</h1>
        <p class="lead">Parti da una tabella e guarda cosa le sta intorno. Frecce piene = FK dichiarate, tratteggiate = candidate.</p>
    </div>
</div>

<form class="card mb-2" id="graph-form" autocomplete="off" data-path="<?= e($path) ?>" data-nodes="<?= e($nodes) ?>">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control ident" style="max-width: 320px" name="t" id="g-table" value="<?= e($table) ?>" data-table-picker placeholder="Tabella di partenza" required>
        <select class="form-select w-auto" name="depth" id="g-depth" title="Livelli">
            <?php foreach ([1, 2, 3] as $d): ?><option value="<?= $d ?>"<?= $depth === $d ? ' selected' : '' ?>><?= $d ?> livell<?= $d === 1 ? 'o' : 'i' ?></option><?php endforeach; ?>
        </select>
        <div class="form-check form-switch small mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="g-cand" name="cand" value="1"<?= $cand ? ' checked' : '' ?>>
            <label class="form-check-label" for="g-cand">Candidate ≥80%</label>
        </div>
        <div class="form-check form-switch small mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="g-hubs" name="hubs" value="1"<?= $hubs ? ' checked' : '' ?>>
            <label class="form-check-label" for="g-hubs">Tabelle hub</label>
        </div>
        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-play"></i> Mostra</button>
        <span class="vr mx-1"></span>
        <input class="form-control form-control-sm ident" style="max-width: 200px" id="g-find" placeholder="Trova nel grafo…">
        <input class="form-control form-control-sm ident" style="max-width: 200px" id="g-path" placeholder="Evidenzia percorso fino a…">
        <select class="form-select form-select-sm w-auto" id="g-layout" title="Disposizione">
            <option value="cose">Automatica</option>
            <option value="breadthfirst">Ad albero</option>
            <option value="concentric">Concentrica</option>
        </select>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" type="button" id="g-relayout" title="Riorganizza"><i class="fa-solid fa-shuffle"></i></button>
            <button class="btn btn-outline-secondary" type="button" id="g-fit" title="Adatta allo schermo"><i class="fa-solid fa-expand"></i></button>
            <button class="btn btn-outline-secondary" type="button" id="g-unhide" title="Mostra i nodi nascosti"><i class="fa-regular fa-eye"></i></button>
            <button class="btn btn-outline-secondary" type="button" id="g-png" title="Salva immagine PNG"><i class="fa-solid fa-image"></i></button>
        </div>
    </div>
</form>

<div class="graph-wrap">
    <div id="cy" class="graph-canvas"></div>
    <aside class="graph-panel card" id="g-panel">
        <div class="card-body small" id="g-info">
            <div class="text-secondary"><i class="fa-solid fa-hand-pointer"></i> Clicca una tabella o una freccia per i dettagli.<br><br>
                Rotella = zoom · trascina lo sfondo = sposta · trascina un nodo = riposiziona.</div>
        </div>
    </aside>
    <div class="graph-status small" id="g-status"></div>
</div>
