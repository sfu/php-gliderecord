<?php namespace SFU;

/**
 *  SFU GlideRecord: PHP API for accessing ServiceNow
 *  
 *  Using this should be similar to anyone familiar with ServiceNow's own GlideRecord
 *  server-side implementation. Not all methods of the full GlideRecord API are available
 *  but there should be enough here to implement most external functionality.
 *  
 *  Uses REST to communicate with ServiceNow. Tested against Fuji.
 *  
 *  @author Mike Sollanych
 *  @package php-gliderecord
 */
 
class GlideRecord implements \Iterator, \Countable, \ArrayAccess {

	// These all start with underscores because it helps to avoid conflicts with column names.
	
	// Name of the table. 
	protected $_table_name;
	
	// Instance of GlideAccess
	protected $_access;
	
	// Encoded queries are enqueued here 
	protected $_queries = [];
	protected $_limit = false;

	// Internal array stuff
	protected $_data = [];
	protected $_data_position = 0;
	protected $_data_firstnext = false;
	protected $_data_modified = [];
	
	
	/**
	 *  Create a GlideRecord instance for a given table
	 */
	public function __construct($table_name) {
	
		// Get the GlideAccess singleton (throws GlideAccessException if not configured)
		$this->_access = GlideAccess::getInstance();
		
		if (!GlideUtil::isValidTable($table_name)) throw new GlideValidationException("Invalid table name $table_name");
		else $this->_table_name = $table_name;
		
		$this->initialize();
	}
	
	/**
	 *  Initialize this object with a clean slate
	 */
	public function initialize() {
		$this->_queries = [];
		$this->_data = [[]];
		$this->_limit = false;
		$this->_data_position = 0;
		$this->_data_firstnext = false;
		$this->_data_modified = [];
	}
	
	/**
	 *  Get internal array, generally for debugging
	 */
	public function getData() { return $this->_data; }
	
	
	/**
	 *  Get a key value from the internal array. Returns Null if the key is not found.
	 */
	public function __get($key)  {
		if (array_key_exists($key, $this->_data[$this->_data_position])) {
			return ($this->_data[$this->_data_position][$key]);
		}
		else return null;
	}
	
	/**
	 *  Set a key value in the internal array. Saves the fact that the field was modified for later update() use.
	 */
	public function __set($key, $value) {
		$this->_data[$this->_data_position][$key] = $value;
		$this->_data_modified[$this->_data_position][] = $key;
	}
	
	/**
	 *  Get Table name
	 */
	public function getTableName() { return $this->_table_name; }
	
	/**
	 *  Add a query to the queue. Todo, normal addQuery without requiring glide encoding. 
	 */
	public function addEncodedQuery($query) {
		$this->_queries[] = $query;
	}
	
	/**
	 *  Order by a given column, in ascending order (A-Z)
	 */
	public function orderBy($column) {
		if (!GlideUtil::isValidColumn($column)) throw new GlideRecordException("Invalid column name to order by: $order_by");
		
		$this->_queries[] = "ORDERBY$column";
	}
	
	/**
	 *  Order by a given column, in descending order (Z-A)
	 */
	public function orderByDesc($column) {
		if (!GlideUtil::isValidColumn($column)) throw new GlideRecordException("Invalid column name to order by: $order_by");
		
		$this->_queries[] = "ORDERBYDESC$column";
	}
		
	/**
	 *  Sets a query limit  
	 */
	public function setLimit($limit) {
		if (!is_int($limit)) throw new GlideRecordException("Invalid limit $limit");
		else $this->_limit = $limit;
	}
	
	/**
	 *  Query: provide an encoded Glide query statement and this will retrieve records and return them inside this GlideRecord.
	 */
	public function query() {
		
		// Build query 
		if (count($this->_queries) < 1) throw new GlideRecordException("No queries have been added to this GlideRecord");
		
		$query = '';
		foreach ($this->_queries as $q) {
			// Add with separator 
			$query .= (strlen($query) > 0) ? '^'.$q : $q;
		}
		
		// Build URL
		$url = "table/".$this->_table_name."?sysparm_query=".urlencode($query);
		if ($this->_limit) $url .= "&sysparm_limit=".$this->_limit; 
		
		// Use GlideAccess to run the GET command 
		// Throw a GlideAccessException or a GlideAuthenticationException on error 
		$result = $this->_access->get($url);
			
		// Set data into internal array
		$this->_data = $result;
		$count = $this->getRowCount();
		
		if ($count > 0) {
			$this->_data_modified = array_fill(0, $count, []);
			return true;
		}
		else return false; 
	}
	
