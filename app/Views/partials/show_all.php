<?php
/**
 * Interruttore "mostra anche tabelle vuote e copie". $noise = Controller::noiseFilter(),
 * opzionale $id (default "show-all"), $submit (true = invia il form al cambio, per le pagine GET).
 */
$id = $id ?? 'show-all';
$c = $noise['counts'];
?>
<div class="form-check form-switch small mb-0" title="Tabelle vuote: <?= e(fmt_int($c['empty'])) ?> · copie di sicurezza riconosciute dal nome: <?= e(fmt_int($c['copies'])) ?>">
    <input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="all" value="1"<?= $noise['all'] ? ' checked' : '' ?><?= !empty($submit) ? ' data-autosubmit' : '' ?>>
    <label class="form-check-label" for="<?= e($id) ?>">
        Mostra anche tabelle vuote e copie
        <?php if (!$noise['all'] && $c['hidden']): ?><span class="text-secondary">(<?= e(fmt_int($c['hidden'])) ?> nascoste)</span><?php endif; ?>
    </label>
</div>
