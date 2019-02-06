<?php
namespace packages\assistant;
class ControllerNotExistException extends Exception {
	public $controller;
	public function __construct(string $controller) {
		parent::__construct("there is no controller with {$controller} name.");
		$this->controller = $controller;
	}
}
