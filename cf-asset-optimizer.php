<?php
/*
Plugin Name: CF Asset Optimizer
Plugin URI: http://crowdfavorite.com
Description: Used to serve optimized and concatenated JS and CSS files enqueued on a page.
Author: Crowd Favorite
Version: 1.1.6
Author URI: http://crowdfavorite.com
*/

if (is_admin()) {
	include_once dirname(__file__).'/admin.php';
}

class CFAssetOptimizerScripts {
	protected static $_cfao_CACHE_DIR;
	protected static $_cfao_CACHE_URL;
	protected static $_LOCKFILE = '';

	public static function getCacheDir() {
		if (empty(self::$_cfao_CACHE_DIR)) {
			$cache_dir = WP_CONTENT_DIR . '/cfao-cache/' . $_SERVER['HTTP_HOST'] . '/js/';
			self::$_cfao_CACHE_DIR = trailingslashit(apply_filters('cfao_script_cache_dir', $cache_dir));
		}
		return self::$_cfao_CACHE_DIR;
	}

	public static function getCacheUrl() {
		if (empty(self::$_cfao_CACHE_URL)) {
			$cache_url = WP_CONTENT_URL . '/cfao-cache/' . $_SERVER['HTTP_HOST'] . '/js/';
			self::$_cfao_CACHE_URL = trailingslashit(apply_filters('cfao_script_cache_url', $cache_url));
		}
		return self::$_cfao_CACHE_URL;
	}

	public static function getLockFile() {
		if (empty(self::$_LOCKFILE)) {
			$lockfile = '.cfaojslock';
			self::$_LOCKFILE = apply_filters('cfao_script_lockfile', $lockfile);
		}
		return self::$_LOCKFILE;
	}

	public static function onWPPrintScripts() {
		global $wp_scripts;
		if (!is_object($wp_scripts)) {
			return;
		}
		$wp_scripts->all_deps($wp_scripts->queue);
		$included_scripts = array();
		$url = self::getConcatenatedScriptUrl($wp_scripts, $included_scripts, $unknown_scripts, $version);
		if ($url) {
			// We have a concatenated file matching this. Output each script's localizations,
			// dequeue the script, then enqueue our concatenated file.
			foreach ($wp_scripts->to_do as $handle) {
				if (in_array($handle, $included_scripts)) {
					// We need to output the localization and deregister this script.
					if (!empty($wp_scripts->registered[$handle]->extra['data'])) {
					?>
<script type="text/javascript">
<?php echo $wp_scripts->registered[$handle]->extra['data']; ?>
</script>
					<?php
					}
					wp_dequeue_script($handle);
				}
				else {
					// Double-check what I depend on and update it as needed to the new script.
					$my_deps = $wp_scripts->registered[$handle]->deps;
					$new_deps = array_diff($my_deps, $included_scripts);
					if (count($my_deps) > count($new_deps)) {
						// We need to add the concatenated script as a dependency
						$new_deps[] = 'cfao-script';
					}
					$wp_scripts->registered[$handle]->deps = $new_deps;
				}
			}
			wp_enqueue_script('cfao-script', $url, array(), $version);
			$wp_scripts->to_do = array();
		}
		else if (!get_option('cfao_using_cache') && (!empty($included_scripts) || !empty($unknown_scripts)) ) {
			if (file_exists(trailingslashit(self::getCacheDir()).self::getLockFile())) {
				// Currently building. Don't overload the system.
				return;
			}
			// We don't have the file built yet. Fire off an asynchronous request to build it
			// and serve the scripts normally.
			$build_args = array(
				'wp_scripts_obj' => json_encode($wp_scripts),
				'key' => get_option('cfao_security_key'),
			);
			wp_remote_post(
				admin_url('admin-ajax.php?action=concat-build-js'),
				array(
					'body' => $build_args,
					'timeout' => 1,
					'redirection' => 0,
				)
			);
		}
	}

