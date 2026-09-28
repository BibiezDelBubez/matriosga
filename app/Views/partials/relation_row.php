<?php
/**
 * Riga di tabella per una relazione: FK dichiarata, candidata o definita dall'utente.
 * $rel (FK dello snapshot, candidata o relazione utente di RelationshipService), $join (SQL da copiare).
 */
$kind = $rel['kind'] ?? (isset($rel['score']) ? 'candidate' : 'fk');
$verifyData = json_encode(['from' => $rel['from'], 'from_cols' => $rel['from_cols'], 'to' => $rel['to'], 'to_cols' => $rel['to_cols']]);
?>
<tr>
    <td data-value="<?= e($rel['from']) ?>"><?= $this->partial('partials/col_ref', ['table' => $rel['from'], 'cols' => $rel['from_cols']]) ?></td>
    <td class="text-secondary"><i class="fa-solid <?= $kind === 'fk' ? 'fa-arrow-right' : 'fa-arrow-right-long' ?>"></i></td>
    <td data-value="<?= e($rel['to']) ?>"><?= $this->partial('partials/col_ref', ['table' => $rel['to'], 'cols' => $rel['to_cols']]) ?></td>
    <?php if ($kind === 'candidate'): ?>
        <td data-value="<?= e($rel['score']) ?>" class="text-nowrap">
            <?= $this->partial('partials/rel_badge', ['rel' => ['kind' => $kind] + $rel]) ?>
            <?php if (!empty($rel['hub'])): ?><span class="badge text-bg-light border" title="Tabella molto referenziata">hub</span><?php endif; ?>
        </td>
        <td class="small text-secondary text-nowrap" title="<?= e($rel['reason_label']) ?>"><?= $rel['reason'] === 'learned'
            ? 'come ' . e(fmt_int($rel['evidence'])) . ' FK'
            : 'nome colonna' ?></td>
    <?php elseif ($kind === 'manual'): ?>
        <td class="text-nowrap"><?= $this->partial('partials/rel_badge', ['rel' => $rel]) ?></td>
        <td class="small text-secondary"><?= e($rel['note']) ?> <span class="text-nowrap">(<?= e($rel['created']) ?>)</span></td>
    <?php else: ?>
        <td class="small ident text-secondary" data-value="<?= e($rel['name']) ?>"><?= e($rel['name']) ?></td>
        <td class="small text-nowrap"><?= $this->partial('partials/fk_rules', ['fk' => $rel]) ?></td>
    <?php endif; ?>
    <td class="text-nowrap text-end">
        <?php if ($kind !== 'fk'): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-verify="<?= e($verifyData) ?>" title="Controlla su un campione quante righe trovano corrispondenza"><i class="fa-solid fa-vial"></i> Verifica</button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-copy="<?= e($join) ?>" title="Copia la query JOIN"><i class="fa-regular fa-copy"></i> JOIN</button>
        <?php if ($kind === 'manual'): ?>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" data-delete-relation="<?= e($rel['id']) ?>" title="Elimina"><i class="fa-regular fa-trash-can"></i></button>
        <?php endif; ?>
    </td>
</tr>
