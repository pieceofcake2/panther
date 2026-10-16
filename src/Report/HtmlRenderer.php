<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use Composer\InstalledVersions;
use Configure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Runner\Version;

/**
 * Renders the report as an HTML page for Chrome to print (`resources/report/report.php` and `report.css`).
 *
 * The page is A4 with a header and the page numbers (CSS page margin boxes): the cover with the result and
 * where the tests ran, the list of the tests, then a page for each test with its screenshots.
 */
final class HtmlRenderer
{
    public const TEMPLATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'report';

    /**
     * Millimeters per CSS pixel of a full page in the report (a page 1366 pixels wide fills the 180 mm of A4)
     */
    public const MM_PER_PIXEL = 180 / 1366;

    /**
     * Constructor
     *
     * @param \PieceOfCake2\Panther\Report\Translator $translator Messages
     */
    public function __construct(private Translator $translator)
    {
    }

    /**
     * Renders the report of a run
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorded run
     * @param \PieceOfCake2\Panther\Report\Environment $environment Where the tests ran
     * @return string HTML
     */
    public function render(Recorder $recorder, Environment $environment): string
    {
        $config = $recorder->config();
        $results = $recorder->results();
        $timezone = new DateTimeZone(date_default_timezone_get());
        $startedAt = (new DateTimeImmutable('@' . (int)$recorder->startedAt()))->setTimezone($timezone);
        $finishedAt = (new DateTimeImmutable('@' . (int)$recorder->finishedAt()))->setTimezone($timezone);

        $title = $config->title ?? $this->t('title');
        $project = $config->project ?? self::rootPackage();
        $counts = self::counts($results);

        $view = [
            'language' => $this->translator->language(),
            'title' => $title,
            'project' => $project,
            'note' => $config->note,
            'css' => (string)file_get_contents(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'report.css'),
            'headerLeft' => trim($title . '　' . ($project ?? '')),
            'headerRight' => trim($this->t('header.run', ['date' => $startedAt->format('Y-m-d H:i')]) . '　' . ($environment->commit ?? '')),
            'passed' => $counts['failed'] === 0 && $counts['errors'] === 0,
            'counts' => $counts,
            'target' => $this->target($environment),
            'run' => $this->run($recorder, $environment, $startedAt, $finishedAt),
            'groups' => $this->groups($results),
        ];

        return self::include(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'report.php', [
            'view' => $view,
            't' => fn(string $key, array $params = []): string => $this->t($key, $params),
            'h' => static fn(?string $text): string => htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'css' => static fn(string $text): string => self::cssString($text),
        ]);
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
     * The rows of the target: repository, branch and commit
     *
     * @param \PieceOfCake2\Panther\Report\Environment $environment Environment
     * @return array<array{label: string, value: string, sub?: string|null, mono?: string|null, warning?: string|null}>
     */
    private function target(Environment $environment): array
    {
        $rows = [];
        if ($environment->repository !== null) {
            $rows[] = ['label' => $this->t('field.repository'), 'value' => $environment->repository];
        }
        if ($environment->branch !== null) {
            $rows[] = ['label' => $this->t('field.branch'), 'value' => $environment->branch];
        }
        if ($environment->commit !== null) {
            $rows[] = [
                'label' => $this->t('field.commit'),
                'mono' => $environment->commit,
                'value' => (string)$environment->commitSubject,
                'warning' => $environment->dirty ? $this->t('commit.dirty') : null,
            ];
        }

        return $rows;
    }

    /**
     * The rows of the run: date, duration, environment, browser and software
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorder
     * @param \PieceOfCake2\Panther\Report\Environment $environment Environment
     * @param \DateTimeImmutable $startedAt Start
     * @param \DateTimeImmutable $finishedAt End
     * @return array<array{label: string, value: string, sub?: string|null}>
     */
    private function run(Recorder $recorder, Environment $environment, DateTimeImmutable $startedAt, DateTimeImmutable $finishedAt): array
    {
        $end = $startedAt->format('Y-m-d') === $finishedAt->format('Y-m-d') ? $finishedAt->format('H:i:s') : $finishedAt->format('Y-m-d H:i:s');
        $rows = [
            ['label' => $this->t('field.date'), 'value' => $this->t('date.range', [
                'start' => $startedAt->format('Y-m-d H:i:s'),
                'end' => $end,
                'timezone' => $startedAt->getTimezone()->getName(),
            ])],
            ['label' => $this->t('field.duration'), 'value' => $this->duration($recorder->finishedAt() - $recorder->startedAt())],
        ];
        if ($environment->run !== null) {
            $rows[] = ['label' => $this->t('field.environment'), 'value' => $environment->run, 'sub' => $environment->runUrl];
        } elseif ($environment->os !== null) {
            $rows[] = ['label' => $this->t('field.environment'), 'value' => $this->t('environment.local', ['os' => $environment->os])];
        }

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
            $rows[] = ['label' => $this->t('field.browser'), 'value' => trim($browser . '　' . implode(' / ', array_keys($sizes)))];
        }

        $software = ['PHP ' . PHP_VERSION, 'PHPUnit ' . Version::id()];
        if (class_exists(Configure::class)) {
            $software[] = 'CakePHP ' . Configure::version();
        }
        if (InstalledVersions::isInstalled('pieceofcake2/panther')) {
            $software[] = 'pieceofcake2/panther ' . InstalledVersions::getPrettyVersion('pieceofcake2/panther');
        }
        $rows[] = ['label' => $this->t('field.software'), 'value' => implode('　', $software)];

        return $rows;
    }