	public static function buildConcatenatedScriptFile() {

		$compile_setting = get_option('cfao_compile_setting');

		if (empty($compile_setting) || $compile_setting == 'off') {
			return;
		}

		$compile_all = $compile_setting == 'on';

		$directory = trailingslashit(self::getCacheDir());
		$security_key = get_option('cfao_security_key');
		if ($security_key != $_POST['key']) {
			exit();
		}
		if (!(file_exists($directory) && is_dir($directory))) {
			// We need to attempt to make the directory.
			if (!mkdir($directory, 0775, true)) {
				error_log('Could not create directory: ' . $directory);
				exit();
			}
		}
		$lockfile = self::getLockFile();
		if (file_exists($directory.$lockfile)) {
			// We're currently running a build. Throttle it to avoid DDOS Attacks.
			exit();
		}
		if (empty($_POST['wp_scripts_obj'])) {
			exit('No scripts object received');
		}
		$scripts_obj = json_decode(stripcslashes($_POST['wp_scripts_obj']));
		if (!$scripts_obj) {
			$scripts_obj = json_decode($_POST['wp_scripts_obj']);
		}
		if (empty($scripts_obj) || empty($scripts_obj->to_do) || empty($scripts_obj->registered)) {
			exit('Issue: ' . print_r($scripts_obj, true));
		}

		$lock = @fopen($directory.$lockfile, 'x');
		if (!$lock) {
			error_log('Could not create lockfile: ' . $directory.$lockfile);
			exit();
		}
		fwrite($lock, time());
		fclose($lock);

		$site_scripts = get_option('cfao_scripts', array());

		if (!is_array($site_scripts)) {
			$site_scripts = array();
		}

		$included_scripts = array();

		$script_file_header =
			"/**\n" .
			" * Included Files\n" .
			" *\n";
		$script_file_src = '';
		$script_blocks = array();
		$current_block = 0;
		$my_domain = strtolower(untrailingslashit(preg_replace('#^http(s)?:#', '', site_url())));
		foreach ($scripts_obj->to_do as $handle) {
			$compare_src = $scripts_obj->registered->$handle->src;
			$no_protocol = preg_replace('#^http(s)?:#', '', $compare_src);
			if (strpos($no_protocol, $my_domain) === 0) {
				// This is a local script. Use the $no_protocol version for enqueuing and management.
				$compare_src = $no_protocol;
			}
			if (empty($site_scripts[$handle])) {
				// We need to register this script in our list
				$site_scripts[$handle] = array(
					'src' => $compare_src,
					'ver' => $scripts_obj->registered->$handle->ver,
					'enabled' => $compile_all,
					'disable_reason' => 'Disabled by default.'
				);
			}
			if ($compile_all || $site_scripts[$handle]['enabled']) {
				if (
					   strtolower($compare_src) != strtolower($site_scripts[$handle]['src'])
					|| $scripts_obj->registered->$handle->ver != $site_scripts[$handle]['ver']
				) {
					// This may not be the same script. Update site_scripts array and disable.
					$site_scripts[$handle] = array(
						'src' => $compare_src,
						'ver' => $scripts_obj->registered->$handle->ver,
						'enabled' => $compile_all,
						'disable_reason' => 'Script changed, automatically disabled.'
					);
				}
				else {
					// This script is enabled and has not changed from the last approved version.
					// Request the file
					$request_url = $site_scripts[$handle]['src'];
					if (strpos($request_url, '//') === 0) {
						$request_url = 'http:'.$request_url;
					}
					if ( !preg_match('|^https?://|', $request_url) && ! ( $scripts_obj->content_url && 0 === strpos($request_url, $scripts_obj->content_url) ) ) {
						$request_url = $scripts_obj->base_url . $request_url;
					}

					if (!empty($site_scripts[$handle]['ver'])) {
						if (strstr($request_url, '?')) {
							$request_url .= '&';
						}
						else {
							$request_url .= '?';
						}
						$request_url .= urlencode($site_scripts[$handle]['ver']);
					}

					$script_request = wp_remote_get(
						$request_url
					);

					// Handle the response
					if (is_wp_error($script_request)) {
						$site_scripts[$handle]['enabled'] = false;
						$site_scripts[$handle]['disable_reason'] = 'WP Error: ' . $script_request->get_error_message();
					}
					else {
						if ($script_request['response']['code'] < 200 || $script_request['response']['code'] >= 400) {
							// There was an error requesting the file
							$site_scripts[$handle]['enabled'] = false;
							$site_scripts[$handle]['disable_reason'] = 'HTTP Error ' . $script_request['response']['code'] . ' - ' . $script_request['response']['message'];
						}
						else {
							// We had a valid script to add to the list.

							// Check to see if it can be concatenated.
							if (empty($site_scripts[$handle]['minify_script'])) {
								// This cannot be minified. It will need to be kept as is.
								if (!empty($script_blocks[$current_block])) {
									// We need to increment the script block first.
									++$current_block;
								}
								$script_blocks[$current_block] = array(
									'minify' => false,
									'src' => $script_request['body'].";\n",
								);
							}
							else {
								// This can be minified. Allow it to be sent in a bundled minify request.
								if (
									   !empty($script_blocks[$current_block])
									&& !($script_blocks[$current_block]['minify'])
								) {
									// Increment current block first.
									++$current_block;
								}
								if (empty($script_blocks[$current_block])) {
									$script_blocks[$current_block] = array(
										'minify' => true,
										'src' => ''
									);
								}
								$script_blocks[$current_block]['src'] .= $script_request['body'].";\n";
							}
							$included_scripts[$handle] = $handle;
							$script_file_header .= ' * ' . $handle . ' as ' . $request_url . "\n";
						}
					}
				}
			}
		}
		$script_file_header .= " **/\n";

		update_option('cfao_scripts', $site_scripts);

		if (!empty($included_scripts)) {
			// We have a file to write
			$filename = self::_getConcatenatedScriptsFilename($included_scripts);
			$file = @fopen($directory.$filename, 'w');
			if (!$file === false) {
				// We have a valid file pointer.

				// Minify the file as dictated by user settings with Google Closure Compiler
				$minify_level = get_option('cfao_minify_js_level', '');
				$compiler_levels = array(
					'whitespace' => 'WHITESPACE_ONLY',
					'simple' => 'SIMPLE_OPTIMIZATIONS',
					'advanced' => 'ADVANCED_OPTIMIZATIONS'
				);
				if (isset($compiler_levels[$minify_level])) {
					foreach ($script_blocks as $block) {
						$src = $block['src'];
						if ($block['minify']) {
							$args = array(
								'js_code' => mb_convert_encoding($src, 'UTF-8'),
								'compilation_level' => $compiler_levels[$minify_level],
								'output_format' => 'text',
								'output_info' => 'compiled_code'
							);
							$response = wp_remote_post(
								'http://closure-compiler.appspot.com/compile',
								array(
									'body' => $args,
									'timeout' => 60
								)
							);
							if (
								   !empty($response)
								&& !is_wp_error($response)
								&& !empty($response['headers'])
								&& !empty($response['response'])
								&& $response['response']['code'] == 200
								&& !empty($response['body'])
								&& !(preg_match('/^Error\([\d]+\)/', $response['body']))
							) {
								$src = $response['body'];
							}
						}
						$script_file_src .= $src;
					}
				}
				else {
					foreach ($script_blocks as $block) {
						$script_file_src .= $block['src'];
					}
				}
				fwrite($file, $script_file_header.$script_file_src);
				fclose($file);
			}
			else {
				error_log('Could not create file: ' . $directory.$filename);
			}
		}
		// Remove the lockfile.
		unlink($directory.$lockfile);
		exit();
	}

