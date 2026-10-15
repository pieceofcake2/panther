<?php
/**
 * @var \View $this
 * @var array<string, mixed> $contact
 */
?>
<h1>Thanks</h1>
<dl>
    <dt>Name</dt><dd id="name"><?= h($contact['name'] ?? '') ?></dd>
    <dt>Topic</dt><dd id="topic"><?= h($contact['topic'] ?? '') ?></dd>
    <dt>Newsletter</dt><dd id="newsletter"><?= !empty($contact['newsletter']) ? 'yes' : 'no' ?></dd>
</dl>
<?= $this->Html->link('Back to the top', '/') ?>
