<?php
namespace packages\assistant;
use packages\base;
class Translator extends process {
	/**
	 * @param string $code language code based on ISO-639
	 * @see view-source:https://www.worldatlas.com/articles/which-languages-are-written-from-right-to-left.html
	 * @return bool
	 */
	public static function isRTL(string $code): bool {
		return in_array(base\translator::getShortCodeLang($code), ['ar', 'az', 'dv', 'ff', 'fa', 'he', 'ku', 'ur']);
	}
}