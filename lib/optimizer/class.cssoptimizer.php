<?php
/**
 * Asset Optimizer Interface Class
 * This class is used as a base for any asset optimizer in this library to define a common interface expectation.
 *
 * @package CFAssetOptimizer
 */

class cf_css_optimizer extends cf_asset_optimizer {
	
	public static function setHooks() {
		add_action('wp_print_styles', 'cf_css_optimizer::_enqueueAssets', 100);
	}
	
	public static function _buildAsset(&$styles) {
		$option_name = self::_getOptionName();
		if (!empty($styles)) {
			$concat = '';
			$changed_settings = false;
			$css_settings = get_option($option_name);
			$content_header =
				"/**\n" .
				" * Included Styles\n" .
				" *\n";
			foreach ($styles as $handle => $url) {
				$result = wp_remote_get(
					$url,
					array(
						'reject_unsafe_urls' => false,
						'timeout' => 1,
					)
				);
				if (is_wp_error($result)) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('WP Error: %s'), $result->get_error_message());
					unset($styles[$handle]);
					$changed_settings = true;
					continue;
				}
				else if (empty($result['response'])) {
					die('HTTP ERROR');
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('Empty response requesting %s'), $url);
					$changed_settings = true;
					unset($styles[$handle]);
					continue;
				}
				else if ($result['response']['code'] < 200 || $result['response']['code'] >= 400) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('HTTP Error %d: %s'), $result['response']['code'], $result['response']['message']);
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
					$concat .= apply_filters('cfao_single_contents', $src, 'css', $handle);
					$concat .= $src . "\n";
				}
				
			}
			if ($changed_settings) {
				update_option($option_name, $css_settings);
			}
			// Set the cache of the file.
			$content_header .= " **/\n";
			$concat = apply_filters('cfao_concat_contents', $concat, 'css');
			$concat = $content_header . $concat;
			$cachemgr = self::_getMyCacheMgr();
			$cachemgr::set($styles, $concat, 'css');
			return $cachemgr::get($styles, 'css');
		}
		return false;
	}
		
	public static function _enqueueAssets() {
		// Determine the files to build and do so.
		global $wp_styles;
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
					$css_settings[$handle]['disable_reason'] = __('Disabled as 3rd-party offsite stylesheet.');
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
					$css_settings[$handle]['disable_reason'] = __('Disabled due to conditional requirement');
					$save_settings = true;
				}
				// Double check that I'm not rtl (TODO include rtl later)
				else if (!empty($registered->extra) && !empty($registered->extra['rtl'])) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = __('Disabled because rtl files not currently supported');
					$save_settings = true;
				}
				// Disable me if I'm dependent on disabled styles
				else if (!empty($disabled_parents)) {
					$css_settings[$handle]['enabled'] = false;
					$css_settings[$handle]['disable_reason'] = sprintf(__('Disabled due to disabled parent styles: %s'), implode(', ', $disabled_parents));
					$save_settings = true;
				}
				else {
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
			throw new Exception('Could not find cache manager');
		}
		foreach ($styles_blocks as $type=>$styles) {
			if (!($asset = $cachemgr::get($styles, 'css'))) {
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
				wp_enqueue_style('cfao-css-'.$type, $asset['url'], $deps, $asset['ver'], $type);
			}
		}
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
cf_css_optimizer::setHooks();