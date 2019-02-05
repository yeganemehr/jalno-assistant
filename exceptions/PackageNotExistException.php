<?php
namespace packages\assistant;
class PackageNotExistException extends Exception {
	public $packageName;
	public function __construct(string $packageName) {
		parent::__construct("there is no package with {$packageName} name in packages directory.");
		$this->packageName = $packageName;
	}
}
