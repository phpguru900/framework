<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('CakeSession', 'Model/Datasource');
class ChoozleDatasource extends DataSource {

/**
 * An optional description of your datasource
 */
    public $description = 'Choozle External API Data';
    public $baseURL;
    public $AccessToken;

/**
 * Our default config options. These options will be customized in our
 * ``app/Config/database.php`` and will be merged in the ``__construct()``.
 */
    public $config = array(
      'user'     => CHOOZLE_CLIENT,
  		'password'     => CHOOZLE_SECRET
    );

/**
 * If we want to create() or update() we need to specify the fields
 * available. We use the same array keys as we do with CakeSchema, eg.
 * fixtures and schema migrations.
 */
    protected $_schema = array(
        'id' => array(
            'type' => 'integer',
            'null' => false,
            'key' => 'primary',
            'length' => 11,
        ),
        'name' => array(
            'type' => 'string',
            'null' => true,
            'length' => 255,
        ),
        'message' => array(
            'type' => 'text',
            'null' => true,
        ),
    );

/**
 * Create our HttpSocket and handle any config tweaks.
 */
    public function __construct($config) {
      parent::__construct($config);
      $this->baseURL = CHOOZLE_ENDPOINT;

			$access_token = CakeSession::read('Choozle.AccessToken');
			$this->AccessToken = (!empty($access_token) ? $access_token : null);

			if ($this->AccessToken == '') {
				$this->getAccessToken();
				$this->initialize();
			} else if (!isset($this->Http)){
				$this->initialize();
			}
    }

    public function initialize() {
    	unset($this->Http);
    	$this->Http = new HttpSocket();
    	$this->Http->configAuth('Digest',array(
				'grant_type' => 'client_credentials',
				'uri' => $this->baseURL.'oauth-token.php',
				'user' => $this->config['user'],
				'pass' => $this->config['password']
			));
    }

		public function getAccessToken() {
			$http = new HttpSocket();
			$http->configAuth('Basic',$this->config['user'],$this->config['password']);
			$url = $this->baseURL.'oauth-token.php';
			$json = $http->post($url,array('grant_type'=>'client_credentials'));
			$token_data = json_decode($json);
			$token = (!empty($token_data) ? $token_data->access_token : '');
			CakeSession::write('Choozle.AccessToken',$token);
			$this->AccessToken = $token;
			unset($http);
		}

/**
 * Since datasources normally connect to a database there are a few things
 * we must change to get them to work without a database.
 */

/**
 * listSources() is for caching. You'll likely want to implement caching in
 * your own way with a custom datasource. So just ``return null``.
 */
    public function listSources($data = null) {
        return null;
    }

/**
 * describe() tells the model your schema for ``Model::save()``.
 *
 * You may want a different schema for each model but still use a single
 * datasource. If this is your case then set a ``schema`` property on your
 * models and simply return ``$model->schema`` here instead.
 */
    public function describe($model) {
        return $this->_schema;
    }

/**
 * calculate() is for determining how we will count the records and is
 * required to get ``update()`` and ``delete()`` to work.
 *
 * We don't count the records here but return a string to be passed to
 * ``read()`` which will do the actual counting. The easiest way is to just
 * return the string 'COUNT' and check for it in ``read()`` where
 * ``$data['fields'] === 'COUNT'``.
 */
    public function calculate(Model $model, $func, $params = array()) {
        return 'COUNT';
    }

/**
 * Implement the R in CRUD. Calls to ``Model::find()`` arrive here.
 */
    public function read(Model $model, $queryData = array(), $recursive = null) {

        /**
         * Here we do the actual count as instructed by our calculate()
         * method above. We could either check the remote source or some
         * other way to get the record count. Here we'll simply return 1 so
         * ``update()`` and ``delete()`` will assume the record exists.
         */
        if ($queryData['fields'] === 'COUNT') {
            return array(array(array('count' => 1)));
        }
        /**
         * Now we get, decode and return the remote data.
         */
        $queryData['conditions']['access_token'] = $this->AccessToken;
        $url = $this->baseURL.$queryData['request'];
        $return = $this->Http->get($url, $queryData['conditions']);
        $response = json_decode($return->body, true);

        if (isset($response['error']) && $response['error'] == 'invalid_request') {
        	// at this point we need to reset the access tokens
        	unset($this->Http);
        	$this->getAccessToken();
        	$this->initialize();
					$queryData['conditions']['access_token'] = $this->AccessToken;
	        $url = $this->baseURL.$queryData['request'];
	        $return = $this->Http->get($url, $queryData['conditions']);
	        $response = json_decode($return->body, true);
        }

        if (is_null($response)) {
          $error = json_last_error();
          throw new CakeException($error);
        }

        return array($model->alias => $response);
    }

/**
 * Implement the C in CRUD. Calls to ``Model::save()`` without $model->id
 * set arrive here.
 */
    public function create(Model $model, $fields = null, $values = null) {
        $data = array_combine($fields, $values);
        $data['user'] = $this->config['user'];
        $data['password'] = $this->config['password'];
        $json = $this->Http->post('http://api2.choozle.com/', $data);
        $res = json_decode($json, true);
        if (is_null($res)) {
            $error = json_last_error();
            throw new CakeException($error);
        }
        return true;
    }

/**
 * Implement the U in CRUD. Calls to ``Model::save()`` with $Model->id
 * set arrive here. Depending on the remote source you can just call
 * ``$this->create()``.
 */
    public function update(Model $model, $fields = null, $values = null, $conditions = null) {
        return $this->create($model, $fields, $values);
    }

/**
 * Implement the D in CRUD. Calls to ``Model::delete()`` arrive here.
 */
    public function delete(Model $model, $id = null) {
        $json = $this->Http->get('http://api2.choozle.com/', array(
            'id' => $id[$model->alias . '.id'],
            'user' => $this->config['user'],
            'password' => $this->config['password'],
        ));
        $res = json_decode($json, true);
        if (is_null($res)) {
            $error = json_last_error();
            throw new CakeException($error);
        }
        return true;
    }

}
?>