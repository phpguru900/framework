<?php
    class AccountsController extends AppController {
    	var $uses = array('Account', 'Subscription','ExternalProvider');

        public function index() {

            // Remove / unset the potentially set current account value
      		$this->Session->delete('User.Account');
            $this->set('account_id', null);

            $account_ids = $this->Account->AccountsUser->find('list', array(
            		'conditions' => array(
            				'AccountsUser.user_id' => $this->Auth->user('id'),
            		),
            		'fields' => array('AccountsUser.account_id')
            ) );

            $accounts = $this->Account->find('all', array(
            		'conditions' => array(
            				"OR" => array(
            						'Account.user_id' => $this->Auth->user('id'),
            						'Account.id' => array_values($account_ids)
            				)
            		),
            		'fields' => array(
            				'Account.id',
            				'Account.company_name',
            				'Account.region_code',
            				'Account.created',
            		),
            		'contain' => array(
            				'Industry' => array('fields' => array('Industry.name')),
            				'Subscription' => array('fields' => array('Subscription.name')),
            				'Region' => array('fields' => array('Region.region')),
            		),
            	));

            $accounts_formatted = array();

            foreach ($accounts as $account) {

            	$account_formatted['id'] =  $account['Account']['id'];
            	$account_formatted['industry'] =  $account['Industry']['name'];
            	$account_formatted['region'] =  $account['Region']['region'];
            	$account_formatted['company_name'] =  $account['Account']['company_name'];
            	$account_formatted['subscription'] =  $account['Subscription']['name'];
            	$account_formatted['created'] =  $account['Account']['created'];
            	$account_formatted['permission'] =  null;

            	$accounts_formatted[][] = $account_formatted;
            }


            $this->set('accounts', $accounts_formatted);
        }

        public function providers($id) {

        	$this->Account->id = $id;
            if (!$this->Account->exists()) {
                throw new NotFoundException(__('Invalid account'));
            }

					$is_admin = $this->isAdmin();
					//if($is_admin){
          // List all available external providers, and check if any has connection data associated with the current account
          $external_providers = $this->ExternalProvider->find("all", array(
          		'contain' => array(
          				'AccountsExternalProviders' => array(
          						'fields' => array('AccountsExternalProviders.connection_data'),
          						'conditions' => array('AccountsExternalProviders.account_id' => $id)
          				)
          		)
          	) );

          $external_providers_formatted = array();

          foreach ($external_providers as $external_provider) {
        		$controller_name = $external_provider['ExternalProvider']['controller_name'];
        		if(App::import('Controller', $controller_name)) {

        			$controller_methods = get_class_methods($controller_name.'Controller');
        			$external_provider_formatted = array();
        			$external_provider_formatted['connection_data']  = $external_provider['ExternalProvider']['connection_data'];
        			$external_provider_formatted['methods'] = array();
        			$external_provider_formatted['name']  = $external_provider['ExternalProvider']['name'];

        			// Set the existing methods per external provider
        			if(in_array('login', $controller_methods)){
        				$external_provider_formatted['methods']['login'] = true;
        			}
        			if(in_array('register', $controller_methods)){
        				$external_provider_formatted['methods']['register'] = true;
        			}
        			$external_providers_formatted[$controller_name] = $external_provider_formatted;

        			$exclusions[] = $external_provider['ExternalProvider']['id'];
        		}
          }
          $this->set('external_providers_active', $external_providers_formatted);

          $account = $this->Account->read();
          $this->set('account', $account);
          $this->set('is_admin', $is_admin);
        }

        public function sites($id = null) {

        	$this->Account->id = $id;
            if (!$this->Account->exists()) {
                throw new NotFoundException(__('Invalid account'));
            }

            // Let's check if the current user is allowed to edit the current account
            // (either by being its owner, or invited to it as an admin)
            $is_admin = $this->isAdmin();

            $account = $this->Account->read();
            $this->set('account', $account);
            $this->set('is_admin', $is_admin);

        }

        public function view($id = null) {

            $this->Account->id = $id;
            if (!$this->Account->exists()) {
                throw new NotFoundException(__('Invalid account'));
            }

            // Let's check if the current user is allowed to edit the current account
            // (either by being its owner, or invited to it as an admin)
            $is_admin = $this->isAdmin();

            $account = $this->Account->read();
            $this->set('account', $account);
            $this->set('is_admin', $is_admin);
        }

        public function add() {

            if ($this->request->is('post')) {
                $this->request->data['Account']['user_id'] = $this->Auth->user('id');
                $this->Account->create();
                if ($this->Account->save($this->request->data)) {
                		// based on selected subscription, add the plugins
                		$data = $this->Subscription->SubscriptionsPlugins->find('all', array('conditions' => array('subscription_id' => $this->request->data['Account']['subscription_id'])));
		                foreach($data as $plugin) {
		                	$db_data = array('AccountsPlugins' => array(
		                		'account_id' => $this->Account->id,
		                		'plugin_id' => $plugin['SubscriptionsPlugins']['plugin_id']
		                	));
		                	$this->Account->AccountsPlugins->save($db_data);
		                }

		                // FOR NOW, we also have to create an iBehavior external provider account behind the scenes until we decide we'll prompt the user about linking their existing account...


                    $this->Session->setFlash(__('<div class="alert-box radius success">The account has been successfully created!</div>'));
                    return $this->redirect(array('action' => 'billing'));
                }
                $this->Session->setFlash(__('<div class="alert alert-box radius text-center">The account could not be saved. Please, try again.</div>'));
            }

            $this->set('industries', ClassRegistry::init('Industry')->find('list'));
            $this->set('subscriptions', $this->Subscription->find('list', array('conditions' => array('referrer_id' => $this->referrer_id))));
            $this->set('accounts', $this->Account->find('count'));
        }

        public function edit($id = null) {

            $this->Account->id = $id;
            if (!$this->Account->exists()) {
                throw new NotFoundException(__('Invalid account'));
                //return $this->redirect(array('action' => 'index'));
            }

            // Let's check if the current user is allowed to edit the current account
            // (either by being its owner, or invited to it as an admin)
            $is_admin = $this->isAdmin();

            if(!$is_admin){
                $this->Session->setFlash(__('<div class="alert alert-box radius">You\'re not allowed to edit that account!</div>'));
                return $this->redirect(array('action' => 'index'));
            }

            if ($this->request->is('post') || $this->request->is('put')) {
                // Preventing the logo from being unset in case the current update doesn't submit a new one
                if(empty($this->request->data['Account']['logo']['name'])){
                    unset($this->request->data['Account']['logo']);
                }

                if ($this->Account->save($this->request->data)) {
                    $this->Session->setFlash(__('<div class="alert-box radius success">The account has been successfully updated!</div>'));
                    return $this->redirect(array('action' => 'index'));
                }

                $this->Session->setFlash(__('<div class="alert alert-box radius text-center">The account could not be saved. Please, try again.</div>'));
            } else {
                $this->request->data = $this->Account->read(null, $id);
            }

            $this->set('industries', ClassRegistry::init('Industry')->find('list'));
            $this->set('subscriptions', $this->Subscription->find('list', array('conditions' => array('referrer_id' => $this->referrer_id))));
        }

    	public function billing() {

    	// then go to analytics

		}

		public function site_tags() {
			// after analytics

		}

		public function google_analytics() {

		}

    public function invite(){

            $account = $this->Account->read(null, $this->request->data['AccountsEmail']['account_id']);

            if ($this->request->is('post')) {

                // Let's check if the currently submitted email address belongs to an existing user
                $user = $this->User->find('first', array(
                        'conditions' => array(
                            'email' => $this->request->data['AccountsEmail']['email']
                        )
                    ));

                if($user){
                    if($user['User']['id']==$this->Auth->user('id')){
                        // You cannot invite yourself!
                        $this->Session->setFlash(__('<div class="alert alert-box radius">You cannot invite yourself to an account!</div>'));
                        return $this->redirect(array('action' => 'view', $this->request->data['AccountsEmail']['account_id']));
                    }

                    // Let's check if the currently submitted user has already been invited to the current account
                    $invitation = $this->Account->AccountsUser->find('first', array(
                            'conditions' => array(
                                'account_id'    => $this->request->data['AccountsEmail']['account_id'],
                                'user_id'       => $user['User']['id']
                            )
                        ));

                    if($invitation){
                        // It already exists
                        $this->Session->setFlash(__('<div class="alert alert-box radius">User already invited to this account!</div>'));

                    }else{
                        // We don't need to insert the accounts-emails record, since the currently invited user already exists
                        $this->Account->AccountsUser->create();

                        unset($this->request->data['AccountsEmail']['email']);
                        $this->request->data['AccountsEmail']['user_id'] = $user['User']['id'];

                        if ($this->Account->AccountsUser->save($this->request->data['AccountsEmail'])) {

                            // Let's send a confirmation email to this matching user
                            $this->sendEmail(array(
                                    'vars'      => array(
                                        'recipient'     => $user['User']['first_name'],
                                        'permission'    => ($this->request->data['AccountsEmail']['permission']=='A' ? 'Admin' : 'Viewer'),
                                        'account'       => $account['Account']['company_name'],
                                        'creator'       => $account['User']['first_name'] . ' ' . $account['User']['last_name'],
                                        'link'          => SITE_URL . 'users/login'
                                    ),
                                    'email'     => $user['User']['email'],
                                    'name'      => ($user['User']['first_name'] . ' ' . $user['User']['last_name']),
                                    'subject'   => 'Zipline Account Invitation',
                                    'template'  => 'invitation'
                                ));

                            $this->Session->setFlash(__('<div class="alert-box radius success">You have successfully sent the invitation!</div>'));
                        } else {
                            $this->Session->setFlash(__('<div class="alert alert-box radius">Unfortunately, the invitation could not be sent.</div>'));
                        }
                    }

                }else{

                    // Let's check if the currently submitted email address has already been invited to the current account
                    $invitation = $this->Account->AccountsEmail->find('first', array(
                            'conditions' => array(
                                'account_id'    => $this->request->data['AccountsEmail']['account_id'],
                                'email'         => $this->request->data['AccountsEmail']['email']
                            )
                        ));

                    if($invitation){
                        // It already exists
                        $this->Session->setFlash(__('<div class="alert alert-box radius">Email already invited to this account!</div>'));

                    }else{
                        // We need to insert a record in the accounts-emails table, because the currently submitted email address doesn't belong to any existing user
                        // As soon as the currently submitted email address would be used by a new registering user, we'll move this record into the accounts-users table
                        $this->request->data['AccountsEmail']['sent_date'] = date('Y-m-d H:i:s');

                        $this->Account->AccountsEmail->create();
                        if ($this->Account->AccountsEmail->save($this->request->data)) {

                            // Let's send a confirmation email to this email address
                            $this->sendEmail(array(
                                    'vars'      => array(
                                        'permission'    => ($this->request->data['AccountsEmail']['permission']=='A' ? 'Admin' : 'Viewer'),
                                        'account'       => $account['Account']['company_name'],
                                        'creator'       => $account['User']['first_name'] . ' ' . $account['User']['last_name'],
                                        'link'          => SITE_URL . 'users/add'
                                    ),
                                    'email'     => $this->request->data['AccountsEmail']['email'],
                                    'name'      => $this->request->data['AccountsEmail']['email'],
                                    'subject'   => 'Zipline Account Invitation',
                                    'template'  => 'invitation'
                                ));

                            $this->Session->setFlash(__('<div class="alert-box radius success">You have successfully sent the invitation!</div>'));
                        } else {
                            $this->Session->setFlash(__('<div class="alert alert-box radius">Unfortunately, the invitation could not be sent.</div>'));
                        }
                    }

                }

                return $this->redirect(array('action' => 'view', $this->request->data['AccountsEmail']['account_id']));
            }

        }

        public function manage_permission(){

            if ($this->request->is('post') && $this->isAdmin()) {

                if($this->request->data['Account']['invitation_type']=='U'){
                    // User invitation

                    $invitation = $this->Account->AccountsUser->read(null, $this->request->data['Account']['invitation_id']);
                    if($invitation){

                        $this->Account->AccountsUser->id = $this->request->data['Account']['invitation_id'];
                        $this->Account->AccountsUser->saveField('permission', $this->request->data['Account']['permission']);
                    }

                }else if($this->request->data['Account']['invitation_type']=='E'){
                    // Email invitation

                    $invitation = $this->Account->AccountsEmail->read(null, $this->request->data['Account']['invitation_id']);
                    if($invitation){

                        $this->Account->AccountsEmail->id = $this->request->data['Account']['invitation_id'];
                        $this->Account->AccountsEmail->saveField('permission', $this->request->data['Account']['permission']);
                    }
                }
            }

            return $this->redirect(array('action' => 'view', $this->request->data['Account']['account_id']));

        }

        public function revoke_permission(){

            if ($this->request->is('post') && $this->isAdmin()) {

                if($this->request->data['Account']['invitation_type']=='U'){
                    // User invitation

                    $this->Account->AccountsUser->delete($this->request->data['Account']['invitation_id']);

                }else if($this->request->data['Account']['invitation_type']=='E'){
                    // Email invitation

                    $this->Account->AccountsEmail->delete($this->request->data['Account']['invitation_id']);
                }
            }

            return $this->redirect(array('action' => 'view', $this->request->data['Account']['account_id']));

        }

        /**
         * Determines if the currently logged in user is the current account's admin
         * (either as its creator, or as an invited admin)
         *
         **/
        private function isAdmin(){

            $account = $this->Account->read();
            //var_dump($account);
            //die();
            $is_admin = ($account['Account']['user_id']==$this->Auth->user('id'));
            if(!$is_admin){
                foreach($account['InvitedUsers'] as $invited){
                    if($invited['id']==$this->Auth->user('id')){
                        $is_admin = ($invited['AccountsUser']['permission']=='A');
                        break;
                    }
                }
            }

            // That's the best way I found to send the currently logged in user's account permission level
            // throughout the project...
            $this->Session->write('User.AccountAdmin', $is_admin);

            return $is_admin;

        }
    }
