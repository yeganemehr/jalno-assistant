<?php
namespace packages\assistant;
class AlreadyClassExistException extends Exception {
	public $class;
	public function __construct(string $class) {
		parent::__construct("{$class} class already exists in autoloader");
		$this->class = $class;
	}
}
