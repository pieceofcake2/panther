<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\TestSuite;

use CakeTestCase;
use Composer\InstalledVersions;
use Symfony\Component\Panther\PantherTestCaseTrait;

/**
 * Base class for End-to-End tests of a CakePHP 2 application with Symfony Panther.
 *
 * It is a `CakeTestCase` that uses Panther's `PantherTestCaseTrait`, so `$fixtures` work as usual and
 * `static::createPantherClient()` starts the application with PHP's built-in web server and a Chrome browser,
 * as in Symfony's `PantherTestCase`:
 *
 * ```
 * class MessagesTest extends PantherTestCase
 * {
 *     public $fixtures = ['app.message'];
 *
 *     public function testIndex(): void
 *     {
 *         $client = static::createPantherClient();
 *         $client->request('GET', '/messages');
 *
 *         $this->assertSelectorTextContains('#messages', 'Hello');
 *     }
 * }
 * ```
 *
 * On top of Panther, for CakePHP 2:
 *
 * - The web server directory defaults to `webroot/` (or `app/webroot/` for the legacy layout) of the project
 * - The web server router defaults to the CakePHP router script of this package (`resources/router.php`)
 * - The assertions of Panther's `WebTestAssertionsTrait`, without the Symfony FrameworkBundle
 *
 * Every other setting is Panther's: `PANTHER_WEB_SERVER_DIR`, `PANTHER_WEB_SERVER_PORT`,
 * `PANTHER_WEB_SERVER_ROUTER`, `PANTHER_EXTERNAL_BASE_URI`, `PANTHER_ERROR_SCREENSHOT_DIR`, `PANTHER_NO_HEADLESS`,
 * `PANTHER_CHROME_ARGUMENTS` and the others, and the options of `createPantherClient()`.
 */
abstract class PantherTestCase extends CakeTestCase
{
    use PantherTestCaseTrait {
        startWebServer as private startPantherWebServer;
    }
    use WebTestAssertionsTrait;

    public const CHROME = 'chrome';
    public const FIREFOX = 'firefox';
    public const SELENIUM = 'selenium';

    /**
     * The CakePHP router script of the PHP built-in web server
     */
    public const ROUTER = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'router.php';

    /**
     * Takes a screenshot of the browsers when the test failed (`PANTHER_ERROR_SCREENSHOT_DIR`), like Panther.
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->takeScreenshotIfTestFailed();
    }

    /**
     * Starts the PHP built-in web server with the CakePHP defaults, then as Panther does.
     *
     * @param array<string, mixed> $options See `PantherTestCaseTrait::$defaultOptions`
     * @return void
     */
    public static function startWebServer(array $options = []): void
    {
        if (!isset($options['webServerDir']) && static::$webServerDir === null && !isset($_SERVER['PANTHER_WEB_SERVER_DIR'])) {
            $options['webServerDir'] = static::defaultWebServerDir();
        }
        $options['router'] ??= $_SERVER['PANTHER_WEB_SERVER_ROUTER'] ?? (realpath(static::ROUTER) ?: static::ROUTER);

        self::startPantherWebServer($options);
    }

    /**
     * The web server directory used when neither the `webServerDir` option nor `PANTHER_WEB_SERVER_DIR` is set:
     * `webroot/` of the project, or `app/webroot/` when only that one exists (the legacy layout).
     *
     * @param string|null $projectRoot Default: the Composer root package
     * @return string
     */
    protected static function defaultWebServerDir(?string $projectRoot = null): string
    {
        if ($projectRoot === null) {
            $installPath = (string)InstalledVersions::getRootPackage()['install_path'];
            $projectRoot = realpath($installPath) ?: $installPath;
        }
        $projectRoot = rtrim($projectRoot, '/\\');
        $legacy = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'webroot';
        if (!is_dir($projectRoot . DIRECTORY_SEPARATOR . 'webroot') && is_dir($legacy)) {
            return $legacy;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . 'webroot';
    }
}
