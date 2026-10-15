<?php
/**
 * Messages of the fixture application
 */

class MessageFixture extends CakeTestFixture
{
    /**
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer', 'key' => 'primary'],
        'body' => ['type' => 'string', 'length' => 255, 'null' => false],
        'created' => ['type' => 'datetime', 'null' => true],
    ];

    /**
     * @var array
     */
    public $records = [
        ['id' => 1, 'body' => 'Hello from the fixture', 'created' => '2026-01-01 00:00:00'],
    ];
}
