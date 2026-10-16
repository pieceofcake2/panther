<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use ReflectionProperty;
use Symfony\Component\Panther\Client;

/**
 * Takes the screenshots of the report with a Panther client.
 *
 * With Chrome, the screenshots are taken with the DevTools Protocol as JPEG images of 1 pixel per CSS pixel, so
 * that a page of a mobile emulation (`deviceScaleFactor` of 3) is not 9 times as large, and a full page is taken
 * in parts that fill the pages of the report: the first one what fits under the title of the test, the next ones
 * what fits on a page, each cut between two rows of the page. With another browser, they are the PNG screenshots of
 * WebDriver, of the viewport.
 */
final class Screenshotter
{
    /**
     * Millimeters per CSS pixel of the screenshots in the report, the same for every mode: a viewport of 1200 x 957
     * is 120 x 96 mm, so that two fit on a page
     */
    public const MM_PER_PIXEL = 0.1;

    /**
     * Millimeters per CSS pixel of the screenshots of a narrow viewport (a phone), whose text would be too small:
     * a viewport of 390 x 844 is 55 x 118 mm
     */
    public const MM_PER_PIXEL_NARROW = 0.14;

    /**
     * A viewport narrower than this is a phone (CSS pixels), as the usual breakpoint of a tablet
     */
    public const NARROW_WIDTH = 768;

    /**
     * The height of the first part of a full page (mm): what fits on the page of the test under its title
     */
    public const FIRST_PART_MM = 190;

    /**
     * The height of the next parts of a full page (mm): what fits on a page of the report with its caption
     */
    public const PART_MM = 230;

    /**
     * The most parts of a full page
     */
    public const MAX_PARTS = 20;

    /**
     * Constructor
     *
     * @param int $jpegQuality JPEG quality (1-100)
     */
    public function __construct(private int $jpegQuality = Config::DEFAULT_JPEG_QUALITY)
    {
    }

    /**
     * Whether the browser of the client is running: a client that quit (e.g. `stopWebServer()` in the test) would
     * start a new browser with `getWebDriver()`, and Panther has no public method to tell
     *
     * @param \Symfony\Component\Panther\Client $client Client
     * @return bool
     */
    public static function isRunning(Client $client): bool
    {
        $property = new ReflectionProperty(Client::class, 'webDriver');
        if (PHP_VERSION_ID < 80100) {
            // Needed on PHP 8.0 only, deprecated on PHP 8.5
            $property->setAccessible(true);
        }

        return $property->getValue($client) !== null;
    }

    /**
     * Takes the screenshots of a step
     *
     * @param \Symfony\Component\Panther\Client $client Client
     * @param string $dir Directory of the image files
     * @param string $prefix Prefix of the file names
     * @param int $step Number of the step
     * @param string|null $name Name of the step
     * @param string|null $type `Screenshot::END`, `Screenshot::FAILURE` or null
     * @param string|null $element CSS selector or XPath of an element to scroll to before taking the viewport
     * @param bool $fullPage Takes the whole page instead of the viewport
     * @param bool $fitToPage Takes what fits on a page of the report (`FIRST_PART_MM`), from the top of the page or of
     *   the element, instead of the viewport
     * @return array<\PieceOfCake2\Panther\Report\Screenshot>
     */
    public function take(Client $client, string $dir, string $prefix, int $step, ?string $name, ?string $type, ?string $element = null, bool $fullPage = false, bool $fitToPage = false): array
    {
        $mode = 'Viewport';
        $top = 0.0;
        if ($element !== null) {
            $top = $this->scrollTo($client, $element);
            $mode = 'Element: ' . $element;
            $fullPage = false;
        }
        if ($fitToPage && !$fullPage) {
            $mode = 'Fit to page' . ($element !== null ? ': ' . $element : '');
        }

        /** @var array{string, string, int, int} $page */
        $page = $client->executeScript('return [location.pathname + location.search, document.title, window.innerWidth, window.innerHeight];');
        [$path, $title, $viewportWidth, $viewportHeight] = $page;

        $devTools = $this->devTools($client);
        $images = [];
        if ($devTools === null) {
            $images[] = ['png', (string)$client->takeScreenshot()];
        } elseif ($fullPage) {
            $mode = 'Full page';
            $images = $this->fullPage($devTools, (int)$viewportWidth);
        } elseif ($fitToPage) {
            $images[] = ['jpg', $this->fitToPage($devTools, $top, (int)$viewportWidth)];
        } else {
            $images[] = ['jpg', $this->capture($devTools, null)];
        }

        $screenshots = [];
        foreach ($images as $i => [$extension, $data]) {
            $file = sprintf('%s-%02d-%02d.%s', $prefix, $step, $i + 1, $extension);
            file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $data);
            $size = getimagesizefromstring($data) ?: [0, 0];
            $screenshots[] = new Screenshot(
                $file,
                $step,
                $name,
                $type,
                $mode,
                $i + 1,
                count($images),
                (string)$path,
                (string)$title,
                (int)$viewportWidth,
                (int)$viewportHeight,
                (int)$size[0],
                (int)$size[1],
            );
        }

