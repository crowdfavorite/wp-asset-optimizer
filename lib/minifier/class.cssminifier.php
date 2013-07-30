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
			'title' => __('CF CSS Minfier'),
			'description' => __('This plugin minifies the output of the CF CSS Optimizer prior to caching using the PHP Minify library.'),
		);
	}
	
	public static function activate() {
		add_action('cfao_concat_contents', 'cfao_css_minifier::minify', 10, 2);
	}
	
	public static function minify($string, $type = 'css', $handle = '') {
		if ($type == 'css') {
			// Run the minification
			set_include_path(CFAO_PLUGIN_DIR.'lib/minify/min/lib');
			include 'Minify/CSS.php';
			$minified = Minify_CSS::minify($string, array('preserveComments' => false));
			if (!empty($minified)) {
				$string = $minified;
			}
			restore_include_path();
		}
		return $string;
	}
	
}
add_action('cfao_minifiers', 'cfao_css_minifier::register');