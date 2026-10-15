<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\ServerExtension;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

class ServerExtensionTest extends TestCase
{
    public function testKeepsTheWebServerUntilTheLastTest(): void
    {
        $previous = PantherTestCase::$stopServerOnTeardown;
        PantherTestCase::$stopServerOnTeardown = true;
        try {
            $extension = new ServerExtension();
            $extension->executeBeforeFirstTest();
            $extension->executeBeforeTest('test');
            $extension->executeAfterTestFailure('test', 'Failed', 0.1);
            $extension->executeAfterTestError('test', 'Error', 0.1);
            $extension->executeAfterTest('test', 0.1);

            $this->assertFalse(PantherTestCase::$stopServerOnTeardown);
        } finally {
            PantherTestCase::$stopServerOnTeardown = $previous;
        }
    }

    public function testStopsTheWebServerAfterTheLastTest(): void
    {
        // Nothing has started in the unit tests: stopping does nothing
        (new ServerExtension())->executeAfterLastTest();

        $this->assertFalse(PantherTestCase::isWebServerStarted());
    }
}
