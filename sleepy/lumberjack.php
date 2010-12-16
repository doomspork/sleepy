<?php

/*******************************************
*
*
*
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
				default:
					$msg = new BaseMessage($message, $code);
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
*
*
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
		$error = $message->getMessage();
		$output = "<div class='$class'>$file:$line - $error</div>";
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
	} //clear file
}

/*******************************************
*
*
*
********************************************/
interface Message {
	public function getMessage();
	public function getCode();
	public function getTrace();
	public function getLine();
	public function getFile();
}

class BaseMessage implements Message {
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
		$size = count($this->trace) - 1;
		$current = $this->trace[$size - 1];
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
		//How do I wish to format the trace?
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
	}
}

?>