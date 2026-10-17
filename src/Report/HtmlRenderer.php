<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use Configure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Runner\Version;
use PieceOfCake2\Report\Count;
use PieceOfCake2\Report\Cover;
use PieceOfCake2\Report\Document;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\Group;
use PieceOfCake2\Report\HtmlRenderer as ReportRenderer;
use PieceOfCake2\Report\Item;
use PieceOfCake2\Report\Message;
use PieceOfCake2\Report\Row;
use PieceOfCake2\Report\Translator;
use PieceOfCake2\Report\Verdict;

/**
 * Renders the report of a run as an HTML page for Chrome to print, with pieceofcake2/report: the cover with the
 * result and where the tests ran, the list of the tests, then a page for each test with its screenshots
 * (`resources/report/steps.php` and `report.css`).
 */
final class HtmlRenderer
{
    public const TEMPLATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'report';

    /**
     * The directory of the messages of the test report
     */
    public const LOCALE_DIR = self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'locales';

    /**
     * The most width of a screenshot (mm): the width of the page
     */
    public const MAX_WIDTH_MM = 179;

    /**
     * How much a band of a full page goes under the next one (mm), so that no seam shows between them
     */
    public const BAND_OVERLAP_MM = 0.1;

    /**
     * Constructor
     *
     * @param \PieceOfCake2\Report\Translator $translator Messages
     */
    public function __construct(private Translator $translator)
    {
    }

    /**
     * The messages of the test report in a language: those of pieceofcake2/report, of this package and of
     * `PANTHER_REPORT_LOCALE_DIR`
     *
     * @param \PieceOfCake2\Panther\Report\Config $config Settings
     * @return \PieceOfCake2\Report\Translator
     */
    public static function translator(Config $config): Translator
    {
        return new Translator($config->language, [self::LOCALE_DIR, $config->localeDir]);
    }

    /**
     * Renders the report of a run
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorded run
     * @param \PieceOfCake2\Report\Environment $environment Where the tests ran
     * @return string HTML
     */
    public function render(Recorder $recorder, Environment $environment): string
    {
        return (new ReportRenderer())->render($this->document($recorder, $environment));
    }

    /**
     * The report of a run
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorded run
     * @param \PieceOfCake2\Report\Environment $environment Where the tests ran
     * @return \PieceOfCake2\Report\Document
     */
    public function document(Recorder $recorder, Environment $environment): Document
    {
        $config = $recorder->config();
        $results = $recorder->results();
        $timezone = new DateTimeZone(date_default_timezone_get());
        $startedAt = (new DateTimeImmutable('@' . (int)$recorder->startedAt()))->setTimezone($timezone);
        $finishedAt = (new DateTimeImmutable('@' . (int)$recorder->finishedAt()))->setTimezone($timezone);
        $cover = new Cover($this->translator);

        $title = $config->title ?? $this->t('title');
        $project = $config->project ?? Cover::rootPackage();
        $counts = self::counts($results);

        return new Document(
            title: $title,
            verdict: new Verdict($counts['failed'] === 0 && $counts['errors'] === 0, $this->t($counts['failed'] === 0 && $counts['errors'] === 0 ? 'verdict.passed' : 'verdict.failed'), [
                new Count($counts['tests'], $this->t('count.tests')),
                new Count($counts['passed'], $this->t('count.passed'), Count::PASS),
                new Count($counts['failed'], $this->t('count.failed'), $counts['failed'] > 0 ? Count::FAIL : ''),
                new Count($counts['errors'], $this->t('count.errors'), $counts['errors'] > 0 ? Count::FAIL : ''),
                new Count($counts['skipped'], $this->t('count.skipped')),
            ]),
            columns: [$this->t('field.no'), $this->t('field.test'), $this->t('field.result'), $this->t('field.time')],
            groups: $this->groups($results),
            project: $project,
            target: $cover->target($environment),
            run: array_merge(
                $cover->run($startedAt, $finishedAt, $recorder->finishedAt() - $recorder->startedAt(), $environment),
                $this->software($recorder),
            ),
            note: $config->note,
            listTitle: $this->t('section.tests'),
            targetTitle: $this->t('section.target'),
            runTitle: $this->t('section.run'),
            headerLeft: Cover::headerLeft($title, $project),
            headerRight: $cover->headerRight($startedAt, $environment),
            css: (string)file_get_contents(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'report.css'),
            language: $this->translator->language(),
        );
    }

