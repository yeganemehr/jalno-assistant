<?php
namespace packages\assistant;
use packages\base\{IO\directory\local as Directory, translator, json, notShellAccess, log};
use packages\assistant\Translator as AssistantTranslator;

class Packages extends Process {
	/**
	 * Check name of package based on grammer.
	 * 
	 * @param string
	 * @return bool
	 */
	public static function isValidPackageName(string $name): bool {
		return preg_match("/^[a-z_][a-z0-9_]*$/i", $name);
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
		$packageDir->file("package.json")->write(json\encode($package, json\PRETTY |  JSON_UNESCAPED_SLASHES));
		if (isset($package['routing'])) {
			$packageDir->file($package['routing'])->write("[]");
		}
		if (isset($package['autoload'])) {
			$packageDir->file($package['autoload'])->write(json\encode(array(
				'files' => []
			), json\PRETTY));
		}
		if (isset($package['languages'])) {
			foreach($package['languages'] as $code => $langFile) {
				$file = $packageDir->file($langFile);
				$dir = $file->getDirectory();
				if (!$dir->exists()) {
					$dir->make();
				}
				$file->write(json\encode(array(
					'rtl' => AssistantTranslator::isRTL($code),
					'phrases' => []
				), json\PRETTY));
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