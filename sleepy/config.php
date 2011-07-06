<?php

class Config {
	
	private static $instance = NULL;
	
	private $options;
	
	private function __construct() {
		$this->options = array();
	}
	
	public static function instance() {
		if(self::$instance == NULL) {
			self::$instance = new Config();
			self::$instance->load(SETTINGS_PATH, TRUE);
		}
		return self::$instance;
	}
	
	/*
	* $conflict options: replace, skip, append
	*/
	public function load($file = '', $conflict = 'replace') {
		$conflict = strtolower($conflict);
		$handle = fopen($file, 'r');
		if($handle == FALSE) {
			Lumberjack::instance()->log('Unable to load settings from file ' . $file, LumberJack::FATAL);	
		}
		
		while ($option = fscanf($handle, "%s=%s\n")) { //"%[a-zA-Z].%[a-zA-Z]=%s\n"
		    list ($option, $value) = $option;
				$option = explode('.', $option);
				$group = $option[0];
				$option = substr($option[1], 0, -1);
				if(isset($this->options[$group][$option])) {
					switch($conflict) {
						case 'replace':
							$this->options[$group][$option] = $value;		
						break;
						case 'append':
							$cur = $this->options[$group][$option];
							$cur = is_array($cur) ? $cur : array($cur);
							$value = array_push($cur, $value);
							$this->options[$group][$option] = $value;
						break;
					}
				} else {
					$this->options[$group][$option] = $value;		
				}
		}
		return fclose($handle);
	}
	
	/**
	* $settings = Config::instance();
	* $settings->getOptions('cache');
	* $settings->getOptions('cache.location');
	* $settings->getOptions('cache', 'location');
	*/
	public function getOptions($group, $option = NULL) {
		$group = is_null($option) ? explode('.', $group) : array($group, $option); 
		$settings = NULL;
		if(array_key_exists($group[0], $this->options)) {
			$settings = $this->options[$group[0]];
			if(isset($group[1]) && array_key_exists($group[1], $settings)) {
				$settings = $settings[$group[1]];
			}
		}
		return $settings;
	}
}

?>