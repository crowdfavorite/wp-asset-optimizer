<?php
/**
 * Cache Manager Interface Class
 * Defines an interface for caching optimized content to the database.
 *
 * @package CFAssetOptimizer
 */

class cfao_database_cache extends cfao_cache {
	
	public static function get($key, $type = '') {
	}
	
	public static function set($key, $content, $type = '') {
	}
	
	
	public static function clear($key = null) {
	}
}