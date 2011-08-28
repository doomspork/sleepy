<?php

class Route {
	
	protected $method = '';
	protected $pattern = '';
	protected $arguments;
	protected $class_method = '';
	protected $class_name = '';
	protected $class_path = '';
	
	public function __construct($method = '', $pattern = '') {
		$this->method = $method;
		$this->pattern = $pattern;
	}
	
	public function setDestinationInfo($name, $method, $path) {
		$this->class_name = $name;
		$this->class_method = $method;
		$this->class_path = $path;
	}
	
	public function matches($method, $uri) {
		if($this->method == $method) {
			LumberJack::instance()->log('Attempting match for URI ' . $uri . ' with pattern ' . $this->pattern, LumberJack::DEBUG);
			$result = preg_match('@^' . $this->pattern . '/?$@i', $uri, $matches);
			if($result && count($matches) > 1) {
				$matches = array_slice($matches, 1);
				$this->arguments = $matches;
			}
			return $result;
		}
		return FALSE;
	}
	
	private function invokeClassMethod() {
	  $clz = new ReflectionClass($this->class_name);
		$method = $clz->getMethod($this->class_method);
		$instance = $clz->newInstance();
		if($this->arguments) {
			$method->invokeArgs($instance, $this->arguments);
		} else {
			$method->invoke($instance);
		}
	}
	
	private function invokeFunction() {
	  $func = new ReflectionFunction($this->class_method);
	  if($this->arguments) {
			$func->invokeArgs($this->arguments);
		} else {
			$func->invoke();
		}
	}
	
	public function invoke() {
		if (@include_once($this->class_path)) {
			if(isset($this->class_name) && strlen($this->class_name) > 0) {
			  $this->invokeClassMethod();
			} else {
			  $this->invokeFunction();
			}
		} else {
			LumberJack::instance()->log($this->class_path . ' was requested but is missing.');
		}
	}
	
	public function setMethod($method) {
		$this->method = trim($method);
	}
	
	public function getMethod() {
		return $this->method;
	}
	
	public function setPattern($pattern) {
		$this->pattern = trim($pattern);
	}
	
	public function setParameters($parameters) {
		$this->parameters = $parameters;
	}
	
	public function getClassPath() {
		return $this->class_path;
	}
		
	public function getClassMethod() {
		return $this->class_method;
	}

	public function getClassName() {
		return $this->class_name;
	}
	
	public function getPattern() {
		return $this->pattern;
	}
	
	public function getParameters() {
		return $this->parameters;
	}
	
	public function getArguments() {
		return $this->arguments;
	}
}

?>