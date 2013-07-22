<?php
/**
 * Cache Manager Interface Class
 * Defines a common interface for caching optimized content.
 *
 * @package CFAssetOptimizer
 */

class cfao_cache {
	
	public static function get($reference, $type = '') {
		throw new Exception('Required function "get" is not implemented');
	}
	
	public static function set($reference, $content, $type = '') {
		throw new Exception('Required function "set" not implemented');
	}
	
	public static function clear($key = null) {
		throw new Exception('Required function "clear" not implemented');
	}
}