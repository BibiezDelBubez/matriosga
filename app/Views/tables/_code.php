<?php /** Scheda SQL / Power Query. @var App\Models\Table $table @var array $code @var list<string> $selected */ ?>
<div class="card-body">
    <?php if ($selected): ?>
        <div class="alert alert-info py-2 small d-flex align-items-center gap-2">
            <i class="fa-solid fa-filter"></i> Codice generato con <?= count($selected) ?> colonne selezionate.
            <a class="ms-auto" href="<?= e(url('/tables/show', ['t' => $table->fullName, 'tab' => 'code'])) ?>">Usa tutte le colonne</a>
        </div>
    <?php else: ?>
        <p class="small text-secondary"><i class="fa-solid fa-lightbulb"></i> Suggerimento: nella scheda <em>Colonne</em> spunta solo le colonne che ti servono e premi
            «SQL/Power Query con colonne selezionate».</p>
    <?php endif; ?>

    <?= $this->partial('partials/code_block', ['title' => 'Power Query — navigazione (consigliato)', 'icon' => 'fa-chart-simple', 'code' => $code['pqTable'],
        'note' => 'Mantiene il query folding: i filtri applicati in Power Query vengono eseguiti da SQL Server. Incolla in Editor avanzato.']) ?>
    <?= $this->partial('partials/code_block', ['title' => 'SQL', 'icon' => 'fa-database', 'code' => $code['sql']]) ?>
    <?= $this->partial('partials/code_block', ['title' => 'Power Query — query SQL nativa', 'icon' => 'fa-chart-simple', 'code' => $code['pqNative'],
        'note' => 'Utile per query personalizzate; Power BI chiederà di approvare la query nativa.']) ?>
</div>
