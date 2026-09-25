<?php /** Scheda anteprima: caricata via JS (js/table.js) solo quando viene aperta. @var App\Models\Table $table */ ?>
<div class="card-body">
    <div id="preview" data-table="<?= e($table->fullName) ?>">
        <div class="text-secondary small"><span class="spinner-border spinner-border-sm"></span> Caricamento prime <?= e(setting('limits.preview_rows')) ?> righe…</div>
    </div>
</div>
