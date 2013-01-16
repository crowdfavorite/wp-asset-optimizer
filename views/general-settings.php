<?php
/*
Template: General Settings
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
?>
<div class="settings">
	<div>
		<input type="radio" name="setting" id="on" value="on"<?php checked($compile_setting, 'on'); ?> />
		<label class="option" for="on">ON</label>
		<span>| <?php echo esc_html(__('This will automatically recompile all CSS and JS files whenever they change.')); ?></span>
	</div>
	<div>
		<input type="radio" name="setting" id="off" value="off"<?php checked($compile_setting, 'off'); ?> />
		<label class="option" for="off">OFF</label>
		<span>| <?php echo esc_html(__('This will stop any files from being compiled.')); ?></span>
	</div>
	<div>
		<input type="radio" name="setting" id="custom" value="custom"<?php checked($compile_setting, 'custom'); ?> />
		<label class="option" for="custom">CUSTOM</label>
		<span>| <?php echo esc_html(__('This lets you choose what you want compiled and what you want to exclude.')); ?></span>
	</div>
</div>
