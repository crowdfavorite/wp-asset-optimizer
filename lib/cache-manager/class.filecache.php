<?php
/**
 * Cache Manager Interface Class
 * Defines an interface for caching optimized content to the file system.
 *
 * @package CFAssetOptimizer
 */

class cfao_file_cache extends cfao_cache {
	private static $_CACHE_BASE_DIR;
	private static $_CACHE_BASE_URL;
	private static $_OPTION = '_cfao_file_cache_settings';
	
	public static function class_name() {
		return 'cfao_file_cache';
	}
	
	public static function get($reference, $type = '') {
		// Find out if we have the output requested cached.
		$key = self::_getKey($reference, $type);
		if (file_exists(self::$_CACHE_BASE_DIR . $key)) {
			$filemtime = filemtime(self::$_CACHE_BASE_DIR . $key);
			return apply_filters('cfao_file_cache_val', array('url' => self::$_CACHE_BASE_URL . $key, 'ver' => $filemtime), self::$_CACHE_BASE_URL, $key, $filemtime);
			return apply_filters('cfao_file_cache_url', $url, $key, self::$_CACHE_BASE_URL, $key, $filemtime);
		}
		return false;
	}
	
	public static function set($reference, $content, $type = '') {
		$key = self::_getKey($reference, $type);
		$success = false;
		if (!self::_lock($key)) {
			return false;
		}
		$success = (file_put_contents(self::$_CACHE_BASE_DIR . $key, $content) !== false);
		self::_release($key);
		return $success;
	}
	
	public static function clear($key = null) {
		$succeeded = true;
		if (empty($key)) {
			if (!self::_lock()) {
				return false;
			}
			while ($filename = readdir(self::$_CACHE_BASE_DIR)) {
				if (strpos($filename, '.') === 0) {
					continue;
				}
				$succeeded &= unlink($key);
			}
			self::_release();
		}
		else {
			if (!self::_lock($key)) {
				return false;
			}
			$succeeded = unlink($key);
			self::_release($key);
		}
		return $succeeded;
	}
	
	public static function _configure() {
		$wp_content_dir = trailingslashit(WP_CONTENT_DIR);
		$wp_content_url = trailingslashit(WP_CONTENT_URL);
		$server_folder = $_SERVER['SERVER_NAME'];
		self::$_CACHE_BASE_DIR = trailingslashit(apply_filters('cfao_file_cache_basedir', trailingslashit($wp_content_dir.'cfao-cache/'.$server_folder), $wp_content_dir, $server_folder));
		self::$_CACHE_BASE_URL = trailingslashit(apply_filters('cfao_file_cache_baseurl', preg_replace('~^https?://[^/]*~', '', trailingslashit($wp_content_url.'cfao-cache/'.$server_folder)), $wp_content_url, $server_folder));
		add_filter('cfao_cache_manager', 'cfao_file_cache::class_name');
		add_filter('cfao_cache_manager', 'cfao_file_cache::class_name');
	}

	protected static function _getKey($components, $cache_type = '') {
		$base_key_string = '';
		$supported_cache_types = apply_filters('cfao_file_cache_types', array('css', 'js'));
		foreach ($components as $name=>$val) {
			$base_key_string .= "$name $val ";
		}
		$base_key_string = md5($base_key_string);
		if (empty($cache_type) || !in_array($cache_type, $supported_cache_types)) {
			$extension = '.cached';
		}
		else {
			$extension = '.cached.' . $cache_type;
		}
		return apply_filters('cfao_file_cache_key', $base_key_string.$extension, $base_key_string, $extension);
	}
	
	protected static function _lock($key = null) {
		$cache_dir = self::$_CACHE_BASE_DIR;
		if (!is_dir(self::$_CACHE_BASE_DIR)) {
			// Make the directory if we can.
			if (!mkdir(self::$_CACHE_BASE_DIR, 775, true)) {
				return false;
			}
		}
		if (file_exists($cache_dir . '.lock.permanent')) {
			// This server is permanently locked out of writing the cache. Just return false;
			return false;
		}
		if (file_exists($cache_dir . '.lock.global')) {
			// Someone has generated a temporary global lock. Check its time.
			$expire = strtotime("+2 minutes", filemtime($cache_dir . '.lock.global'));
			if ($expire > time()) {
				// Global lock is still active.
				return false;
			}
			unlink($cache_dir . '.lock.global');
		}
		if (empty($key)) {
			return (file_put_contents($cache_dir . '.lock.global', '') !== false);
		}
		else {
			if (file_exists($cache_dir . '.lock.local.' . $key)) {
				$expire = strtotime("+2 minutes", filemtime($cache_dir . '.lock.local.' . $key));
				if ($expire > time()) {
					return false;
				}
				unlink($cache_dir . '.lock.local.' . $key);
			}
			return (file_put_contents($cache_dir . '.lock.local.' . $key, '') !== false);
		}
		return false;
	}
	
	protected static function _release($key = null) {
		if (empty($key)) {
			return (!file_exists(self::$_CACHE_BASE_DIR . '.lock.global') || unlink(self::$_CACHE_BASE_DIR . '.lock.global'));
		}
		else {
			return (!file_exists(self::$_CACHE_BASE_DIR . '.lock.local.' . $key) || unlink(self::$_CACHE_BASE_DIR . '.lock.local.' . $key));
		}
	}
}
cfao_file_cache::_configure();