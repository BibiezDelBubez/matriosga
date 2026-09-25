<?php
/** Pulsante "copia". $text = testo da copiare, $label = etichetta opzionale, $title = tooltip. */
?>
<button type="button" class="btn-copy" data-copy="<?= e($text) ?>" title="<?= e($title ?? 'Copia') ?>">
    <i class="fa-regular fa-copy"></i><?= isset($label) ? ' ' . e($label) : '' ?>
</button>
