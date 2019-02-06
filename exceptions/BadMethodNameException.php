<?php
namespace packages\assistant;
class BadMethodNameException extends Exception {
	public $method;
	public function __construct(string $method) {
		parent::__construct("{$method} is invalid for packaged method");
		$this->method = $method;
	}
}
