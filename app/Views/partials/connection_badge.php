<?php
/** Stato connessione in topbar. $conn = Settings::connection(), $meta = MetadataCache::status() */
?>
<div class="d-flex align-items-center gap-2 small">
    <?php if (empty($conn) || ($conn['server'] ?? '') === ''): ?>
        <a class="badge text-bg-warning text-decoration-none" href="<?= e(url('/settings')) ?>">
            <i class="fa-solid fa-plug-circle-exclamation"></i> Connessione non configurata
        </a>
    <?php else: ?>
        <span class="badge text-bg-light border" title="Server / database">
            <i class="fa-solid fa-server text-secondary"></i>
            <span class="ident"><?= e($conn['server']) ?></span> / <span class="ident fw-semibold"><?= e($conn['database']) ?></span>
        </span>
        <?php if (!empty($meta['cached_at'])): ?>
            <span class="text-secondary d-none d-md-inline" title="Data lettura metadata">
                <i class="fa-regular fa-clock"></i> <?= e($meta['cached_at']) ?>
            </span>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-refresh-metadata title="Rilegge la struttura del database">
            <i class="fa-solid fa-rotate"></i><span class="d-none d-xl-inline"> Aggiorna metadata</span>
        </button>
    <?php endif; ?>
</div>
