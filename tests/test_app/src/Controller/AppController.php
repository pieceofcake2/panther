<?php
/**
 * Application controller of the fixture application
 */

App::uses('Controller', 'Controller');

class AppController extends Controller
{
    /**
     * @var array
     */
    public $components = ['Session', 'Flash', 'Security'];

    /**
     * @var array
     */
    public $helpers = ['Html', 'Form', 'Flash'];
}
