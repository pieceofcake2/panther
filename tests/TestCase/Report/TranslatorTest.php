<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Translator;

class TranslatorTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->dir = TMP . 'locales-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . DS . '*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    public function testBundledLanguages(): void
    {
        $this->assertSame('End-to-End Test Report', (new Translator())->translate('title'));
        $this->assertSame('統合テスト報告書', (new Translator('ja'))->translate('title'));
        $this->assertSame('ja', (new Translator('ja'))->language());
    }

    public function testBundledLanguagesHaveTheSameMessages(): void
    {
        $en = json_decode((string)file_get_contents(Translator::LOCALE_DIR . DS . 'en.json'), true);
        $ja = json_decode((string)file_get_contents(Translator::LOCALE_DIR . DS . 'ja.json'), true);

        $this->assertIsArray($en);
        $this->assertIsArray($ja);
        $this->assertSame(array_keys($en), array_keys($ja));
    }

    public function testPlaceholders(): void
    {
        $translator = new Translator('ja');

        $this->assertSame('2分14秒', $translator->translate('duration.minutes', ['minutes' => 2, 'seconds' => 14]));
        $this->assertSame('ページ（データ: {data}）', $translator->translate('data_set', ['name' => 'ページ']));
        $this->assertSame('unknown.key', $translator->translate('unknown.key'));
    }

    public function testAnotherLanguageFallsBackToEnglish(): void
    {
        file_put_contents($this->dir . DS . 'fr.json', json_encode(['title' => 'Rapport des tests']));

        $translator = new Translator('fr', $this->dir);

        $this->assertSame('Rapport des tests', $translator->translate('title'));
        $this->assertSame('Passed', $translator->translate('count.passed'));
    }

    public function testLocaleDirReplacesMessagesOfABundledLanguage(): void
    {
        file_put_contents($this->dir . DS . 'ja.json', json_encode(['title' => '受入テスト報告書']));

        $translator = new Translator('ja', $this->dir);

        $this->assertSame('受入テスト報告書', $translator->translate('title'));
        $this->assertSame('成功', $translator->translate('count.passed'));
    }

    public function testUnknownLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No messages for the report language "fr"');

        new Translator('fr', $this->dir);
    }

    public function testInvalidLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Translator('../en');
    }

    public function testInvalidJson(): void
    {
        file_put_contents($this->dir . DS . 'fr.json', '{');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        new Translator('fr', $this->dir);
    }
}
