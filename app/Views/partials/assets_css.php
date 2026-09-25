<?php foreach (assets_config()['css'] as $css): ?>
    <link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('matriosga.svg')) ?>">
