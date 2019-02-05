<?php
namespace packages\assistant;
use packages\base\{IO, packages as BasePackages, json};
use packages\PhpParser\{ParserFactory, NodeTraverser};
use packages\assistant\PhpParser\AutoloadItemFinder;

class Autoloader extends Process {
	/**
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @return packages\base\IO\file\local|null file of autoload of package.
	 */
	public static function getAutoloaderFileOfPackage(string $package) {
		$config = Packages::getPackageConfig($package);
		if (!isset($config['autoload'])) {
			return null;
		}
		return Packages::getPackageDirectory($package)->file($config['autoload']);
	}

	/**
	 * @throws packages\base\IO\NotFoundException if the file does not exists.
	 * @throws packages\assistant\AutoloaderException if cannot decode json or the file hasn't "files" index.
	 * @return array decoded content of autoloader.json file.
	 */
	public static function parseAutoloaderFile(IO\file $file) {
		if (!$file->exists()) {
			throw new IO\NotFoundException($file);
		}
		$json = json\decode($file->read());
		if (json_last_error()) {
			throw new AutoloaderException($file, $file->getPath(). " json decode error: " . json_last_error_msg() . "(" . json_last_error() . ")");
		}
		if (!isset($json['files'])) {
			throw new AutoloaderException($file, $file->getPath(). " cannot find \"files\" index");
		}
		return $json;
	}

	/**
	 * Parse a php file and return list of fully qualified name of all classes, interfaces and traits in it.
	 * 
	 * @throws packages\base\IO\NotFoundException if cannot find provided file.
	 * @throws packages\PhpParser\Error if there is error in parsing process.
	 * @return string[] fully qualified name of all classes, interfaces and traits in the file.
	 */
	public static function getClassesFromPHPFile(IO\file $file) {
		if (!$file->exists()) {
			throw new IO\NotFoundException($file);
		}
		$traverser = new NodeTraverser;
		$objectVisitor = new AutoloadItemFinder();
		$objectVisitor->setFile($file);
		$traverser->addVisitor($objectVisitor);
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$stmts = $parser->parse($file->read());
		$traverser->traverse($stmts);
		return array_column($objectVisitor->getItems(), 'class');
	}
	/**
	 * remove preffix packages and package name of provided class name.
	 * 
	 * @throws packages\assistant\BadClassNameException if there is no standard prefix in provided class name.
	 * @return string
	 */
	public static function removePackageNamespaceOfClassName(string $package, string $class) {
		$parts = explode("\\", $class, 3);
		if (count($parts) != 3 or $parts[0] != "packages" or $parts[1] != $package) {
			throw new BadClassNameException($class);
		}
		return $parts[2];
	}

	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"file"(string)
	 * 					  other optional indexes: 
	 * 						"class" (string|string[])
	 * 
	 * @throws Exception if there no package index in parameters
	 * @throws Exception if there no file index in parameters
	 * @throws Exception if there no class index in parameters and PhpParser package isn't persent.
	 * @throws packages\assistant\PackageNotExistException if cannot find a package with provided name.
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\base\IO\NotFoundException if cannot find provided file in the package.
	 * @throws packages\PhpParser\Error {@see Autoloader::getClassesFromPHPFile()}
	 * @return void
	 */
	public function add(array $data) {
		if (!isset($data['package'])) {
			throw new \Exception("there is no package name");
		}
		if (!isset($data['file']) or !$data['file']) {
			throw new \Exception("there is no file name");
		}
		if (!isset($data['class']) and !BasePackages::package("PhpParser")) {
			throw new \Exception("there is no class parameter nor  PhpParser package");
		}
		if (!Packages::isPackage($data['package'])) {
			throw new PackageNotExistException($data['package']);
		}
		$file = Packages::getPackageDirectory($data['package'])->file($data['file']);
		
		$autoloaderFile = self::getAutoloaderFileOfPackage($data['package']);
		$autoloader = array('files' => []);
		if ($autoloaderFile) {
			$autoloader = self::parseAutoloaderFile($autoloaderFile);
		}
		if (isset($data['class'])) {
			if (!is_array($data['class'])) {
				$data['class'] = array($data['class']);
			}
			foreach ($data['class'] as &$class) {
				$class = str_replace("/", "\\", $class);
			}
		} else {
			$data['class'] = self::getClassesFromPHPFile($file);
			foreach($data['class'] as &$class) {
				$class = self::removePackageNamespaceOfClassName($data['package'], $class);
			}
		}

		$found = false;
		foreach($autoloader['files'] as &$row) {
			if ($row['file'] == $data['file']) {
				$found = true;
				foreach($data['class'] as $class) {
					if (!in_array($class, $row['classes'])) {
						$row['classes'][] = $class;
					}
				}
				break;
			}
		}
		if (!$found) {
			$autoloader['files'][] = array(
				'classes' => $data['class'],
				'file' => $data['file']
			);
		}
		$autoloaderFile->write(json\encode($autoloader, json\PRETTY | JSON_UNESCAPED_SLASHES));
	}
}