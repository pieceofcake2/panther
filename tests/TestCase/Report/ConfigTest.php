<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Config;

class ConfigTest extends TestCase
{
    public function testNoReportWithoutPdf(): void
    {
        $this->assertNull(Config::fromServer([]));
        $this->assertNull(Config::fromServer(['PANTHER_REPORT_PDF' => ' ', 'PANTHER_REPORT_LANGUAGE' => 'ja']));
    }

    public function testDefaults(): void
    {
        $config = Config::fromServer(['PANTHER_REPORT_PDF' => 'tmp/report.pdf']);

        $this->assertNotNull($config);
        $this->assertSame('tmp/report.pdf', $config->pdf);
        $this->assertSame('en', $config->language);
        $this->assertNull($config->localeDir);
        $this->assertNull($config->title);
        $this->assertNull($config->project);
        $this->assertNull($config->note);
        $this->assertSame(60, $config->jpegQuality);
    }

    public function testValues(): void
    {
        $config = Config::fromServer([
            'PANTHER_REPORT_PDF' => 'tmp/report.pdf',
            'PANTHER_REPORT_LANGUAGE' => 'ja',
            'PANTHER_REPORT_LOCALE_DIR' => 'tests/locales',
            'PANTHER_REPORT_TITLE' => '受入テスト報告書',
            'PANTHER_REPORT_PROJECT' => '採用サイト',
            'PANTHER_REPORT_NOTE' => 'テスト用のデータです。',
            'PANTHER_REPORT_JPEG_QUALITY' => '150',
        ]);

        $this->assertNotNull($config);
        $this->assertSame('ja', $config->language);
        $this->assertSame('tests/locales', $config->localeDir);
        $this->assertSame('受入テスト報告書', $config->title);
        $this->assertSame('採用サイト', $config->project);
        $this->assertSame('テスト用のデータです。', $config->note);
        $this->assertSame(100, $config->jpegQuality);
    }
}
