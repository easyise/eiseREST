<?php 
// #########################################################################
// Database class for mysql functions
// #########################################################################
class eiseSQL extends mysqli{
    
    public $arrIntra2DBTypeMap = array(        
        "integer"=>'int(11)',
        "real"=>'decimal(16,4)',
        "boolean"=>'tinyint(4)',
        "text"=>'varchar(1024)',
        "binary"=>'blob',
        "date"=>'date',
        "time"=>'time',
        "datetime" => 'datetime'
        );

    public $arrDBTypeMap = array(        
        "integer"=>array(MYSQLI_TYPE_SHORT
          , MYSQLI_TYPE_LONG
          , MYSQLI_TYPE_LONGLONG
          , MYSQLI_TYPE_INT24
          , MYSQLI_TYPE_YEAR),
        "real"=>array(MYSQLI_TYPE_DECIMAL
          , MYSQLI_TYPE_NEWDECIMAL
          , MYSQLI_TYPE_FLOAT
          , MYSQLI_TYPE_DOUBLE),
        "boolean"=>array(MYSQLI_TYPE_BIT
          , MYSQLI_TYPE_TINY
          , MYSQLI_TYPE_CHAR),
        "text"=>array(MYSQLI_TYPE_ENUM
          , MYSQLI_TYPE_SET
          , MYSQLI_TYPE_VAR_STRING
          , MYSQLI_TYPE_STRING
          , MYSQLI_TYPE_GEOMETRY),
        "binary"=>array(MYSQLI_TYPE_TINY_BLOB
          , MYSQLI_TYPE_MEDIUM_BLOB
          , MYSQLI_TYPE_LONG_BLOB
          , MYSQLI_TYPE_BLOB),
        "date"=>array(MYSQLI_TYPE_DATE
          , MYSQLI_TYPE_NEWDATE),
        "time"=>array(MYSQLI_TYPE_TIME
          , MYSQLI_TYPE_INTERVAL),
        "datetime" => array(MYSQLI_TYPE_DATETIME),
        "timestamp" => array(MYSQLI_TYPE_TIMESTAMP)
        );

    /* *** WARNING! method connect() only make some adjustments *** */
    function __construct ($dbhost, $dbuser, $dbpass, $dbname, $flagPersistent=false)  {
        
        @parent::__construct(($flagPersistent ? 'p:' : '').$dbhost, $dbuser, $dbpass, $dbname);
        
        if ($this->connect_errno) {
            throw new Exception("Unable to connect to database: {$this->connect_error} ({$this->connect_errno})");
        }
        
        $this->dbhost = $dbhost;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;
        $this->dbname = $dbname;
        $this->flagPersistent = $flagPersistent;
        $this->flagProfiling = false;
        $this->dbtype="MySQL5";
        
        $this->set_charset('utf8');
        
    }
    
    // DUMMY connect to the database
    function connect($host = NULL, $user = NULL, $password = NULL, $database = NULL, $port = NULL, $socket = NULL){
        return true;
    }
    
    function selectDB($dbname='') {
        if ($dbname)
            $this->dbname = $dbname;
            
        $res = $this->select_db($this->dbname);
      
        return $res;
      
    }
    
    // escapes chracters
    function e($str, $usage="for_ins_upd"){ //escape_string
        return "'".($usage!="for_ins_upd" 
        ? "%".str_replace("_", "\_", self::real_escape_string($str))."%"
        : self::real_escape_string($str)
        )."'";
    }
    function unq($sqlReadyValue){
        return (strtoupper($sqlReadyValue)=='NULL' ? null : (string)preg_replace("/^(')(.*)(')$/", '\2', $sqlReadyValue));
    }

    function secure($arg){
        return $this->unq($this->e($arg));
    }
    
