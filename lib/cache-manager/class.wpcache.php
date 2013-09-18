<?php
/**
 * CF Persistent WordPress Object Cache Manager
 * Defines an interface for caching optimized content to the file system.
 *
 * @package CFAssetOptimizer
 */

class cfao_wp_cache extends cfao_cache {
	protected static $_rewrite_base = 'cfao-cached-assets/';
	
	public static function class_name() {
		return 'cfao_wp_cache';
	}
	
	public static function register($handles) {
		if (!function_exists('wp_cache_set') || !function_exists('wp_cache_get')) {
			return $handles;
		}
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		if (is_admin()) {
			add_action('cfao_admin_activate_' . $class_name, $class_name.'::_add_rewrite_rules');
			add_action('cfao_admin_deactivate_' . $class_name, $class_name.'::_clear_rewrite_rules');
		}
		return $handles;
	}
	
	public static function activate() {
		if (!function_exists('wp_cache_set') || !function_exists('wp_cache_get')) {
			return false;
		}
		add_filter('cfao_cache_manager', 'cfao_wp_cache::class_name');
		add_filter('init', 'cfao_wp_cache::_init');
		add_filter('parse_query', 'cfao_wp_cache::_parse_query', 100);
		self::$_rewrite_base = apply_filters('cfao_wp_cache_rewrite_base', self::$_rewrite_base);
		return true;
	}

	public static function listItem() {
		return array(
			'title' => __('CF WordPress Object Cache Integration', 'cf-asset-optimizer'),
			'description' => __('This plugin integrates with existing wp_cache functionality provided by other caching plugins.', 'cf-asset-optimizer'),
		);
	}
	
	public static function get($reference, $type = '') {
		$key = self::_getKey($reference, $type);
		return self::_getByKey($key);
	}
	
	protected static function _getByKey($key) {
		return wp_cache_get($key);
	}
	
	public static function set($reference, $content, $type = '') {
		$key = self::_getKey($reference, $type);
		$asset_data = array(
			'url' => '/'.trailingslashit(self::$_rewrite_base).$key,
			'ver' => time(),
			'contents' => $content
		);
		$result = wp_cache_set($key, $asset_data);
		return ($result !== false && !is_wp_error($result));
	}

	protected static function _getKey($components, $cache_type = '') {
		$base_key_string = '';
		foreach ($components as $name=>$val) {
			$base_key_string .= "$name $val ";
		}
		$base_key_string = 'cfao-'.md5($base_key_string);
		if (!empty($cache_type)) {
			$base_key_string .= '.'.$cache_type;
		}
		return apply_filters('cfao_wp_cache_key', $base_key_string, $components, $cache_type);
	}
	
	public static function _init() {
		global $wp;
		$wp->add_query_var('cfao_asset');
		$wp->add_query_var('ver');
	}
	
	public static function _add_rewrite_rules() {
		add_rewrite_rule('(.*/)?' . trailingslashit(self::$_rewrite_base) . '(.*?)/?(\?(.*))$', 'index.php?cfao_asset=$matches[2]&$matches[4]', 'top');
		add_rewrite_rule('(.*/)?' . trailingslashit(self::$_rewrite_base) . '(.*?)/?$', 'index.php?cfao_asset=$matches[2]', 'top');
		flush_rewrite_rules();
	}
	
	public static function _clear_rewrite_rules() {
		flush_rewrite_rules();
	}
	
	public static function _parse_query($query) {
		global $wp_rewrite, $wp;
		if ($query->is_main_query() && $asset = $query->get('cfao_asset')) {
			if ($cache = self::_getByKey($asset)) {
				$file_data = wp_check_filetype($asset);
				$content_type = apply_filters('cfao_wp_cache_contenttype', (isset($file_data['type']) ? $file_data['type'] : ''), $asset);
				if (!empty($content_type)) {
					header('Content-Type: ' . $file_data['type']);
				}
				echo $cache['contents'];
				exit();
			}
			else {
				header('HTTP/1.1 404 Not Found');
				exit();
			}
		}
		return $query;
	}
	
}
add_filter('cfao_cachers', 'cfao_wp_cache::register');