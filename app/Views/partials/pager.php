<?php
/**
 * Navigazione pagine. $pager (App\Helpers\Pager), $path, $query (parametri da mantenere, senza 'page').
 */
if ($pager->pages <= 1) {
    return;
}
[$first, $last] = $pager->range();
$link = static fn (int $p) => url($path, $query + ['page' => $p > 1 ? $p : null]);
$window = array_unique(array_filter([1, $pager->page - 2, $pager->page - 1, $pager->page, $pager->page + 1, $pager->page + 2, $pager->pages],
    static fn ($p) => $p >= 1 && $p <= $pager->pages));
sort($window);
?>
<nav class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-top small">
    <span class="text-secondary"><?= e(fmt_int($first)) ?>–<?= e(fmt_int($last)) ?> di <?= e(fmt_int($pager->total)) ?></span>
    <ul class="pagination pagination-sm mb-0 ms-auto">
        <li class="page-item<?= $pager->page === 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($pager->page - 1)) ?>" aria-label="Precedente">‹</a></li>
        <?php $prev = 0; foreach ($window as $p): ?>
            <?php if ($p - $prev > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item<?= $p === $pager->page ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($p)) ?>"><?= $p ?></a></li>
        <?php $prev = $p; endforeach; ?>
        <li class="page-item<?= $pager->page === $pager->pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($pager->page + 1)) ?>" aria-label="Successiva">›</a></li>
    </ul>
</nav>
