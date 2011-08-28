<?php

require_once LIB_DIR . DS . 'cache' . DS . 'cacheable.php';

class File implements Cacheable {
	
	private $identifier;
	private $lifetime;
	private $path;
	private $options;
	
	public function __construct($options = array()) {
		if(!isset($options['identifier'])) {
			Lumberjack::instance()->log('Caching cannot be instantiated without a identifier.', LumberJack::FATAL);
		}
		if(!isset($options['location'])) {
			Lumberjack::instance()->log('No location set in configuration for file caching.', LumberJack::FATAL);
		}
		$this->identifier = $options['identifier'];
		$this->path = $options['location'];
		$this->lifetime = (isset($options['lifetime'])) ? $options['lifetime'] : 60; //lifetime is in minutes, milliseconds is a pain.
		$this->policy = explode('|',(isset($options['policy']) ? $options['policy'] : 'STORE')); //GET|STORE|NONE
		array_walk($this->policy, create_function('&$i', '$i = strtoupper(trim($i));')); 
		$this->options = $options;
		$this->setup();
	}
	
	private function setup() {
		Lumberjack::instance()->log('Preparing setup for cache ' . $this->identifier, LumberJack::DEBUG);
		$dir = $this->path . DS . $this->identifier . '$cache';
		if(!is_dir($dir)) {
			$chm = chmod($this->path, 0777);
			if(!$chm && !mkdir($dir, 0777)) {
				Lumberjack::instance()->log('Unable to create folder for cache storage at ' . $dir, LumberJack::FATAL);	
			}
		}
		
		$this->path = $dir . DS . md5($this->identifier);
		if(!(touch($this->path . '.cache') && touch($this->path . '.expiration'))) {
			Lumberjack::instance()->log('An error occurred when creating cache files in directory ' . $dir, LumberJack::FATAL);	
		}
	}
	
	public function store($id, $value) {
		$value = $id . '=' . serialize($value);
		$written = FALSE;
		$locking = (bool) isset($this->options['locking']) ? $this->options['locking'] : TRUE;

		$resource = @fopen($this->path, 'a+');
		if ($resource) {
			if ($locking) {
				@flock($resource, LOCK_EX);
			}
			$length = strlen($value);
			@fwrite($resource, $value, $length);
			if ($locking) {
				@flock($resource, LOCK_UN);
			}
			@fclose($resource);
			$written = true;
		}
	
		if ($written && ($data == file_get_contents($path))) {
			if(in_array('STORE', $this->policy)) {
				$this->renew();
			}
			return TRUE;
		}
		return FALSE;
	}
	
	public function get($id) {
		if(in_array('GET', $this->policy)) {
			$this->renew();
		}
	}
	
	public function gc() {
		if($this->isExpired()) {
			foreach(array($this->getCachePath(), $this->getExpirationPath()) as $path) {
				@chmod($path, 0777);
				if (!@unlink($path)) {
					Lumberjack::instance()->log('Unable to perform garbage collection on file ' . $file, LumberJack::ERROR);
				}
			}
		}
	}
	
	public static function isSupported($options) {
		return is_writable($options['location']);
	}
	
	private function isExpired() {
		$file = $this->getExpirationPath();
		if(file_exists($file)) {
			$time = @file_get_contents($file);
			if(!empty($time) && ($time > time())) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	private function renew() {
		$file = $this->getExpirationPath();
		if(file_exists()) {
			@file_put_contents($file, time() + ($this->lifetime * (60 * 60)));
		}
	}
	
	private function getCachePath() {
		return $this->path . '.cache';
	}
	
	private function getExpirationPath() {
		return $this->path . '.expiration';
	}
}

?>