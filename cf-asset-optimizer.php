<?php
/*
Plugin Name: CF Asset Optimizer
Plugin URI: http://crowdfavorite.com
Description: Used to serve optimized and concatenated JS and CSS files enqueued on a page.
Author: Crowd Favorite
Version: 2.0a
Author URI: http://crowdfavorite.com
*/

	
define('CFAO_PLUGIN_DIR', dirname(__file__).'/');

if (is_admin()) {
	// Not yet implemented admin interface
	//include_once CFAO_PLUGIN_DIR . 'admin/admin.php';
}

include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.cache.php';
include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.filecache.php';
//include_once CFAO_PLUGIN_DIR . 'lib/cache-manager/class.dbcache.php';

include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.assetoptimizer.php';
include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.cssoptimizer.php';
include_once CFAO_PLUGIN_DIR . 'lib/optimizer/class.jsoptimizer.php';

include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.minifier.php';
include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.cssminifier.php';
include_once CFAO_PLUGIN_DIR . 'lib/minifier/class.jsminifier.php';

return;