    /**
     * A message
     *
     * @param string $key Key
     * @param array<string, string|int|float> $params Placeholders
     * @return string
     */
    private function t(string $key, array $params = []): string
    {
        return $this->translator->translate($key, $params);
    }

    /**
     * The numbers of the tests by result
     *
     * @param array<\PieceOfCake2\Panther\Report\TestResult> $results Results
     * @return array{tests: int, passed: int, failed: int, errors: int, skipped: int}
     */
    private static function counts(array $results): array
    {
        $counts = ['tests' => count($results), 'passed' => 0, 'failed' => 0, 'errors' => 0, 'skipped' => 0];
        foreach ($results as $result) {
            $key = match ($result->status) {
                TestResult::FAILED => 'failed',
                TestResult::ERROR => 'errors',
                TestResult::SKIPPED, TestResult::INCOMPLETE => 'skipped',
                default => 'passed',
            };
            $counts[$key]++;
        }

        return $counts;
    }

    /**
     * The rows of the browser and the software
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorder
     * @return array<\PieceOfCake2\Report\Row>
     */
    private function software(Recorder $recorder): array
    {
        $rows = [];
        $browser = $recorder->browser();
        if ($browser !== null) {
            if ($recorder->isHeadless()) {
                $browser = $this->t('browser.headless', ['browser' => $browser]);
            }
            $sizes = [];
            foreach ($recorder->results() as $result) {
                foreach ($result->screenshots as $screenshot) {
                    $sizes[$screenshot->viewportWidth . '×' . $screenshot->viewportHeight] = true;
                }
            }
            $rows[] = new Row($this->t('field.browser'), trim($browser . '　' . implode(' / ', array_keys($sizes))));
        }

        $software = ['PHP ' . PHP_VERSION, 'PHPUnit ' . Version::id()];
        if (class_exists(Configure::class)) {
            $software[] = 'CakePHP ' . Configure::version();
        }
        $software[] = Cover::package('pieceofcake2/panther');
        $rows[] = new Row($this->t('field.software'), implode('　', array_filter($software)));

        return $rows;
    }

    /**
     * The tests grouped by class, numbered `<class>-<test>`
     *
     * @param array<\PieceOfCake2\Panther\Report\TestResult> $results Results
     * @return array<\PieceOfCake2\Report\Group>
     */
    private function groups(array $results): array
    {
        $groups = [];
        foreach ($results as $result) {
            $group = $groups[$result->className] ??= new Group(count($groups) + 1, $result->classTitle);
            $no = $group->no . '-' . (count($group->items) + 1);
            $message = $result->status === TestResult::PASSED || $result->message === null
                ? null
                : new Message(
                    $this->t('message.' . $result->status),
                    $result->message . ($result->location !== null ? "\n" . $result->location : ''),
                    in_array($result->status, [TestResult::FAILED, TestResult::ERROR], true) ? Message::FAIL : Message::NOTICE,
                );
            $time = $this->t('time', ['seconds' => number_format($result->time, 1)]);
            $steps = $this->steps($result);
            $group->items[] = new Item(
                $no,
                $result->dataSet !== null && !$result->titleHasData
                    ? $this->t('data_set', ['name' => $result->title, 'data' => $result->dataSet])
                    : $result->title,
                match ($result->status) {
                    TestResult::PASSED => 'pass',
                    TestResult::FAILED, TestResult::ERROR => 'fail',
                    default => 'skip',
                },
                $this->t('status.' . $result->status),
                $time,
                [new Row($this->t('field.test'), $result->name, monoValue: true), new Row($this->t('field.time'), $time)],
                $result->description,
                $message,
                $steps === [] ? '' : self::include(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'steps.php', [
                    'steps' => $steps,
                    'h' => static fn(?string $text): string => htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ]),
                't' . $no,
            );
        }

        return array_values($groups);
    }

