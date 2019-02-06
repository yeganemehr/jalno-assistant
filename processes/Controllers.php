<?php
namespace packages\assistant;
use packages\base\{IO\directory, IO\file, log};
use packages\PhpParser\{ParserFactory, NodeTraverser, NodeVisitorAbstract, Node};

class Controllers extends Process {
	/**
	 * Check name of method based on grammer.
	 * 
	 * @param string $name
	 * @return bool
	 */
	public static function isValidMethodName(string $method) {
		return Autoloader::isValidClassName($method, false);
	}

	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"name"(string)
	 * 					  other optional indexes: 
	 * 						"file" (string)
	 * 						"namespace" (string) default: controllers
	 * 						"directory" (string)
	 * 						"no-autoload"(bool)
	 * 						"userpanel"(bool)
	 * 
	 * @throws Exception if there is no package index in parameters
	 * @throws Exception if there is no name index in parameters
	 * @throws Exception if there is not exactly one @ in name parameter.
	 * @throws Exception if there is empty file index in parameters
	 * @throws Exception if there is directory file index in parameters
	 * @throws packages\assistant\BadClassNameException if name is invalid.
	 * @throws packages\assistant\BadMethodNameException if name is invalid.
	 * @throws packages\assistant\AlreadyClassExistException if there is anthor class with same name in autoloader.
	 * @throws packages\assistant\AlreadyMethodExistException if there is anthor method in the class with same name.
	 * @throws packages\assistant\ClassNotExistException the class can't be found in the existing file.
	 * @return void
	 */
	public function add(array $data) {
		log::setLevel('debug');
		if (!isset($data['package'])) {
			throw new \Exception("there is no package name");
		}
		if (!isset($data['name']) or !$data['name']) {
			throw new \Exception("there is no name for controller");
		}
		if (isset($data['file']) and !$data['file']) {
			throw new \Exception("there is no file for controller");
		}
		if (isset($data['directory']) and !$data['directory']) {
			throw new \Exception("there is no directory for controller file");
		}
		if (isset($data['address']) and !$data['address']) {
			throw new \Exception("there is no address for controller");
		}
		$data['name'] = str_replace("/", "\\", $data['name']);
		$parts = explode("@", $data['name']);
		if (count($parts) != 2) {
			throw new \Exception("name should exactly has one @");
		}
		list($controllerName, $method) = $parts;

		if (!Autoloader::isValidClassName($controllerName)) {
			throw new BadClassNameException($controllerName);
		}
		if (!self::isValidMethodName($method)) {
			throw new BadMethodNameException($method);
		}

		if (isset($data['namespace'])) {
			if ($data['namespace'] and !Autoloader::isValidClassName($data['namespace'])) {
				throw new BadClassNameException($data['namespace']);
			}
		} else {
			$data['namespace'] = "controllers";
		}
		if ($data['namespace']) {
			$controllerName = $data['namespace'] . "\\" . $controllerName;
		}

		if (!isset($data['directory'])) {
			$data['directory'] = '';
		}
		if (!isset($data['file'])) {
			$data['file'] = Models::getRecommendedFileNameOfClassName($controllerName);
		}
		if (!isset($data['userpanel'])) {
			$data['userpanel'] = false;
		}
		$path = ($data['directory'] ? $data['directory'] . "/" : "") . $data['file'];
		$file = Packages::getPackageDirectory($data['package'])->file($path);

		$parts = explode("\\", $controllerName);
		$namespace = implode("\\", array_slice($parts,0, count($parts) - 1));
		$className = $parts[count($parts) - 1];


		$methodBody  = PHP_EOL;
		$methodBody .= "\t/**" . PHP_EOL;
		$methodBody .= "\t * " . PHP_EOL;
		$methodBody .= "\t *" . PHP_EOL;
		$methodBody .= "\t * @param array \$data" . PHP_EOL;
		$methodBody .= "\t * @return packages\\base\\response" . PHP_EOL;
		$methodBody .= "\t */" . PHP_EOL;
		$methodBody .= "\tpublic function {$method}(array \$data): response {" . PHP_EOL;
		$methodBody .= "\t\treturn new response(true);" . PHP_EOL;
		$methodBody .= "\t}";


		if (!$file->exists()) {
			$content  = '<?php' . PHP_EOL;
			$content .= "namespace packages\\{$data['package']}\\{$namespace};" . PHP_EOL;
			if ($data['userpanel']){
				$content .= "use packages\\base\\{view, NotFound, response};" . PHP_EOL;
				$content .= "use packages\\userpanel\\{controller};" . PHP_EOL;
				$content .= "use packages\\{$data['package']}\\{Authorization, Authentication, views};" . PHP_EOL;
			} else {
				$content .= "use packages\\base\\{controller, view, NotFound, response};" . PHP_EOL;
				$content .= "use packages\\{$data['package']}\\{views};" . PHP_EOL;
			}
			$content .= PHP_EOL;
			$content .= "class {$className} extends controller {" . PHP_EOL;
			if ($data['userpanel']) {
				$content .= "\tprotected \$authentication = true;" . PHP_EOL;
			}
			$content .= $methodBody . PHP_EOL;
			
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
					'file' => $path,
					'class' => $controllerName
				));
			}
		} else {
			$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
			$visitor = new class($className) extends NodeVisitorAbstract {
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
			$fileContent = $file->read();
			$fileLines = explode("\n", $fileContent);
			$traverser = new NodeTraverser;
			$traverser->addVisitor($visitor);
			$stmts = $parser->parse($fileContent);
			$traverser->traverse($stmts);
			if (!$visitor->class) {
				throw new ClassNotExistException($this->className);
			}
			foreach($visitor->class->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\ClassMethod and $stmt->name == $method) {
					throw new AlreadyMethodExistException($method);
				}
			}
			array_splice($fileLines, $visitor->class->getAttribute("endLine") - 1, 0, $methodBody);
			$fileContent = implode("\n", $fileLines);
			$file->write($fileContent);
		}
	}
}