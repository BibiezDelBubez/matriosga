<?php
/** Layout a tutta pagina per la landing (niente sidebar). Variabili: $content, $scripts, $conn, $meta. */
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Matriosga</title>
    <?= $this->partial('partials/assets_css') ?>
</head>
<body class="landing" data-base="<?= e(base_url()) ?>">
<?= $content ?>
<?= $this->partial('partials/assets_js', ['scripts' => $scripts ?? []]) ?>
</body>
</html>
