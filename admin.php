<?php

class CFAssetOptimizerAdmin {

	public static function adminInit() {
		$tab = !empty($_GET['tab']) ? $_GET['tab'] : 'general';
		
		wp_enqueue_style('cfao-admin-css',
			admin_url('admin-ajax.php?action=cfao-admin-css'),
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
	}

	public static function adminMenuCallback() {
		?>
		<?php screen_icon(); ?><h1><?php echo esc_html(__('CF Asset Optimizer')); ?></h1>
		<p><?php echo esc_html(__('Here you can manage the settings for dynamically concatenating and serving your static files.')); ?><p>
		<p><?php echo esc_html(__('Each file lists its handle, information about that file, and the reason it was disabled if such was required.')); ?></p>
		<form method="post" action="" id="cf-asset-optimizer-settings">
			<?php
			wp_nonce_field('cfao-save-settings', 'cfao-save-settings');
			self::_displayGeneralSettingsTab();
			self::_displayFileListTab('scripts');
			self::_displayFileListTab('styles');
			?>
		</form>
		<?php
		}
		
		private static function _displayGeneralSettingsTab() {
			$cfao_using_cache = get_option('cfao_using_cache', false);
			$security_key = get_option('cfao_security_key', '');
			$minify_js_level = get_option('cfao_minify_js_level', '');

			include dirname(__file__).'/views/general-settings.php';
		?>

		<?php
		}
		
		private static function _displayFileListTab($tab_type) {
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

			include dirname(__file__).'/views/file-list.php';
		}
		
	public static function saveSettings() {
		if (empty($_POST['cfao_save_settings'])) {
			// Not our time to save.
			return;
		}
		else {
			$tab = 'general';
			if (!check_admin_referer('cfao-save-settings', 'cfao-save-settings')) {
				wp_die(__('I\'m sorry, Dave. I can\'t do that.'));
			}
			if ($_POST['cfao_save_settings'] == 'save_general_settings') {
				if (!empty($_POST['cfao_using_cache'])) {
					update_option('cfao_using_cache', true);
				}
				else {
					update_option('cfao_using_cache', false);
				}
				update_option('cfao_security_key', md5($_SERVER['SERVER_ADDR'] . time()));
				update_option('cfao_minify_js_level', $_POST['js-minify']);
				do_action('cfao_save_general_settings', $_POST);
			}
			else if ($_POST['cfao_save_settings'] == 'save_scripts') {
				// Save the scripts data
				update_option('cfao_scripts', $_POST['scripts']);
				$tab = 'scripts';
			}
			else if ($_POST['cfao_save_settings'] == 'save_styles') {
				update_option('cfao_styles', $_POST['styles']);
				$tab = 'styles';
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
				$tab = 'scripts';
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
				$tab = 'styles';
			}
			wp_redirect(add_query_arg('tab', $tab, $_SERVER['REQUEST_URI']));
			exit();
		}
	}

}
add_action('admin_menu', 'CFAssetOptimizerAdmin::adminMenu');
add_action('admin_init', 'CFAssetOptimizerAdmin::saveSettings');
add_action('admin_init', 'CFAssetOptimizerAdmin::adminInit');
add_action('wp_ajax_cfao-admin-css', 'CFAssetOptimizerAdmin::adminCSS');
add_action('wp_ajax_cfao-admin-js', 'CFAssetOptimizerAdmin::adminJS');
