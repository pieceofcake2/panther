<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Config;
use PieceOfCake2\Panther\Report\Environment;
use PieceOfCake2\Panther\Report\HtmlRenderer;
use PieceOfCake2\Panther\Report\Recorder;
use PieceOfCake2\Panther\Report\Screenshot;
use PieceOfCake2\Panther\Report\TestResult;
use PieceOfCake2\Panther\Report\Translator;

class HtmlRendererTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->dir = TMP . 'report-' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    /**
     * A recorded run of three tests: one passed with steps, one failed, one skipped
     *
     * @param \PieceOfCake2\Panther\Report\Config $config Settings
     * @return \PieceOfCake2\Panther\Report\Recorder
     */
    private function recorder(Config $config): Recorder
    {
        $recorder = new Recorder($config, $this->dir);

        $passed = new TestResult('App\JobsTest::testList with data set "career"', 'App\JobsTest', 'testList', 'Jobs', 'Lists the jobs', 'career');
        $passed->time = 1.84;
        $passed->screenshots = [
            new Screenshot('0001-01-01.jpg', 1, 'Search form', null, 'Viewport', 1, 1, '/career/', 'Careers <Hilton>', 1366, 768, 1366, 768),
            new Screenshot('0001-02-01.jpg', 2, 'Whole list', null, 'Full page', 1, 2, '/career/', 'Careers', 1366, 768, 1366, 1500),
            new Screenshot('0001-02-02.jpg', 2, 'Whole list', null, 'Full page', 2, 2, '/career/', 'Careers', 1366, 768, 1366, 400),
        ];
        $recorder->add($passed);

        $failed = new TestResult('App\ApplyTest::testErrors', 'App\ApplyTest', 'testErrors', 'Applications', 'Shows the errors', null, false, 'The required fields are checked.');
        $failed->status = TestResult::FAILED;
        $failed->message = 'Failed asserting that "<b>" contains "x".';
        $failed->location = 'tests/TestCase/EndToEnd/ApplyTest.php:42';
        $failed->screenshots = [
            new Screenshot('0002-01-01.jpg', 1, null, Screenshot::FAILURE, 'Viewport', 1, 1, '/contacts/', 'Form', 390, 844, 390, 844),
        ];
        $recorder->add($failed);

        $skipped = new TestResult('App\ApplyTest::testMail', 'App\ApplyTest', 'testMail', 'Applications', 'Sends a mail');
        $skipped->status = TestResult::SKIPPED;
        $skipped->message = 'No mail server';
        $recorder->add($skipped);

        $recorder->finish();

        return $recorder;
    }

    private static function environment(): Environment
    {
        $environment = new Environment();
        $environment->repository = 'acme/app';
        $environment->branch = 'master';
        $environment->commit = 'a1b2c3d';
        $environment->commitSubject = 'feat: add "the" report';
        $environment->run = 'GitHub Actions CI #812';
        $environment->runUrl = 'https://github.com/acme/app/actions/runs/99';

        return $environment;
    }

    public function testRender(): void
    {
        $config = new Config('report.pdf', 'en', null, null, 'Acme app', 'Fictitious data.');

        $html = (new HtmlRenderer(new Translator()))->render($this->recorder($config), self::environment());

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('<h1>End-to-End Test Report</h1>', $html);
        $this->assertStringContainsString('<div class="project">Acme app</div>', $html);
        $this->assertStringContainsString('<div class="verdict failed">', $html);
        $this->assertStringContainsString('<div class="label">FAILED</div>', $html);
        $this->assertMatchesRegularExpression('#<b>3</b><span>Tests</span>.*<b class="t-pass">1</b><span>Passed</span>.*<b class="t-fail">1</b><span>Failed</span>.*<b class="">0</b><span>Errors</span>.*<b>1</b><span>Skipped</span>#s', $html);
        $this->assertStringContainsString('feat: add &quot;the&quot; report', $html);
        $this->assertStringContainsString('https://github.com/acme/app/actions/runs/99', $html);
        $this->assertStringContainsString('<p class="note">Fictitious data.</p>', $html);

        // The list and the pages of the tests, numbered by class
        $this->assertStringContainsString('<h3>1. Jobs</h3>', $html);
        $this->assertStringContainsString('<a href="#t1-1">Lists the jobs (data set: career)</a>', $html);
        $this->assertStringContainsString('<a href="#t2-1">Shows the errors</a>', $html);
        $this->assertStringContainsString('<span class="badge skip">Skipped</span>', $html);
        $this->assertStringContainsString('1.8s', $html);

        // The steps and their captions
        $this->assertStringContainsString('<span class="name">1. Search form</span>', $html);
        $this->assertStringContainsString('<span class="kind">Viewport 1366×768</span><span class="path">/career/</span><span class="page-title">Careers &lt;Hilton&gt;</span>', $html);
        // The parts of a full page follow each other with one caption
        $this->assertSame(1, substr_count($html, '<span class="kind">Full page</span>'));
        $this->assertSame(1, substr_count($html, '<img class="continued"'));
        // The same scale for every mode: 0.1 mm per CSS pixel, 0.14 mm for a phone
        $this->assertSame(3, substr_count($html, 'style="width: 136.6mm"'));
        $this->assertStringContainsString('<span class="name">At the failure</span>', $html);
        // The images of a step are together, and the list repeats its header on each page
        $this->assertSame(3, substr_count($html, '<figure class="step">'));
        $this->assertStringContainsString('<thead><tr><th>No.</th>', $html);
        $this->assertStringContainsString('style="width: 54.6mm"', $html);

        // The failure, escaped, with its file and line; the description of the test
        $this->assertStringContainsString("Failed asserting that &quot;&lt;b&gt;&quot; contains &quot;x&quot;.\ntests/TestCase/EndToEnd/ApplyTest.php:42", $html);
        $this->assertStringContainsString('<p class="desc">The required fields are checked.</p>', $html);
        $this->assertStringContainsString('<b>Reason for skipping</b>', $html);
    }

    public function testRenderInJapanese(): void
    {
        $config = new Config('report.pdf', 'ja', null, '受入テスト報告書');

        $html = (new HtmlRenderer(new Translator('ja')))->render($this->recorder($config), self::environment());

        $this->assertStringContainsString('<html lang="ja">', $html);
        $this->assertStringContainsString('<h1>受入テスト報告書</h1>', $html);
        $this->assertStringContainsString('<div class="label">不合格</div>', $html);
        $this->assertStringContainsString('Lists the jobs（データ: career）', $html);
        $this->assertStringContainsString('<span class="name">失敗した時点の画面</span>', $html);
        $this->assertStringContainsString('<b>失敗の内容</b>', $html);
        // The modes of the screenshots are not translated
        $this->assertStringContainsString('<span class="kind">Viewport 390×844</span>', $html);
        $this->assertStringNotContainsString('<p class="note">', $html);
    }

    public function testRenderALocalRunThatPassed(): void
    {
        $recorder = new Recorder(new Config('report.pdf'), $this->dir);
        $recorder->add(new TestResult('App\TopTest::testTop', 'App\TopTest', 'testTop', 'Top', 'Shows the top page'));
        $environment = new Environment();
        $environment->commit = 'a1b2c3d';
        $environment->commitSubject = 'wip';
        $environment->dirty = true;
        $environment->os = 'macOS 15.1 arm64';

        $html = (new HtmlRenderer(new Translator()))->render($recorder, $environment);

        $this->assertStringContainsString('<div class="verdict passed">', $html);
        $this->assertStringContainsString('<div class="label">PASSED</div>', $html);
        $this->assertStringContainsString('<span class="warn">with uncommitted changes</span>', $html);
        $this->assertStringContainsString('Local (macOS 15.1 arm64)', $html);
        $this->assertStringNotContainsString('field.repository', $html);
        $this->assertStringNotContainsString('<th>Repository</th>', $html);
    }

    public function testHeaderIsACssString(): void
    {
        $config = new Config('report.pdf', 'en', null, 'Report "1" \\ </style>');

        $html = (new HtmlRenderer(new Translator()))->render($this->recorder($config), self::environment());

        $this->assertStringContainsString('@top-left { content: "Report \\22 1\\22  \\5C  \\3C /style\\3E 　', $html);
        $this->assertSame(1, substr_count($html, '</style>'));
    }
}