	private static function _getConcatenatedScriptsFilename($included_scripts) {
		return md5(implode(',', $included_scripts)) . '.js';
	}

	public static function getConcatenatedScriptUrl($wp_scripts, &$included_scripts, &$unknown_scripts, &$version) {

		$compile_setting = get_option('cfao_compile_setting');

		if (empty($compile_setting) || $compile_setting == 'off') {
			return;
		}

		$compile_all = $compile_setting == 'on';

		$directory = trailingslashit(self::getCacheDir());
		$dir_url = trailingslashit(esc_url(self::getCacheUrl()));

		$site_scripts = get_option('cfao_scripts', array());

		if (!is_array($site_scripts)) {
			$site_scripts = array();
		}

		$included_scripts = array();
		$unknown_scripts = array();
		$registered = $wp_scripts->registered;
		$my_domain = strtolower(untrailingslashit(preg_replace('#^http(s)?:#', '', site_url())));
		$update_scripts = false;
		foreach ($wp_scripts->to_do as $handle) {
			$compare_src = $registered[$handle]->src;
			$no_protocol = preg_replace('#^http(s)?:#', '', $compare_src);
			if (strpos($no_protocol, $my_domain) === 0) {
				// This is a local script. Use the $no_protocol version for enqueuing and management.
				$compare_src = $no_protocol;
			}

			if (empty($site_scripts[$handle])) {
				if ($compile_all) {
					// Script needs to be added.
					$site_scripts[$handle] = array(
						'src' => $compare_src,
						'ver' => $registered[$handle]->ver,
						'enabled' => true,
						'disable_reason' => 'Compile all',
					);
					$update_scripts = true;
				}
				else {
					// Note that we have an unknown script, and thus should actually still make the back-end request.
					$unknown_scripts[] = $registered[$handle];
					continue;
				}
			}
			else if (!$compile_all && !($site_scripts[$handle]['enabled'])) {
				// We shouldn't include this script, it's not enabled or recognized.
				continue;
			}
			else if (
				   strtolower($site_scripts[$handle]['src']) != strtolower($compare_src)
				|| $site_scripts[$handle]['ver'] != $registered[$handle]->ver
			) {
				// Script needs to be updated and disabled.
				$site_scripts[$handle] = array(
					'src' => $compare_src,
					'ver' => $registered[$handle]->ver,
					'enabled' => $compile_all,
					'disable_reason' => 'Script changed, automatically disabled',
				);
				$update_scripts = true;
				continue;
			}
			else {
				$can_include = true;
				foreach ($wp_scripts->registered[$handle]->deps as $dep) {
					// Ensure that it is not dependent on any disabled scripts
					if (empty($site_scripts[$dep]) || !$site_scripts[$dep]['enabled']) {
						// We've hit a disabled parent script.
						$can_include = false;
						$site_scripts[$handle]['enabled'] = false;
						$site_scripts[$handle]['disable_reason'] = 'Dependent on disabled script: ' . $dep;
						$update_scripts = true;
						break;
					}
				}
				
				if ($compile_all || $can_include) {
					$included_scripts[$handle] = $handle;
				}
			}
		}

		if ($update_scripts) {
			update_option('cfao_scripts', $site_scripts);
		}

		if (empty($included_scripts) && empty($unknown_scripts)) {
			// All scripts are disabled on this site. Go no further.
			return;
		}

		$filename = self::_getConcatenatedScriptsFilename($included_scripts);

		if (file_exists($directory.$filename)) {
			$version = filemtime($directory.$filename);
			$url = apply_filters('cfao_script_file_url', $dir_url.$filename, $directory.$filename, $filename);
			return esc_url($url);
		}
		else if (get_option('cfao_using_cache', false)) {
			if (file_exists($directory.self::getLockFile())) {
				// We're currently building. Don't overload the system.
				$included_scripts = $unknown_scripts = array();
				return false;
			}
			// We're in a cached environment, so run a synchronous request to build the concatenated
			// file so that it gets cached properly without needing to do multiple invalidations.
			$build_args = array(
				'wp_scripts_obj' => json_encode($wp_scripts),
				'key' => get_option('cfao_security_key'),
				'referer' => $_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI']
			);
			$response = wp_remote_post(
				admin_url('admin-ajax.php?action=concat-build-js'),
				array(
					'body' => $build_args,
					'redirection' => 0,
				)
			);
			if (file_exists($directory.$filename)) {
				$version = filemtime($directory.$filename);
				$url = apply_filters('cfao_script_file_url', $dir_url.$filename, $directory.$filename, $filename);
				return esc_url($url);
			}
		}
		return false;
	}
}
add_action('wp_ajax_concat-build-js', 'CFAssetOptimizerScripts::buildConcatenatedScriptFile');
add_action('wp_ajax_nopriv_concat-build-js', 'CFAssetOptimizerScripts::buildConcatenatedScriptFile');
if (!is_admin()) {
	add_action('wp_print_scripts', 'CFAssetOptimizerScripts::onWPPrintScripts', 100);
}

