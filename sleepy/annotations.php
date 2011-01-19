<?php

/**
* annotations.php
*
* I've been toying with the idea of annotations for awhile, specifically 
* for the sake of replicating Sinatra's (http://sinatrarb.com) microframework in PHP
* 
* After my first draft I stumbled across this link, liked the way he was handled it 
* and decided to incorporate some of his ideas.  I however, did not want to rely on external
* packages, so I've deviated there.
* Credit: http://interfacelab.com/metadataattributes-in-php/
*
* This is a wrapper for the annotations stored in an array.
* I'm using a class because the idea is to expand, separately, 
* on the use of custom annotations w/ reflection.
*
* And because I luv objects.  It makes me sad inside that so few PHP developers use classes, they're beautiful.
*/

interface Annotation {	
	public function getKey();
	public function getValue();
}

class BaseAnnotation implements Annotation {
	protected $key = '';
	protected $value = '';
	
	function __construct($key = '', $value = '') {
		$this->key = $key;
		$this->value = $value;
	}
	
	public function getKey() {
		return $this->key;
	}
	
	public function getValue() {
		return $this->value;
	}
}

class RouteAnnotation extends BaseAnnotation {
	public static $match = '/GET|POST|PUT|DELETE/i';
	function __construct($key, $value) {
		parent::__construct($key, $value);
	}
}

class LabelAnnotation extends BaseAnnotation {
	public static $match = '/[ a-z]+/i';
	function __construct($key, $value) {
		//$key = trim(str_replace('+', '', $key));
		parent::__construct($key, $value);
	}
}

class UserAgentAnnotation extends BaseAnnotation {
	public static $match = '/USER-?AGENT/i';
	function __construct($key, $value) {
		parent::__construct($key, $value);
	}
}

class AnnotationFactory {
	private static $types = array();
		
	private function __construct() {}
	private function __clone() {}

	private static function config() {
		if(count(self::$types) == 0) {
			self::$types = array('Route' => new ReflectionClass('RouteAnnotation'), 'Label' => new ReflectionClass('LabelAnnotation'));
		}
	}

	public static function create($key, $value) {
		self::config();
		foreach(self::$types as $type) {
			$match = $type->getStaticPropertyValue('match');
			if(preg_match($match, $key)) {
				$params = array(trim($key), trim($value));
				return $type->newInstanceArgs($params);
			}
		}
		return NULL;
	}
}


class AnnotationGroup implements Iterator {
	private $position = 0;
	private $array = array();
	
  	public function __construct() {
        $this->position = 0;
    }

    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->array[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {
        return isset($this->array[$this->position]);
    }

	public function add(Annotation $annotation) {
		$this->array[] = $annotation;
	}
}

/*
*
* AnnotationReader
*
* This actually parses the doc comments to create annotations
*/

class AnnotationReader {
	
	private static function ParseDocComments($doc_comment) {
		
		if(empty($doc_comment)) {
			return NULL;
		}
		
		$comments = explode("\n", $doc_comment);
		
		$annotations = new AnnotationGroup();
		$within = false;
		
		foreach($comments as $comment) {
			$line = substr(trim($comment), 2);
			if (strpos($line,'[[')===0) {
				$within=true;
			} else if ($within) {
				if (strpos($line,']]')===0) {
					break;
				}
				else {
					AnnotationReader::ParseLine($line, $annotations);
				}
			}
		}	
		return $annotations;
	}
	
	private static function ParseLine($doc_line, &$annotations) {
		$index = stripos($doc_line, ":");
		$name = substr($doc_line, 0, $index);
		$value = substr($doc_line, $index + 1);
		$annotation = AnnotationFactory::create($name, $value);
		if($annotation !== NULL) {
			$annotations->add($annotation);
		}
		//LOG ELSE
	}
	
	public static function MethodAnnotations(ReflectionMethod $method) {
		$annotations = AnnotationReader::ParseDocComments($method->getDocComment());
		return $annotations;
	}
}

?>