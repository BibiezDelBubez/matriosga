<?php
/**
 * Layout unico. Variabili: $content, $title, opzionali $breadcrumb (array label=>url|null),
 * $scripts (chiavi di assets.optional o percorsi js/...), $conn, $meta, $currentPath (per la voce di menu attiva).
 */
$current = $currentPath ?? '/';
$menu = setting('menu', []);
$active = null;
foreach ($menu as $item) {
    if (str_starts_with($current . '/', $item['path'] . '/')) {
        $active = $item['path'];
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?>Matriosga</title>
    <?= $this->partial('partials/assets_css') ?>
</head>
<body data-base="<?= e(base_url()) ?>">
<div class="app-shell">
    <aside class="app-sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset('matriosga.svg')) ?>" alt="" class="brand-logo"><span>Matriosga</span>
        </a>
        <nav class="nav flex-column">
            <?php foreach ($menu as $item): ?>
                <a class="nav-link<?= $item['path'] === $active ? ' active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                    <i class="fa-solid <?= e($item['icon']) ?> fa-fw"></i><span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">v<?= e(setting('app.version')) ?> · offline</div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-sm btn-light d-lg-none" type="button" data-toggle-sidebar><i class="fa-solid fa-bars"></i></button>
            <nav aria-label="breadcrumb" class="me-auto">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>"><i class="fa-solid fa-house"></i></a></li>
                    <?php foreach (($breadcrumb ?? [($title ?? '') => null]) as $label => $link): ?>
                        <?php if ($label === '') continue; ?>
                        <li class="breadcrumb-item<?= $link === null ? ' active' : '' ?>">
                            <?= $link === null ? e($label) : '<a href="' . e($link) . '">' . e($label) . '</a>' ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?= $this->partial('partials/connection_badge', ['conn' => $conn ?? null, 'meta' => $meta ?? null]) ?>
        </header>

        <main class="app-content">
            <?= $content ?>
        </main>
    </div>
</div>

<?= $this->partial('partials/assets_js', ['scripts' => $scripts ?? []]) ?>
</body>
</html>