class CFAssetOptimizerStyles {
	protected static $_cfao_CACHE_DIR;
	protected static $_cfao_CACHE_URL;
	protected static $_LOCKFILE = '';

	public static function getCacheDir() {
		if (empty(self::$_cfao_CACHE_DIR)) {
			$cache_dir = WP_CONTENT_DIR . '/cfao-cache/' . $_SERVER['HTTP_HOST'] . '/css/';
			self::$_cfao_CACHE_DIR = trailingslashit(apply_filters('cfao_style_cache_dir', $cache_dir));
		}
		return self::$_cfao_CACHE_DIR;
	}

	public static function getCacheUrl() {
		if (empty(self::$_cfao_CACHE_URL)) {
			$cache_url = WP_CONTENT_URL . '/cfao-cache/' . $_SERVER['HTTP_HOST'] . '/css/';
			self::$_cfao_CACHE_URL = trailingslashit(apply_filters('cfao_style_cache_url', $cache_url));
		}
		return self::$_cfao_CACHE_URL;
	}

	public static function getLockFile() {
		if (empty(self::$_LOCKFILE)) {
			$lockfile = '.cfaocsslock';
			self::$_LOCKFILE = apply_filters('cfao_css_lockfile', $lockfile);
		}
		return self::$_LOCKFILE;
	}