    //do_query, returns object mysqli_result
    function q($query){ 
        
        if ($this->flagProfiling)
              $timeStart = microtime(true);
              
        $this->rs = $this->query($query);
        if ($this->flagProfiling) {
             $this->arrQueries[] = $query;
             $this->arrResults[] = ($this->rs 
                ?  Array("affected" => $this->a(), "returned"=>@$this->n($this->rs))
                :  Array("error"=>"Unable to do_query: $query")
                );
            $this->arrMicroseconds[] = number_format(microtime(true)-$timeStart, 6, ".", "");
            $arrBkTrace = debug_backtrace();
            $arrToRecord = array();
            foreach($arrBkTrace as $i=>$call){
                $arrToRecord[$i] = ($call['class'] ? $call['class'].'::' : '').$call['function'].'() @ '.$call['file'].':'.$call['line'];
            }
            $this->arrBacktrace[] = $arrToRecord;
        }
          
        if (!$this->rs) {
            $this->not_right("Unable to do_query: $query");
        }
        return $this->rs;
    }
    
    //num_rows
    function n($mysqli_result){ 
        return $mysqli_result->num_rows;
    }
    
    function f($mysqli_result_or_query){ //fetch_assoc
        if (is_object($mysqli_result_or_query)){
            $mysqli_result = $mysqli_result_or_query;
            return $mysqli_result->fetch_assoc();
        } else if (is_string($mysqli_result_or_query)){
            $sql = $mysqli_result_or_query;
            $mysqli_result = $this->q($sql);
            return $this->f($mysqli_result);
        } else
            throw new Exception('Wront variable type passed to eiseSQL::get_data() function: '.gettype($variant));
    }
    function fa($mysqli_result){ //fetch_ix_array
        return $mysqli_result->fetch_array();
    }
    function ff($mysqli_result){ //fetch_ix_array
        return $this->fetch_fields($mysqli_result);
    }
    function i(){ //insert_id
        return $this->insert_id;
    }
    function a(){ //affected_rows
        return $this->affected_rows;
    }
    function d($mysqli_result_or_query){ //get_data
        if (is_object($mysqli_result_or_query)){
            $mysqli_result = $mysqli_result_or_query;
            $mysqli_result->data_seek(0);
            $arr = $mysqli_result->fetch_array();
            return $arr[0];
        } else if (is_string($mysqli_result_or_query)){
            $sql = $mysqli_result_or_query;
            $mysqli_result = $this->q($sql);
            return $this->d($mysqli_result);
        } else
            throw new Exception('Wront variable type passed to eiseSQL::get_data() function: '.gettype($variant));
    }
    
    function fetch_fields($mysqli_result){
        $arrRet = array();
        $arrFields = $mysqli_result->fetch_fields();
        foreach($arrFields as $field){
            $fname = $field->name;
            foreach($this->arrDBTypeMap as $type=>$arrConst){
                if (in_array($field->type, $arrConst)){
                    $arrRet[$fname]['type'] = $type;
                }
            }
            if (!$arrRet[$fname]['type'])
                $arrRet[$fname]['type'] = 'text';
            if($arrRet[$fname]['type']=='real'){
                $arrRet[$fname]['decimalPlaces'] = $field->decimals;
            }
        }
        return $arrRet;
    }
        
    function escape_string($str, $usage="for_ins_upd"){
        return self::e($str, $usage);
    }


  // #######################################################################
  // Grab the error descriptor
  // #######################################################################
    function graberrordesc() {
      $this->error=mysql_error();
      return $this->error;
    }

  // #######################################################################
  // Grab the error number
  // #######################################################################
    function graberrornum() {
      $this->errornum=$this->errno;
      return $this->errornum;
    }

  // #######################################################################
  // Do the query
  // #######################################################################
    function do_query($query) {     return $this->q($query);}
    
  // #######################################################################
  // Obtain insert ID
  // #######################################################################
    function insert_id() {          return $this->i();    }

  // #######################################################################
  // Fetch the next row in an array
  // #######################################################################
    function fetch_array($sth) {    return $this->f($sth);}
    
  // #######################################################################
  // Gets the one field
  // #######################################################################
    function get_data($sth) {       return $this->d($sth);    }
    
  // #######################################################################
  // Move internal result pointer
  // #######################################################################
    function data_seek( $mysqli_result, $row_number) {
        return $mysqli_result->data_seek($row_number);
    }

  // #######################################################################
  // Finish the statement handler
  // #######################################################################
    function free_result($mysqli_result) {
        return $mysqli_result->free();
    }
    function finish_sth($mysqli_result) {
        return $mysqli_result->free();
    }

