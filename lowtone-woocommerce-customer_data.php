<?php
/*
 * Plugin Name: WooCommerce Customer Data
 * Plugin URI: http://wordpress.lowtone.nl/plugins/woocommerce-customer_data/
 * Description: Extract data from WooCommerce customers.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2013, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\woocommerce\customer_data
 */

namespace lowtone\woocommerce\customer_data {

	use lowtone\content\packages\Package,
		lowtone\util\CSV;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	$__i = Package::init(array(
			Package::INIT_PACKAGES => array("lowtone"),
			Package::INIT_MERGED_PATH => __NAMESPACE__,
			Package::INIT_SUCCESS => function() {

				add_filter("woocommerce_reports_charts", function($charts) {
					$charts["customers"]["charts"]["data"] = array(
							"title" => __("Customer Data", "lowtone_woocommerce_customer_data"),
							"description" => "",
							"hide_title" => true,
							"function" => function() {
								global $wp_list_table;

								$columns = meta();

								if (!isset($_REQUEST["include_shipping"]))
									$columns = array_diff($columns, array(
											"_shipping_first_name",
											"_shipping_last_name",
											"_shipping_company",
											"_shipping_address_1",
											"_shipping_address_2",
											"_shipping_city",
											"_shipping_postcode",
											"_shipping_country",
											"_shipping_state",
										));

								$wp_list_table = new tables\CustomerTable(array(
										"columns" => $columns,
									));

								$wp_list_table->prepare_items();

								$wp_list_table->views();
								
								echo sprintf('<form action="%s" method="get">', esc_attr(admin_url("admin.php")));
								
								$wp_list_table->search_box(__("Search customers"), "customer");

								echo '<input type="hidden" name="page" value="woocommerce_reports" />' .
									'<input type="hidden" name="tab" value="customers" />' .
									'<input type="hidden" name="chart" value="data" />';
								
								$wp_list_table->display();
								
								echo '</form>';
							}
						);

					return $charts;
				});

				add_action("load-woocommerce_page_woocommerce_reports", function() {
					if (!(isset($_REQUEST["tab"]) && "customers" == $_REQUEST["tab"]))
						return;

					if (!(isset($_REQUEST["chart"]) && "data" == $_REQUEST["chart"]))
						return;

					if (!(isset($_REQUEST["download"]) && $_REQUEST["download"]))
						return;

					download();
				});

			}
		));


	function customers($options = NULL) {
		$options = array_merge(array(
				"meta" => meta(),
				"offset" => 0,
				"cache" => true,
				"orderby" => "post_id",
				"order" => "ASC",
			), (array) $options);

		$cache = $options["cache"];

		unset($options["cache"]);

		ksort($options);

		$key = "customers_" . md5(serialize($options));

		if ($cache && (false !== ($result = get_transient($key))))
			return $result;

		global $wpdb;

		$escape = function($val) use ($wpdb) {
			return $wpdb->_real_escape($val);
		};

		$columns = array_map(function($key) use ($escape) {
				$key = $escape($key);

				return "MAX(IF('{$key}' = `meta_key`, `meta_value`, '')) AS '{$key}'"; // Or "MAX(CASE WHEN '{$key}' = `meta_key` THEN `meta_value` END) AS '{$key}'"
			}, $options["meta"]);

		$columns = implode(",", $columns);

		$orderBy = $options["orderby"];

		if ("post_id" != $orderBy && !in_array($orderBy, $options["meta"]))
			$orderBy = "post_id";

		$orderBy = $escape($orderBy);

		$order = $escape($options["order"]);

		$query = "SELECT DISTINCT {$columns}
			FROM `{$wpdb->postmeta}`
			WHERE `post_id` IN (SELECT `ID` FROM `{$wpdb->posts}` WHERE 'shop_order' = `post_type`)
			GROUP BY `post_id`
			ORDER BY `{$orderBy}` {$order}";

		if (isset($options["s"])) {
			$search = $escape($options["s"]);

			$where = array_map(function($key) use ($escape, $search) {
					$key = $escape($key);

					return "`{$key}` LIKE '%{$search}%'";
				}, $options["meta"]);

			$where = implode(" OR ", $where);

			$query = "SELECT * FROM ({$query}) AS `c`
				WHERE {$where}";
		}

		if (isset($options["limit"])) {
			$offset = $escape($options["offset"]);
			$limit = $escape($options["limit"]);

			$query .= " LIMIT {$offset},{$limit}";
		}

		$result = $wpdb->get_results($query, ARRAY_A);

		set_transient($key, $result);

		return $result;
	}

	function meta() {
		return apply_filters("lowtone_woocommerce_customer_data_meta", array(
				"_billing_first_name",
				"_billing_last_name",
				"_billing_company",
				"_billing_address_1",
				"_billing_address_2",
				"_billing_postcode",
				"_billing_city",
				"_billing_country",
				"_billing_state",
				"_billing_email",
				"_billing_phone",
				"_shipping_first_name",
				"_shipping_last_name",
				"_shipping_company",
				"_shipping_address_1",
				"_shipping_address_2",
				"_shipping_city",
				"_shipping_postcode",
				"_shipping_country",
				"_shipping_state",
			));
	}

	function download() {
		$columns = meta();

		if (!isset($_REQUEST["include_shipping"]))
			$columns = array_diff($columns, array(
					"_shipping_first_name",
					"_shipping_last_name",
					"_shipping_company",
					"_shipping_address_1",
					"_shipping_address_2",
					"_shipping_city",
					"_shipping_postcode",
					"_shipping_country",
					"_shipping_state",
				));

		$options = array(
				"meta" => $columns,
			);

		$request = array_intersect_key($_REQUEST, array_flip(array(
				"orderby",
				"order",
				"s",
			)));

		$options = array_merge($options, $request);

		$items = customers($options);

		$csv = new CSV();

		if ($firstLine = reset($items)) {
			array_unshift($items, array_keys($firstLine));

			$csv->exchangeArray($items);
		}

		header("Content-Description: File Transfer");
		header("Content-Type: application/csv");
		header("Content-Disposition: attachment; filename=customers.csv");

		echo $csv;

		exit;
	}

}