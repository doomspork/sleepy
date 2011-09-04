<?php

namespace Sleepy\Annotations;
use Sleepy\Annotations\UndefinedPropertyException as UndefinedException;

class Annotation {
  
  private $fields = array();
  
  public final function __construct($arr) {
    foreach($arr as $key => $value) {
      $this->fields[strtolower($key)] = $value;
    }
  }
  
  public function __get($name) {
    $name = strtolower($name);
    if(isset($this->fields[$name])) {
      return $this->fields[$name];
    }
    throw new UndefinedException('Property ' . $name . ' is undefined for annotation type ' . __CLASS__);
  }
}