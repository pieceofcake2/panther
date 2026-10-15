<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use ClassRegistry;
use Model;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * The web server and the test process share the test database: the browser sees the fixture records, and
 * the test sees what the browser saved.
 *
 * PANTHER_APP_ENV=test in phpunit.xml.dist gives the web server APP_ENV=test, and the fixture application
 * then uses the `test` datasource as `default` (tests/test_app/config/database.php).
 */
class FixtureTest extends PantherTestCase
{
    /**
     * @var array<string>
     */
    public $fixtures = ['app.message'];

    public function testFixtureRecordsAreVisibleInTheBrowser(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/messages');

        $this->assertPageTitleSame('Guest book');
        $this->assertSelectorTextContains('#messages', 'Hello from the fixture');
    }

    public function testRecordsOfTheTestAreVisibleInTheBrowser(): void
    {
        $Message = $this->messages();
        $Message->create();
        $this->assertNotFalse($Message->save(['body' => 'Saved by the test']));

        $client = static::createPantherClient();
        $client->request('GET', '/messages');

        $this->assertSelectorTextContains('#messages', 'Saved by the test');
    }

    public function testFormPostIsVisibleInTheTestProcess(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/messages');
        $client->submitForm('Post', ['data[Message][body]' => 'Posted from the browser']);

        $this->assertSame('/messages', parse_url((string)$client->getCurrentURL(), PHP_URL_PATH));
        $this->assertSelectorTextContains('.message.success', 'Your message was saved.');
        $this->assertSelectorTextContains('#messages', 'Posted from the browser');

        $Message = $this->messages();
        $this->assertSame(2, $Message->find('count'));
        $last = $Message->find('first', ['order' => ['Message.id' => 'DESC']]);
        $this->assertSame('Posted from the browser', $last['Message']['body']);
    }

    public function testEachTestStartsWithTheFixtureRecords(): void
    {
        $this->assertSame(1, $this->messages()->find('count'));
    }

    /**
     * @return \Model
     */
    private function messages(): Model
    {
        $model = ClassRegistry::init('Message');
        $this->assertInstanceOf(Model::class, $model);

        return $model;
    }
}
