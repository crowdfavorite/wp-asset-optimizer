<?php
// Need to configure admin menu.

class cfao_admin {
	private static $_setting_name = '_cf_asset_optimizer_settings';
	private static $_setting;
	
	public static function activate() {
		if (!is_admin()) {
			return;
		}
		$setting = self::$_setting = get_option(self::$_setting_name, array(
			'plugins' => array(
				'minifiers' => array(),
				'cachers' => array(),
				'optimizers' => array(),
			)
		));
		
		$cachers = apply_filters('cfao_cachers', array());
		$minifiers = apply_filters('cfao_minifiers', array());
		$optimizers = apply_filters('cfao_optimizers', array());
		
		$setting_changed = false;
		if (!empty($setting['plugins']['cachers'])) {
			foreach ($setting['plugins']['cachers'] as $class => $active) {
				if (!class_exists($class) || !in_array($class, $cachers)) {
					// TODO Add admin notice regarding programmatic removal of this item.
					unset($setting['plugins']['cachers'][$class]);
					$setting_changed = true;
				}
			}
		}
		foreach ($cachers as $class_name) {
			if (empty($setting['plugins']['cachers'][$class_name])) {
				$setting['plugins']['cachers'][$class_name] = false;
				$setting_changed = true;
			}
		}
		
		if (!empty($setting['plugins']['minifiers'])) {
			foreach ($setting['plugins']['minifiers'] as $class => $active) {
				if (!class_exists($class) || !in_array($class, $minifiers)) {
					// TODO Add admin notice regarding programmatic removal of this item.
					unset($setting['plugins']['minifiers'][$class]);
					$setting_changed = true;
				}
			}
		}	
		foreach ($minifiers as $class_name) {
			if (empty($setting['plugins']['minifiers'][$class_name])) {
				$setting['plugins']['minifiers'][$class_name] = false;
				$setting_changed = true;
			}
		}
		
		if (!empty($setting['plugins']['optimizers'])) {
			foreach ($setting['plugins']['optimizers'] as $class => $active) {
				if (!class_exists($class) || !in_array($class, $optimizers)) {
					// TODO Add admin notice regarding programmatic removal of this item.
					unset($setting['plugins']['optimizers'][$class]);
					$setting_changed = true;
				}
			}
		}
		foreach ($optimizers as $class_name) {
			if (empty($setting['plugins']['optimizers'][$class_name])) {
				$setting['plugins']['optimizers'][$class_name] = false;
				$setting_changed = true;
			}
		}
		
		if ($setting_changed) {
			self::$_setting = $setting;
			update_option(self::$_setting_name, $setting);
		}
		
		add_action('admin_menu', 'cfao_admin::_adminMenu', 1);
		add_action('admin_enqueue_scripts', 'cfao_admin::_adminEnqueueScripts', 1);
		add_action('admin_init', 'cfao_admin::_adminInit');
	}
	
