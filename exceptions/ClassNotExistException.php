<?php
namespace packages\assistant;
class ClassNotExistException extends Exception {
	public $class;
	public function __construct(string $class) {
		parent::__construct("there is no class with {$class} name.");
		$this->class = $class;
	}
}
