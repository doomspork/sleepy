<?php

interface Cacheable {
	public function store($id, $value);
	public function get($id);
	public function gc();
	public static function isSupported($options, &$errors);
}


?>