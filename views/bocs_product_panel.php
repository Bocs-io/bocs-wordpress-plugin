<div id='bocs_product_options' class='panel woocommerce_options_panel'>
	<div class='options_group'>
		<?php

		woocommerce_wp_select(
			array(
				'id' => 'bocs_product_interval',
				'name' => 'bocs_product_interval',
				'label' => __('Interval', 'bocs-wordpress'),
				'desc_tip' => true,
				'description' => __('Select the subscription interval unit', 'bocs-wordpress'),
				'options' => array(
					'days' => __('Days', 'bocs-wordpress'),
					'weeks' => __('Weeks', 'bocs-wordpress'),
					'months' => __('Months', 'bocs-wordpress'),
					'years' => __('Years', 'bocs-wordpress')
				)
			)
		);

		?>
	</div>
	<div class='options_group'>
		<?php

		woocommerce_wp_text_input(
			array(
				'id' => 'bocs_product_interval_count',
				'name' => 'bocs_product_interval_count',
				'label' => __('Interval Count', 'bocs-wordpress'),
				'desc_tip' => true,
				'description' => __('Enter the number of intervals between subscription renewals', 'bocs-wordpress'),
				'type' => 'number',
				'custom_attributes' => array(
					'min' => '1',
					'step' => '1'
				)
			)
		);

		?>
	</div>
	<div class='options_group'>
		<?php

		woocommerce_wp_select(
			array(
				'id' => 'bocs_product_discount_type',
				'name' => 'bocs_product_discount_type',
				'label' => __('Discount Type', 'bocs-wordpress'),
				'desc_tip' => true,
				'description' => __('Select the type of discount to apply to subscription', 'bocs-wordpress'),
				'options' => array(
					'fixed' => __('Fixed Amount', 'bocs-wordpress'),
					'percentage' => __('Percentage', 'bocs-wordpress')
				)
			)
		);

		?>
	</div>
	<div class='options_group'>
		<?php

		woocommerce_wp_text_input(
			array(
				'id' => 'bocs_product_discount',
				'name' => 'bocs_product_discount',
				'label' => __('Discount Amount', 'bocs-wordpress'),
				'desc_tip' => true,
				'description' => __('Enter the discount amount to apply to subscription', 'bocs-wordpress'),
				'type' => 'number',
				'custom_attributes' => array(
					'min' => '0',
					'step' => '0.01'
				)
			)
		);

		?>
	</div>
</div>