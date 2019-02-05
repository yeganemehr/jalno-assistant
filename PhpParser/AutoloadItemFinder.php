<?php
namespace packages\assistant\PhpParser;
use packages\base\IO\{file, directory};
use packages\PhpParser\{Node, NodeVisitorAbstract};

/**
 * @see https://github.com/nikic/PHP-Parser/blob/3.x/doc/2_Usage_of_basic_components.markdown#node-traversation
 */
class AutoloadItemFinder extends NodeVisitorAbstract {
	/**
	 * @var string path of current file
	 */
	protected $file;

	/**
	 * @var array items which found by enterNode() method.
	 */
	protected $items = array();

	/**
	 * @var string current namespace in file.
	 */
	protected $namespace;

	/**
	 * @var string path to package directory.
	 */
	protected $packageDir;
	
	/**
	 * Setter for file property.
	 * 
	 * @param packages\base\IO\file $file
	 * @return void
	 */
	public function setFile(file $file){
		$this->file = $file->getPath();
		$this->namespace = "";
	}

	/**
	 * Setter for packageDir property.
	 * 
	 * @param packages\base\IO\directory $dir
	 * @return void
	 */
	public function setPackageDirectory(directory $dir){
		$this->packageDir = $dir->getPath();
	}

	/**
	 * Called after enter into every node and if it was a class or trait or interface we member it so later we able to generate a autoloader record.
	 * 
	 * @param packages\PhpParser\Node $node
	 * @return void
	 */
	public function enterNode(Node $node) {
        if(
			$node instanceof Node\Stmt\Class_ or
			$node instanceof Node\Stmt\Trait_ or
			$node instanceof Node\Stmt\Interface_
		) {
			$this->items[] = array(
				'file' => $this->file,
				'class' => ($this->namespace ? $this->namespace."\\" : "").$node->name
			);
		} elseif ($node instanceof Node\Stmt\Namespace_) {
			$this->namespace = implode("\\", $node->name->parts);
		}
	}
	/**
	 * @return array array which indexed by file path and filled by class names of each file.
	 */
	public function getMap(){
		$map = array();
		foreach($this->items as $item){
			if(isset($map[$item['file']])){
				$map[$item['file']][] = $item['class'];
			}else{
				$map[$item['file']] = array($item['class']);
			}
		}
		return $map;
	}

	/**
	 * Combine all of finded items.
	 * 
	 * @return array array which can be placed in autoloader.json file.
	 */
	public function getAutoloader(){
		$result = array();
		foreach ($this->getMap() as $file => $classes){
			$result[] = array(
				'file' => substr($file,0, strlen($this->packageDir)) == $this->packageDir ? substr($file, strlen($this->packageDir) + 1) : $file,
				'classes' => $classes
			);
		}
		return array(
			'files' => $result
		);
	}
	
	/**
	 * Get items which found by enterNode() method.
	 *
	 * @return array
	 */ 
	public function getItems(): array {
		return $this->items;
	}
}
