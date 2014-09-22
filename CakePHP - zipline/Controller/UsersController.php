<?php
    class UsersController extends AppController {
        
        public function beforeFilter() {
            parent::beforeFilter();
            $this->Auth->allow('add'); // Letting users register themselves
        }
        
        public function edit($id = null) {

            $this->User->id = $id;
            if (!$this->User->exists()) {
                throw new NotFoundException(__('Invalid user'));
            }
            if ($this->request->is('post') || $this->request->is('put')) {
                if ($this->User->save($this->request->data)) {
                    $this->Session->setFlash(__('The user has been saved'));
                    return $this->redirect(array('action' => 'edit', $id));
                }
                $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
            } else {
                $this->request->data = $this->User->read(null, $id);
                unset($this->request->data['User']['password']);
            }
						$this->set('user',$this->User->read(null, $id));

        }
        
        public function checkAccounts() {
            
            if(ClassRegistry::init('Account')->find('count')==0){
                // There are no accounts yet, so let's
                // add the user's first account
                $this->redirect(array('controller' => 'accounts', 'action' => 'add'));
            }
            
            $this->redirect(array('controller' => 'accounts', 'action' => 'index'));
        }
        /*
        public function index() {
            $this->User->recursive = 0;
            $this->set('users', $this->paginate());
        }

        public function view($id = null) {
            $this->User->id = $id;
            if (!$this->User->exists()) {
                throw new NotFoundException(__('Invalid user'));
            }
            $this->set('user', $this->User->read(null, $id));
        }
        */
        public function add() {
            if ($this->request->is('post')) {
                
                $this->User->create();
                if ($this->User->save($this->request->data)) {
                    
                    // Let's check if the currently registering user's email address has been invited to any existing accounts
                    // If it has been, we'll remove those invitations and insert the proper AccountsUsers records
                    $invitations = $this->Account->AccountsEmail->find('all', array(
                            'conditions' => array(
                                'email' => $this->request->data['User']['email']
                            )
                        ));
                    
                    if($invitations){
                        foreach($invitations as $invitation){
                            $this->Account->AccountsUser->save(array(
                                    'account_id'    => $invitation['AccountsEmail']['account_id'], 
                                    'user_id'       => $this->User->id, 
                                    'permission'    => $invitation['AccountsEmail']['permission']
                                ));
                        }
                        
                        // Delete the current user's existing email invitations
                        $this->Account->AccountsEmail->deleteAll(array(
                                'email' => $this->request->data['User']['email']
                            ), false, false);
                    }
                    
                    $this->Session->setFlash(__('<div class="alert-box radius success"><h5>Your user account has been successfully created!</h5>' .
                        '<p>Check your email for a confirmation message, and activate your account by clicking on the email link.</p></div>'));

                    $this->sendEmail(array(
                            'vars'      => array(
                                'name'          => $this->request->data['User']['first_name'],
                                'confirm_url'   => SITE_URL . 'users/confirm/' . USER_UNIQUE_HASH
                            ),
                            'email'     => $this->request->data['User']['email'],
                            'name'      => $this->request->data['User']['first_name'] . ' ' . $this->request->data['User']['last_name'],
                            'subject'   => 'Choozle Registration Email Confirmation',
                            'template'  => 'registration'
                        ));
                    
                    return $this->redirect(array('action' => 'login'));
                }

                $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
            }
            
            $referrer_id = 1; // By default, Choozle
            if(!empty($_GET['referrer'])){
                $referrer = ClassRegistry::init('Referrer')->find('first', array(
                        'conditions' => array('name' => $_GET['referrer'])
                    ));

                if(!empty($referrer)){
                    $referrer_id = $referrer['Referrer']['id'];
                }
            }

            $this->set('referrer', $referrer_id);
        }

        public function confirm($check = null) {
            
            $user = $this->User->find('first', array(
                    'conditions' => array(
                        'User.unique_hash' => $check
                    )
                ));
            
            if (!$user) {
                //throw new NotFoundException(__('Invalid user'));
                return $this->redirect(array('action' => 'login'));
            }
            
            // User found, let's update his/her email_confirmation column, and display a confirmation message
            $this->User->id = $user['User']['id'];
            
            if($this->User->save(array( 'email_confirmation' => date('Y-m-d H:i:s') ))){
                
                // Send confirmation email
                $this->sendEmail(array(
                        'email'     => $user['User']['email'],
                        'name'      => $user['User']['first_name'] . ' ' . $user['User']['last_name'],
                        'subject'   => 'Welcome to Choozle!',
                        'template'  => 'welcome'
                    ));
                
                $this->Session->setFlash(__('<div class="alert-box radius success">You have successfully confirmed your email address!<br />' .
                    'You can now log in.</div>'));
            }
            return $this->redirect(array('action' => 'login'));
        }
        
        public function login() {

            if($this->Auth->user('id')){
                // Logged in users have nothing to do here
                return $this->redirect(array('action' => 'checkAccounts'));
            }

            if ($this->request->is('post')) {

                // Let's check if the currently submitted email address has been confirmed
                // (if it exists at all)
                $user = $this->User->find('first', array(
                        'conditions' => array(
                            'User.email'                => $this->request->data['User']['email'],
                            'User.email_confirmation IS NOT NULL'
                        )
                    ));

                if (!$user) {
                    $this->Session->setFlash(__('<div class="alert-box radius">Are you sure you\'ve confirmed your email address?</div>'));
                }else{
                    // All seem to be good so far...
                    if ($this->Auth->login()) {
                        return $this->redirect(array('action' => 'checkAccounts'));
                    }

                    $this->Session->setFlash(__('Invalid username or password, try again'));
                }
            }
        }

        public function logout() {
            return $this->redirect($this->Auth->logout());
        }

        /**
         * Reset your account's password
         **/
        public function reset($check = null) {

            if($this->Auth->user('id')){
                // Logged in users have nothing to do here
                return $this->redirect(array('action' => 'checkAccounts'));
            }

            if($check && ($user = $this->User->find('first', array(
                    'conditions' => array(
                        'User.unique_hash' => $check
                    )
                )))){

                // This seem to be a pass reset confirmation
                // Let's see if the new pass has already been submitted
                //$this->User->id = $user['User']['id'];

                if ($this->request->is('post')) {
                    /*
                    if ($this->User->save( array('password' => $this->request->data['User']['password']) )) {
                        $this->Session->setFlash(__('Your password has been successfully updated'));
                        return $this->redirect(array('action' => 'login'));
                    }
                    $this->Session->setFlash(__('Unfortunatelly, your password could not be updated.<br />' .
                        'Please, try again.'));
                    */
                    $db = $this->User->getDataSource();
                    $password = $db->value(AuthComponent::password($this->request->data['User']['password']), 'string');

                    $this->User->updateAll(
                        array('User.password'   => $password),
                        array('User.id ='       => $user['User']['id'])
                    );

                    $this->Session->setFlash(__('Your password has been successfully updated'));
                    return $this->redirect(array('action' => 'login'));
                }

                $this->set('check', $check);
            }else{
                if ($this->request->is('post')) {

                    // Let's check if the currently submitted email address has been confirmed
                    // (if it exists at all)
                    $user = $this->User->find('first', array(
                            'conditions' => array(
                                'User.email' => $this->request->data['User']['email']
                            )
                        ));

                    if (!$user) {
                        // There's no user with such an email address
                        return $this->redirect(array('action' => 'login'));
                    }else{
                        // There is a user with the submitted email address,
                        // so let's send him/her a pass reset link
                        $this->sendEmail(array(
                                'vars'      => array(
                                    'name'          => $user['User']['first_name'],
                                    'confirm_url'   => SITE_URL . 'users/reset/' . $user['User']['unique_hash']
                                ),
                                'email'     => $user['User']['email'],
                                'name'      => $user['User']['first_name'] . ' ' . $user['User']['last_name'],
                                'subject'   => 'Choozle Password Reset Request',
                                'template'  => 'password-reset'
                            ));

                        $this->Session->setFlash(__('We\'ve just sent you an email containing a password reset request link.<br />' .
                            'Please click on it to finalize the password reset process!'));

                    }
                }
            }
        }

    }
