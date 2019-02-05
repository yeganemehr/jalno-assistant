<?php
namespace packages\assistant;
use packages\base\IO\{directory, file};

class Models extends Process {
	/**
	 * Generate a recommend to save your clsas in the file name.
	 * 
	 * @param string $class summerized name of class.
	 * @return string
	 */
	public static function getRecommendedFileNameOfClassName(string $class): string {
		return str_replace("\\", "/", $class) . ".php";
	}

	/**
	 * Generate a recommend file to save your clsas in it.
	 * 
	 * @param packages\base\IO\directory $directory base directory.
	 * @param string $class summerized name of class.
	 * @return packages\base\IO\file
	 */
	public static function getRecommendedFileOfClassName(directory $directory, string $class): file {
		return $directory->file(self::getRecommendedFileNameOfClassName($class));
	}

	/**
	 * Generate a recommend table name based on class name.
	 * 
	 * @param string $package
	 * @param string $class summerized name of class.
	 * @return string
	 */
	public static function getRecommendedTableNameOfClass(string $package, string $class): string {
		return $package . "_" . strtolower(str_replace("\\", "s_", $class))."s";
	}
	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"name"(string)
	 * 					  other optional indexes: 
	 * 						"file" (string)
	 * 						"directory" (string)
	 * 						"no-autoload"(bool)
	 * 						"table"(string)
	 * 						"primary-key"(string)
	 * 
	 * @throws Exception if there is no package index in parameters
	 * @throws Exception if there is no name index in parameters
	 * @throws Exception if there is empty file index in parameters
	 * @throws Exception if there is directory file index in parameters
	 * @throws Exception if there is table file index in parameters
	 * @throws packages\assistant\BadClassNameException if name is invalid.
	 * @throws packages\assistant\AlreadyClassExistException if there is anthor class with same name in autoloader.
	 * @throws packages\assistant\AlreadyFileExistException if there is anthor file with same path.
	 * @return void
	 */
	public function add(array $data) {
		if (!isset($data['package'])) {
			throw new \Exception("there is no package name");
		}
		if (!isset($data['name']) or !$data['name']) {
			throw new \Exception("there is no name for model");
		}
		if (isset($data['file']) and !$data['file']) {
			throw new \Exception("there is no file for model");
		}
		if (isset($data['directory']) and !$data['directory']) {
			throw new \Exception("there is no directory for model file");
		}
		if (isset($data['table']) and !$data['table']) {
			throw new \Exception("there is no table name for model");
		}
		$data['name'] = str_replace("/", "\\", $data['name']);
		if (!Autoloader::isValidClassName($data['name'])) {
			throw new BadClassNameException($data['name']);
		}
		if (Autoloader::classExistsInPackage($data['package'], $data['name'])) {
			throw new AlreadyClassExistException($data['name']);
		}
		if (!isset($data['directory'])) {
			$data['directory'] = 'libraries';
		}
		if (!isset($data['file'])) {
			$data['file'] = self::getRecommendedFileNameOfClassName($data['name']);
		}
		$file = Packages::getPackageDirectory($data['package'])->file($data['directory'] . "/" . $data['file']);
		if ($file->exists()) {
			throw new AlreadyFileExistException($file);
		}
		if (!isset($data['table'])) {
			$data['table'] = self::getRecommendedTableNameOfClass($data['package'], $data['name']);
		}
		$parts = explode("\\", $data['name']);
		$namespace = implode("\\", array_slice($parts,0, count($parts) - 1));
		$className = $parts[count($parts) - 1];
		$content  = '<?php' . PHP_EOL;
		$content .= "namespace packages\\{$data['package']}\\{$namespace};" . PHP_EOL;
		$content .= "use packages\\base\\db\\dbObject;" . PHP_EOL;
		$content .= "class {$className} extends dbObject {" . PHP_EOL;
		$content .= "\tprotected \$dbTable = \"{$data['table']}\";" . PHP_EOL;
		if (isset($data['primary-key'])) {
			$content .= "\tprotected \$primaryKey = \"{$data['primary-key']}\";" . PHP_EOL;
		}
		$content .= "\tprotected \$dbFields = array();" . PHP_EOL;
		$content .= "\tprotected \$relations = array();" . PHP_EOL;
		$content .= "}" . PHP_EOL;
		$directory = $file->getDirectory();
		if (!$directory->exists()) {
			$directory->make(true);
		}
		$file->write($content);
		if (!isset($data['no-autoload']) or !$data['no-autoload']) {
			$autoloader = new Autoloader();
			$autoloader->add(array(
				'package' => $data['package'],
				'file' => $data['directory'] . '/' . $data['file'],
				'class' => $data['name']
			));
		}
	}
}