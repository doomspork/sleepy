<?php

class AnnotationParser {
	
	//private $classes = array();
	private $types = array();
	
	/**
	* @type (optional) array of annotation types to process, empty array results in all annotations processing
	*/
	public function __construct($types = array()) {
		$this->types = $types;
	}
	
	/**
	* @file file path to a single valid PHP file
	*/
	public function parse($file) {
		if(file_exists($file)) {
			$annotations = array();
			$code = file_get_contents($file);
			$classes = $this->findDefinedClasses($code);
			if(count($classes) > 0) {
				require_once $file;
				foreach($classes as $class) {
					$clz = new ReflectionClass($class);
					$methods = $clz->getMethods(ReflectionMethod::IS_PUBLIC);
					foreach($methods as $method) {
						LumberJack::instance()->log('Parsing method ' . $method . ' for annotations.', LumberJack::DEBUG);
						$metainfo = $this->processMethod($method);
					}
				}
			}
		} else {
			LumberJack::instance()->log('Unable to parse file for annotations, file ' . $file . ' not found.', LumberJack::ERROR);
		}
	}
	
	private function initializeAnnotation($name, $metainfo = array()) {
		$class = $name . 'Annotation';
		$instance = NULL;
		if(class_exists($class) == FALSE) {
			$file = dirname(__FILE__) . DS . 'type' . DS . $name . '.php';
			if(file_exists($file)) {
				require_once($file);
			} else {
				LumberJack::instance()->log('Unable to find class definition file for annotation type ' . $name, LumberJack::ERROR);
				return $instance;
			}
		}
		try {
			$clz = new ReflectionClass($class);
			if($clz->implementsInterface('Annotation')) {
				$instance = $clz->newInstanceArgs(array($metainfo));
			} else {
				LumberJack::instance()->log('Annotation type ' . $name . ' does not implement interface Annotation.', LumberJack::ERROR);
			}
		} catch (ReflectionException $ex) {
			LumberJack::instance()->log($ex->getMessage(), LumberJack::ERROR);
		}
		return $instance;
	}
	
	private function findDefinedClasses($code) {
		$tokens = token_get_all($code);
		$count = count($tokens);
		$classes = array();
		for($i = 2; $i < $count; $i++) {
			if($tokens[$i - 2][0] == T_CLASS
				&& $tokens[$i - 1][0] == T_WHITESPACE
				&& $tokens[$i][0] == T_STRING) {
					$class_name = $tokens[$i][1];
					$classes[] = $class_name;
			}
		}
		return $classes;
	}
	
	private function getClassInfo(ReflectionMethod $method) {
		$class = $method->getDeclaringClass();
		$info['name'] = $method->class;
		$info['method'] = $method->name;
		$info['path'] = $class->getFileName();
		return $info;
	}
	
	private function processMethod(ReflectionMethod $method) {
		$lines = explode("\n", $method->getDocComment());
		$metainfo = array();
		$metainfo['class'] = $this->getClassInfo($method);
		foreach($lines as $line) {
			$offset = strpos($line, '$');
			if($offset == FALSE) {
				continue;
			}
			$count = preg_match('/^(\w+)(?:\[(.+)\])?$/i', substr($line, $offset + 1), $matches);
			if($count == 0) {
				LumberJack::instance()->log('Ill formed annotation found in ' . $method->class . '.' . $method->name . '.', LumberJack::WARNING);
				continue;
			}
			$name = $matches[1];
			if($matches > 1) {
				$tokens = explode(',', $matches[2]);
				array_walk($tokens, create_function('&$i', '$i = trim($i);'));
				/**
				foreach($tokens as $token) {
					$parts = explode('>', $token);
					$arg = (count($parts) > 1) ? array($parts[0], $parts[1]) : $parts[0];
					$metainfo['args'][] = $arg;
				}
				*/
				$metainfo['args'] = $tokens;
			}
			if(empty($this->types) || in_array($name, $this->types)) {
				$annot = $this->initializeAnnotation($name, $metainfo);
				if($annot == NULL) {
					LumberJack::instance()->log('An error occured when initializing annotation type ' . $name, LumberJack::ERROR);
				}
			}
		}
	}
	
}

?>