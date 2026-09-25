<?php
/** @var list<array> $tools voci di menu @var string $query @var array $conn @var ?array $meta */
$searchData = array_map(static fn (array $t) => [
    'path'      => $t['path'],
    'label'     => $t['label'],
    'desc'      => $t['desc'],
    'keywords'  => $t['keywords'] ?? [],
    'questions' => $t['questions'] ?? [],
], $tools);
$examples = ['chiavi primarie', 'dove compare un valore', 'tabelle collegate', 'valori distinti', 'foreign key'];
?>
<div class="landing-wrap">
    <header class="landing-hero">
        <img src="<?= e(asset('matriosga.svg')) ?>" alt="Logo Matriosga" class="landing-logo">
        <div class="landing-intro">
            <h1>Matriosga</h1>
            <p>Apri il database SGA strato dopo strato: tabelle, chiavi, relazioni e valori, in pochi secondi.</p>

            <div class="landing-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="tool-search" value="<?= e($query) ?>" autocomplete="off" autofocus
                       placeholder="Cosa vuoi scoprire? es. «chiavi primarie», «tabelle collegate»…"
                       aria-label="Cerca una funzione">
                <kbd title="Premi / per cercare">/</kbd>
            </div>
            <div class="landing-examples">
                <span>Prova:</span>
                <?php foreach ($examples as $example): ?>
                    <button type="button" class="chip" data-example="<?= e($example) ?>"><?= e($example) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <section class="landing-results">
        <div class="landing-quick d-none" id="quick-actions">
            <span class="landing-count me-1">Cerca «<span id="quick-text"></span>» come:</span>
            <a class="chip chip-action" data-quick="/search"><i class="fa-solid fa-magnifying-glass"></i> valore in tutto il DB</a>
            <a class="chip chip-action" data-quick="/tables"><i class="fa-solid fa-table-list"></i> nome tabella</a>
            <a class="chip chip-action" data-quick="/columns"><i class="fa-solid fa-table-columns"></i> nome colonna</a>
        </div>
        <div class="landing-count" id="tool-count" aria-live="polite"></div>
        <div class="landing-grid" id="tool-grid">
            <?php foreach ($tools as $tool): ?>
                <?= $this->partial('partials/tool_card', ['tool' => $tool]) ?>
            <?php endforeach; ?>
        </div>
        <div class="landing-empty d-none" id="tool-empty">
            <i class="fa-regular fa-face-meh"></i>
            Nessuna funzione trovata. Prova con parole come <em>tabella</em>, <em>colonna</em>, <em>valore</em>, <em>relazione</em>.
        </div>
    </section>

    <footer class="landing-foot">
        <?= $this->partial('partials/connection_badge', ['conn' => $conn, 'meta' => $meta]) ?>
        <span class="ms-auto">↑ ↓ per scegliere · Invio per aprire · Esc per pulire</span>
    </footer>
</div>

<script type="application/json" id="tools-data"><?= json_encode($searchData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
