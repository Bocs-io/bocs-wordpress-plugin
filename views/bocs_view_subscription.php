<table class="shop_table subscription_details">
	<tbody>
		<tr>
			<td><?php esc_html_e('Status', 'bocs-wordpress'); ?></td>
			<td>
				<p id="subscriptionStatus"><?php echo esc_html(ucfirst($subscription['data']['subscriptionStatus'])); ?></p>
			</td>
		</tr>
		<tr>
			<td><?php esc_html_e('Start date', 'bocs-wordpress'); ?></td>
			<td>
				<?php
				if (isset($subscription['data']['startDateGmt'])) {
					try {
						$startDate = new DateTime($subscription['data']['startDateGmt']);
						echo esc_html($startDate->format('F j, Y'));
					} catch (Exception $e) {
						error_log(sprintf(
							/* translators: %s: Error message */
							__('Critical: Error formatting start date: %s', 'bocs-wordpress'),
							$e->getMessage()
						));
					}
				}
				?>
			</td>
		</tr>
		<tr>
			<td><?php esc_html_e('Last order date', 'bocs-wordpress'); ?></td>
			<td></td>
		</tr>
		<tr>
			<td><?php esc_html_e('Next payment date', 'bocs-wordpress'); ?></td>
			<td>
				<?php
				if (isset($subscription['data']['nextPaymentDateGmt'])) {
					try {
						$nextPaymentDate = new DateTime($subscription['data']['nextPaymentDateGmt']);
						echo esc_html($nextPaymentDate->format('F j, Y'));
					} catch (Exception $e) {
						error_log(sprintf(
							/* translators: %s: Error message */
							__('Critical: Error formatting next payment date: %s', 'bocs-wordpress'),
							$e->getMessage()
						));
					}
				}
				?>
				<br />
				<input type="date" 
					   id="updated-next-payment-date" 
					   name="updated-next-payment-date" 
					   style="display: none;"
					   aria-label="<?php esc_attr_e('Select next payment date', 'bocs-wordpress'); ?>">
				<br />
				<button class="button bocs-button" 
						id="next-payment-date-button" 
						data-subscription-id="<?php echo esc_attr($subscription['data']['id']); ?>">
					<?php esc_html_e('Set Next Payment Date', 'bocs-wordpress'); ?>
				</button>
			</td>
		</tr>
		<tr>
			<td><?php esc_html_e('Payment', 'bocs-wordpress'); ?></td>
			<td><span data-is_manual="no" class="subscription-payment-method"></span></td>
		</tr>
		<tr>
			<td><?php esc_html_e('Actions', 'bocs-wordpress'); ?></td>
			<td>
				<?php
				$is_cancelled = ucfirst($subscription['data']['subscriptionStatus']) == 'Cancelled';
				?>
				<a href="#" 
				   class="button bocs-button cancel wcs_block_ui_on_click<?php echo $is_cancelled ? ' disabled' : ''; ?>">
					<?php echo esc_html($is_cancelled ? __('Cancelled', 'bocs-wordpress') : __('Cancel', 'bocs-wordpress')); ?>
				</a>
				<a href="#" class="button bocs-button change_payment_method">
					<?php esc_html_e('Change payment', 'bocs-wordpress'); ?>
				</a>
				<a href="#" class="button bocs-button subscription_renewal_early">
					<?php esc_html_e('Renew now', 'bocs-wordpress'); ?>
				</a>
				<a href="#" class="button bocs-button subscription_pause">
					<?php esc_html_e('Pause', 'bocs-wordpress'); ?>
				</a>
				<p id="next-payment-date-wrapper" style="display: none;">
					<label for="next-payment-date">
						<?php esc_html_e('Next Payment (date and time):', 'bocs-wordpress'); ?>
					</label>
					<input type="datetime-local" 
						   id="next-payment-date" 
						   name="next-payment-date" 
						   value="<?php echo esc_attr(str_replace('.000Z', '', $subscription['data']['nextPaymentDateGmt'])); ?>">
					<input type="button" 
						   id="next-payment-date-confirm" 
						   value="<?php esc_attr_e('Confirm', 'bocs-wordpress'); ?>">
					<input type="button" 
						   id="next-payment-date-cancel" 
						   value="<?php esc_attr_e('Cancel', 'bocs-wordpress'); ?>">
				</p>
			</td>
		</tr>
	</tbody>
