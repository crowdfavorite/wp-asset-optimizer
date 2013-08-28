<?php
/**
 * Asset Optimizer Interface Class
 * This class is used as a base for any asset optimizer in this library to define a common interface expectation.
 *
 * @package CFAssetOptimizer
 */

class cf_asset_optimizer {
	protected static $_MY_DOMAINS;
	protected static $_MY_CACHE_MGR;
	
	public static function class_name() {
		return '';
	}

	public static function listItem() {
		return array(
			'title' => __('CF Asset Optimizer Interface'),
			'description' => __('This is the generic interface class for asset optimizers and should not be activated.'),
		);
	}
	
	public static function register($handles) {
		$class_name = self::class_name();
		if (!empty($class_name)) {
			$handles = array_merge($handles, array($class_name));
		}
		return $handles;
	}
	
	public static function activate() {
	}
	
	protected static function _getOptionName($type = '') {
		$option_name_default = false;
		if (!empty($type)) {
			$option_name_default = '_cf_' . $type . '_optimizer_settings';
		}
		return apply_filters('cfao_option_name', $option_name_default, $type);
	}
	
	/**
	 * All domains in this list will be converted to local URLs for comparison
	 **/
	protected static function _getMyDomains($type = '') {
		if (empty(self::$_MY_DOMAIN)) {
			$matches = array();
			preg_match_all('|^(.*?://)?([^/]*)|', home_url(), $matches);
			$my_domains = apply_filters('cfao_my_domains', array($matches[2][0]), $type);
			
			self::$_MY_DOMAINS = $my_domains;
		}
		return self::$_MY_DOMAINS;
	}
	
	protected static function _getMyCacheMgr($type = '') {
		if (!self::$_MY_CACHE_MGR) {
			self::$_MY_CACHE_MGR = apply_filters('cfao_cache_manager', null, $type);
		}
		return self::$_MY_CACHE_MGR;
	}
	
	protected static function _normalizeUrl($url, $type = '') {
		$return = false;
		if (!empty($url)) {
			$keep_protocol = apply_filters('cfao_keep_protocol', false, $url, $type);
			$return = $url;
			$my_domains = '(' . implode('|', array_map('preg_quote', apply_filters('cfao_drop_domains', self::_getMyDomains(), $type))) . ')';
			if (!$keep_protocol) {
				$return = preg_replace('|^.*?://|', '//', $return);
				$return = preg_replace('#^//' . $my_domains . '(/|\?)#', '$2', $return);
				if (strpos($return, '/') !== 0) {
					$return = '/' . $return;
				}
			}
		}
		return $return;
	}
	
	protected static function _isLocal($url, $type = '') {
		$my_domains = '(' . implode('|', array_map('preg_quote', self::_getMyDomains())) . ')';
		return (bool) preg_match(
			'~(^/[^/].*$)|(^(https?:)?(//)?' . $my_domains . '((/|\?|\#).*)?$)~', $url
		);
	}

}
add_action('cfao_optimizers', 'cf_asset_optimizer::register');