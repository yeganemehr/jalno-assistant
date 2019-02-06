<?php
namespace packages\assistant;
class BadFrontendNameException extends Exception {
	public $frontend;
	public function __construct($frontend) {
		parent::__construct("{$frontend} is invalid for frontend name");
		$this->frontend = $frontend;
	}
}
