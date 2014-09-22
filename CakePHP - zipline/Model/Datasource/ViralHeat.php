<?php
App::uses('HttpSocket', 'Network/Http');

class ViralHeat extends DataSource {

/**
 * An optional description of your datasource
 */
    public $description = 'A Viral Heat datasource';

/**
 * Our default config options. These options will be customized in our
 * ``app/Config/database.php`` and will be merged in the ``__construct()``.
 */
    public $config = array(
        'apiKey' => 'njBMST1aXQSDASAM81Fr51bK',
    );

/**
 * If we want to create() or update() we need to specify the fields
 * available. We use the same array keys as we do with CakeSchema, eg.
 * fixtures and schema migrations.
 */
    protected $_schema = array( 'profile' => array(
    		'id' => array(
            'type' => 'integer',
            'null' => false,
            'length' => 11,
        ),
        'name' => array(
            'type' => 'string',
            'null' => false,
            'length' => 100,
        ),
        'expression' => array(
            'type' => 'string',
            'null' => false,
            'length' => 255,
        )
    ));

/**
 * Create our HttpSocket and handle any config tweaks.
 */
    public function __construct($config) {
        parent::__construct($config);
        $this->Http = new HttpSocket();
    }

    public function buildRequestUrl($params) {
    	// build the request path
      $url = 'https://app.viralheat.com/social/api/monitoring/';
      foreach($params as $path_element) {
      	$url .= $path_element.'/';
      }
      $request_path = substr($url, 0, -1);
      return $request_path;
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
				$request_path = $this->buildRequestUrl($queryData['request_path']);

				$conditions = $queryData['conditions'];
				$conditions['api_key'] = $this->config['apiKey'];

        $json = $this->Http->get($request_path, $conditions);
        $res = json_decode($json, true);
        if (is_null($res)) {
            $error = json_last_error();
            throw new CakeException($error);
        }
        return array($model->alias => $res);
    }

/**
 * Implement the C in CRUD. Calls to ``Model::save()`` without $model->id
 * set arrive here.
 */
    public function create(Model $model, $fields = null, $values = null) {

        $data = array_combine($fields, $values);

        $data['api_key'] = $this->config['apiKey'];
        $json = $this->Http->post('https://app.viralheat.com/social/api/monitoring/profiles', $data);
        $res = json_decode($json, true);
        $model->id = $res['profile']['id'];

        if ($res['status'] != 200) {
          $error = json_last_error();
          throw new CakeException($res['status']);
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
        $json = $this->Http->get('http://example.com/api/remove.json', array(
            'id' => $id[$model->alias . '.id'],
            'apiKey' => $this->config['apiKey'],
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