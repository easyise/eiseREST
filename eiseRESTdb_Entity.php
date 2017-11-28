<?php 
/**
 * eiseREST entity class for entities based on database objects like tables or views
 */
include_once 'eiseREST_Entity.php';

class eiseRESTdb_Entity extends eiseREST_Entity {


public $fields = array();


public function __construct($rest, $conf=array()){

	if(!$conf['table'])
		throw new eiseRESTException('No table name specified', 500, null, $rest);


	parent::__construct( $rest, array_merge($conf, array('name'=>$conf['table'])) );

	$this->conf['tableInfo'] = $this->rest->oSQL->getTableInfo($this->conf['table']);

	$table_prefix = $this->conf['tableInfo']['prefix'];

	foreach($this->conf['tableInfo']['columns'] as $colName=>$col){
		if($this->rest->conf['flagNoActivityStamp'] && $col['DataType']=='activity_stamp'){
			continue;
		}

		$fieldNoPrefix = preg_replace('/^'.preg_quote($table_prefix, '/').'/', '', $col['Field']);
		$field = ( $this->rest->conf['flagNoFieldPrefixes'] 
			? $fieldNoPrefix
			: $col['Field']
		);

		$col['Title'] = $col['Comment'] ? $col['Comment'] : $fieldNoPrefix;

		$this->properties[$field] = $col;

		$this->fields[$col['Field']] = $field;

	}

}

protected function get_single_entry( $id ){ return array(); }

protected function execute_query( $query ){ 

	$oSQL = $this->rest->oSQL;

	$sqlFields = '';
	$sqlWhere = $this->get_search_criteria( $query );
	$sqlOrderBy = $this->get_sort_order( $query );

	foreach($this->properties as $field=>$col){
		$sqlFields .= ($sqlFields ? ', ' : '').'`'.$col['Field'].'`';
	}

	$sql = "SELECT {$sqlFields} FROM `{$this->conf['tableInfo']['table']}`".
		($sqlWhere 
			? 'WHERE '.$sqlWhere
			: '').
		($sqlOrderBy
			? 'ORDER BY '.$sqlOrderBy
			: '')
		;

	try {
		$rs = $oSQL->q($sql);
	} catch (Exception $e) {
		throw new eiseRESTException('MySQL error '.$e->getcode(), 500, $e, $this->rest);
	}

	$arrRet = array();

	while($rw = $oSQL->f($rs)){
		$a = array();
		foreach($rw as $field=>$value){
			$a[$this->fields[$field]] = $value;
		}
		$arrRet[] = $a;
	}

	return $arrRet;

}

protected function get_search_criteria ( $query ){

	$sqlWhere = '';
	return $sqlWhere;

}

protected function get_sort_order ( $query ){

	$sqlWhere = '';
	return $sqlWhere;

}


}