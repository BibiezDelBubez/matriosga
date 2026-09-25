<?php /** Regole di una FK dichiarata: ON DELETE/UPDATE (se diverse da NO ACTION), disabilitata, non verificata. $fk */ ?>
<?php foreach (['ON DELETE' => $fk['on_delete'], 'ON UPDATE' => $fk['on_update']] as $label => $value): ?>
    <?php if ($value !== 'NO_ACTION'): ?><span class="badge text-bg-light border small"><?= e($label . ' ' . str_replace('_', ' ', $value)) ?></span><?php endif; ?>
<?php endforeach; ?>
<?php if ($fk['disabled']): ?><span class="badge text-bg-warning">disabilitata</span><?php endif; ?>
<?php if (!$fk['disabled'] && !$fk['trusted']): ?><span class="badge text-bg-light border" title="Vincolo non verificato sui dati esistenti (WITH NOCHECK)">non verificata</span><?php endif; ?>
