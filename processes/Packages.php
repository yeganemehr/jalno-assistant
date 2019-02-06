<?php
namespace packages\assistant;
use packages\base\{IO\directory\local as Directory, IO\file\local as File, translator, json, notShellAccess, log};
use packages\assistant\Translator as AssistantTranslator;

class Packages extends Process {
	/**
	 * Check name of package based on grammer.
	 * 
	 * @param string $name
	 * @return bool
	 */
	public static function isValidPackageName(string $name): bool {
		return Autoloader::isValidClassName($name, false);
	}
	
	/**
	 * Check there is a package with this name in packages directory or not.
	 * 
	 * @param string $name
	 * @return bool
	 */
	public static function isPackage(string $name): bool {
		return (new Directory("packages/{$name}"))->exists();
	}

	/**
	 * @return packages\base\IO\directory\local
	 */
	public static function getPackageDirectory(string $name) {
		return new Directory("packages/" . $name);
	}

	/**
	 * Load package.json of given package and return its content.
	 * 
	 * @param string $name
	 * @throws packages\assistant\PackageConfigException if package.json can't be found or can't be decod.
	 * @return array
	 */
	public static function getPackageConfig(string $name): array {
		$file = new File("packages/{$name}/package.json");
		if (!$file->exists()) {
			throw new PackageConfigException($name, $file->getPath(). " notfound");
		}
		$json = json\decode($file->read());
		if (json_last_error()) {
			throw new PackageConfigException($name, $file->getPath(). " json decode error: " . json_last_error_msg() . "(" . json_last_error() . ")");
		}
		return $json;
	}

	/**
	 * Save given config into the package.json
	 * 
	 * @param string $name must be valid exist.
	 * @param array $config must be valid.
	 * @throws packages\assistant\PackageConfigException if package.json can't be found or can't be decod.
	 * @return void
	 */
	public static function savePackageConfig(string $name, array $config) {
		$file = new File("packages/{$name}/package.json");
		$file->write(json\encode($config, json\PRETTY |  JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @param array $data should be contain "name"(string)
	 * 					  other optional indexes: 
	 * 						"no-router" (bool),
	 * 						"no-autoloader"(bool)
	 * 						"no-frontend"(bool)
	 * 						"lang"(string|string[])
	 * 						"dependency"(string|string[])
	 * 						"git"(bool|string) it can be remote repo url
	 * @throws packages\assistant\BadPackageNameException if guidlines for package name didn't apply for name of new package.
	 * @throws packages\assistant\BadPackageNameException if package name in dependency parameter is invalid.
	 * @throws packages\assistant\AlreadyPackageExistException if there is a directory with same name of new package in packages directory.
	 * @throws packages\base\translator\InvalidLangCode if provided lang parameter was invalid.
	 * @throws packages\base\notShellAccess if shell_exec() is disabled and git parameter is persent.
	 * @throws packages\assistant\GitExecutableException if cannot find git executable file using `which git` command.
	 * @return void
	 */
	public function create(array $data) {
		log::setLevel('debug');
		if (!isset($data['name']) or !$data['name'] or !self::isValidPackageName($data['name'])) {
			throw new BadPackageNameException($data['name']);
		}
		$packageDir = new Directory("packages/{$data['name']}");
		if ($packageDir->exists()) {
			throw new AlreadyPackageExistException($data['name']);
		}
		if (isset($data['lang'])) {
			if (!is_array($data['lang'])) {
				$data['lang'] = array($data['lang']);
			}
			foreach($data['lang'] as $lang) {
				if (!translator::is_validCode($lang)) {
					throw new translator\InvalidLangCode($lang);
				}
			}
		}
		if (isset($data['dependency'])) {
			if (!is_array($data['dependency'])) {
				$data['dependency'] = array($data['dependency']);
			}
			foreach($data['dependency'] as $dependency) {
				if (!self::isValidPackageName($dependency)) {
					throw new BadPackageNameException($lang);
				}
			}
		}
		if (isset($data['git'])) {
			if (!function_exists('shell_exec')) {
				throw new notShellAccess();
			}
			if (strpos(shell_exec("which git"), "not found")) {
				throw new GitExecutableException();
			}
		}
			$package = array();
		if (!isset($data['no-router']) or !$data['no-router']) {
			$package['routing'] = 'routing.json';
		}
		if (!isset($data['no-autoloader']) or !$data['no-autoloader']) {
			$package['autoload'] = 'autoloader.json';
		}
		if (isset($data['dependency']) and $data['dependency']) {
			$package['dependencies'] = $data['dependency'];
		}
		if (isset($data['lang'])) {
			$package['languages'] = [];
			foreach($data['lang'] as $lang) {
				$package['languages'][$lang] = "langs/{$lang}.json";
			}
		}
		$packageDir->make();
		self::savePackageConfig($data['name'], $package);
		
		if (isset($package['routing'])) {
			$packageDir->file($package['routing'])->write("[]");
		}
		if (isset($package['autoload'])) {
			Autoloader::initFile($packageDir->file($package['autoload']));
		}
		if (isset($package['languages'])) {
			foreach($package['languages'] as $code => $langFile) {
				AssistantTranslator::initFile($packageDir->file($langFile), $code);
			}
		}
		if (isset($data['git'])) {
			shell_exec("git -C " . $packageDir->getRealPath() . " init");
			if (is_string($data['git'])) {
				shell_exec("git -C " . $packageDir->getRealPath() . " remote add origin ".$data['git']);
			}
			shell_exec("git -C " . $packageDir->getRealPath() . " add .");
			shell_exec("git -C " . $packageDir->getRealPath() . " commit -m \"Initial commit\"");
		}
	}
}