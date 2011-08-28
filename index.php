<?php
/**
* I'm not really excited about this file.  I'm not going to keep it around either
* but for the time being, it'll be the easiest single point for the .htaccess file
*/
error_reporting(E_ALL);//E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

define('DS', DIRECTORY_SEPARATOR);

define('ROOT', dirname(__FILE__));

define('LIB_DIR', 'sleepy');

define('CORE_PATH', ROOT . DS . LIB_DIR);

define('APP_PATH', ROOT);

define('SETTINGS_PATH', ROOT . DS . 'sleepy.settings');

require_once CORE_PATH . DS . 'dispatcher.php';
require_once CORE_PATH . DS . 'lumberjack.php';

$lumberjack = LumberJack::instance(new BasicOutput());
$lumberjack->setReportingLevel(LumberJack::WARNING);

$dispatcher = new Dispatcher();
$dispatcher->dispatch();
?>