    /**
     * The screenshots of a test by step, with one caption each
     *
     * @param \PieceOfCake2\Panther\Report\TestResult $result Result
     * @return array<array{label: string, mode: string, path: string, title: string, images: array<array<string, mixed>>}>
     */
    private function steps(TestResult $result): array
    {
        $steps = [];
        foreach ($result->screenshots as $screenshot) {
            $steps[$screenshot->step][] = $screenshot;
        }

        $view = [];
        foreach ($steps as $number => $screenshots) {
            $first = $screenshots[0];
            $label = $first->name ?? $this->t($first->type === Screenshot::FAILURE ? 'screenshot.failure' : 'screenshot.end');
            if (count($steps) > 1) {
                $label = $this->t('screenshot.step', ['number' => $number, 'name' => $label]);
            }
            $mode = $first->mode;
            if ($mode === 'Viewport') {
                $mode .= ' ' . $first->viewportWidth . '×' . $first->viewportHeight;
            }
            $images = [];
            foreach ($screenshots as $screenshot) {
                // The same scale for every screenshot: the width of the image in CSS pixels (without a scroll bar),
                // narrower for a viewport wider than the page
                $width = $screenshot->viewportWidth > 0 ? min($screenshot->width, $screenshot->viewportWidth) : $screenshot->width;
                $scale = Screenshotter::scale($screenshot->viewportWidth);
                if ($width > 0 && $width * $scale > self::MAX_WIDTH_MM) {
                    $scale = self::MAX_WIDTH_MM / $width;
                }
                $images[] = [
                    'file' => $screenshot->file,
                    'style' => sprintf('width: %.1fmm', $width * $scale),
                    // An image of a full page after the first one follows it without a gap
                    'continued' => $screenshot->part > 1,
                    'bands' => $screenshot->mode === 'Full page' ? self::bands($screenshot, $width * $scale, $scale) : [],
                ];
            }
            $view[] = ['label' => $label, 'mode' => $mode, 'path' => $first->path, 'title' => $first->title, 'images' => $images];
        }

        return $view;
    }

    /**
     * The bands of an image of a full page: the image between two of its breaks (rows, sections), each one shown
     * through a block that is not split between two pages, so that the page fills its pages and is cut between its
     * rows. The block clips the image: as a background, Chrome would embed the image once per band
     *
     * Each band but the last is 0.1 mm taller than its part of the image, under the next one, so that no seam
     * shows between them.
     *
     * @param \PieceOfCake2\Panther\Report\Screenshot $screenshot Screenshot of a full page
     * @param float $width Width of the image in the report (mm)
     * @param float $scale Millimeters per pixel
     * @return array<array{style: string, imageStyle: string}>
     */
    private static function bands(Screenshot $screenshot, float $width, float $scale): array
    {
        $height = $screenshot->height;
        $positions = array_filter($screenshot->breaks, static fn(int $break): bool => $break > 0 && $break < $height);
        $positions = array_values(array_unique(array_merge([0], $positions, [$height])));
        sort($positions);

        $bands = [];
        $last = count($positions) - 2;
        for ($i = 0; $i <= $last; $i++) {
            $bands[] = [
                'style' => sprintf('height: %.3fmm', ($positions[$i + 1] - $positions[$i]) * $scale + ($i < $last ? self::BAND_OVERLAP_MM : 0)),
                'imageStyle' => sprintf('width: %.3fmm; margin-top: %.3fmm', $width, -$positions[$i] * $scale),
            ];
        }

        return $bands;
    }

    /**
     * Renders a PHP template
     *
     * @param string $template Path of the template
     * @param array<string, mixed> $variables Variables of the template
     * @return string
     */
    private static function include(string $template, array $variables): string
    {
        extract($variables);
        ob_start();
        try {
            include $template;
        } finally {
            $html = (string)ob_get_clean();
        }

        return $html;
    }
}
