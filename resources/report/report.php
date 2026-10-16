<?php
/**
 * The HTML of the End-to-End test report, printed to PDF by Chrome (see `PieceOfCake2\Panther\Report\HtmlRenderer`)
 *
 * @var array<string, mixed> $view
 * @var \Closure(string, array<string, string|int|float>=): string $t Message
 * @var \Closure(?string): string $h Escapes HTML
 * @var \Closure(string): string $css CSS string
 */
?><!doctype html>
<html lang="<?= $h($view['language']) ?>">
<head>
<meta charset="utf-8">
<title><?= $h($view['title']) ?></title>
<style>
<?= $view['css'] ?>
@page { @top-left { content: <?= $css($view['headerLeft']) ?>; } @top-right { content: <?= $css($view['headerRight']) ?>; } }
@page :first { @top-left { content: none; } @top-right { content: none; } }
</style>
</head>
<body>

<section class="cover">
<h1><?= $h($view['title']) ?></h1>
<?php if ($view['project'] !== null) : ?>
    <div class="project"><?= $h($view['project']) ?></div>
<?php endif; ?>

<div class="verdict <?= $view['passed'] ? 'passed' : 'failed' ?>">
<div class="label"><?= $h($t($view['passed'] ? 'verdict.passed' : 'verdict.failed')) ?></div>
<div class="counts">
<div><b><?= (int)$view['counts']['tests'] ?></b><span><?= $h($t('count.tests')) ?></span></div>
<div><b class="t-pass"><?= (int)$view['counts']['passed'] ?></b><span><?= $h($t('count.passed')) ?></span></div>
<div><b class="<?= $view['counts']['failed'] > 0 ? 't-fail' : '' ?>"><?= (int)$view['counts']['failed'] ?></b><span><?= $h($t('count.failed')) ?></span></div>
<div><b class="<?= $view['counts']['errors'] > 0 ? 't-fail' : '' ?>"><?= (int)$view['counts']['errors'] ?></b><span><?= $h($t('count.errors')) ?></span></div>
<div><b><?= (int)$view['counts']['skipped'] ?></b><span><?= $h($t('count.skipped')) ?></span></div>
</div>
</div>

<?php if ($view['target'] !== []) : ?>
    <h2><?= $h($t('section.target')) ?></h2>
    <table class="kv">
    <?php foreach ($view['target'] as $row) : ?>
        <tr>
        <th><?= $h($row['label']) ?></th>
        <td>
        <?php if (($row['mono'] ?? null) !== null) : ?>
            <span class="mono"><?= $h($row['mono']) ?></span>
        <?php endif; ?>
        <?= $h($row['value']) ?>
        <?php if (($row['warning'] ?? null) !== null) : ?>
            <br><span class="warn"><?= $h($row['warning']) ?></span>
        <?php endif; ?>
        </td>
        </tr>
    <?php endforeach; ?>
    </table>
<?php endif; ?>

<h2><?= $h($t('section.run')) ?></h2>
<table class="kv">
<?php foreach ($view['run'] as $row) : ?>
    <tr>
    <th><?= $h($row['label']) ?></th>
    <td>
    <?= $h($row['value']) ?>
    <?php if (($row['sub'] ?? null) !== null) : ?>
        <br><span class="mono sub"><?= $h($row['sub']) ?></span>
    <?php endif; ?>
    </td>
    </tr>
<?php endforeach; ?>
</table>

<?php if ($view['note'] !== null) : ?>
    <p class="note"><?= $h($view['note']) ?></p>
<?php endif; ?>
</section>

<section class="list">
<h2><?= $h($t('section.tests')) ?></h2>
<?php foreach ($view['groups'] as $group) : ?>
    <div class="group-list">
    <h3><?= (int)$group['no'] ?>. <?= $h($group['title']) ?></h3>
    <table>
    <colgroup><col class="no"><col><col class="res"><col class="time"></colgroup>
    <thead><tr><th><?= $h($t('field.no')) ?></th><th><?= $h($t('field.test')) ?></th><th><?= $h($t('field.result')) ?></th><th><?= $h($t('field.time')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($group['tests'] as $test) : ?>
        <tr>
        <td><?= $h($test['no']) ?></td>
        <td><a href="#<?= $h($test['id']) ?>"><?= $h($test['title']) ?></a></td>
        <td class="res"><span class="badge <?= $h($test['badge']) ?>"><?= $h($test['statusLabel']) ?></span></td>
        <td class="num"><?= $h($test['time']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
<?php endforeach; ?>
</section>

<?php foreach ($view['groups'] as $group) : ?>
    <?php foreach ($group['tests'] as $test) : ?>
        <section class="test" id="<?= $h($test['id']) ?>">
        <div class="group"><?= (int)$group['no'] ?>. <?= $h($group['title']) ?></div>
        <div class="head"><h3><?= $h($test['no']) ?>　<?= $h($test['title']) ?></h3><span class="badge <?= $h($test['badge']) ?>"><?= $h($test['statusLabel']) ?></span></div>
        <table class="meta">
        <tr><th><?= $h($t('field.test')) ?></th><td class="mono"><?= $h($test['name']) ?></td></tr>
        <tr><th><?= $h($t('field.time')) ?></th><td><?= $h($test['time']) ?></td></tr>
        </table>
        <?php if ($test['description'] !== null) : ?>
            <p class="desc"><?= nl2br($h($test['description'])) ?></p>
        <?php endif; ?>
        <?php if ($test['message'] !== null) : ?>
            <div class="message <?= $test['badge'] === 'fail' ? 'fail' : 'notice' ?>">
            <b><?= $h($test['messageLabel']) ?></b>
            <pre><?= $h($test['message']) ?></pre>
            </div>
        <?php endif; ?>
        <?php if ($test['steps'] !== []) : ?>
            <div class="shots">
            <?php foreach ($test['steps'] as $step) : ?>
                <div class="step">
                <?php foreach ($step['images'] as $image) : ?>
                    <figure>
                    <img src="<?= $h(rawurlencode($image['file'])) ?>" alt="" style="<?= $h($image['style']) ?>">
                    <figcaption>
                    <span class="name"><?= $h($step['label']) ?></span>
                    <span class="kind"><?= $h($image['mode']) ?></span><span class="path"><?= $h($image['path']) ?></span><span class="page-title"><?= $h($image['title']) ?></span>
                    </figcaption>
                    </figure>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endforeach; ?>

</body>
</html>
