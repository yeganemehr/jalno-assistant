<?php
namespace packages\assistant;
use packages\base\{IO, packages as BasePackages, json};
use packages\PhpParser\{ParserFactory, NodeTraverser, Node, NodeVisitorAbstract};
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
	 * @param string $package package name
	 * @param string $class fully qualified name of class
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
	 * check autoloader file of the package for existace of the class.
	 * 
	 * @param string $package
	 * @param string $class summerized name of class
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @throws packages\assistant\AutoloaderException if the package does not have an autoloader.
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @return bool
	 */
	public static function classExistsInPackage(string $package, string $class): bool {
		$autoloaderFile = self::getAutoloaderFileOfPackage($package);
		if (!$autoloaderFile) {
			throw new AutoloaderException("this package does not have an autoloader");
		}
		return self::classExists($autoloaderFile, $class);
	}

	/**
	 * check autoloader file for existace of the class.
	 * 
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @return bool
	 */
	public static function classExists(IO\file $autoloaderFile, string $class): bool {
		return self::getClassFile($autoloaderFile, $class) !== null;
	}

	/**
	 * find and parse autoloader the package and looking for the class.
	 * 
	 * @param string $package
	 * @param string $class summerized name of class
	 * @throws packages\assistant\PackageConfigException {@see Packages::getPackageConfig()}
	 * @throws packages\assistant\AutoloaderException if the package does not have an autoloader.
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @return packages\base\IO\file|null
	 */
	public static function getClassFileInPackage(string $package, string $class) {
		$autoloaderFile = self::getAutoloaderFileOfPackage($package);
		if (!$autoloaderFile) {
			throw new AutoloaderException("this package does not have an autoloader");
		}
		$result = self::getClassFile($autoloaderFile, $class);
		return $result !== null ? Packages::getPackageDirectory($package)->file($result) : null;
	}

	/**
	 * parse autoloader the package and looking for the class.
	 * 
	 * @throws packages\base\IO\NotFoundException {@see Autoloader::parseAutoloaderFile()}
	 * @throws packages\assistant\AutoloaderException {@see Autoloader::parseAutoloaderFile()}
	 * @return string|null path to the file
	 */
	public static function getClassFile(IO\file $autoloaderFile, string $class) {
		$autoloader = self::parseAutoloaderFile($autoloaderFile);
		foreach($autoloader['files'] as $item) {
			if (in_array($class, $item['classes'])) {
				return $item['file'];
			}
		}
		return null;
	}

	/**
	 * Check name of class based on grammer.
	 * 
	 * @param string $name
	 * @param bool $withNamespace which namespace in class is allowed or not.
	 * @return bool
	 */
	public static function isValidClassName(string $class, bool $withNamespace = true) {
		if ($withNamespace) {
			$parts = explode("\\", $class);
			foreach ($parts as $part) {
				if (!preg_match("/^[a-z_][a-z0-9_]*$/i", $part)){
					return false;
				}
			}
			return true;
		}
		return preg_match("/^[a-z_][a-z0-9_]*$/i", $class);
	}
	/**
	 * @param string $className
	 * @return packages\PhpParser\NodeVisitorAbstract
	 */
	public static function getPhpParserClassFinder(string $className) {
		return new class($className) extends NodeVisitorAbstract {
			/**
			 * @var packages\PhpParser\Node\Stmt\Class_
			 */
			public $class;

			/**
			 * @var string
			 */
			private $className;

			public function __construct(string $className) {
				$this->className = $className;
			}

			/**
			 * @param packages\PhpParser\Node $node
			 */
			public function enterNode(Node $node) {
				if ($node instanceof Node\Stmt\Class_) {
					if (!$this->class and $node->name == $this->className) {
						$this->class = $node;
					}
					return NodeTraverser::DONT_TRAVERSE_CHILDREN;
				}
			}
		};
	}

	/**
	 * @param packages\base\IO\file $file should be exists.
	 * @param string $className
	 * @throws packages\PhpParser\Error if there is error in parsing process.
	 * @return packages\PhpParser\Node\Stmt\Class_
	 */
	public static function getClassFromFile(IO\file $file, string $className) {
		$visitor = self::getPhpParserClassFinder($className);
		$traverser = new NodeTraverser;
		$traverser->addVisitor($visitor);

		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$stmts = $parser->parse($file->read());
		$traverser->traverse($stmts);
		return $visitor->class;
	}

	/**
	 * Init an empty autoloader file.
	 * 
	 * @param packages\base\IO\file
	 * @return void
	 */
	public static function initFile(IO\file $file) {
		$file->write(json\encode(array(
			'files' => []
		), json\PRETTY));
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