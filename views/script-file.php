<?php
/*
  Template: Javascript File
  Author: Crowd Favorite
  Author URI: http://crowdfavorite.com
 */

$reason_value = !empty($details['disable_reason']) ? $details['disable_reason'] : __('Enter reason for disabling this file.');
$reason_help = "<div class='reason-help'><span class='reason-help-close'>&#x33d;</span><p>Tip: You can hover over the '?' of any disabled file to bring up the 'Disabled Reason' field, where you can save notes for why you chose to disable certain files.</p></div>";

$enabled = !empty($details['enabled']);
$row_class = $enabled ? 'compiled' : 'not';
$input_disabled = $enabled ? '' : ' disabled="disabled"';

$attr_escaped_type = esc_attr($tab_type);
$input_class = $tab_type == 'scripts' ? 'js-compile' : 'css-compile';

$file_src = CFAssetOptimizerAdmin::getRelativePath($details['src']);

?>
<tr class="<?php echo $row_class; ?>">
	<td class="reason-hover">
		<p class="index"><?php echo $index; ?></p>
<!-- 		<?php echo $reason_help ?> -->
	</td>
	<td><b><?php echo esc_html($handle); ?> </b>
		<span class="reason">
				<label for="reason-value-<?php echo $tab_type . '-' . $index; ?>">Reason</label><span class="separator">-</span>
				<?php if (empty($read_only)) { ?>
				<input type="text" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][disable_reason]" class="reason-value" id="reason-value-<?php echo $tab_type . '-' . $index; ?>" value="<?php echo esc_attr($reason_value); ?>" />
				<button class="reason-submit" name="cfao_save_settings" value="save_settings">submit</button>
				<?php } else {?>
				<span class="reason-value" ><?php echo esc_html($reason_value); ?></span>
				<?php } ?>
		</span><br/>
		<!--<?php echo esc_html(__('Version: ') . $details['ver']); ?>--><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][ver]" value="<?php echo esc_attr($details['ver']); ?>" />
		<?php echo esc_html($file_src); ?><input type="hidden" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][src]" value="<?php echo esc_attr($details['src']); ?>" /></td>
	<?php if (empty($read_only)) { ?>
	<td class="center">
		<input type="checkbox" class="<?php echo esc_attr($input_class); ?>" id="com-<?php echo $index; ?>" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][enabled]" value="1"<?php checked($details['enabled'], true); ?> />
	</td>
	<?php if ($tab_type == 'scripts') { ?>
	<td class="center">
		<input type="checkbox" class="js-min" id="min-<?php echo $index; ?>" name="<?php echo $attr_escaped_type; ?>[<?php echo esc_attr($handle); ?>][minify_script]" value="true"<?php checked(!empty($details['minify_script'])); ?> <?php echo $input_disabled; ?> />
	</td>
	<?php 
		}
	}
	?>
</tr>