	public static function onWPPrintStyles() {
		global $wp_styles;
		if (!is_object($wp_styles)) {
			return;
		}
		$wp_styles->all_deps($wp_styles->queue);
		$included_styles = array();
		$url = self::getConcatenatedStyleUrl($wp_styles, $included_styles, $unknown_styles, $version);
		if ($url) {
			// We have a concatenated file matching this. Output each style's localizations,
			// dequeue the style, then enqueue our concatenated file.
			foreach ($wp_styles->to_do as $handle) {
				if (in_array($handle, $included_styles)) {
					// We need to output the localization and deregister this style.
					wp_dequeue_style($handle);
				}
				else {
					// Double-check what I depend on and update it as needed to the new style.
					$my_deps = $wp_styles->registered[$handle]->deps;
					$new_deps = array_diff($my_deps, $included_styles);
					if (count($my_deps) > count($new_deps)) {
						// We need to add the concatenated style as a dependency
						$new_deps[] = 'cfao-style';
					}
					$wp_styles->registered[$handle]->deps = $new_deps;
				}
			}
			$my_deps = array();
			foreach ($included_styles as $handle) {
				$inc_deps = $wp_styles->registered[$handle]->deps;
				$new_deps = array_diff($inc_deps, $included_styles, $my_deps);
				foreach ($new_deps as $dep) {
					$my_deps[] = $dep;
				}
			}
			wp_enqueue_style('cfao-style', $url, $my_deps, $version);
			$wp_styles->to_do = array();
		}
		else if (!get_option('cfao_using_cache') && (!empty($included_styles) || !empty($unknown_styles)) ) {
			if (file_exists(trailingslashit(self::getCacheDir()).self::getLockFile())) {
				// Currently building. Don't overload the system.
				return;
			}
			// We don't have the file built yet. Fire off an asynchronous request to build it
			// and serve the styles normally.
			$build_args = array(
				'wp_styles_obj' => json_encode($wp_styles),
				'key' => get_option('cfao_security_key')
			);
			$response = wp_remote_post(
				admin_url('admin-ajax.php?action=concat-build-css'),
				array(
					'body' => $build_args,
					'timeout' => 1,
					'redirection' => 0,
				)
			);
		}
	}

