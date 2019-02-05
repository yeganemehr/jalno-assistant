<?php
namespace packages\assistant;
class PackageConfigException extends Exception {
	public $packageName;
	public function __construct(string $packageName, string $message = "") {
		if (!$message) {
			$message = "package.json for {$packageName} is cruppted or notfound";
		}
		parent::__construct($message);
		$this->packageName = $packageName;
	}
}
