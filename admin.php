<?php

class CFAssetOptimizerAdmin {

	public static function adminInit() {
		$tab = !empty($_GET['tab']) ? $_GET['tab'] : 'general';

		wp_enqueue_style('cfao-admin-css',
			admin_url('admin-ajax.php?action=cfao-admin-css'),
			array(),
			time()
		);

		wp_enqueue_script('jquery.tablesorter',
			plugins_url( basename( dirname( __FILE__ ) ) ) . '/js/plugins/jquery.tablesorter.min.js',
			array(),
			time()
		);

		$script_url = add_query_arg('tab', $tab, admin_url('admin-ajax.php?action=cfao-admin-js'));

		wp_enqueue_script('cfao-admin-js',
			$script_url,
			array(),
			time()
		);
	}

	public static function adminCSS() {
		header('Content-type: text/css');
		echo file_get_contents(dirname(__file__) . '/css/style.css');
		exit();
	}

	public static function adminJS() {
		header('Content-type: application/javascript');
		echo file_get_contents(dirname(__file__) . '/js/script.js');
		exit();
	}

	public static function adminMenu() {
		add_options_page(
			__('CF Asset Optimizer'),
			__('CF Asset Optimizer'),
			'manage_options',
			'cf-asset-optimizer-options',
			'CFAssetOptimizerAdmin::adminMenuCallback'
		);

		add_action( 'plugin_action_links_'.basename( dirname( __file__ ) ).'/cf-asset-optimizer.php', 'CFAssetOptimizerAdmin::adminPluginSettings', 10, 4 );
	}

	function adminPluginSettings( $links ) {
		$settings_link = '<a href="options-general.php?page=cf-asset-optimizer-options">'.__( 'Settings' ).'</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function adminMenuCallback() {

		$styles_cache_dir = CFAssetOptimizerStyles::getCacheDir();
		$script_cache_dir = CFAssetOptimizerScripts::getCacheDir();

		if (!file_exists($styles_cache_dir)) {
			@mkdir($styles_cache_dir, 0775, true);
		}
		if (!file_exists($script_cache_dir)) {
			@mkdir($script_cache_dir, 0775, true);
		}

		if (!is_writable($styles_cache_dir) || !is_writable($script_cache_dir)) {
			if (stripos($styles_cache_dir, 'wp-content/cfao-cache') === false) {
				$warning_message = __('We had a problem. Make sure that directory ' . $styles_cache_dir . ' exists and is writable.');
			}
			if (stripos($script_cache_dir, 'wp-content/cfao-cache') === false) {
				$warning_message = __('We had a problem. Make sure that directory ' . $script_cache_dir . ' exists and is writable.');
			}
			else {
				$warning_message = __('We had a problem. Make sure that you have a directory called \'cfao-cache\' in your wp-content folder, and that the directory is writable.');
			}
		}

		?>
		<div class="wrap">
			<?php screen_icon(); ?><h2><?php echo esc_html(__('Asset Optimizer')); ?></h2>
			<p><a href="http://www.crowdfavorite.com">CrowdFavorite</a>'s <?php echo esc_html(__('Asset Optimizer takes all the separate CSS and JS files included in plugins and external add-ons, and compiles them into one file, helping your pages load faster.')); ?></p>
			<form method="post" action="" id="cf-asset-optimizer-settings" class="settings">
				<?php
				wp_nonce_field('cfao-save-settings', 'cfao-save-settings');
				self::_displayGeneralSettings();
				self::_displayAdvancedSettings();
				?>
				<div class="save-container">
					<?php if (!empty($warning_message)) { ?>
					<p class="warning"><?php echo esc_html($warning_message); ?></p>
					<?php } ?>
					<button class="save" name="cfao_save_settings" value="save_settings"><?php echo esc_html(__('Save')); ?></button>
				</div>
			</form>
		</div>
		<?php
		}

		private static function _displayGeneralSettings() {
			$compile_setting = get_option('cfao_compile_setting', 'off');

			include dirname(__file__).'/views/general-settings.php';
		}

		private static function _displayAdvancedSettings() {
			$scripts = self::_getScriptFileList('scripts');
			$styles =  self::_getScriptFileList('styles');

			$cfao_using_cache = get_option('cfao_using_cache', true);
			$security_key = get_option('cfao_security_key', '');
			$minify_js_level = get_option('cfao_minify_js_level', 'whitespace');

			$styles_cache_dir = CFAssetOptimizerStyles::getCacheDir();
			$script_cache_dir = CFAssetOptimizerScripts::getCacheDir();

			$script_latest_version = CFAssetOptimizerAdmin::_getLatestFiletime($script_cache_dir);
			$styles_latest_version = CFAssetOptimizerAdmin::_getLatestFiletime($styles_cache_dir);

			include dirname(__file__).'/views/advanced-settings.php';
		}

