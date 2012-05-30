<?php
/**
 * OpauthTest
 *
 * @copyright		Copyright © 2012 U-Zyn Chua (http://uzyn.com)
 * @link 			http://opauth.org
 * @package			Opauth
 * @license			MIT License
 */

require '../../lib/Opauth/Opauth.php';

/**
 * OpauthTest class
 */
class OpauthTest extends PHPUnit_Framework_TestCase{

	protected function setUp(){
		// To surpress E_USER_NOTICE on missing $_SERVER indexes
		$_SERVER['HTTP_HOST'] = 'test.example.org';
		$_SERVER['REQUEST_URI'] = '/';
	}
	
	public function testDebugWithDebugOn(){
		$Opauth = self::instantiateOpauthForTesting(array(
			'debug' => true
		));
		$this->expectOutputString('<pre>Debug message</pre>');		
		$Opauth->debug('Debug message');
	}
	
	public function testDebugWithDebugOff(){
		$Opauth = self::instantiateOpauthForTesting(array(
			'debug' => false
		));
		$this->expectOutputString('');
		$Opauth->debug('Debug message');
	}
	
	/**
	 * Make an Opauth config with basic parameters suitable for testing,
	 * especially those that are related to HTTP
	 * 
	 * @param array $config Config changes to be merged with the default
	 * @return array Merged config
	 */
	protected static function configForTest($config = array()){
		return array_merge(array(
			'host' => 'http://test.example.org',
			'path' => '/',
			
			'security_salt' => 'testing-salt',
			
			'Strategy' => array(
				'Test' => array()
			)
		), $config);
	}
	
	/**
	 * Instantiate Opauth with test config suitable for testing
	 * 
	 * @param array $config Config changes to be merged with the default
	 * @param boolean $autoRun Should Opauth be run right after instantiation, defaulted to false
	 * @return object Opauth instance
	 */
	protected static function instantiateOpauthForTesting($config = array(), $autoRun = false){
		$Opauth = new Opauth(self::configForTest($config), $autoRun);
		
		return $Opauth;
	}
	
}