	public static function _adminInit() {
		global $pagenow;
		if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'cf-asset-optimizer-settings' && !empty($_REQUEST['cfao_action'])) {
			check_admin_referer('cfao_nonce', 'cfao_nonce');
			$update_setting = false;
			switch ($_REQUEST['cfao_action']) {
				case 'activate':
					if (!empty($_REQUEST['optimizer'])) {
						if (!is_array($_REQUEST['optimizer'])) {
							$_REQUEST['optimizer'] = array($_REQUEST['optimizer']);
						}
						foreach ($_REQUEST['optimizer'] as $class_name) {
							self::$_setting['plugins']['optimizers'][$class_name] = true;
							$update_setting = true;
						}
					}
					if (!empty($_REQUEST['cacher'])) {
						if (!is_array($_REQUEST['cacher'])) {
							$_REQUEST['cacher'] = array($_REQUEST['cacher']);
						}
						foreach ($_REQUEST['cacher'] as $class_name) {
							self::$_setting['plugins']['cachers'][$class_name] = true;
							$update_setting = true;
						}
					}
					if (!empty($_REQUEST['minifier'])) {
						if (!is_array($_REQUEST['minifier'])) {
							$_REQUEST['minifier'] = $_REQUEST['minifier'];
						}
						foreach ($_REQUEST['minifier'] as $class_name) {
							self::$_setting['plugins']['minifiers'][$class_name] = true;
							$update_setting = true;
						}
					}
					if ($update_setting) {
						update_option(self::$_setting_name, self::$_setting);
					}
					do_action('cfao_admin_activate');
					break;
				case 'deactivate':
					if (!empty($_REQUEST['optimizer'])) {
						if (!is_array($_REQUEST['optimizer'])) {
							$_REQUEST['optimizer'] = array($_REQUEST['optimizer']);
						}
						foreach ($_REQUEST['optimizer'] as $class_name) {
							self::$_setting['plugins']['optimizers'][$class_name] = false;
							$update_setting = true;
						}
					}
					if (!empty($_REQUEST['cacher'])) {
						if (!is_array($_REQUEST['cacher'])) {
							$_REQUEST['cacher'] = array($_REQUEST['cacher']);
						}
						foreach ($_REQUEST['cacher'] as $class_name) {
							self::$_setting['plugins']['cachers'][$class_name] = false;
							$update_setting = true;
						}
					}
					if (!empty($_REQUEST['minifier'])) {
						if (!is_array($_REQUEST['minifier'])) {
							$_REQUEST['minifier'] = $_REQUEST['minifier'];
						}
						foreach ($_REQUEST['minifier'] as $class_name) {
							self::$_setting['plugins']['minifiers'][$class_name] = false;
							$update_setting = true;
						}
					}
					if ($update_setting) {
						update_option(self::$_setting_name, self::$_setting);
					}
					do_action('cfao_admin_deactivate');
					break;
				default:
					do_action('cfao_admin_' . $_REQUEST['cfao_action']);
					break;
			}
			
			// We want to strip to just the base page argument.
			$to_remove = array_diff(array_keys($_GET), array('page'));
			wp_safe_redirect(remove_query_arg($to_remove));
			exit();
		}
	}
	
	public static function _adminMenu() {
		add_menu_page(
			__('CF Asset Optimizer Settings'),
			__('Asset Optimizer'),
			'activate_plugins',
			'cf-asset-optimizer-settings',
			'cfao_admin::_mainPage'
		);
	}
	
	public static function _mainPage() {
		$cfao_nonce = wp_create_nonce('cfao_nonce');
		include CFAO_PLUGIN_DIR . 'admin/list-tables/class.cfao-list-table.php';
		?>
		<h1><?php screen_icon(); echo esc_html(get_admin_page_title()); ?></h1>
		<div class="cfao-general-settings-wrapper" style="clear:both; margin: 15px;">
		<h2><?php echo esc_html_e('Asset Optimizers'); ?></h2>
		<p><?php echo esc_html_e('Asset Optimizers modify the output of assets in order to improve the function of your website.'); ?></p>
		<p><?php echo esc_html_e('Any number of asset optimizers may be active at a time, but only one should run per asset type.'); ?></p>
		<?php
		$list_table = new CFAO_Plugins_List_Table(array(
			'singular' => __('optimizer'),
			'plural' => __('optimizers'),
			'items' => self::$_setting['plugins']['optimizers'],
			'type' => 'optimizer',
			'nonce' => array('cfao_nonce' => $cfao_nonce),
		));
		$list_table->prepare_items();
		$list_table->display();
		
		?>
		<h2><?php echo esc_html_e('Cache Managers'); ?></h2>
		<p><?php echo esc_html_e('Cache managers handle automated storage and retrieval of the output of the asset optimizers.'); ?></p>
		<p><?php echo esc_html_e('This plugin will not generate optimized output without a cache manager selected.'); ?></p>
		<p><?php echo esc_html_e('Only one cache manager may be active at any time. Activating one will deactivate the current active plugin.'); ?></p>
		<?php
		$list_table = new CFAO_Plugins_List_Table(array(
			'singular' => __('cacher'),
			'plural' => __('cachers'),
			'items' => self::$_setting['plugins']['cachers'],
			'type' => 'cacher',
			'nonce' => array('cfao_nonce' => $cfao_nonce),
		));
		$list_table->prepare_items();
		$list_table->display();

		?>
		<h2><?php echo esc_html_e('Minifiers'); ?></h2>
		<p><?php echo esc_html_e('These plugins modify asset optimizer output in order to better compact it for transmission to clients.'); ?></p>
		<p><?php echo esc_html_e('Any number of minifiers may be active at a time, but only one should run per asset type.'); ?></p>
		<?php
		$list_table = new CFAO_Plugins_List_Table(array(
			'singular' => __('minifier'),
			'plural' => __('minifiers'),
			'items' => self::$_setting['plugins']['minifiers'],
			'type' => 'minifier',
			'nonce' => array('cfao_nonce' => $cfao_nonce),
		));
		$list_table->prepare_items();
		$list_table->display();
		?>
		</div>
		<?php
	}
	
	public static function _adminEnqueueScripts() {
		global $pagenow;
		wp_register_style('cfao-list-table', plugins_url(basename(dirname(dirname(__FILE__))).'/admin/css/cfao-list-table.css'), array(), CFAO_VERSION);
		if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'cf-asset-optimizer-settings') {
			wp_enqueue_style('cfao-list-table');
		}
	}
	
}