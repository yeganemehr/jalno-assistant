<?php
namespace packages\assistant;
class AlreadyFrontendExistException extends Exception {
	public $frotnend;
	public function __construct(string $frotnend) {
		parent::__construct("{$frotnend} frontend directory already exists");
		$this->frotnend = $frotnend;
	}
}
