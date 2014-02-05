<?php
/*
Plugin Name: CF Asset Optimizer
Plugin URI: http://crowdfavorite.com
Description: Used to serve optimized and concatenated JS and CSS files enqueued on a page.
Author: Crowd Favorite
Version: 2.0b11
Author URI: http://crowdfavorite.com
*/

define('CFAO_VERSION', '2.0b11');

define('CFAO_PLUGIN_DIR', dirname(__file__).'/');

load_plugin_textdomain('cf-asset-optimizer');

if (is_admin()) {
	// Not yet implemented admin interface
	include_once CFAO_PLUGIN_DIR . 'admin/admin.php';
}

include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.cache.php';
include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.filecache.php';
//include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.dbcache.php';
include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.wpcache.php';

include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.assetoptimizer.php';
include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.cssoptimizer.php';
include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.jsoptimizer.php';

include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.minifier.php';
include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.cssminifier.php';
include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.jsminifier.php';

class cfao_handler {
	public static $_setting_name = '_cf_asset_optimizer_settings';
	
	public static function initialize() {
		if (is_admin()) {
			cfao_admin::activate();
		}
		$setting = get_option(self::$_setting_name, array());
		if (empty($setting) || empty($setting['plugins'])) {
			// We haven't built settings for this yet. Skip it.
			return;
		}
		$update_setting = false;
		foreach ($setting['plugins'] as $type => $plugins) {
			foreach ($plugins as $class => $active) {
				if ($active) {
					if (is_callable(array($class, 'activate'))) {
						call_user_func(array($class, 'activate'));
					}
					else {
						$setting['plugins'][$type][$class] = false;
						$update_setting = true;
					}
				}
			}
		}
		if ($update_setting) {
			update_option(self::$_setting_name, $setting);
		}
	}
	
	public static function log($message, $debug_only = false) {
		$setting = get_option(self::$_setting_name, array());
		if (!empty($setting['debug']) && $setting['debug'] == 1) {
			$file = null;
			if (!empty($setting['custom_log']) && !empty($setting['logdir'])) {
				$file = trailingslashit($setting['logdir']) . 'cfao-debug.log';
				if (!file_exists($file) && is_writable($setting['logdir'])) {
					touch($file);
				}
				if (is_writable($file)) {
					error_log("[CF Asset Optimizer] $message\n", 3, $file);
				}
				else {
					error_log($message);
				}
			}
			else {
				error_log($message);
			}
		}
		if (!$debug_only) {
			error_log($message);
		}
	}
}
add_action('plugins_loaded', 'cfao_handler::initialize', 1);