</table>
<br />
<header>
	<h2><?php esc_html_e('Bocs Subscription Totals', 'bocs-wordpress'); ?></h2>
</header>
<table class="shop_table order_details">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
			<th class="product-total"><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr class="order_item">
			<td class="product-name">
				<?php
				if ($subscription['data']['lineItems']) {
					foreach ($subscription['data']['lineItems'] as $lineItem) {
						try {
							$wc_id = $lineItem['externalSourceId'];
							$product = wc_get_product($wc_id);
							$product_name = '';
							if ($product) {
								$product_name = $product->get_name();
							}
							$quantity = $lineItem['quantity'];
							?>
							<p>
								<?php echo esc_html($product_name); ?> 
								<strong class="product-quantity">
									<?php 
									/* translators: %s: Product quantity */
									printf(esc_html__('× %s', 'bocs-wordpress'), esc_html($quantity)); 
									?>
								</strong>
							</p>
							<?php
						} catch (Exception $e) {
							error_log(sprintf(
								/* translators: 1: Product ID, 2: Error message */
								__('Critical: Error processing product #%1$s: %2$s', 'bocs-wordpress'),
								$wc_id,
								$e->getMessage()
							));
						}
					}
				}
				?>
			</td>
			<td class="product-total">
				<span class="woocommerce-Price-amount amount">
					<span class="woocommerce-Price-currencySymbol">$</span>
					<?php echo esc_html($subscription['data']['total']); ?>
				</span>
				<?php
				// Calculate and format billing interval and period
				$subscriptionBillingInterval = 0;
				$subscriptionBillingPeriod = '';

				if (isset($subscription['data']['billingInterval'])) {
					$subscriptionBillingInterval = $subscription['data']['billingInterval'];
				} else if (isset($subscription['data']['frequency']['frequency'])) {
					$subscriptionBillingInterval = $subscription['data']['frequency']['frequency'];
				}

				if (isset($subscription['data']['billingPeriod'])) {
					$subscriptionBillingPeriod = $subscription['data']['billingPeriod'];
				} else if (isset($subscription['data']['frequency']['timeUnit'])) {
					$subscriptionBillingPeriod = $subscription['data']['frequency']['timeUnit'];
				}

				$subscriptionBillingPeriod = $subscriptionBillingPeriod . 's';
				if ($subscriptionBillingInterval <= 1) {
					$subscriptionBillingPeriod = rtrim($subscriptionBillingPeriod, 's');
					$subscriptionBillingInterval = '';
				}
				?>
				<?php esc_html_e('every', 'bocs-wordpress'); ?> 
				<?php echo esc_html(trim($subscriptionBillingInterval . ' ' . $subscriptionBillingPeriod)); ?>
			</td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<th scope="row"><?php esc_html_e('Subtotal:', 'bocs-wordpress'); ?></th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<span class="woocommerce-Price-currencySymbol">$</span>
					<?php echo esc_html($subscription['data']['total']); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('Total:', 'bocs-wordpress'); ?></th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<span class="woocommerce-Price-currencySymbol">$</span>
					<?php echo esc_html($subscription['data']['total']); ?>
				</span> 
				<?php esc_html_e('every', 'bocs-wordpress'); ?> 
				<?php echo esc_html(trim($subscriptionBillingInterval . ' ' . $subscriptionBillingPeriod)); ?>
			</td>
		</tr>
	</tfoot>
</table>
<?php

error_log('related_orders: ' . json_encode($related_orders));

/**
 * Related Orders Section
 * Displays all orders associated with this subscription.
 * Each order shows:
 * - Order number
 * - Date
 * - Status
 * - Total amount
 * - Available actions
 *
 * @param array $related_orders Array of orders linked to this subscription
 */
