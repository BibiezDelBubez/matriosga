<?php
/** Filtro istantaneo sulle righe di una tabella HTML. $target = selettore tabella (#id), $placeholder, opzionale $value. */
?>
<div class="filter-box">
    <i class="fa-solid fa-filter"></i>
    <input type="search" class="form-control" data-filter-table="<?= e($target) ?>" value="<?= e($value ?? '') ?>"
           placeholder="<?= e($placeholder ?? 'Filtra…') ?>" autocomplete="off">
    <span class="filter-count" data-filter-count="<?= e($target) ?>"></span>
</div>
