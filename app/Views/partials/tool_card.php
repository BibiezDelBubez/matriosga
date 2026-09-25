<?php
/** Card di una funzione (voce di config 'menu'). $tool = voce di menu. Usata da dashboard e landing. */
$ready = $tool['ready'] ?? true;
$tag = $ready ? 'a' : 'div';
?>
<<?= $tag ?> class="tool-btn<?= $ready ? '' : ' is-soon' ?>"<?= $ready ? ' href="' . e(url($tool['path'])) . '"' : ' aria-disabled="true"' ?> data-tool="<?= e($tool['path']) ?>">
    <span class="tool-icon"><i class="fa-solid <?= e($tool['icon']) ?>"></i></span>
    <span class="tool-text">
        <span class="tool-title d-block">
            <?= e($tool['label']) ?>
            <?php if (!$ready): ?><span class="badge badge-soon ms-1">in arrivo</span><?php endif; ?>
        </span>
        <span class="tool-desc d-block"><?= e($tool['desc']) ?></span>
        <span class="tool-match d-none"><i class="fa-solid fa-turn-up fa-rotate-90"></i> <span></span></span>
    </span>
</<?= $tag ?>>
