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
 * that a page of a mobile emulation (`deviceScaleFactor` of 3) is not 9 times as large. A full page is taken as one
 * image with the positions of its rows and sections (`Screenshot::$breaks`), where the report cuts it between its
 * pages. With another browser, they are the PNG screenshots of WebDriver, of the viewport.
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
     * The height of a screenshot that fits on a page (mm): what fits on the page of a test under its title
     */
    public const FIT_TO_PAGE_MM = 190;

    /**
     * The most height of an image of a full page (CSS pixels): Chrome cannot take a taller one. A taller page is taken
     * in several images
     */
    public const MAX_HEIGHT = 16000;

    /**
     * The most images of a full page
     */
    public const MAX_IMAGES = 5;

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
     * @param bool $fitToPage Takes what fits on a page of the report (`FIT_TO_PAGE_MM`), from the top of the page or of
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
            $images[] = ['png', (string)$client->takeScreenshot(), []];
        } elseif ($fullPage) {
            $mode = 'Full page';
            $images = $this->fullPage($devTools);
        } elseif ($fitToPage) {
            $images[] = ['jpg', $this->fitToPage($devTools, $top, (int)$viewportWidth), []];
        } else {
            $images[] = ['jpg', $this->capture($devTools, null), []];
        }

        $screenshots = [];
        foreach ($images as $i => [$extension, $data, $breaks]) {
            $file = sprintf('%s-%02d-%02d.%s', $prefix, $step, $i + 1, $extension);
            file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $data);
            $size = getimagesizefromstring($data) ?: [0, 0];
            $screenshot = new Screenshot(
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
            $screenshot->breaks = $breaks;
            $screenshots[] = $screenshot;
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
     * The whole page as one image (several for a page taller than `MAX_HEIGHT`), with the positions of its rows and
     * sections in each image
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @return array<array{string, string, array<int>}>
     */
    private function fullPage(ChromeDevToolsDriver $devTools): array
    {
        $metrics = $devTools->execute('Page.getLayoutMetrics');
        $width = (int)ceil((float)($metrics['cssLayoutViewport']['clientWidth'] ?? 0));
        $height = (int)ceil((float)($metrics['cssContentSize']['height'] ?? 0));
        if ($width <= 0 || $height <= 0) {
            return [['jpg', $this->capture($devTools, null), []]];
        }

        $breaks = $this->breaks($devTools);
        $images = [];
        $top = 0;
        for ($count = 0; $top < $height && $count < self::MAX_IMAGES; $count++) {
            // A page taller than an image is cut at its lowest break in reach
            $bottom = min($height, $top + self::MAX_HEIGHT);
            if ($bottom < $height) {
                $inReach = array_filter($breaks, static fn(int $break): bool => $break > $top && $break < $bottom);
                $bottom = $inReach !== [] ? max($inReach) : $bottom;
            }
            $inImage = array_filter($breaks, static fn(int $break): bool => $break > $top && $break < $bottom);
            $clip = ['x' => 0, 'y' => $top, 'width' => $width, 'height' => $bottom - $top];
            $images[] = ['jpg', $this->capture($devTools, $clip), array_values(array_map(static fn(int $break): int => $break - $top, $inImage))];
            $top = $bottom;
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
     * What fits on a page of the report under the title of the test (`FIT_TO_PAGE_MM`), from a position of the page
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

        $most = (int)floor(self::FIT_TO_PAGE_MM / self::scale($viewportWidth));

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
