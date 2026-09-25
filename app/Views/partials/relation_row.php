<?php
/**
 * Riga di tabella per una relazione dichiarata o candidata.
 * $rel (FK dello snapshot oppure candidata di RelationshipService), $join (SQL da copiare).
 */
$candidate = isset($rel['score']);
$verifyData = json_encode(['from' => $rel['from'], 'from_cols' => $rel['from_cols'], 'to' => $rel['to'], 'to_cols' => $rel['to_cols']]);
?>
<tr>
    <td data-value="<?= e($rel['from']) ?>"><?= $this->partial('partials/col_ref', ['table' => $rel['from'], 'cols' => $rel['from_cols']]) ?></td>
    <td class="text-secondary"><i class="fa-solid <?= $candidate ? 'fa-arrow-right-long' : 'fa-arrow-right' ?>"></i></td>
    <td data-value="<?= e($rel['to']) ?>"><?= $this->partial('partials/col_ref', ['table' => $rel['to'], 'cols' => $rel['to_cols']]) ?></td>
    <?php if ($candidate): ?>
        <td data-value="<?= e($rel['score']) ?>" class="text-nowrap">
            <span class="badge badge-cand" title="<?= e($rel['reason_label']) ?>">candidata <?= e($rel['score']) ?>%</span>
            <?php if (!empty($rel['hub'])): ?><span class="badge text-bg-light border" title="Tabella molto referenziata">hub</span><?php endif; ?>
        </td>
        <td class="small text-secondary text-nowrap" title="<?= e($rel['reason_label']) ?>"><?= $rel['reason'] === 'learned'
            ? 'come ' . e(fmt_int($rel['evidence'])) . ' FK'
            : 'nome colonna' ?></td>
    <?php else: ?>
        <td class="small ident text-secondary" data-value="<?= e($rel['name']) ?>"><?= e($rel['name']) ?></td>
        <td class="small text-nowrap"><?= $this->partial('partials/fk_rules', ['fk' => $rel]) ?></td>
    <?php endif; ?>
    <td class="text-nowrap text-end">
        <?php if ($candidate): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-verify="<?= e($verifyData) ?>" title="Controlla su un campione quante righe trovano corrispondenza"><i class="fa-solid fa-vial"></i> Verifica</button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-copy="<?= e($join) ?>" title="Copia la query JOIN"><i class="fa-regular fa-copy"></i> JOIN</button>
    </td>
</tr>
