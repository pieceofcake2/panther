<?php
/**
 * Contact message of the fixture application (no table)
 */

App::uses('AppModel', 'Model');

class Contact extends AppModel
{
    /**
     * @var string|false
     */
    public $useTable = false;

    /**
     * @var array
     */
    protected $_schema = [
        'name' => ['type' => 'string', 'length' => 100, 'null' => false],
        'email' => ['type' => 'string', 'length' => 255, 'null' => false],
        'topic' => ['type' => 'string', 'length' => 20, 'null' => false],
        'newsletter' => ['type' => 'boolean', 'null' => false],
        'agree' => ['type' => 'boolean', 'null' => false],
        'message' => ['type' => 'text', 'null' => true],
    ];

    /**
     * @var array
     */
    public $validate = [
        'name' => [
            'notBlank' => ['rule' => 'notBlank', 'message' => 'Enter your name.'],
        ],
        'email' => [
            'email' => ['rule' => 'email', 'message' => 'Enter a valid email address.'],
        ],
        'agree' => [
            'agree' => ['rule' => ['equalTo', '1'], 'message' => 'Agree to the terms.'],
        ],
    ];
}
