<?php
namespace packages\assistant;
class BadPackageNameException extends Exception {
	public $packageName;
	public function __construct($packageName) {
		parent::__construct("{$packageName} is invalid for package name");
		$this->packageName = $packageName;
	}
}
