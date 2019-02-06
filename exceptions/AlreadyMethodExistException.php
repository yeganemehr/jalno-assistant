<?php
namespace packages\assistant;
class AlreadyMethodExistException extends Exception {
	public $method;
	public function __construct(string $method) {
		parent::__construct("{$method} method already exists in the class");
		$this->method = $method;
	}
}