if (isset($related_orders['data']) && !empty($related_orders['data'])) {
    ?>
	<br />
	<header>
		<h2>Related Orders</h2>
	</header>
	<table class="shop_table shop_table_responsive my_account_orders woocommerce-orders-table woocommerce-MyAccount-orders woocommerce-orders-table--orders">

		<thead>
			<tr>
				<th class="order-number woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr">Order</span></th>
				<th class="order-date woocommerce-orders-table__header woocommerce-orders-table__header-order-date woocommerce-orders-table__header-order-date"><span class="nobr">Date</span></th>
				<th class="order-status woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr">Status</span></th>
				<th class="order-total woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr">Total</span></th>
				<th class="order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">&nbsp;</th>
			</tr>
		</thead>

		<tbody> <?php

    $woocommerceOrderIds = [];
    if (count($related_orders['data'])) {
        foreach ($related_orders['data'] as $relatedOrder) {
            if (isset($relatedOrder['externalSourceId']) && $relatedOrder['externalSourceId']) {
                $orderId = intval($relatedOrder['externalSourceId']);
                if ($orderId != 0) {
                    $woocommerceOrderIds[] = $orderId;
                }
            }
        }
    }

    if (isset($related_orders['order_ids']) && is_array($related_orders['order_ids'])) {
        foreach ($related_orders['order_ids'] as $order_id) {
            $woocommerceOrderIds[] = intval($order_id);
        }
    }

    $woocommerceOrderIds = array_unique($woocommerceOrderIds);

    foreach ($woocommerceOrderIds as $wc_order_id) {
        // get the woocommerce order id

        $order = wc_get_order($wc_order_id);

        if (! $order) {
            continue;
        }

        $item_count = $order->get_item_count();
        $order_date = $order->get_date_created();

        ?>
				<tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php

        echo esc_attr($order->get_status());
        ?>">
					<td class="order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php

        esc_attr_e('Order Number', 'woocommerce-subscriptions');
        ?>">
						<a href="<?php

        echo esc_url($order->get_view_order_url());
        ?>">
							<?php

        echo sprintf(esc_html_x('#%s', 'hash before order number', 'woocommerce-subscriptions'), esc_html($order->get_order_number()));
        ?>
						</a>
					</td>
					<td class="order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php

        esc_attr_e('Date', 'woocommerce-subscriptions');
        ?>">
						<time datetime="<?php

        echo esc_attr($order_date->date('Y-m-d'));
        ?>" title="<?php

        echo esc_attr($order_date->getTimestamp());
        ?>"><?php

        echo wp_kses_post($order_date->date_i18n(wc_date_format()));
        ?></time>
					</td>
					<td class="order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php

        esc_attr_e('Status', 'woocommerce-subscriptions');
        ?>" style="white-space:nowrap;">
						<?php

        echo esc_html(wc_get_order_status_name($order->get_status()));
        ?>
					</td>
					<td class="order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php

        echo esc_attr_x('Total', 'Used in data attribute. Escaped', 'woocommerce-subscriptions');
        ?>">
						<?php
        // translators: $1: formatted order total for the order, $2: number of items bought
        echo wp_kses_post(sprintf(_n('%1$s for %2$d item', '%1$s for %2$d items', $item_count, 'woocommerce-subscriptions'), $order->get_formatted_order_total(), $item_count));
        ?>
					</td>
					<td class="order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
						<?php

        $actions = array();

        /*
         * if ($order->needs_payment() && wcs_get_objects_property($order, 'id') == $subscription->get_last_order('ids', 'any')) {
         * $actions['pay'] = array(
         * 'url' => $order->get_checkout_payment_url(),
         * 'name' => esc_html_x('Pay', 'pay for a subscription', 'woocommerce-subscriptions'),
         * );
         * }
         */

        /*
         * if (in_array($order->get_status(), apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending', 'failed'), $order))) {
         * $redirect = wc_get_page_permalink('myaccount');
         *
         * if (wcs_is_view_subscription_page()) {
         * $redirect = $subscription->get_view_order_url();
         * }
         *
         * $actions['cancel'] = array(
         * 'url' => $order->get_cancel_order_url($redirect),
         * 'name' => esc_html_x('Cancel', 'an action on a subscription', 'woocommerce-subscriptions'),
         * );
         * }
         */

        $actions['view'] = array(
            'url' => $order->get_view_order_url(),
            'name' => esc_html_x('View', 'view a subscription', 'woocommerce-subscriptions')
        );

        $actions = apply_filters('woocommerce_my_account_my_orders_actions', $actions, $order);

        if ($actions) {
            foreach ($actions as $key => $action) {
                echo wp_kses_post('<a href="' . esc_url($action['url']) . '" class="woocommerce-button button ' . sanitize_html_class($key) . '">' . esc_html($action['name']) . '</a>');
            }
        }
        ?>
					</td>
				</tr>
			<?php
    }
    ?>
		</tbody>
	</table>
<?php
}
?>
<section class="woocommerce-customer-details">
	<h2 class="woocommerce-column__title"><?php esc_html_e('Billing address', 'bocs-wordpress'); ?></h2>
	<address>
		<?php
		$billing = $subscription['data']['billing'];
		echo esc_html($billing['firstName'] . ' ' . $billing['lastName']);
		echo "<br />";
		if (!empty($billing['company'])) {
			echo esc_html($billing['company']) . "<br />";
		}
		
		$address_parts = array_filter([
			$billing['address1'],
			$billing['address2'],
			$billing['city'],
			$billing['state'],
			$billing['postcode'],
			$billing['country']
		]);
		
		echo esc_html(implode(' ', $address_parts));
		echo "<br />";

		if (!empty($billing['phone'])) {
			echo '<p class="woocommerce-customer-details--phone">' . esc_html($billing['phone']) . '</p>';
		}

		if (!empty($billing['email'])) {
			echo '<p class="woocommerce-customer-details--email">' . esc_html($billing['email']) . '</p>';
		}
		?>

	</address>

