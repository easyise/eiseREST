<?php
/**
 * Base class for REST entity. Required to be derived by any eiseREST entity class.
 * 
 */
class eiseREST_Entity {

public $states = array();

public $actions = array();

public $properties = array();

public $oSQL;

public $rest;

public function __construct($rest, $conf=array()){

	if(!$conf['name'])
		throw new eiseRESTException('No entity name specified', null, null, $conf['REST']);

	if(!is_a($rest, 'eiseREST'))
		throw new eiseRESTException('REST object is not passed to entity', 500, null, true);

	$this->rest = $rest;

	$this->conf = $conf;

}

public function get( $request ){ 

	$this->request = $request;

	if($request['id']){

		return $this->get_single_entry( $request['id'] );

	} else {

		return $this->execute_query( $request['query'] );

	}

}

public function put( $query ){
	throw new eiseRESTException("PUT is not allowed for entity: {$this->conf['name']}", 403, $e, $this->rest);
}

public function post( $query ){
	throw new eiseRESTException("POST is not allowed for entity: {$this->conf['name']}", 403, $e, $this->rest);
}

public function delete( $query ){
	throw new eiseRESTException("DELETE is not allowed for entity: {$this->conf['name']}", 403, $e, $this->rest);
}

public function options( $query ){
	throw new eiseRESTException("OPTIONS not found for entity: {$this->conf['name']}", 404, $e, $this->rest);
}


protected function get_single_entry( $id ){ throw new eiseRESTException("GET is not allowed for entity: {$this->conf['name']}", 403, $e, $this->rest); }

protected function execute_query( $query ){ throw new eiseRESTException("PUT is not allowed for entity: {$this->conf['name']}", 403, $e, $this->rest); }

}

