<table class="shop_table subscription_details">
	<tbody>
		<tr>
			<td>Status</td>
			<td><?php echo ucfirst($subscription['data']['subscriptionStatus'])?></td>
		</tr>
		<tr>
			<td>Start date</td>
			<td><?php
                $date = new DateTime($subscription['data']['startDateGmt']);
                echo $date->format('F j, Y')?>
            </td>
		</tr>
		<tr>
			<td>Last order date</td>
			<td></td>
		</tr>
		<tr>
			<td>Next payment date</td>
			<td><?php
                $date = new DateTime($subscription['data']['nextPaymentDateGmt']);
                echo $date->format('F j, Y');?>
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
			</td>
		</tr>
	</tbody>
</table>