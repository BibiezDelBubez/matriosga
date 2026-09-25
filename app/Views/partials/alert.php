<?php
/** Messaggio. $type = success|danger|warning|info, $message, opzionale $icon. */
$icons = ['success' => 'fa-circle-check', 'danger' => 'fa-circle-xmark', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
?>
<div class="alert alert-<?= e($type) ?> d-flex gap-2 align-items-start">
    <i class="fa-solid <?= e($icon ?? $icons[$type] ?? 'fa-circle-info') ?> mt-1"></i>
    <div><?= $message ?></div>
</div>
