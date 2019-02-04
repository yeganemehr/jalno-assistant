<?php
namespace packages\assistant;
class AlreadyPackageExistException extends Exception {
	public $packageName;
	public function __construct(string $packageName) {
		parent::__construct("{$packageName} package directory already exists");
		$this->packageName = $packageName;
	}
}
