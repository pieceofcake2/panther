<?php
/**
 * The screenshots of a test of the End-to-End test report, by step (see `PieceOfCake2\Panther\Report\HtmlRenderer`)
 *
 * @var array<array{label: string, mode: string, path: string, title: string, images: array<array<string, mixed>>}> $steps
 * @var \Closure(?string): string $h Escapes HTML
 */
?>
<div class="shots">
<?php foreach ($steps as $step) : ?>
    <figure class="step">
    <?php foreach ($step['images'] as $image) : ?>
        <?php if ($image['bands'] !== []) : ?>
            <div class="bands <?= $image['continued'] ? 'continued' : '' ?>" style="<?= $h($image['style']) ?>">
            <?php foreach ($image['bands'] as $band) : ?>
                <div class="band" style="<?= $h($band['style']) ?>"><img src="<?= $h(rawurlencode($image['file'])) ?>" alt="" style="<?= $h($band['imageStyle']) ?>"></div>
            <?php endforeach; ?>
            </div>
        <?php else : ?>
            <img class="<?= $image['continued'] ? 'continued' : '' ?>" src="<?= $h(rawurlencode($image['file'])) ?>" alt="" style="<?= $h($image['style']) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <figcaption>
    <span class="name"><?= $h($step['label']) ?></span>
    <span class="kind"><?= $h($step['mode']) ?></span><span class="path"><?= $h($step['path']) ?></span><span class="page-title"><?= $h($step['title']) ?></span>
    </figcaption>
    </figure>
<?php endforeach; ?>
</div>
