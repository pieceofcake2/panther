<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Example\Report;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * Guest book
 *
 * The tests of the sample report (docs/report.md), on the fixture application of the package.
 */
class GuestBookTest extends PantherTestCase
{
    /**
     * @var array<string>
     */
    public $fixtures = ['app.message'];

    /**
     * Shows the messages of the guest book
     */
    public function testShowsTheMessages(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/messages');

        $this->assertPageTitleSame('Guest book');
        $this->assertSelectorTextContains('#messages', 'Hello from the fixture');
    }

    /**
     * Posts a message, which is shown in the guest book
     */
    public function testPostsAMessage(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/messages');
        $this->takeReportScreenshot('Form');

        $client->submitForm('Post', ['data[Message][body]' => 'Hello from the sample report']);

        $this->assertSelectorWillContain('#messages', 'Hello from the sample report');
        $this->takeReportScreenshot('Posted message');
    }

    /**
     * An empty message is not posted
     *
     * The form shows the error under the field.
     */
    public function testRequiresAMessage(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/messages');

        // Without the validation of the browser, to see the one of the application
        $client->executeScript('document.querySelector("form").noValidate = true;');
        $client->submitForm('Post', ['data[Message][body]' => '']);

        $this->assertSelectorWillContain('.error-message', 'Enter a message.');
        $this->takeReportScreenshot('Error', '.error-message');
    }
}
