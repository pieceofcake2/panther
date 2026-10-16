<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * The web server runs the application in the working directory of the tests (`PANTHER_WORKING_DIR`), so that
 * relative paths point to the same files in both processes
 */
class WorkingDirectoryTest extends PantherTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Each test starts the web server with its own options
        static::stopWebServer();
    }

    public static function tearDownAfterClass(): void
    {
        static::stopWebServer();
        parent::tearDownAfterClass();
    }

    public function testTheWebServerRunsInTheWorkingDirectoryOfTheTests(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/environment/cwd');

        $this->assertSame(['cwd' => getcwd()], $this->body($client->executeScript('return document.body.innerText;')));
    }

    public function testTheEnvOptionSetsTheWorkingDirectory(): void
    {
        $dir = (string)realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'test_app');
        $this->assertNotSame(getcwd(), $dir);

        $client = static::createPantherClient(['env' => [PantherTestCase::WORKING_DIR => $dir]]);
        $client->request('GET', '/environment/cwd');

        $this->assertSame(['cwd' => $dir], $this->body($client->executeScript('return document.body.innerText;')));
    }

    /**
     * @param mixed $text
     * @return mixed
     */
    private function body($text)
    {
        return json_decode((string)$text, true);
    }
}
