<?php
/**
* I'm not really excited about this file.  I'm not going to keep it around either
* but for the time being, it'll be the easiest single point for the .htaccess file
*/

define('VERSION', '0.1');

define('DS', DIRECTORY_SEPARATOR);

define('ROOT', dirname(__FILE__));

define('LIB_DIR', 'sleepy');

define('CORE_PATH', ROOT . DS . LIB_DIR);

define('APP_PATH', ROOT);

require_once(CORE_PATH . DS . 'routes.php');

$lumberjack = LumberJack::instance(new BasicOutput());
$lumberjack->setReportingLevel(LumberJack::ERROR);

$dispatcher = new Dispatcher();
$dispatcher->dispatch();
?>