	public static function buildConcatenatedStyleFile() {

		$compile_setting = get_option('cfao_compile_setting');

		if (empty($compile_setting) || $compile_setting == 'off') {
			return;
		}

		$compile_all = $compile_setting == 'on';

		$directory = trailingslashit(self::getCacheDir());
		$security_key = get_option('cfao_security_key');
		if ($security_key != $_POST['key']) {
			exit();
		}
		if (!(file_exists($directory) && is_dir($directory))) {
			// We need to attempt to make the directory.
			if (!mkdir($directory, 0775, true)) {
				error_log('Could not create directory: ' . $directory);
				exit();
			}
		}
		$lockfile = self::getLockFile();
		if (file_exists($directory.$lockfile)) {
			// We're currently running a build. Throttle it to avoid DDOS Attacks.
			exit();
		}
		if (empty($_POST['wp_styles_obj'])) {
			error_log('No styles object received');
			exit();
		}
		$styles_obj = json_decode(stripcslashes($_POST['wp_styles_obj']));
		if (!$styles_obj) {
			$styles_obj = json_decode($_POST['wp_styles_obj']);
		}
		if (empty($styles_obj) || empty($styles_obj->to_do) || empty($styles_obj->registered)) {
			error_log('Issue: ' . print_r($styles_obj, true));
			exit();
		}

		$lock = @fopen($directory.$lockfile, 'x');
		if (!$lock) {
			error_log('Could not create lockfile: ' . $directory.$lockfile);
			exit();
		}
		fwrite($lock, time());
		fclose($lock);

		$site_styles = get_option('cfao_styles', array());

		if (!is_array($site_styles)) {
			$site_styles = array();
		}

		$included_styles = array();

		$style_file_header =
			"/**\n" .
			" * Included Files\n" .
			" *\n";
		$style_file_src = '';
		$my_domain = strtolower(untrailingslashit(preg_replace('#^http(s)?:#', '', site_url())));
		foreach ($styles_obj->to_do as $handle) {
			$compare_src = $styles_obj->registered->$handle->src;
			$no_protocol = preg_replace('#^http(s)?:#', '', $compare_src);
			if (strpos($no_protocol, $my_domain) === 0) {
				// This is a local script. Use the $no_protocol version for enqueuing and management.
				$compare_src = $no_protocol;
			}
			if (empty($site_styles[$handle])) {
				// We need to register this style in our list
				$site_styles[$handle] = array(
					'src' => $compare_src,
					'ver' => $styles_obj->registered->$handle->ver,
					'enabled' => $compile_all,
					'disable_reason' => 'Disabled by default.'
				);
			}
			else if (
				   !empty($styles_obj->registered->$handle->extra)
				|| ( !empty($styles_obj->args) && $styles_obj->args != 'all')
			) {
				// Don't include conditional stylesheets, they need additional markup.
				$site_styles[$handle] = array(
					'src' => $styles_obj->registered->$handle->src,
					'ver' => $styles_obj->registered->$handle->ver,
					'enabled' => $compile_all,
					'disable_reason' => 'Conditional stylesheet. Requires conditional markup.'
				);
			}
			
			if ($compile_all || $site_styles[$handle]['enabled']) {
				if (
					   strtolower($compare_src) != strtolower($site_styles[$handle]['src'])
					|| $styles_obj->registered->$handle->ver != $site_styles[$handle]['ver']
				) {
					// This may not be the same style. Update site_styles array and disable.
					$site_styles[$handle] = array(
						'src' => $compare_src,
						'ver' => $styles_obj->registered->$handle->ver,
						'enabled' => false,
						'disable_reason' => 'Style changed, automatically disabled.'
					);
				}
				else {
					// This style is enabled and has not changed from the last approved version.
					// Request the file
					$request_url = $site_styles[$handle]['src'];
					if (strpos($request_url, '//') === 0) {
						$request_url = 'http:'.$request_url;
					}
					if (!preg_match('|^https?://|', $request_url) && ! ( $styles_obj->content_url && 0 === strpos($request_url, $styles_obj->content_url) ) ) {
						$request_url = $styles_obj->base_url . $request_url;
					}
					if (!empty($site_styles[$handle]['ver'])) {
						if (strstr($request_url, '?')) {
							$request_url .= '&';
						}
						else {
							$request_url .= '?';
						}
						$request_url .= urlencode($site_styles[$handle]['ver']);
					}
					$style_request = wp_remote_get(
						$request_url
					);

					// Handle the response
					if (is_wp_error($style_request)) {
						$site_styles[$handle]['enabled'] = false;
						$site_styles[$handle]['disable_reason'] = 'WP Error: ' . $style_request->get_error_message();
					}
					else {
						if ($style_request['response']['code'] < 200 || $style_request['response']['code'] >= 400) {
							// There was an error requesting the file
							$site_styles[$handle]['enabled'] = false;
							$site_styles[$handle]['disable_reason'] = 'HTTP Error ' . $style_request['response']['code'] . ' - ' . $style_request['response']['message'];
						}
						else {
							// We had a valid style to add to the list.
							$included_styles[$handle] = $handle;
							$style_file_header .= ' * ' . $handle . ' as ' . $request_url . "\n";
							$src = $style_request['body'] . "\n";

							// Convert relative URLs to absolute URLs.
								// Get URL parts for this script.
							$parts = array();
							preg_match('#(https?://[^/]*)([^?]*/)([^?]*)(\?.*)?#', $request_url, $parts);
							$parts[1] = apply_filters('cfao_styles_relative_domain', $parts[1]);

								// Update paths that are based on web root.
							if (count($parts) > 1) {
								$src = preg_replace('#url\s*\(\s*(["\']?)\s*(/[^[:space:]|data:].+?)\s*\1\s*\)#x',
									'url('.$parts[1].'$2)', $src
								);
							}
								// Update paths based on script location
							if (count($parts) > 2) {
								$src = preg_replace('#url\s*\(\s*(["\']?)\s*(?!(?://|https?://))(/?[^[:space:]|data:].+?)\s*\1\s*\)#x',
									'url('.$parts[1].$parts[2].'$2)', $src
								);
							}

							$style_file_src .= $src . "\n";
						}
					}
				}
			}
		}
		$style_file_header .= " **/\n";

		update_option('cfao_styles', $site_styles);

		if (!empty($included_styles)) {
			// We have a file to write
			$filename = self::_getConcatenatedStylesFilename($included_styles);
			$file = @fopen($directory.$filename, 'w');
			if (!$file === false) {
				// We have a valid file pointer.

				// Minify the contents using Minify library
				set_include_path(dirname(__file__).'/minify/min/lib');
				include 'Minify/CSS.php';
				$style_file_src = Minify_CSS::minify($style_file_src, array('preserveComments' => false));
				restore_include_path();

				// Write the file and close it.
				fwrite($file, $style_file_header.$style_file_src);
				fclose($file);
			}
			else {
				error_log('Could not create file: ' . $directory.$filename);
			}
		}
		unlink($directory.$lockfile);
		exit();
	}

