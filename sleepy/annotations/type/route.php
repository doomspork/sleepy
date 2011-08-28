<?php
require_once LIB_DIR . DS . 'routes' . DS . 'registry.php';
require_once LIB_DIR . DS . 'annotations' . DS . 'annotation.php';

class RouteAnnotation implements Annotation {
	
	/**
	* Acceptable Route Annotations
	* $route[GET, /url/here]
	* $route[POST, /url/:token, :token > pattern]
	*/ 
	public function __construct($metainfo =  array()) {
		$args = $metainfo['args'];
		if(isset($args[0]) && count($args) >= 2) {
			$tokens = (count($args) > 2) ? array_slice($metainfo['args'], 2) : array();
			$url = self::process($args[1], $tokens);
			self::register($args[0], $url, $metainfo['class']);
		} else {
			LumberJack::instance()->log('Route options missing from ' . $metainfo['class']['method'] . ' in file '. $metainfo['class']['path'], LumberJack::ERROR);
		}
	}
	
	private static function process($pattern, $tokens = array()) {
		$result = $pattern;
		foreach($tokens as $token) {
			$token = explode('>', $token);
			$index = strpos($pattern, trim($token[0]));
			if($index == FALSE) {
				LumberJack::instance()->log('Token ' . trim($token[0]) . ' not found when processing pattern ' . $pattern, LumberJack::WARNING);
			} else {
				$length = strlen($token[0]);
				$result = substr_replace($result, '(' . trim($token[1]) . ')', $index, $length);
			}
		}
		return $result;
	}
	
	private static function register($method, $pattern, $class_info) {
		$registry = RouteRegistry::instance();
		$route = new Route($method, $pattern);
		$route->setDestinationInfo($class_info['name'], $class_info['method'], $class_info['path']);
		$registry->register($route);
	}
}

?>