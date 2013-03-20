<?php
/*
Template: General Settings
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
?>
<div class="settings clearfix">
	<div class="button-pref">
		<input type="radio" name="compile_setting" id="on" value="on"<?php checked($compile_setting, 'on'); ?> />
		<label id="btn-on" class="button-select btn-active option" for="on">On</label>
		<span><?php echo esc_html(__('This will automatically recompile all CSS and JS files whenever they change.')); ?></span>
	</div>
	<div class="button-pref">
		<input type="radio" name="compile_setting" id="off" value="off"<?php checked($compile_setting, 'off'); ?> />
		<label id="btn-off" class="option button-select" for="off">Off</label>
		<span><?php echo esc_html(__('This will stop any files from being compiled.')); ?></span>
	</div>
	<div class="button-pref">
		<input type="radio" name="compile_setting" id="custom" value="custom"<?php checked($compile_setting, 'custom'); ?> />
		<label id="btn-custom" class="option button-select" for="custom">Custom</label>
		<span><?php echo esc_html(__('This lets you choose what you want compiled and what you want to exclude.')); ?></span>
	</div>
</div>
<div class="basic-list">
	<section class="section">
		<div class="main">
			<div class="section-info">
				<h3 class="section-header"><?php echo esc_html(__('Javascript')); ?></h3>
				<?php if (!empty($script_latest_version)) { ?>
				<span class="last-updated">
					<?php echo esc_html(__('Latest version:')); ?> <time> <?php echo date('Y/n/j \a\t g:i:sa', $script_latest_version); ?></time>
				</span>
				<?php } ?>
			</div>
			<table id="js-table" class="files"  cellspacing="0">
				<thead>
					<tr>
						<th><a href="#"><?php echo esc_html(__('Order')); ?><span class="icon">&#8691;
						</span></a></th>
						<th class="filename"><a href="#"><?php echo esc_html(__('Name')); ?><span class="icon">&#8691;
						</span></a></th>
					</tr>
					<tr>
					</tr>
				</thead>
				<?php
					$index = 1;
					$tab_type = 'scripts';
					foreach ($scripts as $handle => $details) {
						include('script-file.php');
						$index++;
					}
				?>
			</table>
		</div>
	</section>
	<section class="section">
		<div class="main">
			<div class="section-info">
				<h3 class="section-header">CSS</h3>
				<?php if (!empty($styles_latest_version)) { ?>
				<span class="last-updated">
					<?php echo esc_html(__('Latest version:')); ?> <time> <?php echo date('Y/n/j \a\t g:i:sa', $styles_latest_version); ?></time>
				</span>
				<?php } ?>
			</div>
			<table id="css-table" class="files"  cellspacing="0">
				<thead>
					<tr>
						<th><a href="#">Order<span class="icon">&#8691;
						</span></a></th>
						<th class="filename"><a href="#">Name<span class="icon">&#8691;
						</span></a></th>
					</tr>
				</thead>
				<?php
					$index = 1;
					$tab_type = 'styles';
					foreach ($styles as $handle => $details) {
						include ('script-file.php');
						$index++;
					}
				?>
			</table>
		</div>
	</section>
</div>
