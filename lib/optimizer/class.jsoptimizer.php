<?php
/**
 * Asset Optimizer Interface Class
 * This class is used as a base for any asset optimizer in this library to define a common interface expectation.
 *
 * @package CFAssetOptimizer
 */

class cf_js_optimizer extends cf_asset_optimizer {
	
	public static function setHooks() {
		add_action('wp_print_scripts', 'cf_js_optimizer::_enqueueAsset', 100);
		add_action('wp_print_footer_scripts', 'cf_js_optimizer::_enqueueFooter', 9);
	}
		
	public static function _buildAsset(&$scripts) {
		$option_name = self::_getOptionName();
		if (!empty($scripts)) {
			$concat = '';
			$changed_settings = false;
			$js_settings = get_option($option_name);
			$content_header =
				"/**\n" .
				" * Included Scripts\n" .
				" *\n";
			foreach ($scripts as $handle => $url) {
				if (empty($url)) {
					continue;
				}
				$result = wp_remote_get(
					$url,
					array(
						'reject_unsafe_urls' => false,
						'timeout' => 1,
					)
				);
				if (is_wp_error($result)) {
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('WP Error: %s'), $result->get_error_message());
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				else if (empty($result['response'])) {
					die('HTTP ERROR');
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('Empty response requesting %s'), $url);
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				else if ($result['response']['code'] < 200 || $result['response']['code'] >= 400) {
					$js_settings[$handle]['enabled'] = false;
					$js_settings[$handle]['disable_reason'] = sprintf(__('HTTP Error %d: %s'), $result['response']['code'], $result['response']['message']);
					$changed_settings = true;
					unset($scripts[$handle]);
					continue;
				}
				
				$content_header .= ' * ' . $handle . ' => ' . $url . "\n";
				$src = $result['body'];
				
				$concat .= apply_filters('cfao_single_contents', $src, 'js', $handle);
			}
			
			if ($changed_settings) {
				update_option($option_name, $js_settings);
			}
			// Set the cache of the file.
			$content_header .= " **/\n";
			$concat = apply_filters('cfao_concat_contents', $concat, 'js');
			$concat = $content_header . $concat;
			$cachemgr = self::_getMyCacheMgr();
			$cachemgr::set($scripts, $concat, 'js');
			return $cachemgr::get($scripts, 'js');
		}
		return false;
	}
		
	public static function _enqueueAsset($footer = false) {
		global $wp_scripts;
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
						'src' => sprintf(__('Placeholder registration for script combination: (%s)'), $registered->deps),
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
					$option[$handle]['disable_reason'] = __('Disabled as 3rd-party offsite script.');
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
					$option[$handle]['disable_reason'] = sprintf(__('Disabled as dependent on disabled scripts (%s)'), implode(',', $disabled_deps));
					$update_settings = true;
				}
				else {
					// We can concatenate this script.
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
		if (!($asset = $cachemgr::get($scripts, 'js'))) {
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
			wp_enqueue_script($handle, $asset['url'], array(), $asset['ver'], $footer);
			if (!empty($extra)) {
				// Copy the localization data
				$wp_scripts->registered[$handle]->extra['data'] = $extra;
			}
		}
	}
	
	public static function _enqueueFooter() {
		self::_enqueueAsset(true);
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
cf_js_optimizer::setHooks();