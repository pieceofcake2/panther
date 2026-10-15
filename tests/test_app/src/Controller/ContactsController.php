<?php
/**
 * Pages of the fixture application for the assertions and a CakePHP form
 */

App::uses('AppController', 'Controller');

class ContactsController extends AppController
{
    /**
     * @var array
     */
    public $uses = ['Contact'];

    /**
     * Topics of the contact form
     *
     * @var array<string, string>
     */
    public const TOPICS = ['general' => 'General question', 'billing' => 'Billing & invoices', 'other' => 'Something else'];

    /**
     * @return void
     */
    public function beforeFilter(): void
    {
        parent::beforeFilter();
        $this->Security->unlockedActions = ['ajax', 'remember'];
    }

    /**
     * Links, a popup, AJAX and a cookie
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title_for_layout', 'Panther fixture');
    }

    /**
     * Text loaded by JavaScript
     *
     * @return \CakeResponse
     */
    public function ajax()
    {
        $this->autoRender = false;
        $this->response->type('text');
        $this->response->body('Loaded by AJAX');

        return $this->response;
    }

    /**
     * Sets a cookie and goes back to the index
     *
     * @return \CakeResponse|null
     */
    public function remember()
    {
        $this->response->cookie(['name' => 'remember_me', 'value' => 'yes', 'path' => '/']);

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Contact form with validation, a select and checkboxes
     *
     * @return \CakeResponse|null
     */
    public function add()
    {
        if ($this->request->is('post')) {
            $this->Contact->set($this->request->data);
            if ($this->Contact->validates()) {
                $data = (array)$this->request->data('Contact');
                $this->Session->write('Contact', [
                    'name' => $data['name'],
                    'topic' => self::TOPICS[$data['topic']] ?? '',
                    'newsletter' => !empty($data['newsletter']),
                ]);
                $this->Flash->success('Thank you for your message.');

                return $this->redirect(['action' => 'thanks']);
            }
            $this->Flash->error('Please correct the errors below.');
        }
        $this->set('topics', self::TOPICS);
        $this->set('title_for_layout', 'Contact us');

        return null;
    }

    /**
     * Result of the contact form
     *
     * @return void
     */
    public function thanks(): void
    {
        $this->set('contact', (array)$this->Session->read('Contact'));
        $this->set('title_for_layout', 'Thanks');
    }
}
