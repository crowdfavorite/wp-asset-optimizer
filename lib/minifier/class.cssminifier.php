<?php
/**
 * Minifier interface for CF Asset Optimizer stylesheets
 */

class cfao_css_minifier extends cfao_minifier {
	
	public static function class_name() {
		return 'cfao_css_minifier';
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
			'title' => __('CF CSS Minfier', 'cf-asset-optimizer'),
			'description' => __('This plugin minifies the output of the CF CSS Optimizer prior to caching using the PHP Minify library.', 'cf-asset-optimizer'),
		);
	}
	
	public static function activate() {
		if (!is_admin()) {
			add_action('cfao_concat_contents', 'cfao_css_minifier::minify', 10, 4);
		}
		else {
			add_action('cfao_css_list_bulk_actions', 'cfao_css_minifier::_updateOptimizerBulkActions');
			add_action('cfao_css_list_row_actions', 'cfao_css_minifier::_updateOptimizerRowActions', 10, 4);
			add_action('cfao_admin_css_minify', 'cfao_css_minifier::_setMinify', 10, 2);
			add_action('cfao_admin_css_preserve', 'cfao_css_minifier::_setPreserve', 10, 2);
		}
	}
	
	public static function minify($string, $type = 'css', $handle = '', $settings) {
		if ($type == 'css') {
			// Run the minification
			set_include_path(CFAO_PLUGIN_DIR.'lib/minify/min/lib');
			if (!class_exists('Minify_CSS')) {
				include 'Minify/CSS.php';
			}
			if (!isset($settings[$handle]['minify']) || $settings[$handle]['minify'] == true) {
				$minified = Minify_CSS::minify($string, array('preserveComments' => false));
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
			'minify' => _x('Minify', 'css' 'cf-asset-optimizer'),
			'preserve' => _x('Preserve', 'css', 'cf-asset-optimizer'),
		));
	}
	
	public static function _updateOptimizerRowActions($actions, $item, $nonce_field, $nonce_val) {
		$additional_actions = array();
		if (!isset($item['minify']) || $item['minify'] == true) {
			$additional_actions['preserve'] = '<a href="'.esc_url(add_query_arg(array('cfao_action' => 'preserve', 'css' => array($item['handle']), $nonce_field => $nonce_val))).'">'.esc_html_x('Preserve', 'css', 'cf-asset-optimizer').'</a>';
		}
		else {
			$additional_actions['minify'] = '<a href="'.esc_url(add_query_arg(array('cfao_action' => 'minify', 'css' => array($item['handle']), $nonce_field => $nonce_val))).'">'.esc_html_x('Minify', 'css', 'cf-asset-optimizer').'</a>';
		}
		return array_merge($actions, $additional_actions);
	}
	
}
add_action('cfao_minifiers', 'cfao_css_minifier::register');