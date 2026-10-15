<?php
/**
 * Routes of the fixture application
 */

Router::connect('/', ['controller' => 'contacts', 'action' => 'index']);
// A URL with a file extension that is not a file: the router script hands it to CakePHP
Router::connect('/index.html', ['controller' => 'contacts', 'action' => 'index']);

require CAKE . 'Config' . DS . 'routes.php';
