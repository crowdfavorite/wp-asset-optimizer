<?php
/**
 * Asset Optimizer Interface Class
 * This class is used as a base for any asset optimizer in this library to define a common interface expectation.
 *
 * @package CFAssetOptimizer
 */

class cf_css_optimizer extends cf_asset_optimizer {
	
	public static function activate() {
		if (!is_admin()) {
			// Never presume to handle admin requests
			add_action('wp_print_styles', 'cf_css_optimizer::_enqueueAssets', 100);
		}
		else {
			// Set up my admin interface
			add_action('admin_init', 'cf_css_optimizer::_adminInit');
			add_action('admin_menu', 'cf_css_optimizer::_adminMenu');
			add_action('admin_enqueue_scripts', 'cf_css_optimizer::_adminEnqueueScripts');
			add_filter('cfao_plugin_row_actions', 'cf_css_optimizer::_rowActions', 10, 3);
		}
	}
	
	public static function class_name() {
		return 'cf_css_optimizer';
	}

	public static function listItem() {
		return array(
			'title' => __('CF CSS Optimizer', 'cf-asset-optimizer'),
			'description' => __('This plugin modifies the output of enqueued styles to reduce the number of requests, and has hooks to serve minified content with asset minifiers. It respects media types, dependencies, and after blocks on styles.', 'cf-asset-optimizer'),
		);
	}

	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
	
