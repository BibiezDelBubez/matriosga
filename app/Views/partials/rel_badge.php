<?php
/** Badge del tipo di relazione (stessa resa di Matriosga.relBadge in app.js). $rel con kind fk|candidate|manual. */
?>
<?php if ($rel['kind'] === 'manual'): ?>
    <span class="badge badge-manual" title="<?= e($rel['name'] ?? '') ?>"><i class="fa-solid fa-user-pen"></i> definita da te</span>
<?php elseif ($rel['kind'] === 'candidate'): ?>
    <span class="badge badge-cand" title="<?= e($rel['reason_label'] ?? '') ?>">candidata <?= e($rel['score']) ?>%</span>
<?php else: ?>
    <span class="badge badge-fk" title="<?= e($rel['name'] ?? '') ?>">FK</span>
<?php endif; ?>