	/**
	 *  Get one record, either by sys_id, or by a field equal to a value 
	 */
	public function get($sysid_or_field, $value = false) {
	
		$this->initalize();
	
		// if value, then it's a field, if not, then it's a sysid 
		if (!$value) {
			$this->_queries[] = "sys_id=".$sysid_or_field;
		}
		else {
			$this->_queries[] = $sysid_or_field.'='.$value;
		}
		
		return $this->query();
			
	}
	
	
	
	/**
	 *  Update the current GlideRecord 
	 */
	public function update() {
		
		$mods = $this->_data_modified[$this->_data_position];
		if (count($mods) < 1) return true; // no changes.
			
		// Build data
		$changes = [];
		foreach ($mods as $mod) $changes[$mod] = $this->__get($mod);
		
		// Build URL
		$url = "table/".$this->_table_name."/".$this->__get("sys_id");
		
		// Use GlideAccess to run the PUT command 
		// Throw a GlideAccessException or a GlideAuthenticationException on error 
		$result = $this->_access->put($url, json_encode($changes));
			
		// Clear out the pending updates for this record
		$this->_data_modified[$this->_data_position] = [];
	}		
		
	/**
	 *  Deletes the current record 
	 */
	public function deleteRecord() {
		if (!GlideUtil::isValidSysID($this->__get("sys_id"))) {
			throw new GlideRecordException("Cannot delete this record (no sys_id - is it a valid record?");
		}
		
		// Build URL
		$url = "table/".$this->_table_name."/".$this->__get("sys_id");
		
		// Use GlideAccess to run the DELETE command 
		// Throw a GlideAccessException or a GlideAuthenticationException on error 
		$result = $this->_access->delete($url);
			
		// Unset this record from the current result set
		$this->offsetUnset($this->_data_position);
	}
	
	/**
	 *  Inserts the current record into the database as a new record.
	 *  On success, this GlideRecord object will be changed and will only contain the new object (with any automatic properties and values from Snow).
	 *  Returns the sys_id of the new record or false if insert failed. 
	 */
	public function insert() {
		
		// Copy the data
		$mydata = $this->_data[$this->_data_position];
		unset($mydata["sys_id"]);
		
		// Build URL
		$url = "table/".$this->_table_name;
		
		// Use GlideAccess to run the POST command 
		// Throw a GlideAccessException or a GlideAuthenticationException on error 
		$result = $this->_access->post($url, json_encode($mydata));
		
		if ($result && is_array($result) && array_key_exists("sys_id", $result) && GlideUtil::isValidSysID($result["sys_id"])) {
			
			// This record now takes over the current result set, back at record 0.
			$this->initialize();
			$this->_data = [$result];
			
			return $this->__get("sys_id");
		}
		else return false;
			
	}
	
	
	/**
	 *  Determine if this GlideRecord result set has any more elements in it 
	 */
	public function hasNext() {
		print("Position ".($this->_data_position + 1)." and count ".$this->getRowCount());
		return (($this->_data_position + 1) < $this->getRowCount());
	}
	
	/**
	 *  Iterator functions 
	 */
	
	/**
	 *  Return the current record 
	 */
	public function &current() {
		return $this->_data[$this->_data_position];
	}
	
	/**
	 *  Advance array pointer to next element 
	 */
	public function next() {
		++$this->_data_position;
	}
	
	/**
	 *  Reset array pointer
	 */
	public function rewind() {
		$this->_data_position = 0;
	}
	
	/**
	 *  Validate current position 
	 */
	 public function valid() {
		 return (($this->_data_position) < $this->getRowCount());		 
	 }
	 
	/**
	 *  Return current position
	 */
	 public function key() {
		 return $this->_data_position;
	 }
	
	/**
	 *  Return the number of rows in the result set. Included for similarity to the real GlideRecord.
	 */
	public function getRowCount() {
		return $this->count();
	}
	
	/**
	 *  Countable interface
	 */
	public function count() {
		return count($this->_data);
	}
	
	/**
	 *  ArrayAccess interface
	 */
	public function offsetExists($offset) {
		return ($offset >= 0 && ($offset < $this->count()));
	}
	public function offsetGet($offset) {
		$this->_data_position = $offset;
		return $this;
	}
	public function offsetSet($offset, $value) {
		if (!is_array($value)) throw new GlideRecordException("Cannot directly set a non-array value!");
		
		// Set all new keys as modified
		$this->_data_modified[$offset] = [];
		foreach(array_keys($value) as $k) $this->_data_modified[$offset][] = $k;
		
		// Set new array in place 
		$this->_data[$offset] = $value;
	}
	public function offsetUnset($offset) {
		unset($this->_data[$offset]);
		if ($this->_data_position == $offset) $this->rewind();
	}
}