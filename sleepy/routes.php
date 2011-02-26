<?php
require_once CORE_PATH . DS . 'annotations.php';
require_once CORE_PATH . DS . 'lumberjack.php';

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
			$label = $annotation->getKey();
			$pattern = $annotation->getValue();
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
			$pattern = $this->pattern;
			foreach($this->parameters as $key => $value) {
				$index = stripos($this->pattern, $key) - 1;
				$length = strlen($key) + 1;
				$args[] = $index;
				$pattern = substr_replace($pattern, $value, $index, $length);
				echo $pattern, " <-- pattern<br />\n";
			}
			LumberJack::instance()->log('Attempting match for URI ' . $uri . ' with pattern ' . $pattern, LumberJack::DEBUG);
			$result = preg_match('@^' . $pattern . "/?$@i", $uri);
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
		$this->pattern = trim($pattern);
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

class RouteFactory {

	private function __construct() {}
	private function __clone() {}
	
	public static function getRoutesFromFile($filename) {
		$routes = array();
		include_once($filename);
		$className = substr($filename, strripos($filename, '/') + 1, -4);
		try {
			$clz = new ReflectionClass($className);
			$methods = $clz->getMethods(ReflectionMethod::IS_PUBLIC); 
			foreach($methods as $method) {
				$annotations = AnnotationReader::MethodAnnotations($method);
				if($annotations != NULL) {
					$route = new Route($annotations, $method);
					$routes[] = $route;
				}
			}
		} catch (ReflectionException $exception) {
			LumberJack::instance()->log($exception->getMessage(), LumberJack::ERROR);	
		}
		return $routes;
	}
}

/**
* Interface RouteStorage
*
* Defines the interface to be implemented by mechanisms used to store route information (memcache, flat file, ...)
*
*/
interface RouteStorage {
	public function put(Route $route);
	public function exists($path);
	public function get($path);
	public function expire();
	public function last_modified();
}

class MemcacheStorage implements RouteStorage {
	private $memcache = NULL;
	
	public function __constructor($host, $port = 11211) {
		$this->memcache = new Memcache;
		$this->memcache->connect($host, $port);
	}
	
	public function put(Route $route) {
		return $this->memcache->set($route->getPattern(), $route, 0, 1800);
	}
	
	public function exists($path) {
		if($this->get($path) != FALSE) {
			return TRUE;
		}
		return FALSE;
	}
	
	public function get($path) {
		return $memcache->get($path);
	}
	
	public function expire() {
		return $this->memcache->flush();
	}
	
	public function last_modified() {
		
	}
}

class FlatFileStorage implements RouteStorage {
	private $file_path;
	private $routes = array();
	private $registry = NULL;
	
	public function __construct() {
		$this->file_path = APP_PATH . DS . 'route.store';
		$this->load();
	}
	
	function __destruct() {
		if($this->registry->isCurrent() == FALSE) {
			LumberJack::instance()->log('Writing to route storage.', LumberJack::DEBUG);
  		$this->write();
		}
	}
	
	public function setRegistry(RouteRegistry $registry) {
		$this->registry = $registry;
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
		if(empty($group) == FALSE) {
			foreach($group as $route) {
				if($route->matches($args[0], $args[1])){
					return $route;
				}
			}
		}
		return NULL;
	}
	
	public function expire() {
		$resource = @fopen($this->file_path, 'w');
		if($resource) {
			fclose($resource);
		}
	}
	
	public function last_modified() {
		if (file_exists($filename)) {
			return filemtime($this->file_path);
		} 
		return 0;
	}

	private function write() {
		$serialized = serialize($this->routes);
		
		$file = fopen($this->file_path, 'w');
		if($file == FALSE) {
			LumberJack::instance()->log('An error has occured: route.store could not be created.', LumberJack::ERROR);
			return;
		}
		fclose($file);
		
		if (is_writable($this->file_path) == FALSE) {
			if (!@chmod($this->file_path, 0666)){ // TODO catch exception
				LumberJack::instance()->log('An error has occured: route.store does not appear writable.', LumberJack::ERROR);
				return;
			}
		}
		
		if(!@file_put_contents($this->file_path, $serialized)) {
			LumberJack::instance()->log('An error has occured: file_put_contents failed with file route.store.', LumberJack::ERROR);
		}
	}
	
	private function load() {
		$serialized = file_exists($this->file_path) ? file_get_contents($this->file_path) : FALSE;
		if($serialized != FALSE) {
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
		$this->storage->setRegistry($this);
		if($this->isCurrent() == FALSE) {
			$this->buildRegistry();
		}
	}

	public static function instance(RouteStorage $storage = NULL) {
		if(self::$instance == NULL) {
			self::$instance = new RouteRegistry($storage);
		}
		return self::$instance;
	}
	
	//addRoute
	public function put(Route $route) {
		LumberJack::instance()->log("Put: " . $route->getPattern(), LumberJack::DEBUG);
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
	
	public function isCurrent() {
		$last_modified = $this->storage->last_modified();
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			if(stripos($filename, 'index.php') != FALSE)
				continue;
		  $modified = filemtime($filename);
			if($modified > $last_modified) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	private function buildRegistry() {	
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			if(stripos($filename, 'index.php') != FALSE)
				continue;
			$routes = RouteFactory::getRoutesFromFile($filename);
			foreach($routes as $route) {
				$this->put($route);
			}
		}
		$this->buildDate = time();
	}
}

final class Dispatcher {
	private static $registry = NULL;
	private $route = NULL;
	
	public function __construct($route = NULL) {
		if($registry == NULL) {
			self::$registry = RouteRegistry::instance();
		}
		
		/*
		* TODO: Identify an elegant solution.
		*/ 
		if($route == NULL || is_string($route)) {
			if(is_string($route)) {
				$url = $route;
				$httpMethod = 'GET';
			} else {
				$url = $this->getUrl();
				$httpMethod = $this->getHttpMethod();
			}

			$route = self::$registry->get($httpMethod, $url);
		}

		if($route == NULL) {
			LumberJack::instance()->log('\'' . $this->getUrl() . '\' was not found!', LumberJack::FATAL);
			header($_SERVER["SERVER_PROTOCOL"] . '404 Not Found');
			return;
		}
		$this->setRoute($route);
	}
	
	private function getUrl() {
		return '/' . $_GET['url'];
	}
	
	private function getHttpMethod() {
		return $_SERVER['REQUEST_METHOD'];
	}
	
	private function setRoute(Route $route) {
		$this->route = $route;
	}
	
	public static function redirect($url) {
		$dispatcher = new Dispatcher($url);
		$dispatcher->dispatch();
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
				LumberJack::instance()->log($filename . ' was requested but is missing.');
			}
		}
	}
}

?>