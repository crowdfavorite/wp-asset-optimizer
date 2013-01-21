<?php
/*
Template: Advanced Settings
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
?>
<div class="advanced" style="display:none;">
	<label for="chk_cfao_using_cache">
		<input type="checkbox" name="cfao_using_cache" id="chk_cfao_using_cache" value="true" <?php checked($cfao_using_cache, true); ?> />
		<?php echo esc_html(__('This site is using a site-caching solution.')); ?>
	</label>
	<span class="cachehelp-hover">
		<span class="icon"> ?
		</span>
		<p class="cachehelp">
			<?php echo esc_html(__('Select this option if your site is using a plugin like WP Super Cache, or another static caching solution, to ensure that the concatenated files are generated and served before the cache occurs.')); ?>
		</p>
	</span>
	<section id="ao-js" class="section">
		<div class="main">
			<div class="section-info">
				<h3 class="section-header"><?php echo esc_html(__('Javascript')); ?></h3>
				<span class="last-updated">
					<?php echo esc_html(__('Latest version:')); ?> <time>12/12/32 at 12:41:11pm</time>
				</span>
			</div>
			<table id="js-table" class="files"  cellspacing="0">
				<thead>
					<tr>
						<th><a href="#"><?php echo esc_html(__('Order')); ?><span class="icon">&#8691;
						</span></a></th>
						<th class="filename"><a href="#"><?php echo esc_html(__('Name')); ?><span class="icon">&#8691;
						</span></a></th>
						<th class="center"><?php echo esc_html(__('Compile')); ?>
							<br />
							<input type="checkbox" id="js-compile-all" />
						</th>
						<th class="center"><?php echo esc_html(__('Minify')); ?>
							<br />
							<input type="checkbox" id="js-min-all" >
						</th>
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
			<div>
				<ul class="js-minset">
					<li>
						<input name="js-minify" id="minset-whitespace" type="radio" value="whitespace"<?php checked($minify_js_level, 'whitespace'); ?> />
						<label for="minset-whitespace">Whitespace only (recommended)</label>
					</li>
					<li>
						<input name="js-minify" id="minset-simple" type="radio" value="simple"<?php checked($minify_js_level, 'simple'); ?> />
						<label for="minset-simple">Simple (usually works)</label>
					</li>
					<li>
						<input name="js-minify" id="minset-advanced" type="radio" value="advanced"<?php checked($minify_js_level, 'advanced'); ?> />
						<label for="minset-advanced">Advanced (best performance, but requires strict code use)</label>
					</li>
				</ul>
			</div>
			<button id="js-obliterate" class="button" name="cfao_save_settings" value="clear_scripts_cache"><?php echo esc_html(__('Obliterate JS Files')); ?></button>
		</div>
	</section>
	<section id="ao-css" class="section">
		<div class="main">
			<div class="section-info">
				<h3 class="section-header">CSS</h3>
				<span class="last-updated">
					Latest version: <time>12/12/32 at 12:41:11pm</time>
				</span>
			</div>
			<table id="css-table" class="files"  cellspacing="0">
				<thead>
					<tr>
						<th><a href="#">Order<span class="icon">&#8691;
						</span></a></th>
						<th class="filename"><a href="#">Name<span class="icon">&#8691;
						</span></a></th>
						<th class="center">Compile
							<br />
							<input type="checkbox" id="css-compile-all" />
						</th>
					</tr>
					<tr>
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
			<button id="css-obliterate" class="button" name="cfao_save_settings" value="clear_styles_cache"><?php echo esc_html(__('Obliterate CSS Files')); ?></button>
		</div>
	</section>
</div>