		private static function _getScriptFileList($tab_type) {
			$filetab_types = array(
				'scripts' => 'JavaScript',
				'styles' => 'CSS'
			);

			if (!in_array($tab_type, array_keys($filetab_types))) {
				return;
			}

			$attr_escaped_type = esc_attr($tab_type);
			$html_escaped_name = esc_html(__($filetab_types[$tab_type]));

			$files = get_option('cfao_'.$tab_type, array());

			if (empty($files) || !is_array($files)) {
				$files = array();
			}

			return $files;
		}

	public static function saveSettings() {
		if (empty($_POST['cfao_save_settings'])) {
			// Not our time to save.
			return;
		}
		else {
			if (!check_admin_referer('cfao-save-settings', 'cfao-save-settings')) {
				wp_die(__('I\'m sorry, Dave. I can\'t do that.'));
			}
			if ($_POST['cfao_save_settings'] == 'save_settings') {

				update_option('cfao_compile_setting', $_POST['compile_setting']);

				if (!empty($_POST['cfao_using_cache'])) {
					update_option('cfao_using_cache', true);
				}
				else {
					update_option('cfao_using_cache', false);
				}
				update_option('cfao_security_key', md5($_SERVER['SERVER_ADDR'] . time()));
				update_option('cfao_minify_js_level', $_POST['js-minify']);

				// Save the scripts data
				update_option('cfao_scripts', $_POST['scripts']);
				update_option('cfao_styles', $_POST['styles']);

				do_action('cfao_save_general_settings', $_POST);
			}

			else if ($_POST['cfao_save_settings'] == 'clear_scripts_cache') {
				$dir = CFAssetOptimizerScripts::getCacheDir();
				if (is_dir($dir)) {
					$files = opendir($dir);
					if ($files) {
						while ($file = readdir($files)) {
							if (is_file($dir.'/'.$file) && (preg_match('/\.js$/', $file) || $file == CFAssetOptimizerScripts::getLockFile())) {
								unlink($dir.'/'.$file);
							}
						}
					}
				}
			}
			else if ($_POST['cfao_save_settings'] == 'clear_styles_cache') {
				$dir = CFAssetOptimizerStyles::getCacheDir();
				if (is_dir($dir)) {
					$files = opendir($dir);
					if ($files) {
						while ($file = readdir($files)) {
							if (is_file($dir.'/'.$file) && (preg_match('/\.css$/', $file) || $file == CFAssetOptimizerStyles::getLockFile())) {
								unlink($dir.'/'.$file);
							}
						}
					}
				}
			}

			wp_redirect( $_SERVER['REQUEST_URI'] );
			exit();
		}
	}

	private static function _getLatestFiletime($pathdir) {
		$latest_ctime = 0;

		$d = dir($pathdir);
		while (false !== ($entry = $d->read())) {
			$filepath = "{$pathdir}/{$entry}";
			if (is_file($filepath) && filectime($filepath) > $latest_ctime) {
				$latest_ctime = filectime($filepath);
			}
		}
		$d->close();

		if (!empty($latest_ctime)) {
			$offset = get_option('gmt_offset') * 60 * 60;
			$latest_ctime += $offset;
		}
		
		return $latest_ctime;
	}

	/**
	 * Standardize naming: relative paths for internal scripts/stylesheets,
	 * and absolute paths for external ones pulled from other domains
	 * @param string $src
	 */
	public static function getRelativePath($src) {

		$replace_regex = '/^(?:(?:https?[:])?\/\/)' . $_SERVER['HTTP_HOST'] . '/i';

		$file_src = preg_replace($replace_regex, '', $src);

		return $file_src;
	}

	public static function getFieldName($script_type, $handle, $field) {
		return $script_type . '[' . $handle . '][' . $field . ']';
	}
}

add_action('admin_menu', 'CFAssetOptimizerAdmin::adminMenu');
add_action('admin_init', 'CFAssetOptimizerAdmin::saveSettings');
add_action('load-settings_page_cf-asset-optimizer-options', 'CFAssetOptimizerAdmin::adminInit');
add_action('wp_ajax_cfao-admin-css', 'CFAssetOptimizerAdmin::adminCSS');
add_action('wp_ajax_cfao-admin-js', 'CFAssetOptimizerAdmin::adminJS');
