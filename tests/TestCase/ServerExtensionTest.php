<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Recorder;
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

    public function testRecordsTheRunWithPantherReportPdf(): void
    {
        $previous = Recorder::setCurrent(null);
        $server = $_SERVER;
        $stop = PantherTestCase::$stopServerOnTeardown;
        $pdf = TMP . 'report-' . bin2hex(random_bytes(4)) . '.pdf';
        $_SERVER['PANTHER_REPORT_PDF'] = $pdf;
        // A language without messages: the report fails after the tests, which does not change their result
        $_SERVER['PANTHER_REPORT_LANGUAGE'] = 'xx';
        try {
            $extension = new ServerExtension();
            $extension->executeBeforeFirstTest();
            $recorder = Recorder::current();
            $this->assertNotNull($recorder);
            $this->assertSame($pdf, $recorder->config()->pdf);
            $this->assertDirectoryExists($recorder->dir());

            $extension->executeAfterLastTest();

            $this->assertNull(Recorder::current());
            $this->assertFileDoesNotExist($pdf);
            $this->assertDirectoryDoesNotExist($recorder->dir());
        } finally {
            $_SERVER = $server;
            PantherTestCase::$stopServerOnTeardown = $stop;
            Recorder::setCurrent($previous);
        }
    }

    public function testNoRecordingWithoutPantherReportPdf(): void
    {
        $previous = Recorder::setCurrent(null);
        $stop = PantherTestCase::$stopServerOnTeardown;
        try {
            (new ServerExtension())->executeBeforeFirstTest();

            $this->assertNull(Recorder::current());
        } finally {
            PantherTestCase::$stopServerOnTeardown = $stop;
            Recorder::setCurrent($previous);
        }
    }

    public function testStopsTheWebServerAfterTheLastTest(): void
    {
        // Nothing has started in the unit tests: stopping does nothing
        (new ServerExtension())->executeAfterLastTest();

        $this->assertFalse(PantherTestCase::isWebServerStarted());
    }
}
