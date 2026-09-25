<div class="page-head">
    <h1><i class="fa-solid fa-bug text-danger"></i> Errore <?= e($status) ?></h1>
</div>
<?= $this->partial('partials/alert', ['type' => 'danger', 'message' => e($message)]) ?>
<?php if (!empty($trace)): ?>
    <details class="mt-3"><summary class="text-secondary">Dettagli tecnici</summary>
        <pre class="code-block mt-2"><?= e($trace) ?></pre>
    </details>
<?php endif; ?>
<a class="btn btn-primary mt-3" href="<?= e(url('/')) ?>"><i class="fa-solid fa-house"></i> Torna alla home</a>
<?php if ((int) $status === 412): ?>
    <a class="btn btn-outline-primary mt-3 ms-1" href="<?= e(url('/settings')) ?>"><i class="fa-solid fa-gear"></i> Impostazioni</a>
<?php endif; ?>
