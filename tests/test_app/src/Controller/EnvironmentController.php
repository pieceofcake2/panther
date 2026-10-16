<?php
/**
 * Shows an environment variable as the web server's PHP sees it, for the tests of the router script
 */

App::uses('AppController', 'Controller');

class EnvironmentController extends AppController
{
    /**
     * @var array
     */
    public $uses = [];

    /**
     * The value of a variable in `$_SERVER` and in `env()`, as JSON
     *
     * @param string $name Name of the variable
     * @return \CakeResponse
     */
    public function show($name)
    {
        $this->response->type('json');
        $this->response->body((string)json_encode([
            'server' => $_SERVER[$name] ?? null,
            'env' => env($name),
        ]));

        return $this->response;
    }
}
