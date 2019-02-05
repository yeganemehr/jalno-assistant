<?php
namespace packages\assistant;
class BadClassNameException extends Exception {
	public $class;
	public function __construct(string $class) {
		parent::__construct("{$class} is invalid for packaged class");
		$this->class = $class;
	}
}
