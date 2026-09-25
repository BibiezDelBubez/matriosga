<?php
/** Riferimento "tabella.colonna(e)": $table = "schema.tabella", $cols = list<string>. */
$colText = count($cols) === 1 ? $cols[0] : '(' . implode(', ', $cols) . ')';
?>
<span class="col-ref"><a class="ident" href="<?= e(url('/tables/show', ['t' => $table])) ?>"><?= e($table) ?></a><span class="ident">.</span><strong class="ident"><?= e($colText) ?></strong><?= $this->partial('partials/copy', ['text' => implode(', ', $cols), 'title' => 'Copia nome colonna']) ?></span>
