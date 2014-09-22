<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
App::uses('HttpSocket', 'Network/Http');

class ZiplineDatasource extends DataSource {
    
    /**
    * An optional description of your datasource
    */
    private $description = 'An iBehaviorZipline datasource';
    
    private $Http;
    
    private $requestPath = '/Zipline.svc/';
    
    private $signature;
    
    private $configuration = array(
        'publicKey' => ZIPLINE_PUBLIC_KEY,
        'privateKey' => ZIPLINE_PRIVATE_KEY,
        'baseURL' => ZIPLINE_ENDPOINT
    );
    
     /**
    * $request is a keyed array of various options. Here is the format and our settings:
    */
    private $request = array(
        'method' => 'GET',
        'uri' => array(
            'scheme' => 'http',
            'host' => 'sdk-test.ib-ibi.com',
            'port' => '',
            'user' => null,
            'pass' => null,
            'path' => '',
            'query' => null,
            'fragment' => null
        ),
        'auth' => array(
            'method' => 'Basic',
            'user' => null,
            'pass' => null
        ),
        'version' => '1.1',
        'body' => '',
        'line' => null,
        'header' => array(
            'Content-length' => 0,
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'en-US,en;q=0.8',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-type' => 'application/json',
            'Authorization' => '',
            'Connection' => 'keep-alive',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.112 Safari/534.30',
        ),
        'raw' => null,
        'redirect' => false,
        'cookies' => array()
    );
    
    /**
    * createRequest method is for creating request url for api
    */
    private function createRequest($request, $data) {
        
        $url = $this->configuration['baseURL'] . $this->requestPath . $request . '?key=' . $this->configuration['publicKey'];
        foreach($data as $param => $value){
            $url .= '&' . $param . '=' .urlencode($value);
        }
        $this->createSignature($url);
        return $url;
    }
    
    /**
    * createSignature method creates unique key for authorization  
    */
    private function createSignature($url) {
        $url = str_replace($this->configuration['baseURL'], '', $url); // we're stripping off the base url part for generating the signature
        $this->signature = base64_encode(hash_hmac('sha256', strtolower($url), base64_decode($this->configuration['privateKey']),true));
        $this->request['header']['Authorization'] = $this->signature;
    }
    
    /**
    * Create our HttpSocket and handle any config tweaks.
    */
    public function __construct($config=array()) {
        parent::__construct($config);
        $this->Http = new HttpSocket();
        $this->Http -> config['timeout'] = 300;
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
    public function read(Model $model,$queryData = array(), $recursive = null) { 
        
        if ($queryData['fields'] === 'COUNT') {
            return array(array(array('count' => 1)));
        } 
        $url = $this->createRequest($queryData['request'], $queryData['conditions']);
        $uri = str_replace($this->configuration['baseURL'], '', $url);
        $this->request['uri']['path'] = $uri; 
        $json = $this->Http->request($this->request);
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
        $data['apiKey'] = $this->configuration['publicKey'];
        $url = $this->createRequest($data['request'], $data['conditions']);
        $json = $this->Http->post($url, $data);
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
     * but I think we don't need this method
     */
    public function delete(Model $model, $id = null) {
        $json = $this->Http->get('http://sdk-test.ib-ibi.com', array(
            'id' => $id[$model->alias . '.id'],
            'apiKey' => $this->configuration['publicKey'],
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
