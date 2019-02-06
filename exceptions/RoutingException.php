<?php
namespace packages\assistant;
use packages\base\IO\file;
class RoutingException extends Exception {
	public $routingFile;
	public function __construct(file $file, string $message = "") {
		if (!$message) {
			$message = $file->getPath() . " is cruppted or notfound";
		}
		parent::__construct($message);
		$this->routingFile = $file;
	}
}
