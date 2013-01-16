<?php
/*
  Template: Javascript File
  Author: Crowd Favorite
  Author URI: http://crowdfavorite.com
 */

$reason_value = !empty($details['disable_reason']) ? $details['disable_reason'] : __('Enter reason for disabling this file.');

$enabled = !empty($details['enabled']);
$row_class = $enabled ? 'compiled' : 'not';
$input_disabled = $enabled ? '' : ' disabled="disabled"';

$attr_escaped_type = esc_attr($tab_type);
$input_class = $tab_type == 'scripts' ? 'js-compile' : 'css-compile';

?>
<tr class="<?php echo $row_class; ?>">
	<td class="reason-hover">
		<p class="index"><?php echo $index; ?></p>
		<p class="icon">O</p>
		<div class="reason">
			<div>
				<label for="reason-value-<?php echo $index; ?>">Reason:</label>
				<input type="text" name="reason-value" class="reason-value" id="reason-value-<?php echo $index; ?>" value="<?php esc_html($reason_value); ?>"></input>
				<input type="submit" />
			</div>
		</div>
	</td>
	<td><b><?php echo esc_html($handle); ?></b><br/>
		<!--<?php echo esc_html(__('Version: ') . $details['ver']); ?>--><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][ver]" value="<?php echo esc_attr($details['ver']); ?>" />
		<?php echo esc_html(__('Source: ') . $details['src']); ?><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][src]" value="<?php echo esc_attr($details['src']); ?>" /></td>
	<td class="center">
		<input type="checkbox" class="<?php echo $input_class; ?>" id="com-<?php echo $index; ?>" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][enabled]" value="1"<?php checked($details['enabled'], true); ?> />
	</td>
	<?php if ($tab_type == 'scripts') { ?>
	<td class="center">
		<input type="checkbox" class="js-min" id="min-<?php echo $index; ?>" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][minify_script]" value="true"<?php checked(!empty($details['minify_script'])); ?> <?php echo $input_disabled; ?> />
	</td>
	<?php } ?>
</tr>
