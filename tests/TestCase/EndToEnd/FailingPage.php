<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * A test that fails, run by ErrorScreenshotTest. PHPUnit does not collect it: the file name does not end with Test.php.
 */
class FailingPage extends PantherTestCase
{
    public function testFails(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Not on the page');
    }
}
