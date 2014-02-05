<?php
/**
 * Asset Optimizer Interface Class
 * This class is used as a base for any asset optimizer in this library to define a common interface expectation.
 *
 * @package CFAssetOptimizer
 */

class cf_js_optimizer extends cf_asset_optimizer {
	private $_nonce_data = array();
	
	public static function activate() {
		if (!is_admin()) {
			// Never presume to handle admin requests.
			add_action('wp_print_scripts', 'cf_js_optimizer::_enqueueAsset', 100);
			add_action('wp_print_footer_scripts', 'cf_js_optimizer::_enqueueFooter', 9);
		}
		else {
			add_action('admin_init', 'cf_js_optimizer::_adminInit');
			add_action('admin_menu', 'cf_js_optimizer::_adminMenu');
			add_action('admin_enqueue_scripts', 'cf_js_optimizer::_adminEnqueueScripts');
			add_filter('cfao_plugin_row_actions', 'cf_js_optimizer::_rowActions', 10, 3);
		}
	}
	
	public static function class_name() {
		return 'cf_js_optimizer';
	}

	public static function listItem() {
		return array(
			'title' => __('CF JavaScript Optimizer', 'cf-asset-optimizer'),
			'description' => __('This plugin modifies the output of enqueued scripts to reduce the number of requests, and has hooks to serve minified content with asset minifiers. It respects dependencies and outputs localization properly.', 'cf-asset-optimizer'),
		);
	}

	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
		
	public static function _buildAsset(&$scripts) {
		$option_name = self::_getOptionName();
		if (!empty($scripts)) {
			$concat = '';
			$changed_settings = false;
			$js_settings = get_option($option_name);
			$content_header =
				"/**\n" .
				' * ' . __('Included Scripts', 'cf-asset-optimizer') . "\n" .
				" *\n";
			foreach ($scripts as $handle => $url) {	
				if (empty($url)) {
					$content_header .= " * $handle placeholder active\n";
					continue;
				}
				cfao_handler::log('[CF JS Optimizer] Requesting ('.$url.')', true);
				$result = wp_remote_get(
					$url,
					array(
						'reject_unsafe_urls' => false,
						'timeout' => 1,
						'sslverify' => false, // Some sites have self-signed certs that cause a problem here.
					)
				);
				if (is_wp_error($result)) {
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('WP Error: %s', 'cf-asset-optimizer'), $result->get_error_message());
					cfao_handler::log('[CF JS Optimizer] ('.$url.') - WP Error: ' . $result->get_error_message());
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				else if (empty($result['response'])) {
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('Empty response requesting %s', 'cf-asset-optimizer'), $url);
					cfao_handler::log('[CF JS Optimizer] ('.$url.') - Empty response');
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				else if ($result['response']['code'] < 200 || $result['response']['code'] >= 400) {
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('HTTP Error %d: %s', 'cf-asset-optimizer'), $result['response']['code'], $result['response']['message']);
					cfao_handler::log('[CF JS Optimizer] ('.$url.') - HTTP Error: ' . $result['response']['code'] . ' ' . $result['response']['message']);
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				
				cfao_handler::log('[CF JS Optimizer] Successfully processed ('.$url.')', true);
				$content_header .= ' * ' . $handle . ' => ' . $url . "\n";
				$src = $result['body'];
				
				$concat .= apply_filters('cfao_single_contents', $src, 'js', $handle, $js_settings);
			}
			
			if ($changed_settings) {
				update_option($option_name, $js_settings);
			}
			// Set the cache of the file.
			$content_header .= " **/\n";
			$concat = apply_filters('cfao_concat_contents', $concat, 'js', '', $js_settings);
			if (!empty($concat)) {
				$concat = $content_header . $concat;
				$cachemgr = self::_getMyCacheMgr();
				call_user_func(array($cachemgr, 'set'), $scripts, $concat, 'js');
				return call_user_func(array($cachemgr, 'get'), $scripts, 'js');
			}
		}
		return false;
	}
		
