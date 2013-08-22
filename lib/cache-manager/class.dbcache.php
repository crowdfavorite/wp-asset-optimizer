<?php
/**
 * Cache Manager Interface Class
 * Defines an interface for caching optimized content to the database.
 *
 * @package CFAssetOptimizer
 */

class cfao_database_cache extends cfao_cache {
	protected $_POST_TYPE = '_cfao_database_cache';
	protected $_CACHE_TYPE_TAXONOMY = '_cfao_database_cachetype';
	
	public static function class_name() {
		return 'cfao_database_cache';
	}

	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
	
	public static function activate() {
		add_filter('cfao_cache_manager', 'cfao_wp_cache::class_name');
		add_filter('init', 'cfao_wp_cache::_init');
		add_filter('parse_query', 'cfao_wp_cache::_parse_query', 100);
		self::$_rewrite_base = apply_filters('cfao_db_cache_rewrite_base', self::$_rewrite_base);
		return true;
	}

	public static function listItem() {
		return array(
			'title' => __('CF Asset Optimizer Database Cache'),
			'description' => __('This plugin caches optimized asset data in the database. This is slower than a file cache, but can run in environments where database caching is preferred.'),
		);
	}
	
	public static function get($components, $type = '') {
		$key = self::_getKey($components, $type);
		// TODO get post by pagename, return post contents.
	}
	
	public static function set($components, $content, $type = '') {
		$key = self::_getKey($components, $type);
		// TODO get post by pagename, update if it exists, insert if it does not.
	}
	
	public static function clear($key = null) {
		// TODO Implement an interface for clearing just one record, or all of them.
	}
	
	protected static function _getKey($components, $type = '') {
		$key = '';
		foreach ($components as $handle => $src) {
			$key .= "$handle => $src\n";
		}
		$key = md5($key)."--$type";
		return apply_filters('cfao_database_cache_key', $key, $components, $type);
	}
}
add_filter('cfao_cachers', 'cfao_database_cache::register');