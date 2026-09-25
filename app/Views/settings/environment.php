<?php
/** @var list<array> $checks EnvironmentService::checks() @var array<string,string> $paths EnvironmentService::paths() */
$badge = ['ok' => ['success', 'fa-circle-check', 'OK'], 'warn' => ['warning', 'fa-triangle-exclamation', 'Consigliato'],
    'error' => ['danger', 'fa-circle-xmark', 'Da sistemare'], 'info' => ['secondary', 'fa-circle-info', 'Info']];
$problems = array_filter($checks, static fn ($c) => in_array($c['status'], ['warn', 'error'], true));
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-gear"></i> Impostazioni</h1>
        <p class="lead">Requisiti del server controllati automaticamente, con le istruzioni per sistemarli.</p>
    </div>
</div>
<?= $this->partial('settings/_nav', ['current' => 'environment']) ?>

<?php if (!$problems): ?>
    <?= $this->partial('partials/alert', ['type' => 'success', 'message' => 'Ambiente a posto: tutti i controlli sono superati.']) ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <?php foreach ($checks as $c): ?>
            <?php [$color, $icon, $text] = $badge[$c['status']]; ?>
            <div class="card mb-2 env-check env-<?= e($c['status']) ?>">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <i class="fa-solid <?= e($icon) ?> text-<?= e($color) ?>"></i>
                        <strong><?= e($c['label']) ?></strong>
                        <span class="text-secondary small"><?= e($c['value']) ?></span>
                        <span class="badge text-bg-<?= e($color) ?> ms-auto"><?= e($text) ?></span>
                    </div>
                    <div class="small text-secondary mt-1"><?= e($c['why']) ?></div>
                    <?php if (in_array($c['status'], ['warn', 'error'], true)): ?>
                        <details class="mt-2" open>
                            <summary class="small fw-semibold">Come sistemare</summary>
                            <ol class="small mb-2 mt-1">
                                <?php foreach ($c['fix'] as $step): ?><li class="text-break"><?= e($step) ?></li><?php endforeach; ?>
                            </ol>
                            <?php if ($c['code'] !== ''): ?>
                                <?= $this->partial('partials/code_block', ['title' => 'Da copiare', 'icon' => 'fa-file-lines', 'code' => $c['code']]) ?>
                            <?php endif; ?>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-truck-moving"></i> Installare su un'altra macchina</div>
            <div class="card-body small">
                <ol class="ps-3 mb-0">
                    <li class="mb-2">Installare <strong>XAMPP</strong> (PHP 8.2, 64 bit) e <strong>Microsoft ODBC Driver 18 for SQL Server</strong> (x64).</li>
                    <li class="mb-2">Copiare la cartella di Matriosga in <span class="ident">xampp\htdocs\</span>.
                        La sottocartella <span class="ident">storage\cache</span> si può lasciare vuota: si ricrea da sola.</li>
                    <li class="mb-2">Per portare anche la connessione copiare <strong>insieme</strong>
                        <span class="ident">storage\settings.json</span> e <span class="ident">storage\app.key</span>
                        (la password è cifrata con quella chiave). Altrimenti reinserire i dati in Impostazioni.</li>
                    <li class="mb-2">Avviare Apache e aprire <span class="ident">http://localhost/&lt;cartella&gt;/</span>
                        (se Apache usa un'altra porta, es. <span class="ident">:8080</span>, aggiungerla).</li>
                    <li class="mb-2">Aprire <strong>questa pagina</strong> e sistemare le voci rosse, poi quelle gialle.</li>
                    <li class="mb-2">Impostazioni → <strong>Testa connessione</strong>. Dalla nuova macchina il server SQL deve essere raggiungibile in rete (porta 1433 o istanza nominata).</li>
                    <li>Aprire la Dashboard: la prima lettura della struttura richiede circa un minuto.</li>
                </ol>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fa-solid fa-circle-info"></i> File di configurazione</div>
            <div class="card-body small">
                <dl class="kv mb-0">
                    <?php foreach ($paths as $label => $value): ?>
                        <dt><?= e($label) ?></dt><dd class="ident text-break"><?= e($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