	public static function _enqueueAsset($footer = false) {
		global $wp_scripts;
		if (empty($wp_scripts)) {
			return;
		}
		$wp_scripts->all_deps($wp_scripts->queue);
		$option_name = self::_getOptionName();
		$option = get_option($option_name, array());
		$update_settings = false;
		$scripts = array();
		foreach ($wp_scripts->to_do as $handle) {
			$registered = $wp_scripts->registered[$handle];
			// Double-check script settings
			$full_url = $registered->src;
			if (!$footer && $wp_scripts->groups[$handle] !== 0) {
				// This will be run later, in the footer.
				continue;
			}
			if (empty($full_url) || $full_url === 1) {
				// This is a placeholder. Treat it as always concatenated.
				if (empty($option[$handle])) {
					$option[$handle] = array(
						'src' => sprintf(__('Placeholder registration for script combination: (%s)', 'cf-asset-optimizer'), implode(', ', $registered->deps)),
						'enabled' => true,
					);
					$update_settings = true;
				}
				$scripts[$handle] = '';
				continue;
			}
			if ( !preg_match('|^(https?:)?//|', $full_url) && ! ( $wp_scripts->content_url && 0 === strpos($full_url, $wp_scripts->content_url) ) ) {
				$full_url = $wp_scripts->base_url . $full_url;
			}
			if ($registered->ver !== null) {
				$full_url = add_query_arg('ver', ($registered->ver ? $registered->ver : $wp_scripts->default_version), $full_url);
			}
			if (isset($wp_scripts->args[$handle])) {
				$full_url .= '&' . rawurlencode(htmlentitydecode($wp_scripts->args[$handle]));
			}
			$normalized_url = self::_normalizeUrl($full_url);
			if (empty($option[$handle])) {
				// We don't know this script yet. Register it.
				$option[$handle] = array(
					'src' => $normalized_url,
					'enabled' => self::_isLocal($normalized_url),
				);
				if (!$option[$handle]['enabled']) {
					$option[$handle]['disable_reason'] = __('Disabled as 3rd-party offsite script.', 'cf-asset-optimizer');
				}
				$update_settings = true;
			}
			if ($option[$handle]['enabled']) {
				if ($option[$handle]['src'] !== $normalized_url) {
					// Script has changed. Update its reference, but don't change its enabled state.
					$option[$handle]['src'] = $normalized_url;
					$update_settings = true;
				}
				// Double check that my parents are enabled still.
				$disabled_deps = array();
				foreach ($registered->deps as $dep) {
					if (!$option[$dep]['enabled']) {
						$disabled_deps[] = $dep;
					}
				}
				if (!empty($disabled_deps)) {
					// Can't be enabled because it is dependent on a disabled script.
					$option[$handle]['enabled'] = false;
					$option[$handle]['disable_reason'] = sprintf(__('Disabled as dependent on disabled scripts (%s)', 'cf-asset-optimizer'), implode(',', $disabled_deps));
					$update_settings = true;
				}
				else {
					// We can concatenate this script.
					if (strpos($full_url, '//') === 0) {
						$full_url = (is_ssl() ? 'https:' : 'http:') . $full_url;
					}
					else if (strpos($full_url, '/') === 0) {
						$full_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $full_url;
					}
					$scripts[$handle] = $full_url;
				}
			}
		}
		
		// Update any programmatic changes.
		if ($update_settings) {
			update_option($option_name, $option);
		}
		
		if (empty($scripts)) {
			return;
		}
		
		// Serve from cache if possible.
		$cachemgr = self::_getMyCacheMgr();
		if (!$cachemgr) {
			// We're currently mandating a cache manager is attached to run this.
			return;
		}
		if (!($asset = call_user_func(array($cachemgr, 'get'), $scripts, 'js'))) {
			// We need to rebuild the asset.
			$asset = self::_buildAsset($scripts);
		}
		
		if ($asset) {
			// We were able to get a cached concatenated asset to work with.
			$extra = '';
			$done_handles = array();
			foreach ($scripts as $handle => $url) {
				$done_handles[] = $handle;
				if (!empty($wp_scripts->registered[$handle]->extra['data'])) {
					$extra .= $wp_scripts->registered[$handle]->extra['data'].";\n";
				}
			}
			$wp_scripts->queue = array_diff($wp_scripts->queue, $done_handles);
			$wp_scripts->to_do = array_diff($wp_scripts->to_do, $done_handles);
			$wp_scripts->done = array_merge($wp_scripts->done, $done_handles);
			
			$handle = 'cfao-js';
			if ($footer) {
				$handle = 'cfao-footer-js';
			}	
			$enqueue_url = $asset['url'];
			if (strpos($asset['url'], 'http') === 0) {
				$enqueue_url = $asset['url'];
			}
			else if (strpos($asset['url'], '//') === 0) {
				$enqueue_url = $asset['url'];
			}
			else if (strpos($asset['url'], '/') == 0) {
				$enqueue_url = home_url($asset['url']);
			}
			wp_enqueue_script($handle, $enqueue_url, array(), $asset['ver'], $footer);
			if (!empty($extra)) {
				// Copy the localization data
				$wp_scripts->registered[$handle]->extra['data'] = $extra;
			}
		}
	}
	
	public static function _enqueueFooter() {
		self::_enqueueAsset(true);
	}

