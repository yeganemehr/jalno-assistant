<?php
namespace packages\assistant;
use packages\base\{IO, log, json};

class Router extends Process {

	/**
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @return packages\base\IO\file\local|null routing file of package.
	 */
	public static function getRoutingFileOfPackage(string $package) {
		$config = Packages::getPackageConfig($package);
		if (!isset($config['routing'])) {
			return null;
		}
		return Packages::getPackageDirectory($package)->file($config['routing']);
	}

	/**
	 * @throws packages\base\IO\NotFoundException if the file does not exists.
	 * @throws packages\assistant\RoutingException if cannot decode json.
	 * @return array decoded content of routing.json file.
	 */
	public static function parseRoutingFile(IO\file $file) {
		if (!$file->exists()) {
			throw new IO\NotFoundException($file);
		}
		$json = json\decode($file->read());
		if (json_last_error()) {
			throw new RoutingException($file, $file->getPath(). " json decode error: " . json_last_error_msg() . "(" . json_last_error() . ")");
		}
		return $json;
	}

	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"address"(string)
	 * 						"controller"(string)
	 * 					  other optional indexes:
	 * 						"method"(string|string[])
	 * 						"absolute"(bool)
	 * 						"api"(bool|string)
	 * 						"ajax"(bool|string)
	 * 
	 * @throws Exception if there is no package index in parameters
	 * @throws Exception if there is no name index in parameters
	 * @throws Exception if there is not exactly one @ in controller parameter.
	 * @throws packages\assistant\PackageNotExistException if package is not exists.
	 * @throws packages\assistant\RoutingException if address was empty.
	 * @throws packages\assistant\RoutingException if there was duplicate variable in the address.
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @throws packages\assistant\AutoloaderException {@see Controllers::exists()}
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\base\IO\NotFoundException {@see Controllers::exists()}
	 * @throws packages\PhpParser\Error {@see Autoloader::getClassFromFile()}
	 * @throws packages\assistant\ClassNotExistException {@see Controllers::exists()}
	 * @throws packages\assistant\ControllerNotExistException if there wasn't the controller in the package.
	 * @return void
	 */
	public function add(array $data) {
		log::setLevel('debug');
		if (!isset($data['package'])) {
			throw new \Exception("there is no package name");
		}
		if (!isset($data['address']) or !$data['address']) {
			throw new \Exception("there is no address for routing rule");
		}
		if (!isset($data['controller']) or !$data['controller']) {
			throw new \Exception("there is no routing for routing rule");
		}
		if (!Packages::isPackage($data['package'])) {
			throw new PackageNotExistException($data['package']);
		}
		$routingFile = self::getRoutingFileOfPackage($data['package']);
		if (!$routingFile) {
			throw new RoutingException("the package does have routing file");
		}
		$rules = self::parseRoutingFile($routingFile);

		$adressParts = explode("/", $data['address']);
		while($adressParts and $adressParts[0] == "") {
			array_shift($adressParts);
		}
		if (empty($adressParts)) {
			throw new RoutingException("address is empty");
		}
		$variables = [];
		$path = [];
		foreach ($adressParts as $part) {
			if (preg_match("/^:([a-zA-Z0-9_\\-]+)(:int|\.\.\.)?$/", $part, $matches)) {
				if (in_array($matches[1], $variables)) {
					throw new RoutingException("duplicate variable in the address: {$matches[1]}");
				}
				$partPath = array(
					'type' => 'dynamic',
					'name' => $matches[1]
				);
				$variables[] = $matches[1];
				if ($matches[2] === "...") {
					$partPath['type'] = 'wildcard';
				} elseif ($matches[2] == "[int]") {
					$partPath['regex'] = "/^\\d+$/";
				}
			} else {
				$partPath = $part;
			}
			$path[] = $partPath;
		}
		foreach (['controller', 'ajax', 'api'] as $key) {
			if (isset($data[$key]) and is_string($data[$key])) {
				$data[$key] = str_replace("/", "\\", $data[$key]);
				if (count(explode("@", $data[$key])) != 2) {
					throw new \Exception("controller should exactly has one @");
				}
				if (!Controllers::exists($data['package'], $data[$key])) {
					throw new ControllerNotExistException($data[$key]);
				}
			}
		}
		$rule = array(
			'path' => $variables ? $path : implode("/", $path),
			'controller' => $data['controller'],
		);
		if (isset($data['method'])) {
			$rule['method'] = $data['method'];
		}
		if (isset($data['absolute'])) {
			$rule['absolute'] = $data['absolute'];
		}
		if (isset($data['api'])) {
			$rule['permissions']['api'] = $data['api'];
		}
		if (isset($data['ajax'])) {
			$rule['permissions']['ajax'] = $data['ajax'];
		}
		$rules[] = $rule;
		$routingFile->write(json\encode($rules, json\PRETTY | JSON_UNESCAPED_SLASHES));

	}
}