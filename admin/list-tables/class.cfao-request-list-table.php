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
		if (!empty($args['item_header'])) {
			$this->_item_header = $args['item_header'];
		}
		else {
			$this->_item_header = __('Item');
		}
	}
	
	function ajax_user_can() {
		return current_user_can('activate_plugins');
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
					_nx('All <span class="count">(%d)</span>', 'All <span class="count">(%d)</span>', $item_count, $this->_component_type),
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
		return array(
			'cb' => '',
			'item_details' => esc_html($this->_item_header),
		);
	}
	
	function get_column_info() {
		return array(
			$this->get_columns(),
			array(),
			array(),
		);
	}
	
	function get_table_classes() {
		return array('widefat', 'fixed', strtolower($this->_component_type . '_list_table'));
	}
	
	function display_tablenav() {
		return;
	}
	
	function single_row($item) {
		$id = sanitize_title($item['handle']) . '-row';
		$class = ($item['enabled']) ? 'active': 'inactive';
		echo "<tr id=\"$id\" class=\"$class\">";
		$actions = array();
		if (!$item['enabled']) {
			$actions['enable'] = '<a href="'.esc_url(add_query_arg(array(
				'action' => $this->_component_type . '_activate',
				$this->_component_type => array($item['handle']),
			))).'">'.esc_html(__('Enable')).'</a>';
		}
		else {
			$actions['disable'] = '<a href="'.esc_url(add_query_arg(array(
				'action' => $this->_component_type . '_deactivate',
				$this->_component_type => array($item['handle']),
			))).'">'.esc_html(__('Disable')).'</a>';
		}
		$actions['reset'] = '<a href="'.esc_url(add_query_arg(array('action' => $this->_component_type.'_forget', $this->_component_type => array($item['handle'])))).'">'.esc_html(__('Forget')).'</a>';
		foreach ($this->get_columns() as $key => $text) {
			switch ($key) {
				case 'cb':
					echo '<td class="' . esc_attr($key.'-col') . '">';
					echo '<input type="checkbox" name="' . esc_attr($this->_component_type) . '[]" value="' . $item['handle'] . '" />';
					echo '</td>';
					break;
				case 'item_details':
					echo '<td class="' . esc_attr($key.'-col') . '">';
					echo '<div class="item-title">' . esc_html($item['handle']) . '</div>';
					echo '<p class="item-description">' . esc_html($item['src']) . '</p>';
					if (!$item['enabled'] && !empty($item['disable_reason'])) {
						echo '<p class="disabled-reason">' . esc_html($item['disable_reason']) . '</p>';
					}
					echo $this->row_actions($actions, true);
					echo '</td>';
					break;
				default:
					echo '<td></td>';
			}
		}
		echo '</tr>';
		do_action('cfao_request_list_table_after_' . $this->_component_type . '_row', $item);
	}
	
}