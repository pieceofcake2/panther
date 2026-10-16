<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

/**
 * A screenshot in the report: one image of a step of a test (a full page is split into several images).
 */
final class Screenshot
{
    /**
     * The screenshot taken at the end of a passed test that took no screenshot itself
     */
    public const END = 'end';

    /**
     * The screenshot taken when the test failed
     */
    public const FAILURE = 'failure';

    /**
     * Constructor
     *
     * @param string $file File name in the directory of the report
     * @param int $step Number of the step in the test, from 1
     * @param string|null $name Name given to `takeReportScreenshot()`, or null for `END` and `FAILURE`
     * @param string|null $type `END`, `FAILURE` or null
     * @param string $mode How it was taken: `Viewport`, `Element: <selector>` or `Full page`
     * @param int $part Number of the image of a full page, from 1
     * @param int $parts Number of the images of the step
     * @param string $path Path and query of the URL of the page
     * @param string $title Title of the page
     * @param int $viewportWidth Width of the viewport (CSS pixels)
     * @param int $viewportHeight Height of the viewport (CSS pixels)
     * @param int $width Width of the image (pixels)
     * @param int $height Height of the image (pixels)
     */
    public function __construct(
        public string $file,
        public int $step,
        public ?string $name,
        public ?string $type,
        public string $mode,
        public int $part,
        public int $parts,
        public string $path,
        public string $title,
        public int $viewportWidth,
        public int $viewportHeight,
        public int $width,
        public int $height,
    ) {
    }
}
