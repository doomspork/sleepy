<?php

class AnnotationParser {
	
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
				$this->handleClassParsing($classes);
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
		$tokens = array_filter(token_get_all($code), function($value) { return isset($value[1]) && strlen(trim($value[1])) > 1; });
		$count = count($tokens);
		$classes = array();
    $last = "";
    foreach($tokens as $token) {
      $current = $token[1];
      if($last == "class" && is_string($current)) {
        $classes[] = $current;
      }
      $last = $current;
    }
		return $classes;
	}
	
	private function handleClassParsing($classes) {
	  foreach($classes as $class) {
			$clz = new ReflectionClass($class);
			$methods = $clz->getMethods(ReflectionMethod::IS_PUBLIC);
			foreach($methods as $method) {
				LumberJack::instance()->log('Parsing method ' . $method . ' for annotations.', LumberJack::DEBUG);
				$metainfo = $this->processMethod($method);
			}
		}
	}
	
	private function getClassInfo(ReflectionMethod $method) {
		$class = $method->getDeclaringClass();
		$info['name'] = $method->class;
		$info['method'] = $method->name;
		$info['path'] = $class->getFileName();
		return $info;
	}
	
	private function processMethod(ReflectionFunctionAbstract $function) {
		$lines = explode("\n", $function->getDocComment());
		$metainfo = array();
		$metainfo['class'] = $this->getClassInfo($function);
		foreach($lines as $line) {
			$offset = strpos($line, '$');
			if($offset == FALSE) {
				continue;
			}
			$count = preg_match('/^(\w+)(?:\[(.+)\])?$/i', substr($line, $offset + 1), $matches);
			if($count == 0) {
				LumberJack::instance()->log('Ill formed annotation found in ' . $function->class . '.' . $function->name . '.', LumberJack::WARNING);
				continue;
			}
			$name = $matches[1];
			if($matches > 1) {
			  $tokens = preg_split('/, /', $matches[2]);
				array_walk($tokens, create_function('&$i', '$i = trim($i);'));
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