    /**
     * The tests grouped by class, numbered `<class>-<test>`
     *
     * @param array<\PieceOfCake2\Panther\Report\TestResult> $results Results
     * @return array<array{no: int, title: string, tests: array<array<string, mixed>>}>
     */
    private function groups(array $results): array
    {
        $groups = [];
        foreach ($results as $result) {
            $groups[$result->className] ??= ['no' => count($groups) + 1, 'title' => $result->classTitle, 'tests' => []];
            $group = &$groups[$result->className];
            $no = $group['no'] . '-' . (count($group['tests']) + 1);
            $group['tests'][] = [
                'no' => $no,
                'id' => 't' . $no,
                'title' => $result->dataSet !== null && !$result->titleHasData
                    ? $this->t('data_set', ['name' => $result->title, 'data' => $result->dataSet])
                    : $result->title,
                'name' => $result->name,
                'status' => $result->status,
                'statusLabel' => $this->t('status.' . $result->status),
                'badge' => match ($result->status) {
                    TestResult::PASSED => 'pass',
                    TestResult::FAILED, TestResult::ERROR => 'fail',
                    default => 'skip',
                },
                'time' => $this->t('time', ['seconds' => number_format($result->time, 1)]),
                'description' => $result->description,
                'message' => $result->status === TestResult::PASSED || $result->message === null
                    ? null
                    : $result->message . ($result->location !== null ? "\n" . $result->location : ''),
                'messageLabel' => $this->t('message.' . $result->status),
                'steps' => $this->steps($result),
            ];
            unset($group);
        }

        return array_values($groups);
    }

    /**
     * The screenshots of a test by step
     *
     * @param \PieceOfCake2\Panther\Report\TestResult $result Result
     * @return array<array{label: string, images: array<array<string, mixed>>}>
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
            $images = [];
            foreach ($screenshots as $screenshot) {
                $mode = $screenshot->mode;
                if ($mode === 'Viewport') {
                    $mode .= ' ' . $screenshot->viewportWidth . '×' . $screenshot->viewportHeight;
                }
                if ($screenshot->parts > 1) {
                    $mode .= ' ' . $screenshot->part . '/' . $screenshot->parts;
                }
                $class = $screenshot->height > $screenshot->width ? 'portrait' : '';
                $style = '';
                if ($screenshot->mode === 'Full page' || str_starts_with($screenshot->mode, 'Fit to page')) {
                    // A full page (or a page of it) keeps the scale of the report, so that it fills a page
                    $class = 'full';
                    $style = sprintf('width: %.1fmm', $screenshot->viewportWidth * self::MM_PER_PIXEL);
                }
                $images[] = [
                    'file' => $screenshot->file,
                    'mode' => $mode,
                    'path' => $screenshot->path,
                    'title' => $screenshot->title,
                    'class' => $class,
                    'style' => $style,
                ];
            }
            $view[] = ['label' => $label, 'images' => $images];
        }

        return $view;
    }

    /**
     * A duration, e.g. `2m 14s`
     *
     * @param float $seconds Seconds
     * @return string
     */
    private function duration(float $seconds): string
    {
        $total = (int)round($seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $rest = $total % 60;
        if ($hours > 0) {
            return $this->t('duration.hours', ['hours' => $hours, 'minutes' => sprintf('%02d', $minutes), 'seconds' => sprintf('%02d', $rest)]);
        }
        if ($minutes > 0) {
            return $this->t('duration.minutes', ['minutes' => $minutes, 'seconds' => $rest]);
        }

        return $this->t('duration.seconds', ['seconds' => $rest]);
    }

    /**
     * The name of the Composer root package, e.g. `vendor/app`
     *
     * @return string|null
     */
    private static function rootPackage(): ?string
    {
        $name = InstalledVersions::getRootPackage()['name'];

        return $name === '' || $name === '__root__' ? null : $name;
    }

    /**
     * A CSS string, e.g. for the `content` of a page margin box
     *
     * @param string $text Text
     * @return string
     */
    private static function cssString(string $text): string
    {
        return '"' . (string)preg_replace_callback('/[\\\\"\x00-\x1f\x7f<>]/', static fn(array $m): string => sprintf('\\%X ', ord($m[0])), $text) . '"';
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
