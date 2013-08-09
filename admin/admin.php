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
		if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'cf-asset-optimizer-settings') {
			$update_setting = false;
			if (!empty($_GET['activate_optimizer'])) {
				$to_activate = $_GET['activate_optimizer'];
				if (isset(self::$_setting['plugins']['optimizers'][$to_activate])) {
					self::$_setting['plugins']['optimizers'][$to_activate] = true;
					$update_setting = true;
				}
			}
			if (!empty($_GET['deactivate_optimizer'])) {
				$to_deactivate = $_GET['deactivate_optimizer'];
				if (isset(self::$_setting['plugins']['optimizers'][$to_deactivate])) {
					self::$_setting['plugins']['optimizers'][$to_deactivate] = false;
					$update_setting = true;
				}
			}
			if (!empty($_GET['activate_cacher'])) {
				$to_activate = $_GET['activate_cacher'];
				if (isset(self::$_setting['plugins']['cachers'][$to_activate])) {
					foreach (self::$_setting['plugins']['cachers'] as $class_name => $active) {
						self::$_setting['plugins']['cachers'][$class_name] = ($class_name === $to_activate);
					}
					$update_setting = true;
				}
			}
			if (!empty($_GET['deactivate_cacher'])) {
				$to_deactivate = $_GET['deactivate_cacher'];
				if (isset(self::$_setting['plugins']['cachers'][$to_deactivate])) {
					self::$_setting['plugins']['cachers'][$to_deactivate] = false;
					$update_setting = true;
				}
			}
			if (!empty($_GET['activate_minifier'])) {
				$to_activate = $_GET['activate_minifier'];
				if (isset(self::$_setting['plugins']['minifiers'][$to_activate])) {
					self::$_setting['plugins']['minifiers'][$to_activate] = true;
					$update_setting = true;
				}
			}
			if (!empty($_GET['deactivate_minifier'])) {
				$to_deactivate = $_GET['deactivate_minifier'];
				if (isset(self::$_setting['plugins']['minifiers'][$to_deactivate])) {
					self::$_setting['plugins']['minifiers'][$to_deactivate] = false;
					$update_setting = true;
				}
			}
			if ($update_setting) {
				update_option(self::$_setting_name, self::$_setting);
				wp_safe_redirect(remove_query_arg(array('activate_optimizer', 'activate_cacher', 'activate_minifier', 'deactivate_optimizer', 'deactivate_cacher', 'deactivate_minifier')));
			}
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