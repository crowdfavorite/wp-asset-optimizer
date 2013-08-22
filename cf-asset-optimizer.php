<?php
/*
Plugin Name: CF Asset Optimizer
Plugin URI: http://crowdfavorite.com
Description: Used to serve optimized and concatenated JS and CSS files enqueued on a page.
Author: Crowd Favorite
Version: 2.0a
Author URI: http://crowdfavorite.com
*/

define('CFAO_VERSION', '2.0a');

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

class _cfao_handler {
	public static $_setting_name = '_cf_asset_optimizer_settings';
	
	public static function initialize() {
		$setting = get_option(self::$_setting_name, array());
		if (empty($setting) || empty($setting['plugins'])) {
			// We haven't built settings for this yet. Skip it.
			return;
		}
		$update_setting = false;
		foreach ($setting['plugins'] as $type => $plugins) {
			foreach ($plugins as $class => $active) {
				if ($active) {
					if (is_callable("$class::activate")) {
						$class::activate();
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
		if (is_admin()) {
			cfao_admin::activate();
		}
	}
}
add_action('plugins_loaded', '_cfao_handler::initialize', 1);
