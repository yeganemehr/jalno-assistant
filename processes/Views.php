<?php
namespace packages\assistant;

class Views extends Process {
	/**
	 * @param array $data should be contain:
	 * 						"package"(string)
	 * 						"name"(string)
	 * 					  other optional indexes:
	 * 						"file" (string)
	 * 						"directory" (string)
	 * 						"namespace" (string) default: views
	 * 						"userpanel" (bool) override "base"
	 * 						"base" (bool) default: true
	 * 						"type"("normal", "list", "form", "search") default: "normal"
	 * 						"no-autoload"(bool)
	 * 						"property" (string|string[])
	 * 						"permission" (string|string[])
	 * 
	 */
	public function add(array $data) {
		if (!isset($data['package'])) {
			throw new Exception("there is no package parameter");
		}
		if (!isset($data['name']) or !$data['name']) {
			throw new Exception("there is no name for view");
		}
		if (isset($data['file']) and !$data['file']) {
			throw new Exception("there is no file for view");
		}
		if (isset($data['directory']) and !$data['directory']) {
			throw new Exception("there is no directory for view file");
		}
		if (isset($data['type']) and !in_array($data['type'], ['normal', 'list', 'form', 'search'])) {
			throw new Exception("wrong type");
		}
		$data['name'] = str_replace("/", "\\", $data['name']);

		if (!Autoloader::isValidClassName($data['name'])) {
			throw new BadClassNameException($data['name']);
		}

		if (isset($data['namespace'])) {
			if ($data['namespace'] and !Autoloader::isValidClassName($data['namespace'])) {
				throw new BadClassNameException($data['namespace']);
			}
		} else {
			$data['namespace'] = "views";
		}
		if ($data['namespace']) {
			$viewName = $data['namespace'] . "\\" . $data['name'];
		}

		if (!isset($data['directory'])) {
			$data['directory'] = '';
		}
		if (!isset($data['file'])) {
			$data['file'] = Models::getRecommendedFileNameOfClassName($viewName);
		}
		if (!isset($data['userpanel'])) {
			$data['userpanel'] = false;
		}
		if (!isset($data['type'])) {
			$data['type'] = 'normal';
		}
		$path = ($data['directory'] ? $data['directory'] . "/" : "") . $data['file'];
		$file = Packages::getPackageDirectory($data['package'])->file($path);

		if ($file->exists()) {
			throw new AlreadyFileExistException($file);
		}
		$parent = "";
		$extends = "";
		if ($data['userpanel']) {
			switch ($data['type']) {
				case("normal"): $parent = "packages\\userpanel\\view";  break;
				case("search"): case("list"): $parent = "packages\\userpanel\\views\\listview"; break;
				case("form"): $parent = "packages\\userpanel\\views\\form"; break;
			}
		} else {
			switch ($data['type']) {
				case("normal"): $parent = "packages\\base\\view"; break;
				case("search"): $parent = "packages\\base\\views\\{listview, traits\\form}"; $extends = "listview"; break;
				case("list"): $parent = "packages\\base\\views\\listview"; break;
				case("form"): $parent = "packages\\base\\views\\form"; break;
			}
		}
		$classBody = "";
		if (!isset($data['property'])) {
			$data['property'] = [];
		}
		if (!is_array($data['property'])) {
			$data['property'] = array($data['property']);
		}
		if (!isset($data['permission'])) {
			$data['permission'] = [];
		}
		if (!is_array($data['permission'])) {
			$data['permission'] = array($data['permission']);
		}
		if ($data['permission']) {
			foreach($data['permission'] as $permission) {
				$classBody .= "\t/**" . PHP_EOL;
				$classBody .= "\t * @var bool permission of {$data['package']}_{$permission}" . PHP_EOL;
				$classBody .= "\t */" . PHP_EOL;
				$classBody .= "\tprotected \$can" . self::getAbbrPermission($permission) . ";" . PHP_EOL;
			}
			$classBody .= PHP_EOL;
			$classBody .= "\t/**" . PHP_EOL;
			$classBody .= "\t * Initialize the view and permissions" . PHP_EOL;
			$classBody .= "\t */" . PHP_EOL;
			$classBody .= "\tpublic function __construct() {" . PHP_EOL;
			foreach($data['permission'] as $permission) {
				$classBody .= "\t\t\$this->can" . self::getAbbrPermission($permission) . " = Authorization::is_accessed(\"{$permission}\");" . PHP_EOL;
			}
			$classBody .= PHP_EOL;
			$classBody .= "\t\t\$this->setData(array(" . PHP_EOL;
			foreach($data['permission'] as $permission) {
				$classBody .= "\t\t\t\"{$data['package']}_{$permission}\" => \$this->can" . self::getAbbrPermission($permission) . "," . PHP_EOL;
			}
			$classBody .= "\t\t), \"permissions\");" . PHP_EOL;
			$classBody .= "\t}" . PHP_EOL;
		}
		if ($data['property']) {
			foreach($data['property'] as $property) {
				$classBody .= "\t/**" . PHP_EOL;
				$classBody .= "\t * Setter for {$property}" . PHP_EOL;
				$classBody .= "\t *" . PHP_EOL;
				$classBody .= "\t * @param mixed \${$property}" . PHP_EOL;
				$classBody .= "\t * @return void" . PHP_EOL;
				$classBody .= "\t */" . PHP_EOL;
				$classBody .= "\tpublic function set" . ucfirst($property) . "(\${$property}) {" . PHP_EOL;
				$classBody .= "\t\t\$this->setData(\${$property}, \"{$property}\");" . PHP_EOL;
				$classBody .= "\t}" . PHP_EOL . PHP_EOL;
				$classBody .= "\t/**" . PHP_EOL;
				$classBody .= "\t * Getter for {$property}" . PHP_EOL;
				$classBody .= "\t *" . PHP_EOL;
				$classBody .= "\t * @return mixed" . PHP_EOL;
				$classBody .= "\t */" . PHP_EOL;
				$classBody .= "\tpublic function get" . ucfirst($property) . "() {" . PHP_EOL;
				$classBody .= "\t\treturn \$this->getData(\"{$property}\");" . PHP_EOL;
				$classBody .= "\t}" . PHP_EOL . PHP_EOL;
			}
		}


		$parts = explode("\\", $viewName);
		$namespace = implode("\\", array_slice($parts,0, count($parts) - 1));
		$className = $parts[count($parts) - 1];
		if (!$extends) {
			$extends = substr($parent, strrpos($parent, "\\") + 1);
		}
		$content  = '<?php' . PHP_EOL;
		$content .= "namespace packages\\{$data['package']}\\{$namespace};" . PHP_EOL;
		if ($data['type'] == "search" and $data['userpanel']){
			$content .= "use packages\\base\\views\\traits\\form;" . PHP_EOL;
		}
		$content .= "use {$parent};" . PHP_EOL;
		if ($data['permission']) {
			$content .= "use packages\\{$data['package']}\\Authorization;" . PHP_EOL;
		}
		$content .= PHP_EOL;
		$content .= "class {$className} extends {$extends} {" . PHP_EOL;
		if ($data['type'] == "search") {
			$content .= "\tuse form;" . PHP_EOL;
		}
		$content .= $classBody . PHP_EOL;
		
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
				'class' => $viewName
			));
		}
	}
	private static function getAbbrPermission(string $permission) {
		if (($pos = strrpos($permission, "_")) != null) {
			$permission = substr($permission, $pos + 1);
		}
		return ucfirst($permission);
	}
}