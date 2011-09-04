<?php

class AnnotationParser {
	
	private $types = array();
	
	private static $globalIgnoreList = array(
    'access'=> true, 'author'=> true, 'copyright'=> true, 'deprecated'=> true,
    'example'=> true, 'ignore'=> true, 'internal'=> true, 'link'=> true, 'see'=> true,
    'since'=> true, 'tutorial'=> true, 'version'=> true, 'package'=> true,
    'subpackage'=> true, 'name'=> true, 'global'=> true, 'param'=> true,
    'return'=> true, 'staticvar'=> true, 'category'=> true, 'staticVar'=> true,
    'static'=> true, 'var'=> true, 'throws'=> true, 'inheritdoc'=> true,
    'inheritDoc'=> true, 'license'=> true, 'todo'=> true, 'deprecated'=> true,
    'deprec'=> true, 'author'=> true, 'property' => true, 'method' => true,
    'abstract'=> true, 'exception'=> true, 'magic' => true, 'api' => true,
    'final'=> true, 'filesource'=> true, 'throw' => true, 'uses' => true,
    'usedby'=> true, 'private' => true, 'Annotation' => true, 'override' => true,
    'Required' => true,
    );
	
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
			$functions = $this->findDefinedFunctions($code);
			$classes = $this->findDefinedClasses($code);
			if(count($classes) > 0) {
				require_once $file;
				$this->handleClassParsing($classes);
			} elseif(count($functions) > 0) {
			  require_once $file;
			  $this->handleFunctionParsing($functions);
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
	
	private function findDefinedFunctions($code) {
	  preg_match_all("/(?!function )(\w*)\([$\w, ]*\)/", $code, $matches);
	  $functions = array();
	  foreach($matches[1] as $match) {
	    $functions[] = $match;
	  }
	  return $functions;
	}
	
	private function findDefinedClasses($code) {
		$tokens = array_filter(token_get_all($code), function($value) { return isset($value[1]) && strlen(trim($value[1])) > 1; });
		$count = count($tokens);
		$classes = array();
    $last = '';
    foreach($tokens as $token) {
      $current = $token[1];
      if($last == 'class' && is_string($current)) {
        $classes[] = $current;
      }
      $last = $current;
    }
		return $classes;
	}
	
	private function handleFunctionParsing($functions) {
	  foreach($functions as $function) {
      LumberJack::instance()->log('Parsing function ' . $function . ' for annotations.', LumberJack::DEBUG);
	    $fun = new ReflectionFunction($function);
	    $metainfo = array();
	    $metainfo['class']['name'] = '';
	    $metainfo['class']['method'] = $function;
	    $metainfo['class']['path'] = $fun->getFileName();
	    $this->processMethod($fun, $metainfo);
	  }
	}
	
	private function handleClassParsing($classes) {
	  foreach($classes as $class) {
	    LumberJack::instance()->log('Parsing class ' . $class . ' for annotations.', LumberJack::DEBUG);
			$clz = new ReflectionClass($class);
			$methods = $clz->getMethods(ReflectionMethod::IS_PUBLIC);
			foreach($methods as $method) {
				LumberJack::instance()->log('Parsing method ' . $method . ' for annotations.', LumberJack::DEBUG);
				$metainfo = array();
    		$metainfo['class'] = $this->getClassInfo($method);
        $this->processMethod($method, $metainfo);
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
	
	private function processMethod(ReflectionFunctionAbstract $function, $metainfo) {
		$lines = explode('\n', $function->getDocComment());
		foreach($lines as $line) {
			$count = preg_match('/@(\w+)(?:\((.+)\))?/i', $line, $matches);
			$name = $matches[1];
			if(isset(self::$globalIgnoreList[$name])) {
			  continue;
			} else if($count == 0) {
				LumberJack::instance()->log('Ill formed annotation found in ' . $function->class . '.' . $function->name . '.', LumberJack::WARNING);
				continue;
			}
			if(count($matches) > 1) {
			  $tokens = preg_split('/, /', $matches[2]);
			  $count = count($tokens);
        for($i = 0 ; $i < $count ; $i++) {
          $key = $i;
          $token = explode('=', $tokens[$key]);
          if(count($token) == 2) {
            $key = array_shift($token);
          }
          $tokens[$key] = $token[0];
        }
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