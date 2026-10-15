<?php
declare(strict_types=1);

namespace App\Test\TestCase\EndToEnd;

use ClassRegistry;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * tests/TestCase/EndToEnd/ArticlesTest.php
 */
class ArticlesTest extends PantherTestCase
{
    /**
     * @var array<string>
     */
    public $fixtures = ['app.article'];

    /**
     * @return void
     */
    public function testIndexShowsTheFixtureRecords(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/articles');

        $this->assertPageTitleContains('Articles');
        $this->assertSelectorTextContains('table', 'First article');
    }

    /**
     * @return void
     */
    public function testAdd(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/articles/add');

        // FormHelper puts a hidden input before each checkbox: click the checkbox itself
        $crawler->filter('#ArticlePublished')->click();
        $client->submitForm('Submit', [
            'data[Article][title]' => 'Written in the browser',
            'data[Article][body]' => 'Hello',
        ]);

        $this->assertSelectorTextContains('.message', 'The article has been saved.');
        $this->assertSame(1, ClassRegistry::init('Article')->find('count', [
            'conditions' => ['Article.title' => 'Written in the browser', 'Article.published' => true],
        ]));
    }
}
