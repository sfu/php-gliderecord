<?php namespace SFU;

/**
 *  SFU GlideAccess: PHP API for accessing ServiceNow
 *  
 *  GlideAccess is used by a GlideRecord to make web services calls to ServiceNow.
 *  To use GlideRecord, you first need to call SFU\GlideAccess::init(), and then you can
 *  use GlideRecord as normal anywhere in your code.
 *  
 *  Uses REST to communicate with ServiceNow. Tested against Fuji.
 *  
 *  @author Mike Sollanych
 *  @package php-gliderecord
 */
 
class GlideAccess {
	
	protected static $glideaccess = false;
	protected $client;
	protected $base_uri;
	
	const API_VERSION = "v1";
	const TIMEOUT = 20;
	
	public static function init($server, $username, $password) {
		$glideaccess = new GlideAccess($server, $username, $password);
		self::$glideaccess = $glideaccess;
	}
	
	public static function getInstance() {
		if (self::$glideaccess) return self::$glideaccess;
		else throw new GlideAccessException("GlideAccess not initialized!");
	}
		
	protected function __construct($server, $username, $password) {
		
		$base_uri = "https://".$server.'/api/now/'.self::API_VERSION.'/';
		
		if (!filter_var($base_uri, FILTER_VALIDATE_URL)) throw new GlideAccessException("Invalid server name: $server");
		else $this->base_uri = $base_uri; 
	
		// Init the Guzzle connection 
		$this->client = new \GuzzleHttp\Client(["base_uri" => $this->getBaseURI(), 
		                                        "auth"     => [$username, $password],
												"timeout"  => self::TIMEOUT,
												"verify"   => false ]);
	}
	
	/**
	 *  Return the base URI of the ServiceNow instance and API that this GlideAccess instance is going aginst
	 */
	public function getBaseURI() { return $this->base_uri; }
	
	/**
	 *  Simple logging function
	 */
	protected function log($severity, $message) {
		
		// Todo, improve this with dependency injection
		print ("\n".date("Y-m-d H:i:s")." - $severity $message");
	}
	
	/**
	 *  Error code based exception broker
	 */
	private function handleErrors($response) {
		
		$code = $response->getStatusCode();
		
		switch ($code) {
			case 200:
			case 201:
			case 204:
				return true;
				
			case 401: 
				// Authentication 
				throw new GlideAuthenticationException("Access denied - check the username and password provided to GlideAccess.");
				
			case 403:
				// Authorization
				throw new GlideAuthorizationException("Permission denied");
				
			case 404: 
				// Nothing found
				return false;
			
			default: throw new GlideAccessException("HTTP error code: $code");
		}
	}
		
	/**
	 *  Given a relative URL, call it from ServiceNow and return an associative array based on the JSON body of the response.
	 *  If nothing is found, returns an empty array.
	 */
	public function get($url) {
		
		try {
			$this->log("debug", "Getting URL: ".$url);
			$response = $this->client->get($url);
		}
		catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = $e->getResponse();
			$this->handleErrors($response);
		}
		
		$body = $response->getBody();
		$body_decode = json_decode($body, true);
		
		if (!$body_decode || !array_key_exists("result", $body_decode)) {
			// Todo, improve this to give more debug out
			throw new GlideAccessException("Response from ServiceNow was not valid JSON");
		}
		
		return $body_decode["result"];
		
	}
	
	/**
	 *  Given a relative URL, puts raw data to the URL, generally to update a record.
	 *  Returns false if the record is not found (404). 
	 */
	public function put($url, $data) {
		
		try {
			$this->log("debug", "Putting URL: ".$url);
			$this->log("debug", "\n".print_r($data, true));
			$response = $this->client->put($url, ['body' => $data]);
		}
		catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = $e->getResponse();
			$this->handleErrors($response);
		}
		
		return true;
		
	}
	
	/**
	 *  Given a relative URL, tries to POST, generally used to create a record.
	 */
	public function post($url, $data) {
		
		try {
			$this->log("debug", "Posting URL: ".$url);
			$this->log("debug", "\n".print_r($data, true));
			$response = $this->client->post($url, ['body' => $data]);
		}
		catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = $e->getResponse();
			$this->handleErrors($response);
		}
		
		$body = $response->getBody();
		$body_decode = json_decode($body, true);
		
		if (!$body_decode || !array_key_exists("result", $body_decode)) {
			// Todo, improve this to give more debug out
			throw new GlideAccessException("Response from ServiceNow was not valid JSON");
		}
		
		return $body_decode["result"];
		
	}
	
	/**
	 *  Given a relative URL, tries to DELETE it.
	 *  Returns false if the record is not found (404). 
	 */
	public function delete($url) {
		
		try {
			$this->log("debug", "Deleting URL: ".$url);
			$response = $this->client->delete($url);
		}
		catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = $e->getResponse();
			$this->handleErrors($response);
		}
		
		return true;
		
	}
		
}	