<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes the PDF report of a recorded run, and removes its temporary files.
 */
final class ReportWriter
{
    /**
     * Writes the report to `PANTHER_REPORT_PDF`
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorded run
     * @param \PieceOfCake2\Panther\Report\Environment|null $environment Where the tests ran, default: detected
     * @param \PieceOfCake2\Panther\Report\PdfPrinter|null $printer Printer
     * @return void
     */
    public function write(Recorder $recorder, ?Environment $environment = null, ?PdfPrinter $printer = null): void
    {
        $config = $recorder->config();
        $recorder->finish();
        try {
            $translator = new Translator($config->language, $config->localeDir);
            $html = (new HtmlRenderer($translator))->render($recorder, $environment ?? Environment::detect());
            $file = $recorder->dir() . DIRECTORY_SEPARATOR . 'report.html';
            file_put_contents($file, $html);
            ($printer ?? new PdfPrinter())->print($file, $config->pdf);
        } finally {
            self::remove($recorder->dir());
        }
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