  // #######################################################################
  // Grab the total rows
  // #######################################################################
    function total_rows($mysqli_result) {   return $this->n($mysqli_result);}
    function num_rows($mysqli_result) {     return $this->n($mysqli_result);}
  function affected_rows(){           return $this->a();              }
  
  function get_new_guid(){
        return $this->d("SELECT UUID()"); 
  }
    
  // #######################################################################
  // Die
  // #######################################################################
    function not_right($error="MySQL error") {
        if ($this->flagProfiling)
            $this->showProfileInfo();
        throw new Exception("{$this->errno}: {$this->error},\r\n{$error}\r\n", $this->errno);
    }
    
    function startProfiling(){
       $this->arrQueries = Array();
       $this->arrResults = Array();
       $this->arrMicroseconds = Array();
       $this->arrBacktrace = Array();
       $this->flagProfiling = true;
    }
    
    function showProfileInfo(){
       echo "<pre>";
       echo "Profiling results:";
       for($ii=0;$ii<count($this->arrQueries);$ii++){
          echo "\r\nQuery ##".($ii+1)." (a: {$this->arrResults[$ii]['affected']}, n: {$this->arrResults[$ii]['returned']}), time ".$this->arrMicroseconds[$ii].":\r\n";
          echo $this->arrQueries[$ii]."\r\n";
          echo 'Backtrace:';
          print_r($this->arrBacktrace[$ii]);
          echo "\r\n";
       }
       echo "</pre>";
    }

    function getProfileInfo(){
      $arrRet = array();
      for($ii=0;$ii<count($this->arrQueries);$ii++){
          $arrRet[] = array('query'=>$this->arrQueries[$ii]
            , 'affected'=>$this->arrResults[$ii]['affected']
            , 'returned'=>$this->arrResults[$ii]['returned']
            , 'backtrace'=>$this->backtrace[$ii]
            , 'time'=>$this->arrMicroseconds[$ii]);
      }
      return $arrRet;
    }

