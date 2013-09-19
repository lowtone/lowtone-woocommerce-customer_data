<?php
namespace lowtone\woocommerce\customer_data\tables;
use lowtone\wp\admin\listtables\ListTable,
	lowtone\net\URL;

/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2013, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\woocommerce\customer_data\tables
 */
class CustomerTable extends ListTable {

	public function __construct($args = array()) {
		parent::__construct(array(
				"plural" => "customers",
				"singular" => "customer",
			));

		$args = array_merge(array(
				"columns" => \lowtone\woocommerce\customer_data\meta(),
			), (array) $args);

		$this->columns = $args["columns"];
	}

	public function prepare_items() {
		$options = array(
				"meta" => $this->columns,
			);

		$request = array_intersect_key($_REQUEST, array_flip(array(
				"orderby",
				"order",
				"s",
			)));

		$options = array_merge($options, $request);

		$items = \lowtone\woocommerce\customer_data\customers($options);

		$totalItems = count($items);

		$itemsPerPage = 25;

		$offset = ($this->get_pagenum() - 1) * $itemsPerPage;

		$this->items = array_slice($items, $offset, $itemsPerPage);

		$this->_column_headers = array( 
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		$this->set_pagination_args(array(
			"total_items" => $totalItems,
			"per_page"    => $itemsPerPage,
			"total_pages" => ceil($totalItems / $itemsPerPage)
		));

	}

	public function has_items() {
		return count($this->items) > 0;
	}

	public function get_views() {
		return array();
	}

	public function extra_tablenav($which) {
		switch ($which) {
			case  "top":
				$downloadUrl = URL::fromCurrent()->appendQuery("download=1");

				echo '<div class="alignleft actions">' . 
					sprintf('<a href="%s" class="button">', esc_url($downloadUrl)) . __("Download", "lowtone_woocommerce_customer_data") . '</a>' . 
					'</div>';

				echo '<div class="alignleft actions" style="line-height: 24px">' . 
					sprintf('<input id="lowtone_woocommerce_customer_data_include_shipping" type="checkbox" name="include_shipping" value="1" style="margin-right: .5em" onchange="this.form.submit()" %s>', isset($_REQUEST["include_shipping"]) && $_REQUEST["include_shipping"] ? 'checked="checked"' : "") . 
					'<label for="lowtone_woocommerce_customer_data_include_shipping">' . __("Include shipping details", "lowtone_woocommerce_customer_data") . '</label>' .
					'</div>';

				break;
		}
	}

	public function get_columns() {
		$columns = apply_filters("lowtone_woocommerce_customer_data_table_columns", array(
				"_billing_first_name" => __("First Name", "lowtone_woocommerce_customer_data"),
				"_billing_last_name" => __("Last Name", "lowtone_woocommerce_customer_data"),
				"_billing_company" => __("Company", "lowtone_woocommerce_customer_data"),
				"_billing_address_1" => __("Address 1", "lowtone_woocommerce_customer_data"),
				"_billing_address_2" => __("Address 2", "lowtone_woocommerce_customer_data"),
				"_billing_postcode" => __("Postcode", "lowtone_woocommerce_customer_data"),
				"_billing_city" => __("City", "lowtone_woocommerce_customer_data"),
				"_billing_country" => __("Country", "lowtone_woocommerce_customer_data"),
				"_billing_state" => __("State", "lowtone_woocommerce_customer_data"),
				"_billing_email" => __("Email", "lowtone_woocommerce_customer_data"),
				"_billing_phone" => __("Phone", "lowtone_woocommerce_customer_data"),
				"_shipping_first_name" => __("Shipping First Name", "lowtone_woocommerce_customer_data"),
				"_shipping_last_name" => __("Shipping Last Name", "lowtone_woocommerce_customer_data"),
				"_shipping_company" => __("Shipping Company", "lowtone_woocommerce_customer_data"),
				"_shipping_address_1" => __("Shipping Address 1", "lowtone_woocommerce_customer_data"),
				"_shipping_address_2" => __("Shipping Address 2", "lowtone_woocommerce_customer_data"),
				"_shipping_city" => __("Shipping City", "lowtone_woocommerce_customer_data"),
				"_shipping_postcode" => __("Shipping Postcode", "lowtone_woocommerce_customer_data"),
				"_shipping_country" => __("Shipping Country", "lowtone_woocommerce_customer_data"),
				"_shipping_state" => __("Shipping State", "lowtone_woocommerce_customer_data"),
			));

		return array_intersect_key($columns, array_flip($this->columns));
	}

		public function get_sortable_columns() {
			return array(
					"_billing_first_name" => array("_billing_first_name", false),
					"_billing_last_name" => array("_billing_last_name", false),
				);
		}

	public function get_hidden_columns() {
		return array();
	}

	public function column_default($item, $column) {
		return (string) $item[$column];
	}

	public function column__billing_email($item) {
		return sprintf('<a href="mailto:%s">', esc_attr($email = $item["_billing_email"])) . 
			esc_html($email) . 
			'</a>';
	}

}