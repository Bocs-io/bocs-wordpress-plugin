<table class="shop_table subscription_details">
	<tbody>
		<tr>
			<td>Status</td>
			<td>
				<p id="subscriptionStatus"><?php echo ucfirst($subscription['data']['subscriptionStatus']) ?></p>
			</td>
		</tr>
		<tr>
			<td>Start date</td>
			<td>
				<?php
				if (isset($subscription['data']['startDateGmt'])) {
					$date = new DateTime($subscription['data']['startDateGmt']);
					echo $date->format('F j, Y');
				} ?>
			</td>
		</tr>
		<tr>
			<td>Last order date</td>
			<td></td>
		</tr>
		<tr>
			<td>Next payment date</td>
			<td><?php
				if (isset($subscription['data']['nextPaymentDateGmt'])) {
					$date = new DateTime($subscription['data']['nextPaymentDateGmt']);
					echo $date->format('F j, Y');
				} ?>
			</td>
		</tr>
		<tr>
			<td>Payment</td>
			<td><span data-is_manual="no" class="subscription-payment-method"></span></td>
		</tr>
		<tr>
			<td>Actions</td>
			<td>
				<a href="#" class="button bocs-button cancel wcs_block_ui_on_click<?php echo ucfirst($subscription['data']['subscriptionStatus']) == 'Cancelled' ? ' disabled' : '' ?>"><?php echo ucfirst($subscription['data']['subscriptionStatus']) == 'Cancelled' ? 'Cancelled' : 'Cancel' ?></a>
				<a href="#" class="button bocs-button change_payment_method">Change payment</a>
				<a href="#" class="button bocs-button subscription_renewal_early">Renew now</a>
				<a href="#" class="button bocs-button subscription_pause">Pause</a>
				<p id="next-payment-date-wrapper" style="display: none;">
					<label for="next-payment-date">Next Payment (date and time):</label>
					<input type="datetime-local" id="next-payment-date" name="next-payment-date" value="<?php echo str_replace('.000Z', '', $subscription['data']['nextPaymentDateGmt']); ?>">
					<input type="button" id="next-payment-date-confirm" value="Confirm">
					<input type="button" id="next-payment-date-cancel" value="Cancel">
				</p>
			</td>
		</tr>
	</tbody>
</table>
<br />
<header>
	<h2>Bocs Subscription Totals</h2>
</header>
<table class="shop_table order_details">
	<thead>
		<tr>
			<th class="product-name">Product</th>
			<th class="product-total">Total</th>
		</tr>
	</thead>
	<tbody>
		<tr class="order_item">
			<td class="product-name">
				<?php
				// get all the list of the products and its quantity
				if ($subscription['data']['lineItems']) {
					foreach ($subscription['data']['lineItems'] as $lineItem) {
						// get the name of the product
						$wc_id = $lineItem['externalSourceId'];
						$product = wc_get_product($wc_id);
						$product_name = '';
						if ($product) {
							$product_name =  $product->get_name();
						}
						$quantity = $lineItem['quantity'];
				?>
						<p><?php echo $product_name ?> <strong class="product-quantity">× <?php echo $quantity ?></strong> </p>
				<?php
					}
				}
				?>
			</td>
			<td class="product-total">
				<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">$</span><?php echo $subscription['data']['total'] ?></span>
				<?php

				$billingInterval = 0;
				$billingPeriod = '';

				if (isset($subscription['data']['billingInterval'])) {
					$billingInterval = $subscription['data']['billingInterval'];
				}

				if (empty($billingInterval) && isset($subscription['data']['frequency']['frequency'])) {
					$billingInterval = $subscription['data']['frequency']['frequency'];
				}

				if (isset($subscription['data']['billingPeriod'])) {
					$billingPeriod = $subscription['data']['billingPeriod'];
				}

				if (empty($billingPeriod) && isset($subscription['data']['frequency']['timeUnit'])) {
					$billingPeriod = $subscription['data']['frequency']['timeUnit'];
				}

				$billingPeriod = $billingPeriod . 's';

				if ($billingInterval <= 1) {
					// Remove trailing 's' if it exists
					$billingPeriod = rtrim($billingPeriod, 's');
					$billingInterval = '';
				}

				?>every <?php echo trim($billingInterval . ' ' . $billingPeriod); ?>
			</td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<th scope="row">Subtotal:</th>
			<td><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">$</span><?php echo $subscription['data']['total'] ?></span></td>
		</tr>
		<tr>
			<th scope="row">Total:</th>
			<td><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">$</span><?php echo $subscription['data']['total'] ?></span> every <?php echo trim($billingInterval . ' ' . $billingPeriod); ?></td>
		</tr>
	</tfoot>