	public static function _buildAsset(&$styles) {
		$option_name = self::_getOptionName();
		if (!empty($styles)) {
			$concat = '';
			$changed_settings = false;
			$css_settings = get_option($option_name);
			$content_header =
				"/**\n" .
				' * ' . __('Included Styles', 'cf-asset-optimizer') . "\n" .
				" *\n";
			foreach ($styles as $handle => $url) {
				if (empty($url)) {
					$content_header .= " * $handle placeholder active\n";
				}
				$result = wp_remote_get(
					$url,
					array(
						'reject_unsafe_urls' => false,
						'timeout' => 1,
						'sslverify' => false, // Some sites have self-signed certs that cause a problem here.
					)
				);
				if (is_wp_error($result)) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('WP Error: %s', 'cf-asset-optimizer'), $result->get_error_message());
					error_log('[CF Asset Optimizer] ('.$url.') - WP Error: ' . $result->get_error_message());
					unset($styles[$handle]);
					$changed_settings = true;
					continue;
				}
				else if (empty($result['response'])) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('Empty response requesting %s', 'cf-asset-optimizer'), $url);
					error_log('[CF Asset Optimizer] ('.$url.') - Empty response');
					$changed_settings = true;
					unset($styles[$handle]);
					continue;
				}
				else if ($result['response']['code'] < 200 || $result['response']['code'] >= 400) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('HTTP Error %d: %s', 'cf-asset-optimizer'), $result['response']['code'], $result['response']['message']);
					error_log('[CF Asset Optimizer] ('.$url.') - HTTP Error: ' . $result['response']['code'] . ' ' . $result['response']['message']);
					$changed_settings = true;
					unset($styles[$handle]);
					continue;
				}
				$content_header .= ' * ' . $handle . ' => ' . $url . "\n";
				$src = $result['body'];
				
				// Get URL parts for this script.
				$parts = array();
				preg_match('#https?:(//[^/]*)([^?]*/)([^?]*)(\?.*)?#', $url, $parts);
				$parts[1] = apply_filters('cfao_styles_relative_domain', $parts[1]);
							
				// Update paths that are based on web root.
				if (count($parts) > 1) {
					$regex = '~
							url\s*\(             # url( with optional internal whitespace
							\s*                  # optional whitespace
							(                    # begin group 1
							  ["\']?             #   an optional single or double quote
							)                    # end option group 1
							\s*                  # optional whitespace
							(                    # begin option group 2
							  /                  #     url starts with / for web root url
							  [^[:space:]]       #     one single non-space character
							  .+?                #     one or more (non-greedy) any character
							)                    # end option group 2
							\s*                  # optional whitespace
							\1                   # match opening delimiter
							\s*                  # optional whitespace
							\)                   # closing )
							~x';
					$src = preg_replace($regex,'url('.$parts[1].'$2)', $src);
				}
								
				// Update paths based on style location
				if (count($parts) > 2) {
					$regex = '~
						  url\s*\(             # url( with optional internal whitespace
						  \s*                  # optional whitespace
						  (                    # begin group 1 (optional delimiter)
						    ["\']?             #   an optional single or double quote
						  )                    # end group 1
						  \s*                  # optional whitespace
						  (?!                  # negative lookahead assertion: skip if...
						     (?:                #   noncapturing group (not needed with lookaheads)
						       [\'"]            #     keep optional quote out of url match
						       |                #     or
						       //               #     url starts with //
						       |                #     or 
						       https?://        #     url starts with http:// or https://
						       |                #     or
						       data:            #     url starts with data:
						     )                  #   end noncapturing group
						   )                    # end negative lookahead
						  (                    # begin group 2 (relative URL)
						    /?                 #   optional root /
						    [^[:space:]]       #   one single nonspace character
						    .+?                #   one or more (non-greedy) any character

						  )                    # end group 2
						  \s*                  # optional whitespace
						  \1                   # match opening delimiter
						  \s*                  # optional whitespace
						  \)                   # closing )
						  ~x';
					$src = preg_replace($regex, 'url('.$parts[1].$parts[2].'$2)', $src);
					$concat .= apply_filters('cfao_single_contents', $src, 'css', $handle, $css_settings);
					$concat .= $src . "\n";
				}
				
			}
			if ($changed_settings) {
				update_option($option_name, $css_settings);
			}
			// Set the cache of the file.
			$content_header .= " **/\n";
			$concat = apply_filters('cfao_concat_contents', $concat, 'css', '', $css_settings);
			if (!empty($concat)) {
				$concat = $content_header . $concat;
				$cachemgr = self::_getMyCacheMgr();
				call_user_func(array($cachemgr, 'set'), $styles, $concat, 'css');
				return call_user_func(array($cachemgr, 'get'), $styles, 'css');
			}
		}
		return false;
	}
		
	public static function _enqueueAssets() {
		// Determine the files to build and do so.
		global $wp_styles;
		if (empty($wp_styles)) {
			return;
		}
		$option_name = self::_getOptionName();
		$wp_styles->all_deps($wp_styles->queue);
		$styles_blocks = array('all'=>array()); // Ensure all runs first.
		$css_settings = get_option($option_name, array());
		$save_settings = false;
		if (empty($css_settings)) {
			$save_settings = true;
			$css_settings = array();
		}
		foreach ($wp_styles->to_do as $handle) {
			$registered = $wp_styles->registered[$handle];
			$type = (empty($registered->args)) ? 'all' : strtolower($registered->args);
			$ver = (empty($registered->ver)) ? $wp_styles->default_version : $registered->ver;
			// Use the wp_styles object to build the URL
			$full_url = $wp_styles->_css_href($registered->src, $ver, $handle);
			$url = self::_normalizeUrl($full_url);
			if (!isset($css_settings[$handle])) {
				$css_settings[$handle] = array(
					'src' => $url,
					'enabled' => self::_isLocal($url),
				);
				if (!$css_settings[$handle]['enabled']) {
					$css_settings[$handle]['disable_reason'] = __('Disabled as 3rd-party offsite stylesheet.', 'cf-asset-optimizer');
				}
				$save_settings = true;
			}
			if ($css_settings[$handle]['enabled']) {
				// Double check if we've changed
				if ($css_settings[$handle]['src'] !== $url) {
					// We've changed. Update the reference, but don't change enabled state.
					$css_settings[$handle]['src'] = $url;
					$save_settings = true;
				}
				// Double-check that things I'm dependent on are enabled.
				$disabled_parents = array();
				foreach ($registered->deps as $parent) {
					if (empty($css_settings[$parent]) || $css_settings[$parent]['enabled'] === false) {
						$disabled_parents[] = $parent;
					}
				}
				// Double-check that I'm not conditional.
				if (!empty($registered->extra) && !empty($registered->extra['conditional'])) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = __('Disabled due to conditional requirement', 'cf-asset-optimizer');
					$save_settings = true;
				}
				// Double check that I'm not rtl (TODO include rtl later)
				else if (!empty($registered->extra) && !empty($registered->extra['rtl'])) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = __('Disabled because rtl files not currently supported', 'cf-asset-optimizer');
					$save_settings = true;
				}
				// Disable me if I'm dependent on disabled styles
				else if (!empty($disabled_parents)) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('Disabled due to disabled parent styles: %s', 'cf-asset-optimizer'), implode(', ', $disabled_parents));
					$save_settings = true;
				}
				else {
					if (strpos($full_url, '//') === 0) {
						$full_url = (is_ssl() ? 'https:' : 'http:') . $full_url;
					}
					else if (strpos($full_url, '/') === 0) {
						$full_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $full_url;
					}
					if (empty($styles_blocks[$type])) {
						$styles_blocks[$type] = array();
					}
					$styles_blocks[$type][$handle] = $full_url;
				}
			}
		}
		if ($save_settings) {
			update_option($option_name, $css_settings);
		}
		$cachemgr = self::_getMyCacheMgr();
		if (!$cachemgr) {
			// We are currently mandating at least some kind of cache and response.
			return;
		}
		foreach ($styles_blocks as $type=>$styles) {
			if (!($asset = call_user_func(array($cachemgr, 'get'), $styles, 'css'))) {
				// We need to generate the asset.
				$asset = self::_buildAsset($styles, true);
			}
			if (!empty($asset)) {
				// We can enqueue this asset and "complete" the scripts within it.
				$after_block = '';
				$handles = array();
				foreach ($styles as $handle => $style) {
					$handles[] = $handle;
					if (!empty($wp_styles->registered[$handle]->extra['after'])) {
						$after_block .= '<style type="text/css">'.implode("\n", $wp_styles->registered[$handle]->extra['after']).'</style>';
					}
				}
				$wp_styles->queue = array_diff($wp_styles->queue, $handles);
				$wp_styles->to_do = array_diff($wp_styles->to_do, $handles);
				$wp_styles->done = array_merge($wp_styles->done, $handles);
				$deps = array();
				if (!$type == 'all') {
					$deps = array('cfao-css-all');
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
				wp_enqueue_style('cfao-css-'.$type, $enqueue_url, $deps, $asset['ver'], $type);
			}
		}
	}
	
	public static function _adminInit() {
		// Save our settings, if needed.
		if (
			!empty($_GET['page']) && $_GET['page'] == 'cf-css-optimizer-settings'
			&& (!empty($_GET['cfao_action']) || !empty($_REQUEST['submit-header']) || !empty($_REQUEST['submit-footer']))
			&& !empty($_REQUEST['css'])
			&& is_array($_REQUEST['css'])
		) {
			check_admin_referer('cfao_nonce_css', 'cfao_nonce_css');
			$setting = get_option(self::_getOptionName(), array());
			$update_setting = false;
			$action = isset($_GET['cfao_action']) ? $_GET['cfao_action'] : '';
			if (!empty($_REQUEST['submit-header'])) {
				$action = isset($_REQUEST['cfao_action-header']) ? $_REQUEST['cfao_action-header'] : '';
			}
			else if (!empty($_REQUEST['submit-footer'])) {
				$action = isset($_REQUEST['cfao_action-footer']) ? $_REQUEST['cfao_action-footer'] : '';
			}
			$css = $_REQUEST['css'];
			switch ($action) {
				case 'enable':
					foreach ($css as $handle) {
						if (isset($setting[$handle])) {
							$setting[$handle]['enabled'] = true;
							unset($setting[$handle]['disable_reason']);
							$update_setting = true;
						}
					}
					break;
				case 'disable':
					foreach ($css as $handle) {
						if (isset($setting[$handle])) {
							$setting[$handle]['enabled'] = false;
							$user = wp_get_current_user();
							$setting[$handle]['disable_reason'] = sprintf(__('Manually disabled by %s', 'cf-asset-optimizer'), $user->display_name);
							$update_setting = true;
						}
					}
					break;
				case 'forget':
					foreach ($css as $handle) {
						if (isset($setting[$handle])) {
							unset($setting[$handle]);
							$update_setting = true;
						}
					}
					break;
				default:
					$setting = apply_filters('cfao_admin_css_'.$action, $setting, $css);
					$update_setting = true;
			}
			
			if ($update_setting) {
				update_option(self::_getOptionName(), $setting);
			}
			
			wp_safe_redirect(remove_query_arg(array('cfao_action', 'css')));
			exit();
		}
	}
	
	public static function _adminMenu() {
		add_submenu_page(
			'cf-asset-optimizer-settings',
			__('CF CSS Optimizer', 'cf-asset-optimizer'),
			__('CSS Optimizer', 'cf-asset-optimizer'),
			'activate_plugins',
			'cf-css-optimizer-settings',
			'cf_css_optimizer::_adminPage'
		);
	}
	
	public static function _adminPage() {
		$settings = get_option(self::_getOptionName(), array());
		?>
		<h1><?php screen_icon(); echo esc_html(get_admin_page_title()); ?></h1>
		<div class="cf_css_optimizer_settings" style="clear:both;margin:15px;">
		<p><?php esc_html_e('The CSS Asset Optimizer will concatenate all enabled styles below into a single request per media type declaration to reduce the number of requests used to generate a page, improving page load time and reducing server load.', 'cf-asset-optimizer'); ?></p>
		<?php
		include_once CFAO_PLUGIN_DIR . 'admin/list-tables/class.cfao-request-list-table.php';
		?>
		<form action="" method="POST">
		<?php
		$list_table = new CFAO_Requests_List_Table(array(
			'singular' => 'stylesheet',
			'plural' => 'stylesheets',
			'items' => $settings,
			'type' => 'css',
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
		if ($pagenow == 'admin.php' && !empty($_GET['page']) && $_GET['page'] == 'cf-css-optimizer-settings') {
			wp_enqueue_style('cfao-list-table');
		}
	}
	
	public static function _rowActions($actions, $component_type, $item) {
		if ($component_type == 'optimizer' && $item['class_name'] == self::class_name() && isset($item['active']) && $item['active']) {
			$actions['settings'] = '<a href="' . add_query_arg(array('page' => 'cf-css-optimizer-settings'), remove_query_arg(array('page'))) . '">' . esc_html__('Settings', 'cf-asset-optimizer') . '</a>';
		}
		return $actions;
	}
	
	protected static function _getOptionName() {
		return parent::_getOptionName('css');
	}
	
	protected static function _getMyDomains() {
		return parent::_getMyDomains('css');
	}
	
	protected static function _normalizeUrl($url) {
		return parent::_normalizeUrl($url, 'css');
	}
	
	protected static function _getMyCacheMgr() {
		return parent::_getMyCacheMgr('css');
	}
	
	protected static function _isLocal($url) {
		return parent::_isLocal($url, 'css');
	}
	
}
add_action('cfao_optimizers', 'cf_css_optimizer::register');