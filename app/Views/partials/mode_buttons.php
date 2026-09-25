<?php /** Scelta modalità di confronto testo. $modes (chiave => etichetta), $mode (selezionata). */ ?>
<span class="form-label d-block">Modalità</span>
<div class="btn-group w-100" role="group">
    <?php foreach ($modes as $key => $label): ?>
        <input type="radio" class="btn-check" name="mode" id="mode-<?= e($key) ?>" value="<?= e($key) ?>"<?= $mode === $key ? ' checked' : '' ?>>
        <label class="btn btn-outline-primary" for="mode-<?= e($key) ?>"><?= e($label) ?></label>
    <?php endforeach; ?>
</div>
