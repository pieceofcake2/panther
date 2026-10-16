<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\TestSuite;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

class PantherTestCaseTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'panther_test_root_' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (['webroot', 'app/webroot', 'app'] as $dir) {
            if (is_dir($this->root . '/' . $dir)) {
                rmdir($this->root . '/' . $dir);
            }
        }
        rmdir($this->root);
    }

    public function testDefaultWebServerDirIsWebroot(): void
    {
        mkdir($this->root . '/webroot');

        $this->assertSame($this->root . DIRECTORY_SEPARATOR . 'webroot', TestPantherTestCase::webServerDirOf($this->root));
    }

    public function testDefaultWebServerDirOfTheLegacyLayout(): void
    {
        mkdir($this->root . '/app/webroot', 0777, true);

        $this->assertSame(
            $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'webroot',
            TestPantherTestCase::webServerDirOf($this->root . DIRECTORY_SEPARATOR),
        );
    }

    public function testDefaultWebServerDirOfTheComposerRootPackage(): void
    {
        $this->assertSame(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'webroot', TestPantherTestCase::webServerDirOf(null));
    }

    public function testScreenshotNameReplacesCharactersNotAllowedInArtifacts(): void
    {
        $this->assertSame(
            'App\\Test\\FooTest::testBar with data set _a_b_ (_x_y_)',
            PantherTestCase::screenshotName('App\\Test\\FooTest::testBar with data set "a/b" (<x|y>)'),
        );
        $this->assertSame('FooTest::testBar_with__', PantherTestCase::screenshotName("FooTest::testBar\nwith*?"));
    }

    public function testRouter(): void
    {
        $this->assertFileExists(PantherTestCase::ROUTER);
    }
}
