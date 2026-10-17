<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use FilesystemIterator;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\PdfPrinter;
use PieceOfCake2\Report\ReportWriter as Writer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes the PDF report of a recorded run with pieceofcake2/report, and removes its temporary files.
 */
final class ReportWriter
{
    /**
     * Writes the report to `PANTHER_REPORT_PDF`
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorded run
     * @param \PieceOfCake2\Report\Environment|null $environment Where the tests ran, default: detected
     * @param \PieceOfCake2\Report\PdfPrinter|null $printer Printer, default: Chrome of `PANTHER_CHROME_BINARY`, without
     *  its sandbox with `PANTHER_NO_SANDBOX`, as for the tests
     * @return void
     */
    public function write(Recorder $recorder, ?Environment $environment = null, ?PdfPrinter $printer = null): void
    {
        $config = $recorder->config();
        $recorder->finish();
        try {
            $renderer = new HtmlRenderer(HtmlRenderer::translator($config));
            $doc = $renderer->document($recorder, $environment ?? Environment::detect());
            // The HTML is written next to the screenshots, which it links to
            (new Writer($printer ?? self::printer($_SERVER)))->write($doc, $config->pdf, $recorder->dir());
        } finally {
            self::remove($recorder->dir());
        }
    }

    /**
     * The printer of the settings of the tests
     *
     * @param array<string, mixed> $server `$_SERVER`
     * @return \PieceOfCake2\Report\PdfPrinter
     */
    public static function printer(array $server): PdfPrinter
    {
        $binary = $server['PANTHER_CHROME_BINARY'] ?? null;

        return new PdfPrinter(
            is_string($binary) && $binary !== '' ? $binary : null,
            filter_var($server['PANTHER_NO_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * Removes a directory and its files
     *
     * @param string $dir Directory
     * @return void
     */
    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
