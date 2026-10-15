<?php
/**
 * @var \View $this
 * @var array<string, string> $topics
 */
?>
<h1>Contact us</h1>
<?= $this->Form->create('Contact', ['novalidate' => true]) ?>
<?= $this->Form->input('name', ['label' => 'Your name']) ?>
<?= $this->Form->input('email', ['label' => 'Email address']) ?>
<?= $this->Form->input('topic', ['label' => 'Topic', 'options' => $topics]) ?>
<?= $this->Form->input('newsletter', ['type' => 'checkbox', 'label' => 'Send me the newsletter']) ?>
<?= $this->Form->input('agree', ['type' => 'checkbox', 'label' => 'I agree to the terms']) ?>
<?= $this->Form->input('message', ['type' => 'textarea', 'label' => 'Message']) ?>
<?= $this->Form->button('Send message', ['type' => 'submit']) ?>
<?= $this->Form->end() ?>
