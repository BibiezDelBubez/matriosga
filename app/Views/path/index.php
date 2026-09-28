<?php
/**
 * @var string $from @var string $to @var array $opt @var string $avoid @var array $noise
 * @var ?array $result PathFinderService::find @var list<string> $sql @var list<string> $pq
 */
$toggleUrl = static fn (array $change) => url('/path', array_merge([
    'from' => $from, 'to' => $to, 'depth' => $opt['depth'], 'cand' => $opt['candidates'] ? 1 : null,
    'score' => $opt['minScore'], 'hubs' => $opt['hubs'] ? 1 : null, 'avoid' => $avoid, 'all' => $noise['all'] ? 1 : null,
], $change));
?>
<div class="page-head">
    <div>
        <h1><i class="fa-solid fa-route"></i> Percorso tra due tabelle</h1>
        <p class="lead">Queste due tabelle sono collegate? Attraverso quali tabelle e colonne?</p>
    </div>
</div>

<form class="card mb-3" method="get" action="<?= e(url('/path')) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="from">Tabella di partenza</label>
                <input class="form-control ident" id="from" name="from" value="<?= e($from) ?>" data-table-picker required placeholder="es. DocTes" autofocus>
            </div>
            <div class="col-lg-1 text-center pb-2 d-none d-lg-block">
                <button type="button" class="btn btn-sm btn-light" data-swap="#from,#to" title="Inverti"><i class="fa-solid fa-right-left"></i></button>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="to">Tabella di destinazione</label>
                <input class="form-control ident" id="to" name="to" value="<?= e($to) ?>" data-table-picker required placeholder="es. BaCliFor">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="depth">Passaggi massimi</label>
                <select class="form-select" id="depth" name="depth">
                    <?php foreach (range(1, 6) as $d): ?>
                        <option value="<?= $d ?>"<?= $opt['depth'] === $d ? ' selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 d-grid">
                <button class="btn btn-primary" type="submit" title="Cerca percorsi"><i class="fa-solid fa-route"></i></button>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-3 small align-items-center">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="cand" name="cand" value="1"<?= $opt['candidates'] ? ' checked' : '' ?>>
                <label class="form-check-label" for="cand">Usa anche relazioni <span class="badge badge-cand">candidate</span> con affidabilità ≥</label>
            </div>
            <select class="form-select form-select-sm w-auto" name="score" aria-label="Affidabilità minima">
                <?php foreach ([100, 90, 80, 60, 50] as $s): ?>
                    <option value="<?= $s ?>"<?= $opt['minScore'] === $s ? ' selected' : '' ?>><?= $s ?>%</option>
                <?php endforeach; ?>
            </select>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="hubs" name="hubs" value="1"<?= $opt['hubs'] ? ' checked' : '' ?>>
                <label class="form-check-label" for="hubs">Passa anche da tabelle hub <span class="text-secondary">(BaSocieta, BaCliFor…)</span></label>
            </div>
            <?= $this->partial('partials/show_all', ['noise' => $noise]) ?>
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <label for="avoid" class="text-nowrap">Evita:</label>
                <input class="form-control form-control-sm ident" id="avoid" name="avoid" value="<?= e($avoid) ?>" placeholder="tabelle da non attraversare, separate da virgola">
            </div>
        </div>
    </div>
</form>

<?php if ($result === null): ?>
    <div class="empty-state"><i class="fa-solid fa-route"></i>Scegli due tabelle. Per default si usano solo le FK dichiarate e non si passa dalle tabelle hub
        (quasi tutto in SGA è collegato a <span class="ident">BaSocieta</span>: sarebbe un percorso inutile).</div>
<?php elseif (!$result['paths']): ?>
    <div class="card"><div class="card-body">
        <h2 class="h5"><i class="fa-solid fa-link-slash text-secondary"></i> Nessun percorso entro <?= e($opt['depth']) ?> passaggi</h2>
        <?php if ($result['distance'] !== null): ?>
            <p>Esiste un percorso di <strong><?= e($result['distance']) ?></strong> passaggi: <a href="<?= e($toggleUrl(['depth' => $result['distance']])) ?>">aumenta i passaggi massimi</a>.</p>
        <?php endif; ?>
        <ul class="mb-0">
            <?php if (!$opt['candidates']): ?><li><a href="<?= e($toggleUrl(['cand' => 1])) ?>">Riprova usando anche le relazioni candidate</a></li><?php endif; ?>
            <?php if (!$opt['hubs'] && $result['blocked']): ?>
                <li>Le tabelle sono collegate a tabelle hub escluse (<?= e(implode(', ', array_slice($result['blocked'], 0, 5))) ?>):
                    <a href="<?= e($toggleUrl(['hubs' => 1])) ?>">riprova passando anche dagli hub</a></li>
            <?php endif; ?>
        </ul>
    </div></div>
