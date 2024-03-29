<?php
/**
 * eiseREST class for easy as 1-2-3 REST interface
 */
class eiseREST {

protected static $defaultConf = array(

	'error500OnException' => true
	, 'Content-Type' => 'application/json'
	, 'root_directory' => '/'

	, 'debug' => false

);

public $conf = array();
public $oSQL;

/**
 * Current request method: GET, POST, PUT, DELETE, etc
 */
public $method;

/**
 * Requested entities list as parsed from URI
 */
public $requestedEntities = array();

/**
 * Entities parsed from the request, as recursive list. ['next'] element points to a next entry.
 */
public $requestHierarchy;

/**
 * Array of registered entities
 */
public $entities = array();


public function __construct($conf = array()){

	$this->conf = array_merge(self::$defaultConf, $conf);

}

public function registerEntity($entity){
	
	$entity->rest = $this;

	$this->entities[$entity->conf['name']] = $entity;

}

public function authenticate(){

	$apiConf  = $this->conf;
	$rootEntityConf = (array)$this->requestedEntities[0]['entityObject']->conf;

	if( count(array_intersect(array('auth_keys', 'auth_table'), array_merge(
			array_keys($apiConf)
			, array_keys($rootEntityConf)
		)
		))==0 )
		return true;

	if (!isset($_SERVER['PHP_AUTH_USER'])) {
	    header("WWW-Authenticate: Basic realm=\"{$this->conf['name']}\"");
	    header('HTTP/1.0 401 Unauthorized');
	    echo "Only authorized access permitted to the {$this->conf['name']}";
	    exit;
	} else {
		$keys = (isset($rootEntityConf['auth_keys']) ? $rootEntityConf['auth_keys'] : $apiConf['auth_keys']);
	    if($keys){
	    	$auth = false;
	    	if($keys[0])
		    	foreach ($keys as $keypair) {
		    		if($keypair[$_SERVER['PHP_AUTH_USER']] == md5($_SERVER['PHP_AUTH_PW'])){
		    			$auth = true;
		    			break;
		    		}
		    	}
		    else
		    	if($keys[$_SERVER['PHP_AUTH_USER']]==md5($_SERVER['PHP_AUTH_PW']))
		    		$auth = true;
		    if(!$auth){
		    	header("WWW-Authenticate: Basic realm=\"{$this->conf['name']}\"");
		    	header('HTTP/1.0 401 Unauthorized');
		    	echo "Access to the {$this->conf['name']} is denied for user {$_SERVER['PHP_AUTH_USER']}";
		    	exit;
		    }
	    }
	}
}

public function parse_request(){

	$this->method = $_SERVER['REQUEST_METHOD'];

	$this->requestedEntities = $this->parse_uri();

}

public function parse_uri(){
	
	$this->requestedEntities = array();
	$this->requestHierarchy = array();

	$basedir = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
	$aURI = explode('?', preg_replace('/^'.preg_quote($basedir, '/').'/i', '', $_SERVER['REQUEST_URI']));
	$dirURI = $aURI[0];

	$aPossibleRoots = [ $this->conf['root_directory'] ];
	$aNamePrefixes = [];
	foreach ($this->entities as $$entID => $ent) {
		if($ent->conf['root_directory']){
			$aPossibleRoots[] = $ent->conf['root_directory'];
			if($ent->conf['name_prefix'])
				$aNamePrefixes[$ent->conf['root_directory']] = $ent->conf['name_prefix'];
		}
	}

	$name_prefix = '';
	foreach($aPossibleRoots as $root_dir){
		if ( preg_match('/^'.preg_quote($root_dir.'/', '/').'/', $dirURI) ) {
			$dirURI = preg_replace('/^'.preg_quote($root_dir.'/', '/').'/i', '', $dirURI);
			$name_prefix = $aNamePrefixes[$root_dir];
			break;	
		}
		
	}
	$dirURI = trim($dirURI, '/');

	$aURI = explode('/', $dirURI);

	for( $ix=0; $ix<count($aURI); $ix++ ){

		$ent = ($name_prefix ? $name_prefix.'_' : '').strtoupper($aURI[$ix]);

		$aObj = array(
			'entity'=>$ent
		);

		if(is_a($this->entities[$ent], 'eiseREST_Entity')){
			$aObj['entityObject'] = $this->entities[$ent];
		}

		if( isset($aURI[$ix+1]) ){
			$aObj['id'] = $aURI[$ix+1];
			$ix++;
		}

		if($ix==count($aURI)-1){
			$aObj['query'] = $_GET;
			$aObj['rawInput'] = file_get_contents('php://input');
			$aObj['method'] = $this->method;
			$aObj['Content-Type'] = $_SERVER['CONTENT_TYPE'];
		} else {
			$aObj['method'] = 'GET';
		}

		$this->requestedEntities[] = $aObj;
		

	}

	return $this->requestedEntities;

}

public function execute( $request = null ){

	$request = !$request ? $this->requestedEntities[0] : $request;

	$o = $request['entityObject'];
	$ent = $request['entity'];

	if(!is_a($o, 'eiseREST_Entity')){
		throw new eiseRESTException('Entity '.$request['entity'].' not found', 404, null, $this);
	}

	switch($request['method']){
		case 'GET':
			$data = $o->get( $request );
			$full_data = array_merge((array)$request['parent_data'], (array)$data);

			$ix = null;
			foreach ($this->requestedEntities as $key => $oEnt) {
				if($oEnt['entity']==$ent){
					$ix = $key;
					break;
				}
			}
			if($ix+1 !== count($this->requestedEntities)){
				$this->requestedEntities[$ix+1]['parent_data'] = $full_data;
				$this->requestedEntities[$ix+1]['parent_entity'] = $o;
				$this->execute( $this->requestedEntities[$ix+1] );
			}
			else
				self::output( 200, $data , $this->conf , $o);
			break;
		case 'POST':
			$return = $o->post( $request );
			self::output( 201, $return , $this->conf , $o );
		case 'PUT':
			$return = $o->put( $request );
			self::output( 204, $return , $this->conf , $o );
		case 'DELETE':
			$return = $o->delete( $request );
			self::output( 204, null , $this->conf , $o );
		case 'OPTIONS':
			$return = $o->options( $request );
			self::output( 200, $return , $this->conf , $o );
		default:
			throw new eiseRESTException('Unknown method', 500, null, $this);


	}

	return $full_data;

}


public static function output( $code, $data, $conf=array() , $oEntity = null){

	#echo ("{$_SERVER['SERVER_PROTOCOL']} {$code} {$data}");die();

	if($code!==200)
		header("{$_SERVER['SERVER_PROTOCOL']} {$code} {$data}");


	header('Access-Control-Allow-Origin: '.($code!==200 
		? (
			$conf['origin'] 
			? $conf['origin']
			: 'http://'.$_SERVER['HTTP_HOST'])
		: '*'
		)
	);
	header("Access-Control-Allow-Credentials: true");
	header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
	header('Access-Control-Max-Age: 1000');
	header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');

	header('Content-Type: '.$conf['Content-Type']);

	$str = self::process_output($data, $conf, $oEntity);

	if($conf['debug'] && $code!==200){
		$str .= "\r\n\r\n".$conf['debug_data'];
	}


	#header('Content-Length: '.strlen($str));

	echo $str;


	die();

}


public static function process_output($data, $conf, $oEntity = null){
	
	if(is_string($data))
		return $data;

	switch($conf['Content-Type']){
		case 'text/html':
			if(is_a($oEntity, 'eiseREST_Entity'))
				return $oEntity->htmlize( $data );

		case 'text/xml':
			return $this->xmlize( $data );

		case 'application/json':
			return json_encode( $data );

		case 'text/plain':
		default:
			return var_export($data, true);
	}
}

}


class eiseRESTException extends Exception {

public function __construct($msg, $code=0, $previous = null, $rest=null){

	parent::__construct($msg, $code, $previous);

	if($rest && $rest->conf['error500OnException']){
		if(is_a($previous, 'Exception')){
			$rest->conf['debug_data'] = 'Code: '.$previous->getCode()."\r\nMessage: ".$previous->getMessage()."\r\n\r\nStack trace:".$previous->getTraceAsString();
		}
		eiseREST::output( (!$code ? 500 : $code), $msg, ($rest ? $rest->conf : array()) );
	}

}

}