<?php
/**
 * CFAO List Table
 * Used to display information about CFAO plugins
 */

class CFAO_Requests_List_Table extends WP_List_Table {
	private $_component_type;
	
	function __construct($args = array()) {
		$args = array_merge(array('screen' => get_current_screen()), $args);
		parent::__construct($args);
		if (!empty($args['items']) && is_array($args['items'])) {
			$this->items = $args['items']; // Expected to be associative array of handles associated to src, enabled status, and potentially a disable_reason
		}
		if (!empty($args['type'])) {
			$this->_component_type = $args['type'];
		}
		else {
			$this->_component_type = 'undefined';
		}
		if (!empty($args['support_bulk'])) {
			$this->_support_bulk = (bool) $args['support_bulk'];
		}
		else {
			$this->_support_bulk = false;
		}
	}
	
	function ajax_user_can() {
		return current_user_can('activate_plugins');
	}
	
	function bulk_actions() {
		// Defines the bulk actions for this table.
		$section = '-header';
		if (!isset($this->_actions)) {
			$actions = array(
				'enable' => _x('Enable', $this->_component_type, 'cf-asset-optimizer'),
				'disable' => _x('Disable', $this->_component_type, 'cf-asset-optimizer'),
				'forget' => _x('Forget', $this->_component_type, 'cf-asset-optimizer'),
			);
			$this->_actions = apply_filters('cfao_'.$this->_component_type.'_list_bulk_actions', $actions);
		}
		else {
			$section = '-footer';
		}
		if (empty($this->_actions)) {
			return;
		}
		echo '<select name="cfao_action' . $section .'">';
		foreach ($this->_actions as $action => $text) {
			echo '<option value="' . esc_attr($action) . '">' . esc_html($text) . '</option>';
		}
		echo '</select>';
		submit_button( _x('Apply', $this->_component_type, 'cf-asset-optimizer'), 'action', 'submit'.$section, false, array( 'id' => "submit$section" ));
		echo "\n";
	}
	
	function prepare_items() {
		$new_items = array();
		if (empty($this->items)) {
			return;
		}
		foreach ($this->items as $handle => $data) {
			$new_items[] = array_merge(array('handle' => $handle), $data);
		}
		$this->items = $new_items;
		$this->set_pagination_args(array(
			'total_items' => count($this->items),
			'per_page' => -1
		));
	}
	
	function search_box($text, $input_id) {
		// Search interface not necessary here.
		return;
	}
	
	function get_views() {
		// Currently only supporting one.
		$item_count = count($this->items);
		return array(
			'all' => sprintf(
				'<span class="current">%s</span>',
				sprintf(
					_nx('All <span class="count">(%d)</span>', 'All <span class="count">(%d)</span>', $item_count, $this->_component_type, 'cf-asset-optimizer'),
					number_format_i18n($item_count)
				)
			),
		);
	}
	
	function get_columns() {
		$columns = array();
		if ($this->_support_bulk) {
			$columns['cb'] = '<input type="checkbox" name="" class="select-all" />';
		}
		$columns['handle'] = esc_html(_x('Handle', $this->_component_type, 'cf-asset-optimizer'));
		$columns['source'] = esc_html(_x('Source', $this->_component_type, 'cf-asset-optimizer'));
		return $columns;
	}
	
	function get_column_info() {
		return array(
			$this->get_columns(),
			array(),
			array(),
		);
	}
	
	function get_table_classes() {
		return array('widefat', 'fixed', strtolower($this->_component_type . '_list_table'), 'cfao-request-list-table');
	}
	
	function single_row($item) {
		$id = sanitize_title($item['handle']) . '-row';
		$class = ($item['enabled']) ? 'active': 'inactive';
		$cols = $this->get_columns();
		echo "<tr id=\"$id\" class=\"$class\">";
		$actions = array();
		$nonce = wp_create_nonce('cfao_nonce_'.$this->_component_type);
		if (!$item['enabled']) {
			$actions['enable'] = '<a href="'.esc_url(add_query_arg(array(
				'cfao_action' => 'enable',
				$this->_component_type => array($item['handle']),
				'cfao_nonce_'.$this->_component_type => $nonce,
			))).'">' . esc_html_x('Enable', $this->_component_type, 'cf-asset-optimizer') . '</a>';
		}
		else {
			$actions['disable'] = '<a href="'.esc_url(add_query_arg(array(
				'cfao_action' => 'disable',
				'cfao_nonce_'.$this->_component_type => $nonce,
				$this->_component_type => array($item['handle']),
			))).'">' . esc_html_x('Disable', $this->_component_type, 'cf-asset-optimizer') . '</a>';
		}
		$actions['reset'] = '<a href="'.esc_url(add_query_arg(array(
				'cfao_action' => 'forget',
				'cfao_nonce_'.$this->_component_type => $nonce,
				$this->_component_type => array($item['handle'])
			))).'">' . esc_html('Forget', $this->_component_type, 'cf-asset-optimizer') . '</a>';
		$actions = apply_filters('cfao_' . $this->_component_type . '_list_row_actions', $actions, $item, 'cfao_nonce_'.$this->_component_type, $nonce);
		foreach ($this->get_columns() as $key => $text) {
			switch ($key) {
				case 'cb':
					echo '<th scope="row" class="column-cb check-column">';
					echo '<input type="checkbox" name="' . esc_attr($this->_component_type) . '[]" value="' . esc_attr($item['handle']) . '"/>';
					echo '</th>';
					break;
				case 'handle':
					echo '<td class="' . esc_attr($key . '-col') . '">';
					echo '<div class="handle">' . esc_html($item['handle']) . '</div>';
					echo '<div class="actions">' . $this->row_actions($actions, true) . '</div>';
					echo '</td>';
					break;
				case 'source':
					echo '<td class="' . esc_attr($key . '-col') . '">';
					echo '<div class="source">' . esc_html($item['src']) . '</div>';
					echo '</td>';
					break;
				default:
					echo '<td></td>';
			}
		}
		echo '</tr>';
		if (!$item['enabled']) {
			echo '<tr class="disabled-reason"><td colspan="' . count($cols) . '" class="disabled-reason"><div class="updated">' . esc_html($item['disable_reason']) . '</div></td></tr>';
		}
		do_action('cfao_request_list_table_after_' . $this->_component_type . '_row', $item);
	}
	
	/**
	 * Copied directly from wp-list-table so we could change the nonce info.
	 */
	function display_tablenav( $which ) {
		if ( 'top' == $which )
			wp_nonce_field( 'cfao_nonce_' . $this->_component_type, 'cfao_nonce_' . $this->_component_type );
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<div class="alignleft actions">
				<?php $this->bulk_actions(); ?>
			</div>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
			<br class="clear" />
		</div>
		<?php
	}
	
}