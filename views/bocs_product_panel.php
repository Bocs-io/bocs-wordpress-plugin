<div id='bocs_product_options' class='panel woocommerce_options_panel'>
	<div class='options_group'>
		<?php

		woocommerce_wp_select(
			array(
				'id' => 'bocs_product_interval',
				'name' => 'bocs_product_interval',
				'label' => __( 'Interval', 'bocs_product_interval' ),
				'desc_tip' => 'true',
				'description' => __( 'Unit interval', 'bocs_product_interval_desc' ),
				'options' => array(
					'days' => 'Days',
					'weeks' => 'Weeks',
					'months' => 'Months',
					'years' => 'Years'
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
				'label' => __( 'Interval Count', 'bocs_product_interval_count' ),
				'desc_tip' => 'true',
				'description' => __( 'Unit count interval', 'bocs_product_interval_count_desc' ),
				'type' => 'number'
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
				'label' => __( 'Discount Type', 'bocs_product_discount_type' ),
				'desc_tip' => 'true',
				'description' => __( 'Discount Type', 'bocs_product_discount_type_desc' ),
				'options' => array(
					'fixed' => 'Fixed',
					'percentage' => 'Percentage'
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
				'label' => __( 'Discount amount', 'bocs_product_discount' ),
				'desc_tip' => 'true',
				'description' => __( 'Discount', 'bocs_product_discount_desc' ),
				'type' => 'number'
			)
		);

		?>
	</div>
</div>