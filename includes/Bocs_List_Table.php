<?php

class Bocs_List_Table extends WP_List_Table
{
	// we will add some code here for the table list related to bocs

	public function get_columns()
	{
		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'status'            => "Status",
			'subscription'      => "Subscription",
			'items'             => "Items",
			'total'             => "Total",
			"start_date"        => "Start Date",
			"next_payment"      => "Next Payment",
			"last_order_date"   => "Last Order Date",
			"orders"            => "Orders"

		);

		return $columns;
	}

	// Bind table with columns, data and all
	public function prepare_items()
	{
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->_get_table_data();
	}

	public function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'status':
			case 'subscription':
			case 'items':
			case 'total':
			case "start_date":
			case "next_payment":
			case "last_order_date":
			case "orders":
			default:
				return $item[$column_name];
		}
	}

	public function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="element[]" value="%s" />',
			$item['id']
		);
	}

	// Get table data
	private function _get_table_data()
	{

		/*global $wpdb;

		$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'posts WHERE post_type = %s ORDER BY ID DESC', "bocs_subscription");
		$subscriptions = $wpdb->get_results($sql);

		$result = array();

		foreach ($subscriptions as $subscription){

			$id = $subscription->ID;
			$status = $subscription->post_status;

			// get the customer
			$customer_id = get_post_meta($id, '_customer_user', true);
			$customer = get_userdata($customer_id);
			$first_name = $customer->first_name;
			$last_name = $customer->last_name;
			$title = "#" . $id . " for " . $first_name . " " . $last_name;
			$next_payment = get_post_meta( $id, 'bocs_subscription_next_payment', true);
			$start_date = get_post_meta( $id, 'bocs_subscription_start', true);
			$last_order_date = "";
			$items = 0;
			$total = 0;
			$orders = 0;

			$parent_order_id = $subscription->post_parent;

			if ($parent_order_id != 0){

				$order = wc_get_order( $parent_order_id );

				foreach ($order->get_items() as $line_item){
					$items += $line_item->get_quantity();
				}

				$total = $order->get_total();
			}

			$sql = $wpdb->prepare('SELECT post_id FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = %s AND meta_value = %d ORDER BY post_id DESC', "bocs_subscription_renewal_id", $id);
			$renewals = $wpdb->get_results($sql);

			$orders = count($renewals);

			$paid_dates = array();

			foreach ($renewals as $renewal){
				$paid_date = get_post_meta( $renewal->post_id, '_paid_date', true );
				if (!empty($paid_date)){
					$paid_dates[] = $paid_date;
				}
			}

			rsort($paid_dates);

			if (count($paid_dates) > 0){
				$last_order_date = $paid_dates[0];
			}

			$result[] = array(
				"id"                => $id,
				"status"           => $status,
				"first_name"        => $first_name,
				"last_name"         => $last_name,
				"subscription"      => $title,
				"items"             => $items,
				"total"             => $total,
				"start_date"        => $start_date,
				"next_payment"      => $next_payment,
				"last_order_date"   => $last_order_date,
				"orders"            => $orders
			);

		}

		return $result;*/

		// get the list of the subscriptions from the Bocs API
		// error_reporting(E_ALL);

		$currency_symbol = get_woocommerce_currency_symbol();

		$result = array();

		$options = get_option('bocs_plugin_options');
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => BOCS_API_URL . 'subscriptions',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				'Organization: ' . $options['bocs_headers']['organization'],
				'Content-Type: application/json',
				'Store: ' . $options['bocs_headers']['store'],
				'Authorization: ' . $options['bocs_headers']['authorization']
			),
		));

		$response = curl_exec($curl);
		$object = json_decode($response);
		curl_close($curl);
		// error_log('bocs-subscriptions start');

		if ($object) {
			if (isset($object->data)) {
				if (count($object->data) > 0) {
					// error_log(print_r(json_encode($object->data), true));
					foreach ($object->data as $subscription) {


						$status = $subscription->status;
						$subscription_price = $subscription->price;
						$products = $subscription->products;
						$name = $subscription->bocs->name;
						$type = $subscription->bocs->type;
						$contact_name = $subscription->contact->firstName . ' ' . $subscription->contact->lastName;
						$frequency = $subscription->frequency->frequency . ' ' . $subscription->frequency->timeUnit;
						if ($subscription->frequency->frequency == 1) {
							$frequency = $subscription->frequency->timeUnit;
							if (substr($frequency, -1) === 's') {
								$frequency = substr($frequency, 0, -1);
							}
						}
						$discount = $subscription->frequency->discount;
						$discount_type = $subscription->frequency->discountType;

						$date = new DateTime($subscription->nextOrderDate);
						$next_order_date = $date->format('F j, Y, g:i a');

						$activationDate = '';
						if (!empty($subscription->createdAt)) {
							$date = new DateTime($subscription->createdAt);
							$activationDate = $date->format('F j, Y, g:i a');
						}

						$total_items = 0;

						$total = 0;

						foreach ($products as $product) {
							$total_items += $product->quantity;
							$total += $product->price * $product->quantity;
						}

						$final_price = $subscription_price;
						if ($subscription_price == 0) {
							$final_price = $total;
						}

						$final_price = number_format($final_price, 2, '.', ',');

						$result[] = array(
							"id" => $subscription->id,
							"status" => $subscription->status,
							"first_name" => $subscription->contact->firstName,
							"last_name" => $subscription->contact->lastName,
							"subscription" => $name . ' for ' . $subscription->contact->firstName . ' ' . $subscription->contact->lastName,
							"items" => $total_items . ' items',
							"total" => $currency_symbol . $final_price,
							"start_date" => $activationDate,
							"next_payment" => $next_order_date,
							"last_order_date" => $activationDate,
							"orders" => 1
						);
					}
				}
			}
		}

		// error_log('bocs-subscriptions end');

		// error_reporting(0);

		return $result;
	}
}
