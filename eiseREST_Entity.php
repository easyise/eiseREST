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

public function put(){}

public function post(){}

public function delete(){}


protected function get_single_entry( $id ){ return array(); }

protected function execute_query( $query ){ return array(); }

}

