<?php namespace SFU;

/**
 *  SFU GlideUtil: Utility functions for GlideRecord 
 *  
 *  @author Mike Sollanych
 *  @package php-gliderecord
 */
 
class GlideUtil {
	
	static function isValidTable($table_name) {
		return (preg_match("/^[a-zA-Z0-9_]+$/", $table_name) ? true : false);
	}
	
	static function isValidColumn($table_name) {
		return (preg_match("/^[a-zA-Z0-9_]+$/", $table_name) ? true : false);
	}
	
	static function isValidSysID($sys_id) {
		return (preg_match("/^[a-z0-9]{32}$/", $sys_id) ? true : false);
	}
}