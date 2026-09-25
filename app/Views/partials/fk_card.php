<?php
/** Una FK dichiarata: $fk (dallo snapshot), $join (SQL JOIN da copiare). */
?>
<div class="fk-card">
    <div class="fk-head">
        <span class="badge badge-fk">FK</span>
        <span class="ident small"><?= e($fk['name']) ?></span><?= $this->partial('partials/copy', ['text' => $fk['name']]) ?>
        <?= $this->partial('partials/fk_rules', ['fk' => $fk]) ?>
        <button type="button" class="btn btn-sm btn-link ms-auto p-0" data-copy="<?= e($join) ?>" title="Copia la query JOIN"><i class="fa-regular fa-copy"></i> JOIN</button>
    </div>
    <div class="fk-flow">
        <?= $this->partial('partials/col_ref', ['table' => $fk['from'], 'cols' => $fk['from_cols']]) ?>
        <div class="fk-arrow"><i class="fa-solid fa-arrow-down"></i></div>
        <?= $this->partial('partials/col_ref', ['table' => $fk['to'], 'cols' => $fk['to_cols']]) ?>
    </div>
</div>
