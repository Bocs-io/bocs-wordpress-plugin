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
<h2>Bocs Subscription Totals</h2>
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