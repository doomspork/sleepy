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
				$args[$index] = $length;
				$this->pattern = substr_replace($this->pattern, $value, $index, $length);
			}
			$this->pattern = str_replace('/', '\/', $this->pattern);
			$result = preg_match('/^' . $this->pattern . '$/i', $uri);
			if($result && isset($args)) {
				foreach($args as $index => $length) {
					$this->arguments[] = substr($uri, $index, $length);
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


interface RouteStorage {
	public function put(Route $route);
	public function exists($path);
	public function get($path);
	public function expire();
	public function last_modified();
}

class FlatFileStorage implements RouteStorage {
	const FILE_PATH = APP_PATH . DS . 'route.store';
	private $routes = array();
	
	public function __construct() {
		$this->load();
	}
	
	function __destruct() {
  	$this->write();
	}
	
	public function put(Route $route) {
		$httpMethod = trim($route->getHttpMethod());
		$this->routes[$httpMethod][] = $route;
	}
	
	/*
	* Thoughts on passing paths as HTTPMETHOD@URL?
	* example: GET@/blog/12
	*/
	public function exists($path) {
		$args = explode('@', $path);
		return array_key_exists($args[0], $this->routes) && in_array($args[1], $this->routes[$args[0]]);
	}
	
	public function get($path) {
		$args = explode('@', $path);
		$group = $this->routes[$args[0]];
		foreach($group as $route) {
			if($route->matches($args[0], $args[1])){
				return $route;
			}
		}
		return NULL;
	}
	
	public function expire() {
		$resource = @fopen(self::FILE_PATH, 'w');
		if($resource) {
			fclose($resource);
		}
	}
	
	public function last_modified() {
		return filemtime(self::FILE_PATH);
	}

	private function write() {
		$serialized = serialize($this->routes);
		
		if(is_file(self::FILE_PATH) == FALSE) {
			$file = fopen(self::FILE_PATH, 'w');
			if($file == FALSE) {
				LumberJack::instance()->log('An error has occured: route.store could not be created.');
			}
			fclose($file);
		}
		
		if (is_writable(self::FILE_PATH) == FALSE) {
			if (!@chmod(self::FILE_PATH, 0666)){ // TODO catch exception
				LumberJack::instance()->log('An error has occured: route.store does not appear writable.');
			}
		}
		
		if(!@file_put_contents(self::FILE_PATH, $serialized)) {
			LumberJack::instance()->log('An error has occured: file_put_contents failed with file route.store.');
		}
	}
	
	private function load() {
		$serialized = file_exists(self::FILE_PATH) ? file_get_contents(self::FILE_PATH) : FALSE;
		if($serialized !== FALSE) {
			$this->routes = unserialize($serialized);
		} 
	}
}

class RouteRegistry {
	private $storage = NULL;
	private $buildDate = NULL;
	
	private static $instance = NULL;
	
	private function __construct(RouteStorage $storage) {
		$this->storage = $storage; 
	}

	public static function instance(RouteStorage $storage = NULL) {
		if(self::$instance == NULL) {
			self::$instance = new RouteRegistry($storage);
		}
		return self::$instance;
	}
	
	//addRoute
	public function put(Route $route) {
		$this->storage->put($route);
	}
	
	//getRoute
	public function get($httpMethod, $path) {
		if(strpos($httpMethod, '@') === FALSE) { 
			$httpMethod .= '@' . $path;
		}
		return $this->storage->get($httpMethod);
	}
	
	//clearRoutes
	public function expire() {
		$this->storage->expire();
	}
	
	private function isCurrent() {
		$last_modified = $this->storage->last_modified();
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			$filename = substr($filename, strripos($filename, DS) + 1);
			if($filename == 'index.php')
				continue;
		  $modified = filemtime($filename);
			if($modified > $last_modified) {
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
				$this->put($route);
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
		$this->route = $this->registry->get($httpMethod, $url);
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