<?php
/**
* I'm not really excited about this file.  I'm not going to keep it around either
* but for the time being, it'll be the easiest single point for the .htaccess file
*/

define('VERSION', '1.0.0a');

define('DS', DIRECTORY_SEPARATOR);

define('ROOT', dirname(__FILE__));
//define('APP_DIR', '');
define('LIB_DIR', 'sleepy');

define('CORE_PATH', ROOT . DS . LIB_DIR);
define('APP_PATH', ROOT);
//define('PLUGIN_PATH', ''); sleepy v2.0 ?

require_once(CORE_PATH . DS . 'routes.php');

$dispatcher = new Dispatcher();
$dispatcher->dispatch();
?>