<?php else: ?>
    <p class="mb-2"><strong><?= count($result['paths']) ?></strong> percorsi trovati, il più corto di <strong><?= e($result['distance']) ?></strong> passaggi.
        <?php if ($result['truncated']): ?><span class="text-secondary small">Mostrati i migliori; restringi con «Evita» o riduci i passaggi.</span><?php endif; ?></p>
    <?php foreach ($result['paths'] as $i => $path): ?>
        <div class="card mb-3 path-card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <span>Percorso <?= $i + 1 ?> · <?= count($path['steps']) ?> <?= count($path['steps']) === 1 ? 'passaggio' : 'passaggi' ?></span>
                <?php if ($path['manual']): ?><span class="badge badge-manual"><?= $path['manual'] ?> definite da te</span><?php endif; ?>
                <?php if ($path['candidates']): ?>
                    <span class="badge badge-cand"><?= $path['candidates'] ?> relazioni candidate</span>
                <?php elseif (!$path['manual']): ?>
                    <span class="badge badge-fk">solo FK dichiarate</span>
                <?php endif; ?>
                <?php if ($path['weak']): ?>
                    <span class="badge text-bg-warning" title="Passa da una tabella a cui puntano entrambe (es. un'anagrafica o una configurazione): collega righe che hanno solo un valore in comune e di solito moltiplica le righe.">
                        <i class="fa-solid fa-triangle-exclamation"></i> collegamento debole</span>
                <?php endif; ?>
                <span class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($sql[$i]) ?>"><i class="fa-regular fa-copy"></i> SQL JOIN</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($pq[$i]) ?>"><i class="fa-regular fa-copy"></i> Power Query</button>
                    <a class="btn btn-sm btn-outline-secondary" title="Apri nel grafo" href="<?= e(url('/graph', ['t' => $path['nodes'][0], 'nodes' => implode(',', $path['nodes']),
                        'path' => end($path['nodes']), 'cand' => $opt['candidates'] ? 1 : null, 'hubs' => $opt['hubs'] ? 1 : null])) ?>"><i class="fa-solid fa-diagram-project"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#code-<?= $i ?>"><i class="fa-solid fa-code"></i></button>
                </span>
            </div>
            <div class="card-body path-chain">
                <?php foreach ($path['steps'] as $s): ?>
                    <?php $rel = $s['rel']; ?>
                    <div class="path-node"><?= $this->partial('partials/table_link', ['full' => $s['a']]) ?></div>
                    <div class="path-edge is-<?= e($rel['kind']) ?>">
                        <div class="ident small">
                            <?php foreach ($s['cols_a'] as $k => $col): ?>
                                <div><?= e($col) ?> <span class="text-secondary">=</span> <?= e($s['cols_b'][$k]) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small">
                            <?= $this->partial('partials/rel_badge', ['rel' => $rel]) ?>
                            <?php if ($rel['kind'] === 'fk'): ?>
                                <span class="ident text-secondary"><?= e($rel['name']) ?></span>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-verify="<?= e(json_encode(['from' => $rel['from'], 'from_cols' => $rel['from_cols'], 'to' => $rel['to'], 'to_cols' => $rel['to_cols']])) ?>"><i class="fa-solid fa-vial"></i> Verifica</button>
                            <?php endif; ?>
                            <span class="text-secondary ms-1" title="Chi punta a chi"><?= $s['forward'] ? '↓ punta a' : '↑ è puntata da' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="path-node"><?= $this->partial('partials/table_link', ['full' => $path['nodes'][count($path['nodes']) - 1]]) ?></div>
            </div>
            <div class="collapse" id="code-<?= $i ?>">
                <div class="card-body pt-0">
                    <?= $this->partial('partials/code_block', ['title' => 'SQL', 'icon' => 'fa-database', 'code' => $sql[$i]]) ?>
                    <?= $this->partial('partials/code_block', ['title' => 'Power Query', 'icon' => 'fa-chart-simple', 'code' => $pq[$i]]) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
