<?php
/**
 * @var \View $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title')) ?></title>
    <?= $this->Html->css('app') ?>
</head>
<body>
    <main>
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>
    <?= $this->Html->script('app') ?>
</body>
</html>