	private static function _getConcatenatedStylesFilename($included_styles) {
		return md5(implode(',', $included_styles)) . '.css';
	}

	public static function getConcatenatedStyleUrl($wp_styles, &$included_styles, &$unknown_styles, &$version) {

		$compile_setting = get_option('cfao_compile_setting');

		if (empty($compile_setting) || $compile_setting == 'off') {
			return;
		}

		$compile_all = $compile_setting == 'on';

		$directory = self::getCacheDir();;
		$dir_url = esc_url(self::getCacheUrl());

		$site_styles = get_option('cfao_styles', array());

		if (!is_array($site_styles)) {
			$site_styles = array();
		}

		$included_styles = array();
		$unknown_styles = array();
		$registered = $wp_styles->registered;
		$my_domain = strtolower(untrailingslashit(preg_replace('#^http(s)?:#', '', site_url())));
		foreach ($wp_styles->to_do as $handle) {
			$compare_src = $registered[$handle]->src;
			$no_protocol = preg_replace('#^http(s)?:#', '', $compare_src);
			if (strpos($no_protocol, $my_domain) === 0) {
				// This is a local script. Use the $no_protocol version for enqueuing and management.
				$compare_src = $no_protocol;
			}
			if ( empty($site_styles[$handle]) ) {
				if ($compile_all) {
					// Script needs to be added.
					$site_styles[$handle] = array(
						'src' => $compare_src,
						'ver' => $registered[$handle]->ver,
						'enabled' => true,
						'disable_reason' => 'Compile all',
					);
					$update_scripts = true;
				}
				else {
					// Note that we have an unknown script, and thus should actually still make the back-end request.
					$unknown_styles[] = $registered[$handle];
					continue;
				}
			}
			else if (!$compile_all && !($site_styles[$handle]['enabled'])) {
				// We shouldn't include this style, it's not enabled.
				continue;
			}
			else if (
				   strtolower($site_styles[$handle]['src']) != strtolower($compare_src)
				|| $site_styles[$handle]['ver'] != $registered[$handle]->ver
			) {
				// Script needs to be updated and disabled.
				$site_styles[$handle] = array(
					'src' => $compare_src,
					'ver' => $registered[$handle]->ver,
					'enabled' => $compile_all,
					'disable_reason' => 'Script changed, automatically disabled',
				);
				$update_scripts = true;
				continue;
			}
			else {
				$can_include = true;
				foreach ($wp_styles->registered[$handle]->deps as $dep) {
					// Ensure that it is not dependent on any disabled styles
					if (empty($site_styles[$dep]) || !$site_styles[$dep]['enabled']) {
						// We've hit a disabled parent style.
						$can_include = false;
						$site_styles[$handle]['enabled'] = $compile_all;
						$site_styles[$handle]['disable_reason'] = 'Dependent on disabled style: ' . $dep;
						update_option('cfao_styles', $site_styles);
						break;
					}
				}
				if ($compile_all || $can_include) {
					$included_styles[$handle] = $handle;
				}
			}
		}

		if ($update_scripts) {
			update_option('cfao_styles', $site_styles);
		}

		if (empty($included_styles) && empty($unknown_styles)) {
			return false;
		}

		$filename = self::_getConcatenatedStylesFilename($included_styles);

		if (file_exists($directory.$filename)) {
			$version = filemtime($directory.$filename);
			$url = apply_filters('cfao_style_file_url', $dir_url.$filename, $directory.$filename, $filename);
			return esc_url($url);
		}
		else if (get_option('cfao_using_cache', false)) {
			if (file_exists($directory.self::getLockFile())) {
				$included_styles = $unknown_styles = array();
				return false;
			}
			// We're in a cached environment, so run a synchronous request to build the concatenated
			// file so that it gets cached properly without needing to do multiple invalidations.
			$build_args = array(
				'wp_styles_obj' => json_encode($wp_styles),
				'key' => get_option('cfao_security_key')
			);
			$response = wp_remote_post(
				admin_url('admin-ajax.php?action=concat-build-css'),
				array(
					'body' => $build_args,
					'redirection' => 0,
				)
			);
			if (file_exists($directory.$filename)) {
				$version = filemtime($directory.$filename);
				$url = apply_filters('cfao_style_file_url', $dir_url.$filename, $directory.$filename, $filename);
				return esc_url($url);
			}
		}
		return false;
	}
}
add_action('wp_ajax_concat-build-css', 'CFAssetOptimizerStyles::buildConcatenatedStyleFile');
add_action('wp_ajax_nopriv_concat-build-css', 'CFAssetOptimizerStyles::buildConcatenatedStyleFile');
if (!is_admin()) {
	add_action('wp_print_styles', 'CFAssetOptimizerStyles::onWPPrintStyles', 100);
}


?>
