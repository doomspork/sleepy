<?php

require_once dirname(__FILE__) . DS . 'cacheable.php';

class Cache {
	
	//Storage mechanism instance
	private $storage;
	
	//Value validators
	private $validators;
	
	public function __construct($identifier = '', $options = array()) {
		$options['indentifer'] = $identifier;
		$options['location'] = (isset($options['location']) ? $options['location'] : dirname(__FILE__));
		$type = isset($options['storage']) ? $options['storage'] : 'file';
		
		$stores = $this->getStores($options);
		if(!in_array($type, $stores)) {
			Lumberjack::instance()->log('Cache storage type ' . $type . ' unsupported.', LumberJack::FATAL);
		}
		//TODO: Load default options then apply user supplied options.
		$this->storage = $this->getInstance($type, $options);
		$this->validators = array();
		$this->gc();
	}
	
	private function &getInstance($type = 'file', $options = array()) {
		$filename = dirname(__FILE__) . DS . 'storage' . DS . $type . '.php';
		if(!class_exists($type)) {
			require_once($filename);
		}
		
		$clz = new ReflectionClass($type);
		$instance = $clz->newInstanceArgs($options);
		return $instance;
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
		return (FALSE == $this->get($id));
	}
	
	public function getStores($options = array()) {
		$stores = array();
		foreach (glob(dirname(__FILE__) . DS . 'storage' . DS . '*.php') as $filename) {
			$class = trim(substr($filename, strripos($filename, '/') + 1, -4));
			$instance = $this->getInstance($class, $options);
			if(($instance instanceof Cacheable) && $instance->isSupported()) {
				$stores[] = $class;
			}
		}
		return $stores;
	}
	
}

?>