<?php

class Home {
	public function __construct() {
		
	}
	
	/**
	* [[
	* GET: /
	* ]]
	*/
	public function home() {
		echo "Welcome!";
	}
	
	/**
	* [[
	* GET: /blog/:name
	*	name: [a-z]+
	* ]]
	*/
	public function test($name) {
		echo "OMG A BLOG IS HERE!!1! Teh name are $name";
	}
}

?>