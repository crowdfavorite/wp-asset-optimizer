<?php
/*
Template: File List
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
?>
<div class="tab" id="cf-asset-optimizer-settings-<?php echo $attr_escaped_type; ?>">
	<h2 class="tab-title"><?php echo $html_escaped_name; ?></h2>
	<?php if (in_array($tab_type, array('scripts', 'styles'))) { ?>
	<p><?php echo esc_html(sprintf(__('%s files are stored in: '), $filetab_types[$tab_type])); ?>
	<?php
		if ($tab_type == 'scripts') {
			echo CFAssetOptimizerScripts::getCacheDir();
		}
		else if ($tab_type == 'styles') {
			echo CFAssetOptimizerStyles::getCacheDir();
		}
	?>
	</p>
	<p><?php echo esc_html(__('Please be sure the above directory is writable, or could be created, by the web user.')); ?></p>
	<?php } ?>
	<fieldset id="cf-asset-optimizer-<?php echo $attr_escaped_type; ?>-list">
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
	<button class="button-primary" name="cfao_save_settings" value="save_<?php echo $attr_escaped_type; ?>"><?php echo esc_html(__('Save '. $filetab_types[$tab_type] . ' Settings')); ?></button>
	<button class="button" name="cfao_save_settings" value="clear_<?php echo $attr_escaped_type; ?>_cache"><?php echo esc_html(__('Clear '. $filetab_types[$tab_type] .' Cache')); ?></button>
</div>