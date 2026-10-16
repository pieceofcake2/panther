<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use RuntimeException;
use Symfony\Component\Panther\ProcessManager\ChromeManager;

/**
 * Prints an HTML file to PDF with a headless Chrome of its own (`Page.printToPDF` of the DevTools Protocol).
 *
 * It uses a new Chrome, not the browser of the tests, which may be in any state (a mobile emulation, another
 * window size, `PANTHER_NO_HEADLESS`), with the chromedriver that Panther finds (`drivers/` or the `PATH`) and
 * `PANTHER_CHROME_BINARY`. `PANTHER_NO_SANDBOX` disables the sandbox, as for the tests.
 */
final class PdfPrinter
{
    /**
     * Prints the HTML file to the PDF file
     *
     * @param string $html Path of the HTML file
     * @param string $pdf Path of the PDF file
     * @return void
     * @throws \RuntimeException When Chrome cannot print the page
     */
    public function print(string $html, string $pdf): void
    {
        $arguments = ['--headless', '--disable-gpu', '--hide-scrollbars'];
        if (filter_var($_SERVER['PANTHER_NO_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $arguments[] = '--no-sandbox';
        }

        $manager = new ChromeManager(null, $arguments, ['port' => self::freePort(), 'request_timeout_in_ms' => 120000]);
        try {
            $driver = $manager->start();
            try {
                if (!$driver instanceof RemoteWebDriver) {
                    throw new RuntimeException('Chrome is needed to print the report.');
                }
                $driver->get(self::fileUrl($html));
                $result = (new ChromeDevToolsDriver($driver))->execute('Page.printToPDF', [
                    'printBackground' => true,
                    'preferCSSPageSize' => true,
                    // The bookmarks of the PDF, from the headings (Chrome 125+)
                    'generateDocumentOutline' => true,
                ]);
            } finally {
                $driver->quit();
            }
        } finally {
            $manager->quit();
        }

        $data = base64_decode((string)($result['data'] ?? ''), true);
        if ($data === false || !str_starts_with($data, '%PDF')) {
            throw new RuntimeException('Chrome did not return a PDF.');
        }

        $dir = dirname($pdf);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create the directory of the report: %s', $dir));
        }
        if (file_put_contents($pdf, $data) === false) {
            throw new RuntimeException(sprintf('Cannot write the report: %s', $pdf));
        }
    }

    /**
     * A free port for chromedriver, so that it does not conflict with the one of the tests
     *
     * @return int
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            return 9516;
        }
        $name = (string)stream_socket_get_name($socket, false);
        fclose($socket);

        return (int)substr((string)strrchr($name, ':'), 1);
    }

    /**
     * The `file:` URL of a local file
     *
     * @param string $path Path
     * @return string
     */
    private static function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', (string)realpath($path));

        return 'file://' . (str_starts_with($path, '/') ? '' : '/') . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
