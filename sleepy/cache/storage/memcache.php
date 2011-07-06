<?php

require_once LIB_DIR . DS . 'cache' . DS . 'cacheable.php';

class Memcache implements Cacheable {
	
	public function __construct($options = array()) {
		
	}
	
	public function store($id, $value) {
		
	}
	
	public function get($id) {
		
	}
	
	public function gc() {
		
	}
	
	public function isSupported() {
		
	}
}

?>