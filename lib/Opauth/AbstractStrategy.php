<?php
/**
 * Opauth Strategy
 * Individual strategies are to be extended from this class
 *
 * @copyright    Copyright © 2012 U-Zyn Chua (http://uzyn.com)
 * @link         http://opauth.org
 * @package      Opauth.Strategy
 * @license      MIT License
 */
namespace Opauth;

use \Exception;
use Opauth\Request;

/**
 * Opauth Strategy
 * Individual strategies are to be extended from this class
 *
 * @package			Opauth.Strategy
 */
abstract class AbstractStrategy {

	/**
	 * Compulsory config keys, listed as unassociative arrays
	 * eg. array('app_id', 'app_secret');
	 */
	public $expects;

	/**
	 * Optional config keys with respective default values, listed as associative arrays
	 * eg. array('scope' => 'email');
	 */
	public $defaults;

	/**
	 * Auth response array, containing results after successful authentication
	 */
	public $auth;

	/**
	 * Configurations and settings unique to a particular strategy
	 */
	protected $strategy;

	/**
	 * Url parameter to run callback()
	 *
	 * @var string
	 */
	public $callback = 'callback';

	protected $sessionKey = '_opauth_data';

	/**
	 * Constructor
	 *
	 * @param Request Request object
	 * @param array $config Strategy-specific configuration
	 */
	public function __construct(Request $Request, $config = array()) {
		$this->Request = $Request;
		$this->strategy = $config;

		if (is_array($this->expects)) {
			foreach ($this->expects as $key) {
				$this->expects($key);
			}
		}

		if (is_array($this->defaults)) {
			foreach ($this->defaults as $key => $value) {
				$this->optional($key, $value);
			}
		}

		/**
		 * Additional helpful values
		 */

		foreach ($this->strategy as $key => $value) {
			$this->strategy[$key] = $this->envReplace($value, $this->strategy);
		}
	}

	/**
	 * Returns the complete callbackurl
	 *
	 * @return string
	 */
	protected function callbackUrl() {
		return $this->Request->providerUrl() . '/' . $this->callback;
	}

	/**
	 * Getter/setter method for session data
	 *
	 * @param array $data Array to write or null to read
	 * @return array Sessiondata
	 */
	protected function sessionData($data = null) {
		if (!session_id()) {
			session_start();
		}
		if (!$data) {
			$data = $_SESSION[$this->sessionKey];
			unset($_SESSION[$this->sessionKey]);
			return $data;
		}
		return $_SESSION[$this->sessionKey] = $data;
	}

	/**
	 * Adds strategy config values to params array if set.
	 * $configKeys array may contain string values, or key => value pairs
	 * key for the strategy key, value for the params key to set
	 *
	 * @param array $configKeys
	 * @param array $params
	 * @return array
	 */
	protected function addParams($configKeys, $params = array()) {
		foreach ($configKeys as $configKey => $paramKey) {
			if (is_numeric($configKey)) {
				$configKey = $paramKey;
			}
			if (!empty($this->strategy[$configKey])) {
				$params[$paramKey] = $this->strategy[$configKey];
			}
		}
		return $params;
	}

	/**
	 * Packs $auth nicely and send to callback_url, ships $auth either via GET, POST or session.
	 * Set shipping transport via callback_transport config, default being session.
	 */
	protected function _callback() {

		// To standardize the way of accessing data, objects are translated to arrays
		$this->auth = $this->recursiveGetObjectVars($this->auth);

		$this->auth['provider'] = $this->strategy['provider'];

		$params = array(
			'auth' => $this->auth,
		);

		return $params;
	}

	/**
	 * Error callback
	 *
	 * More info: https://github.com/uzyn/opauth/wiki/Auth-response#wiki-error-response
	 *
	 * @param array $error Data on error to be sent back along with the callback
	 *   $error = array(
	 *     'provider'	// Provider name
	 *     'code'		// Error code, can be int (HTTP status) or string (eg. access_denied)
	 *     'message'	// User-friendly error message
	 *     'raw'		// Actual detail on the error, as returned by the provider
	 *   )
	 *
	 */
	public function errorCallback($error) {
		$error = $this->recursiveGetObjectVars($error);
		$error['provider'] = $this->strategy['provider'];

		$params = array(
			'error' => $error,
		);
		return $params;
	}

	/**
	 * Ensures that a compulsory value is set, throws an error if it's not set
	 *
	 * @param string $key Expected configuration key
	 * @param string $not If value is set as $not, trigger E_USER_ERROR
	 * @return mixed The loaded value
	 */
	protected function expects($key, $not = null) {
		if (!array_key_exists($key, $this->strategy)) {
			throw new Exception(get_class($this) . " config parameter for \"$key\" expected.");
		}

		$value = $this->strategy[$key];
		if (empty($value) || $value == $not) {
			throw new Exception(get_class($this) . " config parameter for \"$key\" expected.");
		}
		return $value;
	}

	/**
	 * Loads a default value into $strategy if the associated key is not found
	 *
	 * @param string $key Configuration key to be loaded
	 * @param string $default Default value for the configuration key if none is set by the user
	 * @return mixed The loaded value
	 */
	protected function optional($key, $default = null) {
		if (!array_key_exists($key, $this->strategy)) {
			$this->strategy[$key] = $default;
			return $default;
		}
		return $this->strategy[$key];
	}

	/**
	 * Maps user profile to auth response
	 *
	 * @param array $profile User profile obtained from provider
	 * @param string $profile_path Path to a $profile property. Use dot(.) to separate levels.
	 *        eg. Path to $profile['a']['b']['c'] would be 'a.b.c'
	 * @param string $auth_path Path to $this->auth that is to be set.
	 */
	protected function mapProfile($profile, $profile_path, $auth_path){
		$from = explode('.', $profile_path);

		$base = $profile;
		foreach ($from as $element) {
			if (!is_array($base) || array_key_exists($element, $base)) {
				return false;
			}
				$base = $base[$element];
		}
		$value = $base;

		$to = explode('.', $auth_path);

		$auth = &$this->auth;
		foreach ($to as $element){
			$auth = &$auth[$element];
		}
		$auth = $value;
		return true;

	}


	/**
	 * *****************************************************
	 * Utilities
	 * A collection of static functions for strategy's use
	 * *****************************************************
	 */

	/**
	* Recursively converts object into array
	* Basically get_object_vars, but recursive.
	*
	* @param mixed $obj Object
	* @return array Array of object properties
	*/
	public static function recursiveGetObjectVars($obj){
		$arr = array();
		$_arr = is_object($obj) ? get_object_vars($obj) : $obj;

		foreach ($_arr as $key => $val){
			$val = (is_array($val) || is_object($val)) ? self::recursiveGetObjectVars($val) : $val;

			// Transform boolean into 1 or 0 to make it safe across all Opauth HTTP transports
			if (is_bool($val)) $val = ($val) ? 1 : 0;

			$arr[$key] = $val;
		}

		return $arr;
	}

	/**
	 * Replace defined env values enclused in {} with values from $dictionary
	 *
	 * @param string $value Input string
	 * @param array $dictionary Dictionary to lookup values from
	 * @return string String substitued with value from dictionary, if applicable
	 */
	public static function envReplace($value, $dictionary) {
		if (is_string($value) && preg_match_all('/{([A-Za-z0-9-_]+)}/', $value, $matches)) {
			foreach ($matches[1] as $key){
				if (array_key_exists($key, $dictionary)){
					$value = str_replace('{'.$key.'}', $dictionary[$key], $value);
				}
			}
			return $value;
		}
		return $value;
	}

}
