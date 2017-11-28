<?php
include 'eiseREST.php';

include 'inc_mysqli.php';

include 'eiseRESTdb_Entity.php';

class eiseRESTdb extends eiseREST{

protected static $defaultConf = array(
	/** 'allow' for whitelist-based functionality, 'deny' - for blacklist one. List of allowed for denied tables are under 'table' property */
	'allowDeny' => 'allow'
	/** array of tables allowed or denied for quering*/
	, 'tables' => array()
	, 'table_prefix' => array('vw', 'svw', 'tbl')

	/** app table */
	, 'stbl_app' => 'stbl_app'

	, 'DBHOST' => 'localhost'
	, 'DBUSER' => null
	, 'DBPASS' => null
	, 'DBNAME' => null

	, 'flagNoActivityStamp' => true
	, 'flagNoFieldPrefixes' => true
);


public function __construct( $conf = array() ){

	parent::__construct($conf);

	$this->conf = array_merge(parent::$defaultConf, self::$defaultConf, $conf);

	if(!$this->conf['DBNAME'] || !$this->conf['DBUSER'] || !$this->conf['DBPASS'] )
		throw new eiseRESTException('No database credentials specified', null, null, $this);

	try {
		$oSQL = $this->oSQL = new eiseSQL($this->conf['DBHOST'], $this->conf['DBUSER'], $this->conf['DBPASS'], $this->conf['DBNAME']);
	} catch (Exception $e) {
		throw new eiseRESTException($e->getMessage(), null, null, $this);		
	}

	foreach((array)$this->conf['table_prefix'] as $prfx){

		$prfx = $prfx.'_';

		if(preg_match('/^allow/i', $this->conf['allowDeny'])){
			foreach((array)$this->conf['tables'] as $tbl){
				$tblName = $prfx.preg_replace('/^'.preg_quote($prfx,'/').'/', '', $tbl);
				$tblName = $oSQL->d("SHOW TABLES LIKE '{$tblName}'");
				if(!$tblName)
					continue;

				if(!$this->entities[$tbl])
					$this->registerEntityByTable($tblName, $prfx);

			}
		} else {
			$rsT = $oSQL->q('SHOW TABLES');
			while($rwT=$oSQL->fa($rsT)){
				$tbl = $rwT[0];
				$tbl_no_prefix = preg_replace('/'.preg_quote($prfx, '/').'/', '', $tbl);
				if( in_array($tbl, $this->conf['tables']) || in_array($tbl_no_prefix, $this->conf['tables']) )
					continue;

				$this->registerEntityByTable($tbl, $prfx);

			}
		}

	}
}

private function registerEntityByTable($tbl, $prfx){

	$tbl_no_prefix = ($prfx ? preg_replace('/^'.preg_quote($prfx,'/').'/', '', $tbl) :  $tbl);

	$this->entities[$tbl_no_prefix] = new eiseRESTdb_Entity( $this, array('table'=>$tbl) );

}

	
}