<?php

namespace Sleepy\Annotations;
use Reflector;
use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use ReflectionFunction;

class AnnotationReader {
  
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
  
  public static function getClassAnnotations($class) {
    $class = ($class instanceof ReflectionClass) ? $class : new ReflectionClass($class);
    return self::getAnnotations($class);
  }
  
  public static function getPropertyAnnotations($class, $property = "") {
    $property = ($class instanceof ReflectionProperty) ? $class : new ReflectionProperty($class, $property);
    return self::getAnnotations($class);
  }
  
  public static function getMethodAnnotations($class, $method = "") {
    $property = ($class instanceof ReflectionMethod) ? $class : new ReflectionMethod($class, $method);
    return self::getAnnotations($class);
  }
  
  public static function getFunctionAnnotations($function) {
    $function = ($class instanceof ReflectionFunction) ? $class : new ReflectionFunction($function);
    return self::getAnnotations($class);
  }
  
  private static function getAnnotations(Reflector $reflector) {
    return (!is_null($reflector)) ? 
      self::parseDocBlock($reflector->getDocComment()) :
      array();
  }
  
  private static function instantiateAnnotation($type, $values) {
    $class = '\\Annotations\\Types\\' . $type . 'Annotation';
		$instance = NULL;
		if(class_exists($class) == FALSE) {
      throw new ClassNotFoundException('Unable to find class definition file for annotation type ' . $type);
		}
    $clz = new ReflectionClass($class);
    if(!$clz->isSubclassOf('Annotations\Annotation')) {
      throw new IllFormedException('Annotation type ' . $name . ' does not implement interface Annotation.', LumberJack::ERROR);
    }
    $instance = $clz->newInstanceArgs(array($values));
		return $instance;
  }
  
  private static function parseDocBlock($block) {
    $lines = explode('\n', $block);
    $annotations = array();
    foreach($lines as $line) {
      $count = preg_match('/@(\w+)(?:\((.+)\))?/i', $line, $matches);
      if($count == 0) {
        throw new IllFormedException('Ill formed annotation found in ' . $function->class . '.' . $function->name . '.');
      }
      $type = $matches[1];
      if(isset(self::$globalIgnoreList[$type])) {
        continue;
      }
      $values = array();
      if($count > 0) {
        $tokens = preg_split('/, /', $matches[2]);
        $count = count($tokens);
        for($i = 0 ; $i < $count ; $i++) {
          $token = explode('=', $tokens[$i]);
          $key = (count($token) == 2) ? array_shift($token) : 'value';
          $values[$key] = $token[0];
        }
      }
      $annotation = NULL;
      try {
        $annotation = self::instantiateAnnotation($type, $values);
      } catch(Exception $e) { }
      
      if(!is_null($annotation)) {
        $annotations[] = $annotation;
      }
    }
    return $annotations;
  }
}


?>