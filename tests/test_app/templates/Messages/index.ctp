<?php
/**
 * @var \View $this
 * @var array<array<string, mixed>> $messages
 */
?>
<h1>Guest book</h1>
<ul id="messages">
    <?php foreach ($messages as $message) : ?>
        <li><?= h($message['Message']['body']) ?></li>
    <?php endforeach; ?>
</ul>
<?= $this->Form->create('Message', ['url' => ['controller' => 'messages', 'action' => 'index']]) ?>
<?= $this->Form->input('body', ['label' => 'Your message']) ?>
<?= $this->Form->end('Post') ?>
