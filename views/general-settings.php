<?php
/*
Template: General Settings
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
?>
<div class="tab" id="cf-asset-optimizer-settings-general">
	<h2 class="tab-title"><?php echo esc_html(__('General Settings')); ?></h2>
	<fieldset id="cf-asset-optimizer-general-settings">
		<legend><h3><?php echo esc_html(__('General Settings')); ?></h3></legend>
		<ul>
			<li>
				<label for="chk_cfao_using_cache">
					<input type="checkbox" name="cfao_using_cache" id="chk_cfao_using_cache" value="true"<?php checked($cfao_using_cache, true); ?> />
					<?php echo esc_html(__('This site is using a site-caching solution.')); ?>
				</label>
				<p><?php echo esc_html(__('Select this option if your site is using a plugin like WP Super Cache, or another static caching solution, to ensure that the concatenated files are generated and served before the cache occurs.')); ?></p>
			</li>
			<?php do_action('cfao_general_settings_tab_list'); ?>
		</ul>
	</fieldset>
	<fieldset id="cf-asset-optimizer-settings-minify-settings">
		<legend><h3><?php echo esc_html(__('JavaScript Minification Settings')); ?></h3></legend>
		<p><?php echo esc_html(__('Minification of JavaScript is done through Google\'s Closure Compiler. Levels of minification available are listed below.')); ?></p>
		<ul>
			<li>
				<input type="radio" name="js-minify" id="cfao-js-minify-none" value=""<?php checked (empty($minify_js_level)); ?> />
				<label for="cfao-js-minify-none"><?php echo esc_html(__('None')); ?></label>
			</li>
			<li>
				<input type="radio" name="js-minify" id="cfao-js-minify-wsonly" value="whitespace"<?php checked($minify_js_level, 'whitespace'); ?> />
				<label for="cfao-js-minify-wsonly"><?php echo esc_html(__('Whitespace only (recommended)')); ?></label>
			</li>
			<li>
				<input type="radio" name="js-minify" id="cfao-js-minify-simple" value="simple"<?php checked($minify_js_level, 'simple'); ?> />
				<label for="cfao-js-minify-simple"><?php echo esc_html(__('Simple (usually works)')); ?></label>
			</li>
			<li>
				<input type="radio" name="js-minify" id="cfao-js-minify-advanced" value="advanced"<?php checked($minify_js_level, 'advanced'); ?> />
				<label for="cfao-js-minify-advanced"><?php echo esc_html(__('Advanced (best performance, requires strict code use)')); ?></label>
			</li>
		</ul>
	</fieldset>
	<button class="button-primary" name="cfao_save_settings" value="save_general_settings"><?php echo esc_html('Save General Settings', true); ?></button>
</div>