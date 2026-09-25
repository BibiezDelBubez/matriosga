<?php /** Scheda indici. @var App\Models\Table $table */ ?>
<?php if (!$table->indexes()): ?>
    <div class="empty-state"><i class="fa-solid fa-bolt"></i>Nessun indice su questo oggetto.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Chiave</th><th>Colonne chiave (in ordine)</th><th>Colonne incluse</th></tr></thead>
            <tbody>
            <?php foreach ($table->indexes() as $idx): ?>
                <tr>
                    <td class="text-nowrap"><span class="ident"><?= e($idx['name']) ?></span><?= $this->partial('partials/copy', ['text' => $idx['name']]) ?></td>
                    <td class="small"><?= e(str_replace('_', ' ', strtolower($idx['type']))) ?></td>
                    <td>
                        <?php if ($idx['pk']): ?><span class="badge badge-pk">PK</span>
                        <?php elseif ($idx['uq']): ?><span class="badge badge-idx">UNIQUE constraint</span>
                        <?php elseif ($idx['unique']): ?><span class="badge badge-idx">UNIQUE</span><?php endif; ?>
                    </td>
                    <td class="ident"><?= e(implode(', ', $idx['columns'])) ?></td>
                    <td class="ident text-secondary"><?= e(implode(', ', $idx['included'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