</section>
<?php
/**
 * BOCS API Integration Script
 * Handles the next payment date modification functionality.
 * 
 * Features:
 * - Date picker for selecting new payment date
 * - API integration for updating subscription details
 * - Error handling and validation
 * - User feedback through alerts
 * 
 * Required Configuration:
 * - BOCS API URL
 * - Organization header
 * - Store header
 * - Authorization header
 * 
 * @requires WordPress option 'bocs_plugin_options' with valid API headers
 */
$options = get_option('bocs_plugin_options');
$options['bocs_headers'] = $options['bocs_headers'] ?? array();

// Validate required API headers
$required_headers = ['organization', 'store', 'authorization'];
$missing_headers = [];

foreach ($required_headers as $header) {
    if (empty($options['bocs_headers'][$header])) {
        $missing_headers[] = ucfirst($header);
    }
}

if (!empty($missing_headers)) {
    error_log('BOCS API Error: Missing required headers: ' . implode(', ', $missing_headers));
} else {
?>
    <script>
        /**
         * BOCS API Configuration Object
         * Contains the API endpoint and required headers for authentication
         */
        const bocsApiConfig = {
            apiUrl: '<?php echo BOCS_API_URL; ?>',
            headers: {
                Organization: '<?php echo $options['bocs_headers']['organization']; ?>',
                Store: '<?php echo $options['bocs_headers']['store']; ?>',
                Authorization: '<?php echo $options['bocs_headers']['authorization']; ?>'
            }
        };
        
        /**
         * Next Payment Date Update Handler
         * Manages the UI and API interaction for updating subscription payment dates.
         * 
         * Features:
         * - Toggle between view/edit modes
         * - Date validation
         * - API error handling
         * - User feedback
         */
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('updated-next-payment-date');
            const toggleButton = document.getElementById('next-payment-date-button');
            let isEditing = false;

            toggleButton.addEventListener('click', function() {
                if (!isEditing) {
                    // Enter edit mode
                    dateInput.style.display = 'inline-block';
                    toggleButton.textContent = 'Update';
                } else {
                    // Process the update
                    const selectedDate = dateInput.value;
                    
                    if (selectedDate) {
                        // Format date for API
                        const formattedDate = new Date(selectedDate).toISOString();
                        const subscriptionId = toggleButton.dataset.subscriptionId;
                        
                        if (!subscriptionId) {
                            console.error('Subscription ID is missing');
                            alert('Error: Unable to update subscription. Missing subscription ID.');
                            return;
                        }
                        
                        // Send update request to BOCS API
                        updateNextPaymentDate(subscriptionId, formattedDate);
                    }

                    // Exit edit mode
                    dateInput.style.display = 'none';
                    toggleButton.textContent = 'Set Next Payment Date';
                }
                isEditing = !isEditing;
            });

            /**
             * Sends an API request to update the next payment date
             * @param {string} subscriptionId - The ID of the subscription to update
             * @param {string} formattedDate - The new payment date in ISO format
             */
            function updateNextPaymentDate(subscriptionId, formattedDate) {
                fetch(`${bocsApiConfig.apiUrl}subscriptions/${subscriptionId}`, {
                    method: 'PUT',
                    headers: {
                        ...bocsApiConfig.headers,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        nextPaymentDateGmt: formattedDate
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Success:', data);
                    alert('Next payment date updated successfully!');
                })
                .catch((error) => {
                    console.error('Error:', error);
                    alert('Error: Failed to update next payment date. Please try again.');
                });
            }
        });
    </script>
<?php
}
?>