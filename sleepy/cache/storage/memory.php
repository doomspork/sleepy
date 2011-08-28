<?php

require_once LIB_DIR . DS . 'cache' . DS . 'cacheable.php';

class Memory implements Cacheable {
	
	private $identifier;
	private $lifetime;
	private $last_purge;
	private $options;
	
	private $storage;
	
	public function __construct($options = array()) {
		if(!isset($options['identifier'])) {
			Lumberjack::instance()->log('Caching cannot be instantiated without a identifier.', LumberJack::FATAL);
		}
		$this->identifier = $options['identifier'];
		$this->lifetime = (isset($options['lifetime'])) ? $options['lifetime'] : 60; //lifetime is in minutes, milliseconds is a pain.
		$this->policy = explode('|',(isset($options['policy']) ? $options['policy'] : 'STORE')); //GET|STORE|NONE
		array_walk($this->policy, create_function('&$i', '$i = strtoupper(trim($i));')); 
		$this->options = $options;
		$this->setup();
	}
	
	private function setup() {
		Lumberjack::instance()->log('Preparing setup for cache ' . $this->identifier, LumberJack::DEBUG);
	  $this->storage = array();
	}
	
	public function store($id, $value) {
		$this->storage($id, serialize($value));
		if(in_array('STORE', $this->policy)) {
      $this->renew();
    }
    return TRUE;
	}
	
	public function get($id) {
		if(in_array('GET', $this->policy)) {
			$this->renew();
		}
	}
	
	public function gc() {
		if($this->isExpired()) {
			$this->storage = array();
			$this->last_purge = time();
		}
	}
	
	public static function isSupported($optionss) {
		return TRUE;
	}
	
	private function isExpired() {
		$time = $this->last_purge + (60 * (60 * 1000));
		return $time < time();
	}
	
	private function renew($action) {
	  $this->gc();
    $this->last_purge = time();
	}
}

?>