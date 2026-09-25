<?php
/** Script comuni + script di pagina ($scripts: chiavi di assets.optional o percorsi js/...). */
$assets = assets_config();
?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>
<?php foreach ($assets['js'] as $js): ?>
    <script src="<?= e(asset($js)) ?>"></script>
<?php endforeach; ?>
<?php foreach (($scripts ?? []) as $script): ?>
    <script src="<?= e(asset($assets['optional'][$script] ?? $script)) ?>"></script>
<?php endforeach; ?>
