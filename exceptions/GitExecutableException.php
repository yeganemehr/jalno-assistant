<?php
namespace packages\assistant;
class GitExecutableException extends Exception {
	public function __construct() {
		parent::__construct("cannot find executable file, maybe It not installed?");
	}
}
