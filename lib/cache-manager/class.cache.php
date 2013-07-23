<?php
/**
 * Cache Manager Interface Class
 * Defines a common interface for caching optimized content.
 *
 * @package CFAssetOptimizer
 */

class cfao_cache {
	
	public static function class_name() {
		return null;
	}
	
	public static function get($reference, $type = '') {
		throw new Exception('Required function "get" is not implemented');
	}
	
	public static function set($reference, $content, $type = '') {
		throw new Exception('Required function "set" not implemented');
	}
	
	public static function clear($key = null) {
		throw new Exception('Required function "clear" not implemented');
	}
	
	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
	
	public static function listItem() {
		return array(
			__('CF Cache Manager Interface'),
			__('This plugin is a generic interface and should not appear in the plugins list or be activated.'),
		);
	}
}
add_filter('cfao_cachers', 'cfao_cache::register');