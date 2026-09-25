<?php
/** @var list<list<mixed>> $tables (Catalog::summaries, formato compatto) @var list<string> $schemas @var string $query @var array $stats */
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-table-list"></i> Tabelle e viste</h1>
        <p class="lead"><?= e(fmt_int($stats['tables'])) ?> tabelle e <?= e(fmt_int($stats['views'])) ?> viste. Scrivi parte del nome per filtrare, clicca per vedere colonne, chiavi e dati.</p>
    </div>
</div>

<div class="card">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <div class="filter-box flex-grow-1" style="min-width: 260px">
            <i class="fa-solid fa-filter"></i>
            <input type="search" class="form-control" id="tables-filter" value="<?= e($query) ?>" autocomplete="off" autofocus
                   placeholder="Filtra per nome o descrizione… (più parole = tutte devono comparire, es. «doc righe»)">
            <span class="filter-count" id="tables-count"></span>
        </div>
        <select class="form-select w-auto" id="tables-type" aria-label="Tipo">
            <option value="">Tabelle e viste</option>
            <option value="U">Solo tabelle</option>
            <option value="V">Solo viste</option>
        </select>
        <?php if (count($schemas) > 1): ?>
            <select class="form-select w-auto" id="tables-schema" aria-label="Schema">
                <option value="">Tutti gli schemi</option>
                <?php foreach ($schemas as $schema): ?>
                    <option value="<?= e($schema) ?>"><?= e($schema) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <div class="form-check form-switch ms-1">
            <input class="form-check-input" type="checkbox" role="switch" id="tables-nonempty">
            <label class="form-check-label small" for="tables-nonempty">Solo con righe</label>
        </div>
    </div>
    <div class="table-responsive scroll-y">
        <table class="table table-sm table-hover table-sticky mb-0" id="tables-list">
            <thead>
            <tr>
                <th data-key="0" class="sortable-th">Nome</th>
                <th data-key="2" class="sortable-th text-end" title="Righe (stima da sys.partitions)">Righe</th>
                <th data-key="3" class="sortable-th text-end">Colonne</th>
                <th>Chiave primaria</th>
                <th data-key="5" class="sortable-th text-end" title="FK in uscita: questa tabella punta ad altre">FK →</th>
                <th data-key="6" class="sortable-th text-end" title="FK in entrata: altre tabelle puntano a questa">→ FK</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div class="empty-state d-none" id="tables-empty"><i class="fa-solid fa-magnifying-glass"></i>Nessuna tabella corrisponde al filtro.</div>
        <div class="text-center small text-secondary py-2 d-none" id="tables-more"></div>
    </div>
</div>

<script type="application/json" id="tables-data"><?= json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
