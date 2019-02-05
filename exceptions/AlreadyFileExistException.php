<?php
namespace packages\assistant;
use packages\base\IO\file;

class AlreadyFileExistException extends Exception {
	public $theFile;
	public function __construct(file $file) {
		parent::__construct($file->getPath() . " file already exists");
		$this->theFile = $file;
	}
}
