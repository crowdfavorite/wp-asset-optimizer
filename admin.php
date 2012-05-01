<?php

class CFConcatenateStaticAdmin {

	public static function adminInit() {
		$tab = !empty($_GET['tab']) ? $_GET['tab'] : 'general';
		
		wp_enqueue_style('cfconcat-admin-css',
			admin_url('admin-ajax.php?action=cfconcat-admin-css'),
			array(),
			time()
		);
		
		$script_url = add_query_arg('tab', $tab, admin_url('admin-ajax.php?action=cfconcat-admin-js'));
		
		wp_enqueue_script('cfconcat-admin-js',
			$script_url,
			array(),
			time()
		);
	}
	
		public static function adminCSS() {
			header('Content-type: text/css');
			?>
			form#cf-concat-static-settings fieldset {
				padding: 5px;
				border: thin solid #000;
			}
			
				form#cf-concat-static-settings fieldset > ul > li {
					padding: 5px;
					margin: 5px auto;
					border-top: thin solid #000;
				}
				
				form#cf-concat-static-settings fieldset > ul > li:first-child {
					border-top: none;
					margin-top: 0px;
					padding-top: 0px;
				}
				
			form#cf-concat-static-settings div.tabs {
				margin-top: 10px;
				overflow: hidden;
			}
			
				form#cf-concat-static-settings div.tabs ul {
					overflow: hidden;
				}
				
					form#cf-concat-static-settings div.tabs li {
						float: left;
						margin: 0px 5px;
					}
					
					form#cf-concat-static-settings div.tabs li a {
						font-size: 18px;
					}
					
					form#cf-concat-static-settings div.tabs li.active-tab a {
						color: #333;
						font-weight: bold;
						text-decoration: none;
						cursor: default;
					}
			<?php
			exit();
		}
		
		public static function adminJS() {
			header('Content-type: application/javascript');
			$tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : 'general';
			?>
			jQuery(document).ready(function($) {
				var $tabsWrapper = $('<div></div>').addClass('tabs'),
					$tabsList = $('<ul></ul>'),
					$myLink, 
					tabs = {},
					$form = $('form#cf-concat-static-settings'),
					numTabs = 0;
					
				$form.find('div.tab').each(function() {
					var $this = $(this),
						myLabelText = $this.find('h2.tab-title').text();
					tabs[myLabelText] = $this.attr('id');
					$this.find('h2.tab-title').remove();
				});
				
				for (var key in tabs) {
					if (tabs.hasOwnProperty(key)) {
						numTabs ++;
					}
				}
				
				$('.concat-file-list ul').after('<a href="#" class="remove-concat-file">Remove</a>');
				
				$('a.remove-concat-file').click(function(event) {
					event.stopPropagation();
					event.preventDefault();
					$(this).parent().remove();
				});
				
				if (numTabs > 1) {
					for (var name in tabs) {
						$tabsList.append('<li><a href="#'+tabs[name]+'">'+name+'</a></li>');
					}
					
					$tabsList.find('a').click(function(e) {
						var $this = $(this);
						e.preventDefault();
						e.stopPropagation();
						$this
							.parents('ul')
								.children()
									.removeClass('active-tab')
									.end()
								.end()
							.parent()
								.addClass('active-tab')
								.end()
							.parents('form')
								.find('div.tab')
									.hide()
									.end()
								.end();
								
						$($this.attr('href')).show();
					});
					
					$form.prepend($tabsWrapper);
					$tabsWrapper.append($tabsList);
					
					$selectedTab = $tabsList.find('a[href="#cf-concat-static-settings-'+<?php echo json_encode($tab); ?>+'"]');
					
					if ($selectedTab.length > 0) {
						$selectedTab.click();
					}
					else {
						$tabsList.find('li:first-child a').click();
					}
				}
			});
			<?php
			exit();
		}

	public static function adminMenu() {
		add_options_page(
			__('CF Concatenate Static Files'),
			__('Concat Static Files'),
			'manage_options',
			'cf-concat-static-options',
			'CFConcatenateStaticAdmin::adminMenuCallback'
		);
	}
	
		public static function adminMenuCallback() {
		?>
		<?php screen_icon(); ?><h1><?php echo esc_html(__('CF Concatenate Static Files')); ?></h1>
		<p>Here you can manage the settings for dynamically concatenating and serving your static files.<p>
		<p>Each file lists its handle, information about that file, and the reason it was disabled if such was required.</p>
		<p>Files are stored in: <?php echo esc_html(CFCONCAT_CACHE_DIR); ?></p>
		<form method="post" action="" id="cf-concat-static-settings">
			<?php
			wp_nonce_field('cfconcat-save-settings', 'cfconcat-save-settings');
			self::_displayGeneralSettingsTab();
			self::_displayFileListTab('scripts');
			self::_displayFileListTab('styles');
			?>
		</form>
		<?php
		}
		
		private static function _displayGeneralSettingsTab() {
			$cfconcat_using_cache = get_option('cfconcat_using_cache', false);
			$security_key = get_option('cfconcat_security_key', '');
			$minify_js_level = get_option('cfconcat_minify_js_level', '');
		?>
			<div class="tab" id="cf-concat-static-settings-general">
				<h2 class="tab-title"><?php echo esc_html(__('General Settings')); ?></h2>
				<fieldset id="cf-concat-static-general-settings">
					<legend><h3><?php echo esc_html(__('General Settings')); ?></h3></legend>
					<ul>
						<li>
							<label for="chk_cfconcat_using_cache">
								<input type="checkbox" name="cfconcat_using_cache" id="chk_cfconcat_using_cache" value="true"<?php checked($cfconcat_using_cache, true); ?> />
								<?php echo esc_html(__('This site is using a site-caching solution.')); ?>
							</label>
							<p><?php echo esc_html(__('Select this option if your site is using a plugin like WP Super Cache, or another static caching solution, to ensure that the concatenated files are generated and served before the cache occurs.')); ?></p>
						</li>
						<?php do_action('cfconcat_general_settings_tab_list'); ?>
					</ul>
				</fieldset>
				<fieldset id="cf-concat-static-settings-minify-settings">
					<legend><h3><?php echo esc_html(__('JavaScript Minification Settings')); ?></h3></legend>
					<p>Minification of JavaScript is done through Google's Closure Compiler. Levels of minification available are listed below.
					<ul>
						<li>
							<input type="radio" name="js-minify" id="cfconcat-js-minify-none" value=""<?php checked (empty($minify_js_level)); ?> />
							<label for="cfconcat-js-minify-none"><?php echo esc_html(__('None')); ?></label>
						</li>
						<li>
							<input type="radio" name="js-minify" id="cfconcat-js-minify-wsonly" value="whitespace"<?php checked($minify_js_level, 'whitespace'); ?> />
							<label for="cfconcat-js-minify-wsonly"><?php echo esc_html(__('Whitespace only (recommended)')); ?></label>
						</li>
						<li>
							<input type="radio" name="js-minify" id="cfconcat-js-minify-simple" value="simple"<?php checked($minify_js_level, 'simple'); ?> />
							<label for="cfconcat-js-minify-simple"><?php echo esc_html(__('Simple (usually works)')); ?></label>
						</li>
						<li>
							<input type="radio" name="js-minify" id="cfconcat-js-minify-advanced" value="advanced"<?php checked($minify_js_level, 'advanced'); ?> />
							<label for="cfconcat-js-minify-advanced"><?php echo esc_html(__('Advanced (best performance, requires strict code use)')); ?></label>
						</li>
					</ul>
				</fieldset>
				<button class="button-primary" name="cfconcat_save_settings" value="save_general_settings"><?php echo esc_html('Save General Settings', true); ?></button>
			</div>
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
			
			$files = get_option('cfconcat_'.$tab_type, array());
			
			if (empty($files) || !is_array($files)) {
				$files = array();
			}
			
			?>
			<div class="tab" id="cf-concat-static-settings-<?php echo $attr_escaped_type; ?>">
				<h2 class="tab-title"><?php echo $html_escaped_name; ?></h2>
				<fieldset id="cf-concat-static-<?php echo $attr_escaped_type; ?>-list">
					<legend><h3><?php echo esc_html(__($filetab_types[$tab_type] . ' Files')); ?></h3></legend>
					<ul class="concat-file-list">
					<?php
						foreach ($files as $handle=>$details) {
						?>
						<li>
							<h4><?php echo esc_html($handle); ?></h4>
							<ul>
								<li><?php echo esc_html(__('Source: ') . $details['src']); ?><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][src]" value="<?php echo esc_attr($details['src']); ?>" /></li>
								<li><?php echo esc_html(__('Version: ') . $details['ver']); ?><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][ver]" value="<?php echo esc_attr($details['ver']); ?>" /></li>
								<li><input type="radio" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][enabled]" value="1"<?php checked($details['enabled'], true); ?> />Enabled</li>
								<li><input type="radio" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][enabled]" value=""<?php checked($details['enabled'], false); ?> />Disabled</li>
								<?php if (!($details['enabled'])) { ?>
								<li>Disabled reason: <?php echo esc_html($details['disable_reason']); ?><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][disable_reason]" value="<?php echo esc_attr($details['disable_reason']); ?>" /></li>
								<?php
								}
								if ($tab_type == 'scripts') {
								?>
								<li>
									<label><?php echo esc_html(__('Allow Minification?')); ?></label>
									<input type="radio" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][minify_script]" id="<?php echo $attr_escaped_type.'-'.$handle.'-minify_script-no'; ?>" value=""<?php checked(empty($details['minify_script'])); ?> />
									<label for="<?php echo $attr_escaped_type.'-'.$handle.'-minify_script-no'; ?>"><?php echo esc_html(__('No')); ?></label>
									<input type="radio" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][minify_script]" id="<?php echo $attr_escaped_type.'-'.$handle.'-minify_script-yes'; ?>" value="true"<?php checked(!empty($details['minify_script'])); ?> />
									<label for="<?php echo $attr_escaped_type.'-'.$handle.'-minify_script-yes'; ?>"><?php echo esc_html(__('Yes (recommended)')); ?></label>
								</li>
								<?php } ?>
							</ul>
							<?php if ($details['enabled']) { ?>
							<input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][disable_reason]" value="<?php echo esc_attr('Disabled by user'); ?>" />
							<?php } ?>
						</li>
						<?php
						}
					?>
					</ul>
				</fieldset>
				<button class="button-primary" name="cfconcat_save_settings" value="save_<?php echo $attr_escaped_type; ?>"><?php echo esc_html(__('Save '. $filetab_types[$tab_type] . ' Settings')); ?></button>
				<button class="button" name="cfconcat_save_settings" value="clear_<?php echo $attr_escaped_type; ?>_cache"><?php echo esc_html(__('Clear '. $filetab_types[$tab_type] .' Cache')); ?></button>
			</div>
			<?php
		}
		
	public static function saveSettings() {
		if (empty($_POST['cfconcat_save_settings'])) {
			// Not our time to save.
			return;
		}
		else {
			$tab = 'general';
			if (!check_admin_referer('cfconcat-save-settings', 'cfconcat-save-settings')) {
				wp_die(__('I\'m sorry, Dave. I can\'t do that.'));
			}
			if ($_POST['cfconcat_save_settings'] == 'save_general_settings') {
				if (!empty($_POST['cfconcat_using_cache'])) {
					update_option('cfconcat_using_cache', true);
				}
				else {
					update_option('cfconcat_using_cache', false);
				}
				update_option('cfconcat_security_key', md5($_SERVER['SERVER_ADDR'] . time()));
				update_option('cfconcat_minify_js_level', $_POST['js-minify']);
				do_action('cfconcat_save_general_settings', $_POST);
			}
			else if ($_POST['cfconcat_save_settings'] == 'save_scripts') {
				// Save the scripts data
				update_option('cfconcat_scripts', $_POST['scripts']);
				$tab = 'scripts';
			}
			else if ($_POST['cfconcat_save_settings'] == 'save_styles') {
				update_option('cfconcat_styles', $_POST['styles']);
				$tab = 'styles';
			}
			else if ($_POST['cfconcat_save_settings'] == 'clear_scripts_cache') {
				$dir = CFCONCAT_CACHE_DIR.'/js';
				if (is_dir($dir)) {
					$files = opendir($dir);
					if ($files) {
						while ($file = readdir($files)) {
							if (is_file($dir.'/'.$file) && (preg_match('/\.js$/', $file) || $file == '.lock')) {
								unlink($dir.'/'.$file);
							}
						}
					}
				}
				$tab = 'scripts';
			}
			else if ($_POST['cfconcat_save_settings'] == 'clear_styles_cache') {
				$dir = CFCONCAT_CACHE_DIR.'/css';
				if (is_dir($dir)) {
					$files = opendir($dir);
					if ($files) {
						while ($file = readdir($files)) {
							if (is_file($dir.'/'.$file) && (preg_match('/\.css$/', $file) || $file == '.lock')) {
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
add_action('admin_menu', 'CFConcatenateStaticAdmin::adminMenu');
add_action('admin_init', 'CFConcatenateStaticAdmin::saveSettings');
add_action('admin_init', 'CFConcatenateStaticAdmin::adminInit');
add_action('wp_ajax_cfconcat-admin-css', 'CFConcatenateStaticAdmin::adminCSS');
add_action('wp_ajax_cfconcat-admin-js', 'CFConcatenateStaticAdmin::adminJS');