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
 * in parts of the same height, at most `PART_HEIGHT` CSS pixels, so that a part fits on a page of the report. With another browser, they are the PNG
 * screenshots of WebDriver, of the viewport.
 */
final class Screenshotter
{
    /**
     * The most height of a part of a full page (CSS pixels): about a page of A4 at the scale of the report.
     * A page is split into parts of the same height, so that the last part is not a sliver
     */
    public const PART_HEIGHT = 1500;

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
     * @param bool $fitToPage Takes what fits on a page of the report (`PART_HEIGHT`), from the top of the page or of
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
            $images = $this->fullPage($devTools);
        } elseif ($fitToPage) {
            $images[] = ['jpg', $this->fitToPage($devTools, $top)];
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
     * @return array<array{string, string}>
     */
    private function fullPage(ChromeDevToolsDriver $devTools): array
    {
        $metrics = $devTools->execute('Page.getLayoutMetrics');
        $width = (int)ceil((float)($metrics['cssLayoutViewport']['clientWidth'] ?? 0));
        $height = (int)ceil((float)($metrics['cssContentSize']['height'] ?? 0));
        if ($width <= 0 || $height <= 0) {
            return [['jpg', $this->capture($devTools, null)]];
        }

        $images = [];
        $parts = (int)ceil($height / self::PART_HEIGHT);
        $partHeight = (int)ceil($height / $parts);
        $taken = min($parts, self::MAX_PARTS);
        for ($part = 0; $part < $taken; $part++) {
            $y = $part * $partHeight;
            $clip = ['x' => 0, 'y' => $y, 'width' => $width, 'height' => min($partHeight, $height - $y)];
            $images[] = ['jpg', $this->capture($devTools, $clip)];
        }

        return $images;
    }

    /**
     * What fits on a page of the report: `PART_HEIGHT` CSS pixels of the page from a position, or less at its end
     *
     * @param \Facebook\WebDriver\Chrome\ChromeDevToolsDriver $devTools DevTools
     * @param float $top Top of the area in the page (CSS pixels)
     * @return string
     */
    private function fitToPage(ChromeDevToolsDriver $devTools, float $top): string
    {
        $metrics = $devTools->execute('Page.getLayoutMetrics');
        $width = (int)ceil((float)($metrics['cssLayoutViewport']['clientWidth'] ?? 0));
        $height = (int)ceil((float)($metrics['cssContentSize']['height'] ?? 0));
        $top = max(0, min((int)floor($top), $height - 1));
        if ($width <= 0 || $height <= 0) {
            return $this->capture($devTools, null);
        }

        return $this->capture($devTools, ['x' => 0, 'y' => $top, 'width' => $width, 'height' => min(self::PART_HEIGHT, $height - $top)]);
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
