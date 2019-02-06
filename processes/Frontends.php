<?php
namespace packages\assistant;
use packages\base;
use packages\base\json;

class Frontends extends Process {
	/**
	 * Check name of frontend based on grammer.
	 * 
	 * @param string $name
	 * @return bool
	 */
	public static function isValidFrontName(string $name): bool {
		return Autoloader::isValidClassName($name, false);
	}
	

	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"name" (string)
	 * 					 other optional indexes: 
	 * 						"directory"(string) default: frontend
	 * 						"autoloader"(bool) default: true
	 * 						"lang" (string|string[]) default: fa_IR
	 * 						"userpanel"(bool) default: name == "clipone"
	 * 						"jquery" (bool) default: userpanel != true
	 * 						"bootstrap" (bool) default: userpanel != true
	 * 						"npm" (string|string[]) npm dependencies
	 * 						"ts" (bool) default: true
	 * 						"less" (bool) default: true
	 * 						"tslint"(bool) default: true
	 * @throws packages\assistant\PackageNotExistException if package does not exists.
	 * @throws packages\assistant\AlreadyFrontendExistException if frontend directory exists.
	 * @throws packages\base\translator\InvalidLangCode if provided language code was invalid.
	 * 
	 */
	public function create(array $data) {
		if (!isset($data['package'])) {
			throw new Exception("there is no package parameter");
		}
		if (!isset($data['name']) or !$data['name']) {
			throw new Exception("there is no name for frontend");
		}
		if (isset($data['directory']) and !$data['directory']) {
			throw new Exception("there is no directory for frontend");
		}
		if (!self::isValidFrontName($data['name'])) {
			throw new BadFrontendNameException($data['name']);
		}
		if (!Packages::isPackage($data['package'])) {
			throw new PackageNotExistException($data['package']);
		}
		if (!isset($data['directory'])) {
			$data['directory'] = 'frontend';
		}
		if (!isset($data['autoloader'])) {
			$data['autoloader'] = true;
		}
		if (!isset($data['ts'])) {
			$data['ts'] = true;
		}
		if (!isset($data['less'])) {
			$data['less'] = true;
		}
		if (!isset($data['tslint'])) {
			$data['tslint'] = true;
		}
		if (!isset($data['lang'])) {
			$data['lang'] = 'fa_IR';
		}
		if (!is_array($data['lang'])) {
			$data['lang'] = array($data['lang']);
		}
		foreach($data['lang'] as $lang) {
			if (!base\translator::is_validCode($lang)) {
				throw new base\translator\InvalidLangCode($lang);
			}
		}


		if (!isset($data['userpanel'])) {
			$data['userpanel'] = ($data['name'] == "clipone");
		}
		if (!isset($data['npm'])) {
			$data['npm'] = [];
		}
		if (!is_array($data['npm'])) {
			$data['npm'] = array($data['npm']);
		}
		$data['jquery'] = (((isset($data['jquery']) and $data['jquery']) or (!isset($data['jquery']) and !$data['userpanel'])) and !in_array("jquery", $data['npm']));
		if ($data['jquery']) {
			$data['npm'][] = "jquery@3.2.1";
		}
		$data['bootstrap'] = (((isset($data['bootstrap']) and $data['bootstrap']) or (!isset($data['bootstrap']) and !$data['userpanel'])) and !in_array("bootstrap", $data['npm']));
		if ($data['bootstrap']) {
			$data['npm'][] = "bootstrap@3.4.0";
		}

		$directory = Packages::getPackageDirectory($data['package'])->directory($data['directory']);
		if ($directory->exists()) {
			throw new AlreadyFrontendExistException($data['directory']);
		}
		$directory->make();
		$themeJSON = array(
			'name' => $data['name'],
			'languages' => array(),
			'assets' => array(),
			'views' => array()
		);
		if ($data['autoloader']) {
			$themeJSON['autoload'] = 'autoloader.json';
		}
		if ($data['lang']) {
			foreach($data['lang'] as $lang) {
				$themeJSON['languages'][$lang] = "langs/{$lang}.json";
			}
		}
		if ($data['npm']) {
			foreach ($data['npm'] as $npm) {
				$themeJSON['assets'][] = array(
					'type' => "package",
					'name' => $npm,
				);
			}
		}
		if ($data['less']) {
			$themeJSON['assets'][] = array(
				'type' => "less",
				'file' => "assets/less/main.less",
			);
		}
		if ($data['ts']) {
			$themeJSON['assets'][] = array(
				'type' => "ts",
				'file' => "assets/ts/Main.ts",
			);
		}
		$directory->file("theme.json")->write(json\encode($themeJSON, json\PRETTY | JSON_UNESCAPED_SLASHES));
		$directory->directory("html")->make();
		$directory->directory("views")->make();

		if ($data['autoloader']) {
			Autoloader::initFile($directory->file($themeJSON['autoload']));
		}

		foreach($themeJSON['languages'] as $code => $file) {
			Translator::initFile($directory->file($file), $code);
		}
		$filesToCreate = [];
		if ($data['less']) {
			$filesToCreate[] = "assets/less/main.less";
		}
		if ($data['ts']) {
			$filesToCreate[] = "assets/ts/Main.ts";
		}
		foreach($filesToCreate as $file) {
			$file = $directory->file($file);
			$dir = $file->getDirectory();
			if (!$dir->exists()) {
				$dir->make(true);
			}
			$file->write("");
		}

		$packageConfig = Packages::getPackageConfig($data['package']);
		if (isset($packageConfig['frontend']) and $packageConfig['frontend']) {
			if (!is_array($packageConfig['frontend'])) {
				$packageConfig['frontend'] = array($packageConfig['frontend']);
			}
			if (!in_array($data['directory'], $packageConfig['frontend'])) {
				$packageConfig['frontend'][] = $data['directory'];
			}
		} else {
			$packageConfig['frontend'] = $data['directory'];
		}
		Packages::savePackageConfig($data['package'], $packageConfig);

		if ($data['npm'] or $data['userpanel']) {
			$npmPackageJson = array(
				'name' => $data['name'],
				'private' => true,
				'dependencies' => [],
				'devDependencies' => []
			);
			foreach($data['npm'] as $combined) {
				list($packageName, $version) = explode("@", $combined, 2);
				if (!$version) {
					$version = "*";
				}
				$npmPackageJson['dependencies'][$packageName] = $version;
			}
			if ($data['ts']) {
				$npmPackageJson['devDependencies']['typescript'] = '^3.3.1';
			}
			if ($data['tslint']) {
				$npmPackageJson['devDependencies']['tslint'] = '^5.12.1';
			}
			if ($data['jquery'] or $data['userpanel']) {
				$npmPackageJson['devDependencies']['@types/jquery'] = '^3.3.29';
			}
			if ($data['bootstrap'] or $data['userpanel']) {
				$npmPackageJson['devDependencies']['@types/bootstrap'] = '^3.3.33';
			}

			$directory->file("package.json")->write(json\encode($npmPackageJson, json\PRETTY | JSON_UNESCAPED_SLASHES |  JSON_FORCE_OBJECT));
		}
		if ($data['ts']) {
			$directory->file("tsconfig.json")->write(json\encode(array(
				"compilerOptions" => array(
					"module" => "commonjs",
					"target" => "es5",
					"sourceMap" => false,
					"removeComments" => true
				),
				"files" => ["./assets/ts/Main.ts"]
			), json\PRETTY | JSON_UNESCAPED_SLASHES));

			$mainTS = "";
			if ($data['jquery'] or $data['userpanel']) {
				$mainTS .= "import * as \$ from \"jquery\";" . PHP_EOL;
			}
			if ($data['bootstrap']) {
				$mainTS .= "import \"bootstrap\";" . PHP_EOL;
			}
			if ($mainTS) {
				$mainTS .= PHP_EOL;
			}
			$mainTS .= "export default class Main {" . PHP_EOL;
			$mainTS .= "\tpublic static init() {" . PHP_EOL;
			$mainTS .= "\t\t// Write your code here..." . PHP_EOL;
			$mainTS .= "\t}" . PHP_EOL;
			$mainTS .= "}" . PHP_EOL;
			
			if ($data['jquery'] or $data['userpanel']) {
				$mainTS  .= "$(() => {" . PHP_EOL;
				$mainTS  .= "\tMain.init();" . PHP_EOL;
				$mainTS  .= "});" . PHP_EOL;
			}
			$directory->file("assets/ts/Main.ts")->write($mainTS);
		}
		if ($data['tslint']) {
			$directory->file("tslint.json")->write(json\encode(array(
				"defaultSeverity" => "error",
				"extends" => ["tslint:recommended"],
				"jsRules" => [],
				"rules" => array(
					"indent" => [true, "tabs", 4],
					"variable-name" => false,
					"no-console" => false,
					"max-line-length" => false,
					"no-empty" => false,
					"object-literal-sort-keys" => false,
					"no-empty-interface" => false,
					"object-literal-shorthand" => false
				),
				"rulesDirectory" => []
			), json\PRETTY));
		}
		if ($data['npm'] or $data['ts']) {
			if (function_exists('shell_exec')) {
				if (strpos(shell_exec("which yarn"), "not found") !== null) {
					shell_exec("cd " . $directory->getPath()."; yarn install");
				} elseif (strpos(shell_exec("which npm"), "not found") !== null) {
					shell_exec("cd " . $directory->getPath()."; npm install");
				}
			}
		}
	}
}