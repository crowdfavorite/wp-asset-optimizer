<?php
/**
 * Minifier interface for CF Asset Optimizer stylesheets
 */

class cfao_css_minifier extends cfao_minifier {
	
	public static function setHooks() {
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
cfao_css_minifier::setHooks();