        return $screenshots;
    }

    /**
     * Scrolls the element to the top of the viewport
     *
     * @param \Symfony\Component\Panther\Client $client Client
     * @param string $element CSS selector or XPath
     * @return float The top of the element in the page (CSS pixels)
     */
    private function scrollTo(Client $client, string $element): float
    {
        $by = preg_match('#\A(\.?/|\()#', $element) ? WebDriverBy::xpath($element) : WebDriverBy::cssSelector($element);
        $found = $client->getWebDriver()->findElement($by);

        return (float)$client->executeScript(
            'arguments[0].scrollIntoView({block: "start", inline: "nearest"}); return arguments[0].getBoundingClientRect().top + window.scrollY;',
            [$found],
        );
    }

    /**
     * The DevTools of Chrome, or null for another browser
     *
     * @param \Symfony\Component\Panther\Client $client Client
     * @return \Facebook\WebDriver\Chrome\ChromeDevToolsDriver|null
     */
    private function devTools(Client $client): ?ChromeDevToolsDriver
    {
        $driver = $client->getWebDriver();
        if (!$driver instanceof RemoteWebDriver) {
            return null;
        }
        $browser = strtolower((string)$driver->getCapabilities()?->getBrowserName());

        return str_contains($browser, 'chrome') ? new ChromeDevToolsDriver($driver) : null;
    }

    /**
     * The parts of the whole page
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @param int $viewportWidth Width of the viewport (CSS pixels), for the scale in the report
     * @return array<array{string, string}>
     */
    private function fullPage(ChromeDevToolsDriver $devTools, int $viewportWidth): array
    {
        $metrics = $devTools->execute('Page.getLayoutMetrics');
        $width = (int)ceil((float)($metrics['cssLayoutViewport']['clientWidth'] ?? 0));
        $height = (int)ceil((float)($metrics['cssContentSize']['height'] ?? 0));
        if ($width <= 0 || $height <= 0) {
            return [['jpg', $this->capture($devTools, null)]];
        }

        $images = [];
        $scale = self::scale($viewportWidth);
        $first = (int)floor(self::FIRST_PART_MM / $scale);
        $next = (int)floor(self::PART_MM / $scale);
        foreach (self::parts($height, $this->breaks($devTools), $first, $next) as [$top, $bottom]) {
            $clip = ['x' => 0, 'y' => $top, 'width' => $width, 'height' => $bottom - $top];
            $images[] = ['jpg', $this->capture($devTools, $clip)];
        }

        return $images;
    }

    /**
     * Where a page can be cut: the top and the bottom of its wide elements (rows, sections, headings...), between
     * which no text or field is cut
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @return array<int> Positions in the page (CSS pixels)
     */
    private function breaks(ChromeDevToolsDriver $devTools): array
    {
        $script = <<<'JS'
            (() => {
                const width = document.documentElement.clientWidth;
                const breaks = new Set();
                for (const element of document.body.querySelectorAll('*')) {
                    const rect = element.getBoundingClientRect();
                    if (rect.width >= width * 0.6 && rect.height > 0) {
                        breaks.add(Math.round(rect.top + window.scrollY));
                        breaks.add(Math.round(rect.bottom + window.scrollY));
                    }
                }
                return Array.from(breaks);
            })()
            JS;
        $result = $devTools->execute('Runtime.evaluate', ['expression' => $script, 'returnByValue' => true]);
        $breaks = array_map('intval', (array)($result['result']['value'] ?? []));
        sort($breaks);

        return $breaks;
    }

    /**
     * Millimeters per CSS pixel of a screenshot in the report
     *
     * @param int $viewportWidth Width of the viewport (CSS pixels)
     * @return float
     */
    public static function scale(int $viewportWidth): float
    {
        return $viewportWidth > 0 && $viewportWidth < self::NARROW_WIDTH ? self::MM_PER_PIXEL_NARROW : self::MM_PER_PIXEL;
    }

    /**
     * The parts of a page that fill the pages of the report: the first one at most `$first` high (under the title of
     * the test), the next ones at most `$next` (a page), each cut at the lowest break in its second half, so that no
     * line is cut in two
     *
     * @param int $height Height of the page (CSS pixels)
     * @param array<int> $breaks Where the page can be cut, sorted
     * @param int $first Most height of the first part (CSS pixels)
     * @param int $next Most height of the next parts (CSS pixels)
     * @return array<array{int, int}> The top and the bottom of each part, at most `MAX_PARTS`
     */
    public static function parts(int $height, array $breaks, int $first, int $next): array
    {
        $parts = [];
        $top = 0;
        for ($count = 0; $top < $height && $count < self::MAX_PARTS; $count++) {
            $most = $count === 0 ? $first : $next;
            if ($height - $top <= $most) {
                $parts[] = [$top, $height];
                break;
            }
            // The lowest break in the second half of the part; none: cut at its height, as a block taller than a
            // page has to be cut anyway
            $bottom = $top + $most;
            $min = $top + intdiv($most, 2);
            $candidates = array_filter($breaks, static fn(int $break): bool => $break >= $min && $break <= $top + $most);
            if ($candidates !== []) {
                $bottom = max($candidates);
            }
            $parts[] = [$top, $bottom];
            $top = $bottom;
        }

        return $parts;
    }

    /**
     * What fits on a page of the report under the title of the test (`FIRST_PART_MM`), from a position of the page
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @param float $top Top of the area in the page (CSS pixels)
     * @param int $viewportWidth Width of the viewport (CSS pixels), for the scale in the report
     * @return string
     */
    private function fitToPage(ChromeDevToolsDriver $devTools, float $top, int $viewportWidth): string
    {
        $metrics = $devTools->execute('Page.getLayoutMetrics');
        $width = (int)ceil((float)($metrics['cssLayoutViewport']['clientWidth'] ?? 0));
        $height = (int)ceil((float)($metrics['cssContentSize']['height'] ?? 0));
        $top = max(0, min((int)floor($top), $height - 1));
        if ($width <= 0 || $height <= 0) {
            return $this->capture($devTools, null);
        }

        $most = (int)floor(self::FIRST_PART_MM / self::scale($viewportWidth));

        return $this->capture($devTools, ['x' => 0, 'y' => $top, 'width' => $width, 'height' => min($most, $height - $top)]);
    }

    /**
     * Takes a JPEG image of 1 pixel per CSS pixel
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @param array<string, int|float>|null $clip Area of the page (CSS pixels), or null for the viewport
     * @return string
     */
    private function capture(ChromeDevToolsDriver $devTools, ?array $clip): string
    {
        $params = ['format' => 'jpeg', 'quality' => $this->jpegQuality];
        if ($clip === null) {
            // The viewport, scaled down to CSS pixels when the device scale factor is not 1
            $metrics = $devTools->execute('Page.getLayoutMetrics');
            $viewport = $metrics['cssVisualViewport'] ?? [];
            $clip = [
                'x' => (float)($viewport['pageX'] ?? 0),
                'y' => (float)($viewport['pageY'] ?? 0),
                'width' => (float)($viewport['clientWidth'] ?? 0),
                'height' => (float)($viewport['clientHeight'] ?? 0),
            ];
            if ($clip['width'] <= 0 || $clip['height'] <= 0) {
                $clip = null;
            }
        } else {
            $params['captureBeyondViewport'] = true;
        }
        if ($clip !== null) {
            // The scale of the clip multiplies the device scale factor: 1 / ratio makes 1 pixel per CSS pixel
            $clip['scale'] = 1 / $this->devicePixelRatio($devTools);
            $params['clip'] = $clip;
        }

        $result = $devTools->execute('Page.captureScreenshot', $params);

        return (string)base64_decode((string)($result['data'] ?? ''), true);
    }

    /**
     * The device pixel ratio of the page
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @return float
     */
    private function devicePixelRatio(ChromeDevToolsDriver $devTools): float
    {
        $result = $devTools->execute('Runtime.evaluate', ['expression' => 'window.devicePixelRatio', 'returnByValue' => true]);
        $ratio = (float)($result['result']['value'] ?? 1);

        return $ratio > 0 ? $ratio : 1.0;
    }
}
