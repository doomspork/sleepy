<?php

require_once dirname(__FILE__) . DS . 'cacheable.php';

class Cache {
	
	//Storage mechanism instance
	private $storage;
	
	//Value validators
	private $validators;
	
	public function __construct($identifier = '', $options) {
	  if(!is_array($options)) {
	    $options = array();
	  }
		$options['identifier'] = $identifier;
		$options['location'] = (isset($options['location']) ? $options['location'] : dirname(__FILE__));
		$type = isset($options['storage']) ? $options['storage'] : 'file';
		$this->storage = $this->getStore($type, $options);
		if(is_null($this->storage)) {
			Lumberjack::instance()->log('Cache storage type ' . $type . ' unsupported.', LumberJack::FATAL);
		}
		$this->validators = array();
		$this->gc();
	}
	
	private function getInstance($type, $options = array()) {
		$clz = new ReflectionClass($type);
		$instance = $clz->newInstanceArgs(array($options));
		return $instance;
	}
	
  private function getStore($type, $options = array()) {
    $filename = dirname(__FILE__) . DS . 'storage' . DS . strtolower($type) . '.php';
    if(file_exists($filename)) {
      require_once($filename);
			$class = trim(substr($filename, strripos($filename, '/') + 1, -4));
			if($class::isSupported($options)) {
				return $this->getInstance($type, $options);
			}
    }
    return NULL;
  }
	
	//Garbage Collection & Expiration
	public function gc() {
		$this->storage->gc();
	}
	
	//add item to cache for specific id
	public function store($id, $value, $replace = true) {
		$result = FALSE;
		if($id != NULL && (!$this->exists($id) || $replace)) { // logical implication: A -> B
				$this->storage->store($id, $value);
				$result = TRUE;
		}
		$this->gc();
		return $result;
	}
	
	//Get one or more items from cache
	public function get($id) {
		$value = NULL;
		if(isset($id) && $id != NULL) {
			if(!is_array($id)) {
				$id[] = $id;
			}
			
			$results = array();
			for($i = 0 ; $i < count($id) ; $i++) {
				$results[] = $this->storage->get($id);
			}
			
			if(count($results) > 0) {
				$value = $results;
			}
		}
		$this->gc();
		return $value;
	}
	
	public function exists($id) {
		return (NULL == $this->get($id));
	}
}

?>