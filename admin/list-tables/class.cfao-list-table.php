<?php
/**
 * CFAO List Table
 * Used to display information about CFAO plugins
 */

class CFAO_Plugins_List_Table extends WP_List_Table {
	private $_component_type;
	
	function __construct($args = array()) {
		$args = array_merge(array('screen' => get_current_screen()), $args);
		parent::__construct($args);
		if (!empty($args['items']) && is_array($args['items'])) {
			$this->items = $args['items']; // Expected to be class names
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
		if (!empty($args['nonce'])) {
			if (!is_array($args['nonce'])) {
				$args['nonce'] = array('_nonce' => $args['nonce']);
			}
			$this->_nonce = $args['nonce'];
		}
		else {
			$this->_nonce = array();
		}
	}
	
	function ajax_user_can() {
		return current_user_can('activate_plugins');
	}
	
	function prepare_items() {
		$new_items = array();
		foreach ($this->items as $class_name => $active) {
			if (is_callable("$class_name::listItem")) {
				$new_item = $class_name::listItem();
				$new_item['active'] = $active;
				$new_item['class_name'] = $class_name;
				$new_items[] = $new_item;
			}
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
		return array(
			'name' => __('Name'),
			'description' => __('Description'),
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
		return array('widefat', 'fixed', strtolower($this->_component_type . '_list_table'), 'cfao-list-table');
	}
	
	function display_tablenav() {
		return;
	}
	
	function single_row($item) {
		$id = sanitize_title($item['class_name']);
		$class = ($item['active']) ? 'active': 'inactive';
		echo "<tr id=\"$id\" class=\"$class\">";
		$actions = array();
		if (!$item['active']) {
			$actions['enable'] = '<a href="' . esc_url(add_query_arg(array_merge(array('cfao_action' => 'activate', $this->_component_type => array($item['class_name'])), $this->_nonce))) . '">Enable</a>';
		}
		else {
			$actions['disable'] = '<a href="' . esc_url(add_query_arg(array_merge(array('cfao_action' => 'deactivate', $this->_component_type => array($item['class_name'])), $this->_nonce))) . '">Disable</a>';
		}
		$nonce_field = array_keys($this->_nonce);
		$nonce_val;
		if (empty($nonce_field)) {
			$nonce_field = '';
		}
		else {
			$nonce_field = $nonce_field[0];
			$nonce_val = $this->_nonce[$nonce_field];
		}
		$actions = apply_filters('cfao_plugin_row_actions', $actions, $this->_component_type, $item, $this, $nonce_field, $nonce_val);
		foreach ($this->get_columns() as $key => $text) {
			switch ($key) {
				case 'name':
					echo '<td class="plugin-name"><strong>' . esc_html($item['title']) . '</strong>';
					echo $this->row_actions($actions, true);
					break;
				case 'description':
					echo '<td class="plugin-desc">' . esc_html($item['description']);
					break;
				default:
					break;
			}
			echo '</td>';
		}
		echo '</tr>';
		do_action('cfao_plugins_list_table_after_' . $this->_component_type . '_row', $item);
	}
	
}