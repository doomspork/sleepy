<?php

require_once LIB_DIR . DS . 'routes' . DS . 'registry.php';
require_once LIB_DIR . DS . 'routes' . DS . 'route.php';

final class Dispatcher {
	private static $registry = NULL;
	private $route = NULL;
	
	public function __construct($route = NULL) {
		if(self::$registry == NULL) {
			self::$registry = RouteRegistry::instance();
		}
		
		/*
		* TODO: Identify an elegant solution.
		*/ 
		if(is_string($route)) {
				$url = $route;
				$httpMethod = 'GET';
		} else {
				$url = $this->getUrl();
				$httpMethod = $this->getHttpMethod();
		}
		$route = self::$registry->get($httpMethod, $url);

		if($route == NULL) {
			LumberJack::instance()->log('\'' . $this->getUrl() . '\' was not found!', LumberJack::FATAL);
			header($_SERVER["SERVER_PROTOCOL"] . '404 Not Found');
			return;
		}
		$this->setRoute($route);
	}
	
	private function getUrl() {
		if(empty($_GET))
			return '/';
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
		if($this->route)
			$this->route->invoke();
	}
}

?>