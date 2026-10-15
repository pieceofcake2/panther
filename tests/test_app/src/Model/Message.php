<?php
/**
 * Message of the guest book of the fixture application (table `messages`)
 */

App::uses('AppModel', 'Model');

class Message extends AppModel
{
    /**
     * @var array
     */
    public $validate = [
        'body' => [
            'notBlank' => ['rule' => 'notBlank', 'message' => 'Enter a message.'],
        ],
    ];
}
