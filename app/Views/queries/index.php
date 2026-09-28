<?php
/**
 * @var string $query @var bool $whole @var array{total: int, readable: int} $access @var string $login @var string $database
 * @var ?list<array> $results SqlModuleService::search() (pagina corrente) @var ?App\Helpers\Pager $pager
 */
use App\Helpers\Text;
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-scroll"></i> Query di SGA</h1>
        <p class="lead">Dentro il database, oltre alle tabelle, ci sono query già scritte da Zucchetti (viste, funzioni, trigger).
            Cercaci il nome di una tabella o di una colonna per vedere <strong>come il gestionale stesso la usa e con cosa la collega</strong>.</p>
    </div>
</div>

<?php if ($access['total'] === 0): ?>
    <?= $this->partial('partials/alert', ['type' => 'info', 'message' => 'In questo database non ci sono query di SGA (viste, funzioni o trigger).']) ?>
<?php elseif ($access['readable'] === 0): ?>
    <div class="card border-warning mb-3">
        <div class="card-body">
            <h2 class="h5"><i class="fa-solid fa-lock text-warning"></i> Serve un permesso in più</h2>
            <p class="mb-2">Nel database ci sono <strong><?= e(fmt_int($access['total'])) ?></strong> query di SGA, ma l'utente SQL con cui Matriosga si collega
                (<span class="ident"><?= e($login) ?></span>) <strong>non può leggerne il testo</strong>.</p>
            <p class="mb-2">Chiedi a chi gestisce SQL Server di eseguire queste due righe:</p>
            <?= $this->partial('partials/code_block', ['title' => 'Da girare al DBA', 'icon' => 'fa-user-shield',
                'code' => "USE [{$database}];\nGRANT VIEW DEFINITION TO [{$login}];"]) ?>
            <p class="small text-secondary mb-0"><i class="fa-solid fa-shield-halved"></i> È un permesso di <strong>sola lettura</strong>:
                permette di vedere come sono scritte viste e funzioni. Non permette di modificare nulla né di leggere altri dati.
                Dopo che è stato dato, ricarica questa pagina.</p>
        </div>
    </div>
<?php else: ?>
    <form class="card mb-3" method="get" action="<?= e(url('/queries')) ?>">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-9">
                    <label class="form-label" for="q">Nome di una tabella o di una colonna</label>
                    <input class="form-control form-control-lg ident" id="q" name="q" value="<?= e($query) ?>" placeholder="es. BaCliFor, RAGIONE_SOCIALE, FaFattNcTestata" autofocus required>
                </div>
                <div class="col-lg-2">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="word" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="word" name="word" value="1"<?= $whole ? ' checked' : '' ?>>
                        <label class="form-check-label small" for="word" title="Attivo: «BaCliFor» non trova «BaCliForNote»">Parola intera</label>
                    </div>
                </div>
                <div class="col-lg-1 d-grid">
                    <button class="btn btn-primary btn-lg" type="submit" title="Cerca"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </div>
            <div class="small text-secondary mt-2">Cerca in <?= e(fmt_int($access['readable'])) ?> query di SGA
                <?php if ($access['readable'] < $access['total']): ?>(<?= e(fmt_int($access['total'] - $access['readable'])) ?> non leggibili: cifrate dal fornitore)<?php endif; ?>.</div>
        </div>
    </form>

    <?php if ($results === null): ?>
        <div class="empty-state"><i class="fa-solid fa-scroll"></i>Scrivi il nome di una tabella o di una colonna.</div>
    <?php elseif (!$results): ?>
        <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i>Nessuna query di SGA contiene «<?= e($query) ?>».
            <?php if ($whole): ?><br><a href="<?= e(url('/queries', ['q' => $query, 'word' => 0])) ?>">Riprova anche dentro nomi più lunghi</a><?php endif; ?></div>
    <?php else: ?>
        <p class="mb-2"><strong><?= e(fmt_int($pager->total)) ?></strong> query di SGA contengono «<span class="ident"><?= e($query) ?></span>».</p>
        <?php foreach ($results as $r): ?>
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="badge badge-type" title="<?= e($r['help']) ?>"><?= e($r['label']) ?></span>
                        <span class="ident fw-semibold"><?= e($r['name']) ?></span><?= $this->partial('partials/copy', ['text' => $r['name']]) ?>
                        <?php if ($r['parent']): ?><span class="small text-secondary">sulla tabella <?= $this->partial('partials/table_link', ['full' => $r['parent']]) ?></span><?php endif; ?>
                        <span class="small text-secondary"><?= e($r['hits']) ?> <?= $r['hits'] === 1 ? 'volta' : 'volte' ?></span>
                        <span class="ms-auto d-flex gap-2">
                            <?php if ($r['type'] === 'VIEW'): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/tables/show', ['t' => $r['name']])) ?>"><i class="fa-solid fa-table"></i> Apri come tabella</a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-module="<?= e($r['name']) ?>"><i class="fa-solid fa-code"></i> Codice completo</button>
                        </span>
                    </div>
                    <div class="small text-secondary mb-1"><?= e($r['help']) ?></div>
                    <?php foreach ($r['snippets'] as $s): ?>
                        <pre class="snippet"><?= Text::highlight($s, $query, $whole) ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="card"><?= $this->partial('partials/pager', ['pager' => $pager, 'path' => '/queries', 'query' => ['q' => $query, 'word' => $whole ? null : 0]]) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="module-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title ident" id="module-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="module-body" data-term="<?= e($query) ?>" data-word="<?= $whole ? 1 : 0 ?>"></div>
        </div>
    </div>
</div>
