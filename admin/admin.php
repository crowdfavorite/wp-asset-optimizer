<?php
// Need to configure admin menu.

class cfao_admin {
	private static $_setting_name = '_cf_asset_optimizer_settings';
	private static $_setting;
	
	public static function _configure() {
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
		
		add_action('admin_menu', 'cfao_admin::_adminMenu');
	}
	
	public static function _adminMenu() {
		add_menu_page(
			__('Asset Optimizer'),
			__('Asset Optimizer'),
			'activate_plugins',
			'cf-asset-optimizer-menu',
			'cfao_admin::_mainPage'
		);
		foreach (self::$_setting['plugins'] as $type) {
			foreach ($type as $class => $active) {
				if ($active) {
					$class::addSubMenu();
				}
			}
		}
	}
	
	public static function _mainPage() {
		include CFAO_PLUGIN_DIR . 'admin/list-tables/class.cfao-list-table.php';
		screen_icon();
		?>
		<div class="cfao-general-settings-wrapper" style="margin-right: 15px;">
		<h1><?php screen_icon(); echo esc_html_e('CF Asset Optimizer Settings'); ?></h1>
		<div class="cfao-general-settings-inner-wrapper" style="clear:both">
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
		</div>
		<?php
	}
	
}
add_action('init', 'cfao_admin::_configure');