</table>
<?php if (count($related_orders['data']) > 0) { ?>
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
				foreach ($related_orders['data'] as $related_order) {
					// get the woocommerce order id
					$wc_order_id = 0;
					if ($related_order['externalSourceId']) {
						$wc_order_id = $related_order['externalSourceId'];
					}
					if ($wc_order_id == 0) continue;

					$order = wc_get_order($wc_order_id);

					if (!$order) {
						continue;
					}

					$item_count = $order->get_item_count();
					$order_date = $order->get_date_created();

				?>
				<tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($order->get_status()); ?>">
					<td class="order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php esc_attr_e('Order Number', 'woocommerce-subscriptions'); ?>">
						<a href="<?php echo esc_url($order->get_view_order_url()); ?>">
							<?php echo sprintf(esc_html_x('#%s', 'hash before order number', 'woocommerce-subscriptions'), esc_html($order->get_order_number())); ?>
						</a>
					</td>
					<td class="order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php esc_attr_e('Date', 'woocommerce-subscriptions'); ?>">
						<time datetime="<?php echo esc_attr($order_date->date('Y-m-d')); ?>" title="<?php echo esc_attr($order_date->getTimestamp()); ?>"><?php echo wp_kses_post($order_date->date_i18n(wc_date_format())); ?></time>
					</td>
					<td class="order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php esc_attr_e('Status', 'woocommerce-subscriptions'); ?>" style="white-space:nowrap;">
						<?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
					</td>
					<td class="order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php echo esc_attr_x('Total', 'Used in data attribute. Escaped', 'woocommerce-subscriptions'); ?>">
						<?php
						// translators: $1: formatted order total for the order, $2: number of items bought
						echo wp_kses_post(sprintf(_n('%1$s for %2$d item', '%1$s for %2$d items', $item_count, 'woocommerce-subscriptions'), $order->get_formatted_order_total(), $item_count));
						?>
					</td>
					<td class="order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
						<?php $actions = array();

						/*if ($order->needs_payment() && wcs_get_objects_property($order, 'id') == $subscription->get_last_order('ids', 'any')) {
							$actions['pay'] = array(
								'url'  => $order->get_checkout_payment_url(),
								'name' => esc_html_x('Pay', 'pay for a subscription', 'woocommerce-subscriptions'),
							);
						}*/

						/*if (in_array($order->get_status(), apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending', 'failed'), $order))) {
							$redirect = wc_get_page_permalink('myaccount');

							if (wcs_is_view_subscription_page()) {
								$redirect = $subscription->get_view_order_url();
							}

							$actions['cancel'] = array(
								'url'  => $order->get_cancel_order_url($redirect),
								'name' => esc_html_x('Cancel', 'an action on a subscription', 'woocommerce-subscriptions'),
							);
						}*/

						$actions['view'] = array(
							'url'  => $order->get_view_order_url(),
							'name' => esc_html_x('View', 'view a subscription', 'woocommerce-subscriptions'),
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
			<?php } ?>
		</tbody>
	</table>
<?php } ?>
<section class="woocommerce-customer-details">
	<h2 class="woocommerce-column__title">Billing address</h2>
	<address>
		<?php
		echo $subscription['data']['billing']['firstName'] . ' ' . $subscription['data']['billing']['lastName'];
		echo "<br />";
		if (!empty($subscription['data']['billing']['company'])) {
			echo $subscription['data']['billing']['company'];
			echo "<br />";
		}
		if (!empty($subscription['data']['billing']['address1'])) {
			echo $subscription['data']['billing']['address1'];
		}
		if (!empty($subscription['data']['billing']['address2'])) {
			echo ' ' . $subscription['data']['billing']['address2'];
		}
		if (!empty($subscription['data']['billing']['city'])) {
			echo ' ' . $subscription['data']['billing']['city'];
		}
		if (!empty($subscription['data']['billing']['state'])) {
			echo ' ' . $subscription['data']['billing']['state'];
		}
		if (!empty($subscription['data']['billing']['postcode'])) {
			echo ' ' . $subscription['data']['billing']['postcode'];
		}
		if (!empty($subscription['data']['billing']['country'])) {
			echo ' ' . $subscription['data']['billing']['country'];
		}
		echo "<br />"; ?>

		<?php if (!empty($subscription['data']['billing']['phone'])) : ?>
			<p class="woocommerce-customer-details--phone"><?php echo esc_html($subscription['data']['billing']['phone']); ?></p>
		<?php endif; ?>

		<?php if (!empty($subscription['data']['billing']['email'])) : ?>
			<p class="woocommerce-customer-details--email"><?php echo esc_html($subscription['data']['billing']['email']); ?></p>
		<?php endif; ?>
	</address>

</section>