	public static function _adminInit() {
		// Save our settings, if needed.
		if (
			!empty($_GET['page']) && $_GET['page'] == 'cf-js-optimizer-settings'
			&& (!empty($_GET['cfao_action']) || !empty($_REQUEST['submit-header']) || !empty($_REQUEST['submit-footer']))
			&& !empty($_REQUEST['js'])
			&& is_array($_REQUEST['js'])
		) {
			check_admin_referer('cfao_nonce_js', 'cfao_nonce_js');
			$setting = get_option(self::_getOptionName(), array());
			$update_setting = false;
			$action = isset($_GET['cfao_action']) ? $_GET['cfao_action'] : '';
			if (!empty($_REQUEST['submit-header'])) {
				$action = isset($_REQUEST['cfao_action-header']) ? $_REQUEST['cfao_action-header'] : '';
			}
			else if (!empty($_REQUEST['submit-footer'])) {
				$action = isset($_REQUEST['cfao_action-footer']) ? $_REQUEST['cfao_action-footer'] : '';
			}
			$js = $_REQUEST['js'];
			switch ($action) {
				case 'enable':
					foreach ($js as $handle) {
						if (isset($setting[$handle])) {
							$setting[$handle]['enabled'] = true;
							unset($setting[$handle]['disable_reason']);
							$update_setting = true;
						}
					}
					break;
				case 'disable':
					foreach ($js as $handle) {
						if (isset($setting[$handle])) {
							$setting[$handle]['enabled'] = false;
							$user = wp_get_current_user();
							$setting[$handle]['disable_reason'] = sprintf(__('Manually disabled by %s', 'cf-asset-optimizer'), $user->display_name);
							$update_setting = true;
						}
					}
					break;
				case 'forget':
					foreach ($js as $handle) {
						if (isset($setting[$handle])) {
							unset($setting[$handle]);
							$update_setting = true;
						}
					}
					break;
				default:
					$setting = apply_filters('cfao_admin_js_'.$action, $setting, $js);
					$update_setting = true;
			}
			
			if ($update_setting) {
				update_option(self::_getOptionName(), $setting);
			}
			
			wp_safe_redirect(remove_query_arg(array('cfao_action', 'js')));
			exit();
		}
	}
	
	public static function _adminMenu() {
		add_submenu_page(
			'cf-asset-optimizer-settings',
			__('CF JavaScript Optimizer'),
			__('JavaScript Optimizer'),
			'activate_plugins',
			'cf-js-optimizer-settings',
			'cf_js_optimizer::_adminPage'
		);
	}
	
	public static function _adminPage() {
		$settings = get_option(self::_getOptionName(), array());
		?>
		<h1><?php screen_icon(); echo esc_html(get_admin_page_title()); ?></h1>
		<div class="cf_js_optimizer_settings" style="clear:both;margin:15px;">
		<p><?php esc_html_e('The JavaScript Asset Optimizer will concatenate all enabled scripts below into a single request (or two if you\'ve got separate header and footer scripts) to reduce the number of requests used to generate a page, improving page load time and reducing server load.', 'cf-asset-optimizer'); ?></p>
		<?php
		include_once CFAO_PLUGIN_DIR . 'admin/list-tables/class.cfao-request-list-table.php';
		?>
		<form action="" method="POST">
		<?php
		$list_table = new CFAO_Requests_List_Table(array(
			'singular' => 'script',
			'plural' => 'scripts',
			'items' => $settings,
			'type' => 'js',
			'support_bulk' => true,
		));
		$list_table->prepare_items();
		$list_table->display();
		?>
		</form>
		</div>
		<?php
	}

	public static function _adminEnqueueScripts() {
		global $pagenow;
		if ($pagenow == 'admin.php' && !empty($_GET['page']) && $_GET['page'] == 'cf-js-optimizer-settings') {
			wp_enqueue_style('cfao-list-table');
		}
	}

	public static function _rowActions($actions, $component_type, $item) {
		if ($component_type == 'optimizer' && $item['class_name'] == self::class_name() && isset($item['active']) && $item['active']) {
			$actions['settings'] = '<a href="' . add_query_arg(array('page' => 'cf-js-optimizer-settings'), remove_query_arg(array('page'))) . '">' . esc_html__('Settings', 'cf-asset-optimizer') . '</a>';
		}
		return $actions;
	}
	
	protected static function _getOptionName() {
		return parent::_getOptionName('js');
	}

	protected static function _getMyDomains() {
		return parent::_getMyDomains('js');
	}

	protected static function _normalizeUrl($url) {
		return parent::_normalizeUrl($url, 'js');
	}

	protected static function _getMyCacheMgr() {
		return parent::_getMyCacheMgr('js');
	}

	protected static function _isLocal($url) {
		return parent::_isLocal($url, 'js');
	}
}
add_action('cfao_optimizers', 'cf_js_optimizer::register');