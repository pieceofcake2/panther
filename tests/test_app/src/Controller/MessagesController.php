<?php
/**
 * Guest book of the fixture application: reads and writes the `messages` table
 */

App::uses('AppController', 'Controller');

class MessagesController extends AppController
{
    /**
     * @var array
     */
    public $uses = ['Message'];

    /**
     * The messages and a form to add one
     *
     * @return \CakeResponse|null
     */
    public function index()
    {
        if ($this->request->is('post')) {
            $this->Message->create();
            if ($this->Message->save($this->request->data)) {
                $this->Flash->success('Your message was saved.');

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Please correct the errors below.');
        }
        $this->set('messages', $this->Message->find('all', ['order' => ['Message.id' => 'ASC']]));
        $this->set('title_for_layout', 'Guest book');

        return null;
    }
}
