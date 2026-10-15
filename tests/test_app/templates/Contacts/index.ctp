<?php
/**
 * @var \View $this
 */
?>
<h1>Welcome to the fixture app</h1>
<p class="hidden-text" style="display: none">Hidden paragraph</p>
<ul>
    <li><?= $this->Html->link('Contact us', ['action' => 'add']) ?></li>
    <li><?= $this->Html->link('Remember me', ['action' => 'remember']) ?></li>
    <li><?= $this->Html->link('Guest book', ['controller' => 'messages', 'action' => 'index']) ?></li>
</ul>
<button type="button" id="load-button" data-state="idle">Load more</button>
<button type="button" id="next-button" disabled>Next</button>
<div id="ajax-result"></div>
<div id="spinner" style="display: none">Loading...</div>
