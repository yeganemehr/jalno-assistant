<?php
namespace packages\assistant;
use packages\base;
use packages\base\json;

class Translator extends process {
	/**
	 * @param string $code language code based on ISO-639
	 * @see view-source:https://www.worldatlas.com/articles/which-languages-are-written-from-right-to-left.html
	 * @return bool
	 */
	public static function isRTL(string $code): bool {
		return in_array(base\translator::getShortCodeLang($code), ['ar', 'az', 'dv', 'ff', 'fa', 'he', 'ku', 'ur']);
	}
	
	/**
	 * Init an empty language file.
	 * 
	 * @param packages\base\IO\file a file. if parent directory doesn't exists, will create.
	 * @param string $code a jalno-valid language code.
	 * @return void
	 */
	public static function initFile(base\IO\file $file, string $code) {
		$dir = $file->getDirectory();
		if (!$dir->exists()) {
			$dir->make();
		}
		$file->write(json\encode(array(
			'rtl' => self::isRTL($code),
			'phrases' => []
		), json\PRETTY));
	}
}