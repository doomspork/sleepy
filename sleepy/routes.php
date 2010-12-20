<?php
require_once(CORE_PATH . DS . 'annotations.php');
require_once(CORE_PATH . DS . 'lumberjack.php');

class Route {
	protected $httpMethod = '';
	protected $method = NULL;
	protected $class = NULL;
	protected $pattern = '';
	protected $parameters = array();
	protected $arguments = array();
	
	public function __construct(AnnotationGroup $annotations = NULL, ReflectionMethod $method = NULL) {
		if($annotations !== NULL) {
			$this->parseAnnotations($annotations);
		}
		if($method !== NULL) {
			$this->method = $method->getName();
			$this->class = $method->getDeclaringClass()->getName();
		}
	}
	
	private function parseAnnotations(AnnotationGroup $annotations) {
		foreach($annotations as $annotation) {
			$label = $annotation->getLabel();
			$pattern = $annotation->getPattern();
			if($annotation instanceof RouteAnnotation) {
				$this->setHttpMethod($label);
				$this->setPattern($pattern);
			} else if ($annotation instanceof LabelAnnotation) {
				$this->parameters[$label] = $pattern;
			}
			
		}
	}
	
	public function matches($httpMethod, $uri) {
		if($this->httpMethod == $httpMethod) {
			foreach($this->parameters as $key => $value) {
				$index = stripos($this->pattern, $key) - 1;
				$length = strlen($key) + 1;
				$args[] = $index;
				$this->pattern = substr_replace($this->pattern, $value, $index, $length);
			}
			$this->pattern = str_replace('/', '\/', $this->pattern);
			$result = preg_match('/^' . $this->pattern . '\/?$/i', $uri);
			if($result && isset($args)) {
				foreach($args as $index) {
					$str = substr($uri, $index);
					$this->arguments[] = (substr($str, -1) == '/') ? substr(strrev(strstr(strrev($str), strrev('/'))), 0, - strlen('/')) : $str;
				}
			}
			return $result;
		}
		return FALSE;
	}
	
	public function setHttpMethod($httpMethod) {
		$this->httpMethod = trim($httpMethod);
	}
	
	public function setMethod(ReflectionMethod $method) {
		$this->method = $method	;
	}
	
	public function setPattern($pattern) {
		$pattern = trim($pattern);
		//$pattern = (stripos($pattern, '/') == 0) ? substr($pattern, 1) : $pattern;
		$this->pattern = $pattern; //explode('/', $pattern);
	}
	
	public function setParameters($parameters) {
		$this->parameters = $parameters;
	}
	
	public function getHttpMethod() {
		return $this->httpMethod;
	}
	
	public function getMethod() {
		return $this->method;
	}

	public function getClass() {
		return $this->class;
	}
	
	private function getPattern() {
		return $this->pattern;
	}
	
	private function getParameters() {
		return $this->parameters;
	}
	
	public function getArguments() {
		return $this->arguments;
	}
}

class RouteFactory {

	private function __construct() {}
	private function __clone() {}
	
	public static function getRoutesFromFile($filename) {
		$routes = array();
		@include_once(APP_PATH . DS . $filename);
		$className = rtrim($filename, '.php');
		$clz = new ReflectionClass($className);
		$methods = $clz->getMethods(ReflectionMethod::IS_PUBLIC); 
		foreach($methods as $method) {
			$annotations = AnnotationReader::MethodAnnotations($method);
			if($annotations != NULL) {
				$route = new Route($annotations, $method);
				$routes[] = $route;
			}
		}
		return $routes;
	}
}

class RouteRegistry {
	
	private $buildDate = NULL;
	private $routes = array();
	
	public function __construct() { 
	}

	public function addRoute(Route $route) {
		$httpMethod = trim($route->getHttpMethod());
		$this->routes[$httpMethod][] = $route;
	}
	
	public function getRoute($httpMethod, $path) {
		if(isset($this->routes[$httpMethod]) && $path) {
			foreach($this->routes[$httpMethod] as $route) {
				if($route->matches($httpMethod, $path)){
					return $route;
				}
			}
		}
		return NULL;
	}
	
	public function clearRoutes() {
		$this->routes = array();
	}
	
	public static function store($registry) {
		$serialized = serialize($registry);
		$route_store = APP_PATH . DS . 'route.store';
		
		if(is_file($route_store) == FALSE) {
			$file = fopen($route_store, 'w');
			if($file == FALSE) {
				LumberJack::instance()->log('An error has occured: route.store could not be created.');
				return FALSE; 
			}
			fclose($file);
		}
		
		if (is_writable($route_store) == FALSE) {
			if (!@chmod($route_store, 0666)){ // TODO catch exception
				LumberJack::instance()->log('An error has occured: route.store does not appear writable.');
				return FALSE; 
			}
		}
		
		if(!@file_put_contents($route_store, $serialized)) {
			LumberJack::instance()->log('An error has occured: file_put_contents failed with file route.store.');
			return FALSE;
		}
		return TRUE;
	}
	
	public static function retrieve() {
		$serialized = file_exists(APP_PATH . DS . 'route.store') ? file_get_contents(APP_PATH . DS .  'route.store') : FALSE;
		if($serialized !== FALSE) {
			$registry = unserialize($serialized);
			if($registry->isCurrent() == FALSE) {
				$registry->buildRegistry();
				RouteRegistry::store($registry);
			}
		} else {
			LumberJack::instance()->log('No route.store found, attempting to build registry', LumberJack::DEBUG);
			$registry = new RouteRegistry();
			$registry->buildRegistry();
			RouteRegistry::store($registry);
		}
		return $registry;
	}
	
	private function isCurrent() {
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			$filename = substr($filename, strripos($filename, DS) + 1);
			if($filename == 'index.php')
				continue;
		  $modified = filemtime($filename);
			if($modified > $this->buildDate) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	public function buildRegistry() {	
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			$filename = substr($filename, strripos($filename, DS) + 1);
			if($filename == 'index.php')
				continue;
			$routes = RouteFactory::getRoutesFromFile($filename);
			foreach($routes as $route) {
				$this->addRoute($route);
			}
		}
		$this->buildDate = time();
	}
}


class Dispatcher {
	private $registry = null;
	private $route = null;
	
	public function __construct() {
		$this->registry = RouteRegistry::retrieve();
		
		$url = $this->getUrl();
		$httpMethod = $this->getHttpMethod();
		$this->route = $this->registry->getRoute($httpMethod, $url);
	}
	
	private function getUrl() {
		return '/' . $_GET['url'];
	}
	
	private function getHttpMethod() {
		return $_SERVER['REQUEST_METHOD'];
	}
	
	public function dispatch() {
		$route = $this->route;
		if($route != NULL) {
			$method = $route->getMethod();
			$clz = $route->getClass();
			$args = $route->getArguments();
			$filename = $clz . '.php';
			if (@include_once(APP_PATH . DS . $filename)) {
				$clz = new ReflectionClass($clz);
				$method = $clz->getMethod($method);
				$instance = $clz->newInstance();
				$method->invokeArgs($instance, $args);
			} else {
				LumberJack::instance()->log($filename . ' was requested but could not be located.');
			}
		} else {
			LumberJack::instance()->log('\'' . $this->getUrl() . '\' was not found!', LumberJack::FATAL);
		}
	}
}


?>