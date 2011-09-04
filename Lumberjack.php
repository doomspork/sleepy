<?php
namespace Lumberjack;

/*******************************************
* LumberJack
*
* LumberJack serves as a simple logging class with extensibility
*		through the use of LoggingAdapters.  By default the BasicOutput
*		adapter is used.  Dependency injection is used to configure
*		which adapter will be utilized by LumberJack. 
*		
* Roadmap:
* + Implement functionality to support N adapters per LumberJack instance
* + Implement additional adapters and adapter functionality
* + Redesign the log method better utitlize custom Message types
* + Add support for logging of exceptions
* + Add support for logging Message objects
********************************************/

final class LumberJack {
	const DEBUG = 0;
	const WARNING = 1;
	const ERROR = 2;
	const FATAL = 3;
	
	private $adapter = NULL;
	private $reporting_level = 1;
	
	private static $instance = NULL;

	private function __construct(LoggingAdapter $adapter = NULL) {
		if($adapter == NULL) {
			$adapter = new BasicOutput();
		}
		$this->adapter = $adapter;
	}
	
	private function __clone() {}
	
	public static function instance(LoggingAdapter $adapter = NULL) {
		if(self::$instance == NULL) {
			self::$instance = new LumberJack($adapter);
		}
		return self::$instance;
	}
	
	public function logException(Exception $exception) {
		
	}
	
	public function log($message, $type = self::ERROR, $code = 0) {
		$msg = NULL;
		if($type >= $this->getReportingLevel()) {
			switch($type) {
				case self::DEBUG:
					$msg = new DebugMessage($message, $code);
					break;
				case self::WARNING:
					$msg = new WarningMessage($message, $code);
					break;
				case self::ERROR:
					$msg = new ErrorMessage($message, $code);
					break;
				case self::FATAL:
					$msg = new FatalMessage($message, $code);
					break;
			}
			if($msg != NULL) {
				$this->adapter->log($msg);
			}
		}
	}
	
	public function setReportingLevel($reporting_level = self::WARNING) {
		$this->reporting_level = $reporting_level;
	}
	
	public function getReportingLevel() {
		return $this->reporting_level;
	}
}

/*******************************************
* LoggingAdapter
*
* LoggingAdapter is an interface which can be implemented
*		to create new means of logging messages and errors from
*		the LumberJack library
*
*	Two implements are contained below: BasicOutput and FileLogging
* + BasicOutput simply prints messages to the screen, hence the name.
* + FileLogging writes all messages to a set
*
********************************************/
interface LoggingAdapter { 
	public function log(Message $message);
	public function reset();
}

class BasicOutput implements LoggingAdapter {
	public function log(Message $message) {
		$class = strtolower(get_class($message));
		$file = $message->getFile();
		$line = $message->getLine();
		$message = $message->getMessage();
		$output = "<div class='$class'>$file:$line - $message</div>";
		echo $output;
	}
	
	public function reset() { } //Nothing to reset
}

class FileLogging implements LoggingAdapter {
	private $filename;
	private $date;
	
	public function __construct($filename = NULL){
		$this->date = date('omd');
		if($filename == NULL){
			$filename = 'Errors - ' . $this->date . '.log';
		}
		$this->filename = $filename;
	}
	
	public function log(Message $message) {
		$handle = fopen($filename, 'a');
		if($handle != FALSE) {
			$class = strtolower(get_class($message));
			$file = $message->getFile();
			$line = $message->getLine();
			$error = $message->getMessage();
			$output = "$class - $file:$line - $error";
			fwrite($handle, $output);
			fclose($handle);		
		}
	}
	
	public function reset() { 
		$handle = fopen($filename, 'w');
		fclose($handle);
	}
}

/*******************************************
* Message
*
* Message is an interface used to implement the wrapper message
*		which contains the information used within LumberJack.
*		The use of an interface provides an avenue by which developers
*		can implement additional message types.
*
* Currently there is a basic set of messages predefined which should
*		cover the needs of most applications.  These types do not implement
*		differing functionality, at this time, their class types are used 
*		during the logging process.
* 
* Roadmap:
* + Provide a clean and concise formatting for trace logs
********************************************/
interface Message {
	public function getMessage();
	public function getCode();
	public function getTrace();
	public function getLine();
	public function getFile();
}

abstract class BaseMessage implements Message {
	protected $message;
	protected $code = 0;
	protected $line;
	protected $file;
	protected $trace;
	
	public function __construct($message = NULL, $code = 0) {
		$this->message = $message;
		$this->code = $code;
		$this->parseTrace();
	}
	
	final private function parseTrace() {
		$this->trace = debug_backtrace(true);
		$current = $this->trace[3];
		$this->file = $current['file'];
		$this->line = $current['line'];
	}
	
	public function getMessage() {
		return $this->message;
	}
	
	public function getCode() {
		return $this->code;
	}
	
	public function getFile() {
		return $this->file;
	}
	
	public function getLine() {
		return $this->line;
	}
	
	public function getTrace() {
		return $this->trace;
	}
}

class DebugMessage extends BaseMessage {
	public function __construct($message = NULL, $code = 0) {
		parent::__construct($message, $code);
	}
}

class WarningMessage extends BaseMessage {
	public function __construct($message = NULL, $code = 0) {
		parent::__construct($message, $code);
	}
}

class ErrorMessage extends BaseMessage {
	public function __construct($message = NULL, $code = 0) {
		parent::__construct($message, $code);
	}
}

class FatalMessage extends BaseMessage {
	public function __construct($message = NULL, $code = 0) {
		parent::__construct($message, $code);
		//die();
	}
}

?>