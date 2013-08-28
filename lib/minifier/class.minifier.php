<?php
/**
 * Minifier interface for CF Asset Minifier
 */

class cfao_minifier {
	
	public static function class_name() {
		return '';
	}

	public static function listItem() {
		return array(
			'title' => __('CF Asset Minifier Interface'),
			'description' => __('This is the interface class for minifiers for the asset optimizer and should not be activated.'),
		);
	}
	
	public static function activate() {
		
	}
	
	public static function minify($string, $type = '', $handle = '') {
		return $string;
	}
	
	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
	
}