    function getTableInfo($tblName, $dbName=null){
        
        $oSQL = $this;
        $dbName = ($dbName ? $dbName : $this->dbname);
        
        $arrPK = Array();

        $rwTableStatus=$oSQL->f($oSQL->q("SHOW TABLE STATUS FROM $dbName LIKE '".$tblName."'"));
        if($rwTableStatus['Comment']=='VIEW' && $rwTableStatus['Engine']==null){
            $tableType = 'view';
        } else {
            $tableType = 'table';
        }

        
        $sqlCols = "SHOW FULL COLUMNS FROM `".$tblName."`";
        $rsCols  = $oSQL->do_query($sqlCols);
        $ii = 0;
        while ($rwCol = $oSQL->fetch_array($rsCols)){
            
            if ($ii==0)
                $firstCol = $rwCol["Field"];
            
            $strPrefix = (isset($strPrefix) && $strPrefix==substr($rwCol["Field"], 0, 3) 
                ? substr($rwCol["Field"], 0, 3)
                : (!isset($strPrefix) ? substr($rwCol["Field"], 0, 3) : "")
                );
            
            if (preg_match("/int/i", $rwCol["Type"]))
                $rwCol["DataType"] = "integer";
            
            if (preg_match("/float/i", $rwCol["Type"])
               || preg_match("/double/i", $rwCol["Type"])
               || preg_match("/decimal/i", $rwCol["Type"]))
                $rwCol["DataType"] = "real";
            
            if (preg_match("/tinyint/i", $rwCol["Type"])
                || preg_match("/bit/i", $rwCol["Type"]))
                $rwCol["DataType"] = "boolean";
            
            if (preg_match("/char/i", $rwCol["Type"])
               || preg_match("/text/i", $rwCol["Type"]))
                $rwCol["DataType"] = "text";
            
            if (preg_match("/binary/i", $rwCol["Type"])
               || preg_match("/blob/i", $rwCol["Type"]))
                $rwCol["DataType"] = "binary";
                
            if (preg_match("/date/i", $rwCol["Type"])
               || preg_match("/time/i", $rwCol["Type"]))
                $rwCol["DataType"] = $rwCol["Type"];
                
            if (preg_match("/ID$/", $rwCol["Field"]) && $rwCol["Key"] != "PRI"){
                $rwCol["FKDataType"] = $rwCol["DataType"];
                $rwCol["DataType"] = "FK";
            }
            
            if ($rwCol["Key"] == "PRI" 
                    || preg_match("/^$strPrefix(GU){0,1}ID$/i",$rwCol["Field"])
                ){
                $rwCol["PKDataType"] = $rwCol["DataType"];
                $rwCol["DataType"] = "PK";
            }
            
            if ($rwCol["Field"]==$strPrefix."InsertBy" 
              || $rwCol["Field"]==$strPrefix."InsertDate" 
              || $rwCol["Field"]==$strPrefix."EditBy" 
              || $rwCol["Field"]==$strPrefix."EditDate" ) {
                $rwCol["DataType"] = "activity_stamp"; 
                $arrTable['hasActivityStamp'] = true;
            }
            $arrCols[$rwCol["Field"]] = $rwCol;
            if ($rwCol["Key"] == "PRI"){
                $arrPK[] = $rwCol["Field"];
                if ($rwCol["Extra"]=="auto_increment")
                    $pkType = "auto_increment";
                else 
                    if (preg_match("/GUID$/", $rwCol["Field"]) && preg_match("/^(varchar)|(char)/", $rwCol["Type"]))
                        $pkType = "GUID";
                    else 
                        $pkType = "user_defined";
            }
            $ii++;
        }
        
        if (count($arrPK)==0)
            $arrPK[] = $arrCols[$firstCol]['Field'];
        
        $sqlKeys = "SHOW KEYS FROM `".$tblName."`";
        $rsKeys  = $oSQL->do_query($sqlKeys);
        while ($rwKey = $oSQL->fetch_array($rsKeys)){
          $arrKeys[] = $rwKey;
        }
        
        //foreign key constraints
        $rwCreate = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE `{$tblName}`"));
        $strCreate = $rwCreate["Create Table"];
        $arrCreate = explode("\n", $strCreate);$arrCreateLen = count($arrCreate);
        for($i=0;$i<$arrCreateLen;$i++){
            // CONSTRAINT `FK_vhcTypeID` FOREIGN KEY (`vhcTypeID`) REFERENCES `tbl_vehicle_type` (`vhtID`)
            if (preg_match("/^CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/", trim($arrCreate[$i]), $arrConstraint)){
                foreach($arrCols as $idx=>$col){
                    if ($col["Field"]==$arrConstraint[2]) { //if column equals to foreign key constraint
                        $arrCols[$idx]["DataType"]="FK";
                        $arrCols[$idx]["ref_table"] = $arrConstraint[3];
                        $arrCols[$idx]["ref_column"] = $arrConstraint[4];
                        break;
                    }
                }
                /*
                echo "<pre>";
                print_r($arrConstraint);
                echo "</pre>";
                //*/
            }
        }
        
        $arrColsIX = Array();
        foreach($arrCols as $ix => $col){ $arrColsIX[$col["Field"]] = $col["Field"]; }
        
        $strPKVars = $strPKCond = $strPKURI = '';
        foreach($arrPK as $pk){
            $strPKVars .= "\${$pk}  = (isset(\$_POST['{$pk}']) ? \$_POST['{$pk}'] : \$_GET['{$pk}'] );\r\n";
            $strPKCond .= ($strPKCond!="" ? " AND " : "")."`{$pk}` = \".".(
                    in_array($arrCols["DataType"], Array("integer", "boolean"))
                    ? "(int)(\${$pk})"
                    : "\$oSQL->e(\${$pk})"
                ).".\"";
            $strPKURI .= ($strPKURI!="" ? "&" : "")."{$pk}=\".urlencode(\${$pk}).\"";
        }
        
        $arrTable['columns'] = $arrCols;
        $arrTable['keys'] = $arrKeys;
        $arrTable['PK'] = $arrPK;
        $arrTable['PKtype'] = $pkType;
        $arrTable['prefix'] = $strPrefix;
        $arrTable['table'] = $tblName;
        $arrTable['columns_index'] = $arrColsIX;
        
        $arrTable["PKVars"] = $strPKVars;
        $arrTable["PKCond"] = $strPKCond;
        $arrTable["PKURI"] = $strPKURI;

        $arrTable['type'] = $tableType;

        $arrTable['Comment'] = $rwTableStatus['Comment'];
        
        return $arrTable;
    }

}
