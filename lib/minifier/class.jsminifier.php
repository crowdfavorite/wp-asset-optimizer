<?php
/**
 * Minifier interface for CF Asset Optimizer JavaScript
 */

class cfao_js_minifier {
	
	public static function class_name() {
		return 'cfao_js_minifier';
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
			'title' => __('CF JavaScript Minfier'),
			'description' => __('This plugin minifies the output of the CF JavaScript Optimizer prior to caching using the PHP Minify library.'),
		);
	}
	
	public static function activate() {
		// We want to minify based on single hook contents here
		if (!is_admin()) {
			add_action('cfao_single_contents', 'cfao_js_minifier::minify', 10, 4);
		}
		else {
			add_action('cfao_js_list_bulk_actions', 'cfao_js_minifier::_updateOptimizerBulkActions');
			add_action('cfao_js_list_row_actions', 'cfao_js_minifier::_updateOptimizerRowActions', 10, 2);
			add_action('cfao_admin_js_minify', 'cfao_js_minifier::_setMinify', 10, 2);
			add_action('cfao_admin_js_preserve', 'cfao_js_minifier::_setPreserve', 10, 2);
		}
	}
	
	public static function minify($string, $type = 'js', $handle = '', $settings) {
		if ($type == 'js') {
			// Run the minification
			set_include_path(CFAO_PLUGIN_DIR.'lib/minify/min/lib');
			if (!class_exists('JSMin')) {
				include 'JSMin.php';
			}
			if (!isset($settings[$handle]['minify']) || $settings[$handle]['minify'] == true) {
				$minified = JSMin::minify($string);
				if (!empty($minified)) {
					$string = $minified;
				}
			}	
			restore_include_path();
		}
		return $string;
	}
	
	public static function _setMinify($setting, $handles) {
		if (empty($handles) || empty($setting)) {
			return;
		}
		foreach ($handles as $handle) {
			if (isset($setting[$handle])) {
				$setting[$handle]['minify'] = true;
			}
		}
		return $setting;
	}
	
	public static function _setPreserve($setting, $handles) {
		if (empty($handles) || empty($setting)) {
			return;
		}
		foreach ($handles as $handle) {
			if (isset($setting[$handle])) {
				$setting[$handle]['minify'] = false;
			}
		}
		return $setting;
	}

	public static function _updateOptimizerBulkActions($actions) {
		return array_merge($actions, array(
			'minify' => __('Minify'),
			'preserve' => __('Preserve'),
		));
	}

	public static function _updateOptimizerRowActions($actions, $item) {
		$additional_actions = array();
		if (!isset($item['minify']) || $item['minify'] == false) {
			$additional_actions['minify'] = '<a href="'.esc_url(add_query_arg(array('cfao_action' => 'minify', 'js' => array($item['handle'])))).'">'.esc_html(__('Minify')).'</a>';
		}
		else {
			$additional_actions['preserve'] = '<a href="'.esc_url(add_query_arg(array('cfao_action' => 'preserve', 'js' => array($item['handle'])))).'">'.esc_html(__('Preserve')).'</a>';
		}
		return array_merge($actions, $additional_actions);
	}
	
}
add_action('cfao_minifiers', 'cfao_js_minifier::register');