<?php
/** Codice copiabile (SQL / Power Query). $title, $code, opzionali $icon, $note. Il codice è solo mostrato, mai eseguito. */
?>
<div class="code-card mb-3">
    <div class="code-head">
        <span><i class="fa-solid <?= e($icon ?? 'fa-code') ?>"></i> <?= e($title) ?></span>
        <button type="button" class="btn btn-sm btn-outline-light" data-copy="<?= e($code) ?>"><i class="fa-regular fa-copy"></i> Copia</button>
    </div>
    <pre class="code-block"><?= e($code) ?></pre>
    <?php if (!empty($note)): ?><div class="code-note"><?= e($note) ?></div><?php endif; ?>
</div>
