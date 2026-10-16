<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

/**
 * The settings of the End-to-End test report, read from `$_SERVER` like the other `PANTHER_*` variables.
 *
 * The report is written only when `PANTHER_REPORT_PDF` is set:
 *
 * | Variable | Default | |
 * |---|---|---|
 * | `PANTHER_REPORT_PDF` | | Path of the PDF file to write |
 * | `PANTHER_REPORT_LANGUAGE` | `en` | Language of the headings and labels (`en`, `ja` or one of `PANTHER_REPORT_LOCALE_DIR`) |
 * | `PANTHER_REPORT_LOCALE_DIR` | | Directory of `<language>.json` files that add or replace languages |
 * | `PANTHER_REPORT_TITLE` | The title of the language | Title of the report |
 * | `PANTHER_REPORT_PROJECT` | The name of the Composer root package | Name of the tested project under the title |
 * | `PANTHER_REPORT_NOTE` | | A note on the cover, e.g. that the screenshots show test data |
 * | `PANTHER_REPORT_JPEG_QUALITY` | `60` | JPEG quality of the screenshots (1-100) |
 */
final class Config
{
    public const DEFAULT_LANGUAGE = 'en';
    public const DEFAULT_JPEG_QUALITY = 60;

    /**
     * Constructor
     *
     * @param string $pdf Path of the PDF file
     * @param string $language Language of the headings and labels
     * @param string|null $localeDir Directory of additional `<language>.json` files
     * @param string|null $title Title, instead of the one of the language
     * @param string|null $project Name of the tested project
     * @param string|null $note Note on the cover
     * @param int $jpegQuality JPEG quality of the screenshots
     */
    public function __construct(
        public string $pdf,
        public string $language = self::DEFAULT_LANGUAGE,
        public ?string $localeDir = null,
        public ?string $title = null,
        public ?string $project = null,
        public ?string $note = null,
        public int $jpegQuality = self::DEFAULT_JPEG_QUALITY,
    ) {
    }

    /**
     * The settings of `$_SERVER`, or null when `PANTHER_REPORT_PDF` is not set (no report)
     *
     * @param array<string, mixed> $server `$_SERVER`
     * @return self|null
     */
    public static function fromServer(array $server): ?self
    {
        $pdf = self::value($server, 'PANTHER_REPORT_PDF');
        if ($pdf === null) {
            return null;
        }

        $quality = (int)(self::value($server, 'PANTHER_REPORT_JPEG_QUALITY') ?? self::DEFAULT_JPEG_QUALITY);

        return new self(
            $pdf,
            self::value($server, 'PANTHER_REPORT_LANGUAGE') ?? self::DEFAULT_LANGUAGE,
            self::value($server, 'PANTHER_REPORT_LOCALE_DIR'),
            self::value($server, 'PANTHER_REPORT_TITLE'),
            self::value($server, 'PANTHER_REPORT_PROJECT'),
            self::value($server, 'PANTHER_REPORT_NOTE'),
            max(1, min(100, $quality)),
        );
    }

    /**
     * A non-empty string value of `$_SERVER`
     *
     * @param array<string, mixed> $server `$_SERVER`
     * @param string $name Name of the variable
     * @return string|null
     */
    private static function value(array $server, string $name): ?string
    {
        $value = $server[$name] ?? null;
        if (!is_scalar($value) || trim((string)$value) === '') {
            return null;
        }

        return (string)$value;
    }
}
