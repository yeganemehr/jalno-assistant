<?php
namespace packages\assistant;
use packages\base\IO\file;
class AutoloaderException extends Exception {
	public $autoloaderFile;
	public function __construct(file $file, string $message = "") {
		if (!$message) {
			$message = $file->getPath() . " is cruppted or notfound";
		}
		parent::__construct($message);
		$this->autoloaderFile = $file;
	}
}
