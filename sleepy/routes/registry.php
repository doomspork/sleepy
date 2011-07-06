<?php

require_once LIB_DIR . DS . 'config.php';
require_once LIB_DIR . DS . 'cache' . DS . 'cache.php';
require_once LIB_DIR . DS . 'routes' . DS . 'route.php';
require_once LIB_DIR . DS . 'annotations' . DS . 'parser.php';

class RouteRegistry {
	private static $instance = NULL;
	
	private $cache = NULL;
	
	private $routes;
	
	public static function instance() {
		if(self::$instance == NULL) {
			self::$instance = new RouteRegistry();
			self::$instance->buildRegistry();
		}
		return self::$instance;
	}
	
	private function __construct() {
		foreach(array('GET', 'POST', 'DELETE', 'PUT') as $key) {
			$this->routes[strtolower($key)] = array();
		}
		/**
		$options = $this->getRouteOptions() || array();
 		if($options == NULL) {
			$options = array();
		}
		LumberJack::instance()->log('Instantiating cache instance', LumberJack::DEBUG);
		$this->cache = new Cache('routes', $options);	
		*/
	}
	
	private function getRouteOptions() {
		$settings = Config::instance();
		return $settings->getOptions('routes');
	}
	
	public function register(Route $route) {
		LumberJack::instance()->log('Registrying route: ' . $route->getPattern(), LumberJack::DEBUG);
		$this->routes[strtolower($route->getMethod())][$route->getPattern()] = $route;
	}
	
	public function get($method, $path) {
		foreach($this->routes[strtolower($method)] as $route) {
			if($route->matches($method, $path))
				return $route;
		}
		return NULL;
	}
	
	public function expire() {
		$this->storage->gc(TRUE);
	}
	
	private function buildRegistry() {
		$parser = new AnnotationParser(array('route'));
		foreach (glob(APP_PATH . DS . '*.php') as $filename) {
			if(stripos($filename, 'index.php') != FALSE)
				continue;
			LumberJack::instance()->log('Parsing file ' . $filename, LumberJack::DEBUG);
			$parser->parse($filename);
		}
		$this->buildDate = time();
	}
}

?>