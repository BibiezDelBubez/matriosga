<?php
/** Link al dettaglio di una tabella + copia nome. $full = "schema.tabella", opzionale $view (bool). */
?>
<span class="text-nowrap"><?php if (!empty($view)): ?><i class="fa-regular fa-eye text-secondary me-1" title="Vista"></i><?php endif; ?><a class="ident" href="<?= e(url('/tables/show', ['t' => $full])) ?>"><?= e($full) ?></a><?= $this->partial('partials/copy', ['text' => $full, 'title' => 'Copia nome tabella']) ?></span>
