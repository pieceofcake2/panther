<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * The variables Panther passes to the web server reach `$_SERVER` of the application, as with Apache or PHP-FPM
 */
class EnvironmentTest extends PantherTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The web server of the earlier tests was started without the variable
        static::stopWebServer();
    }

    public static function tearDownAfterClass(): void
    {
        static::stopWebServer();
        parent::tearDownAfterClass();
    }

    public function testVariablesPassedToTheWebServerAreInServer(): void
    {
        $client = static::createPantherClient(['env' => ['PANTHER_FIXTURE_VALUE' => 'from-the-test']]);
        $client->request('GET', '/environment/show/PANTHER_FIXTURE_VALUE');

        $this->assertSame(
            ['server' => 'from-the-test', 'env' => 'from-the-test'],
            json_decode($client->executeScript('return document.body.innerText;'), true),
        );
    }

    public function testDefaultOptionsEnvIsPassedToTheWebServer(): void
    {
        static::stopWebServer();
        $default = static::$defaultOptions['env'];
        static::$defaultOptions['env'] = ['PANTHER_FIXTURE_VALUE' => 'from-the-default-options'];
        try {
            $client = static::createPantherClient();
            $client->request('GET', '/environment/show/PANTHER_FIXTURE_VALUE');

            $this->assertSame(
                ['server' => 'from-the-default-options', 'env' => 'from-the-default-options'],
                json_decode($client->executeScript('return document.body.innerText;'), true),
            );
        } finally {
            static::$defaultOptions['env'] = $default;
        }
    }
}
