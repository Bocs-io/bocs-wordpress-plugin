<?php

class Bocs_Cart
{

    public function add_subscription_options_to_cart()
    {
        // echo 'add_subscription_options_to_cart';
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs-cart-convert-to-subscription.php';

        $product_ids = array();

        // get the current bocs subscription id
        $bocs_id = ! empty($bocs_id) ? $bocs_id : '';

        if (empty($bocs_id) && isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');

            if (empty($bocs_id)) {
                if (isset($_COOKIE['__bocs_id'])) {
                    $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
                }
            }
        }

        if (empty($bocs_id)) {

            // Check if WooCommerce is active and the cart is not empty
            if (WC()->cart && ! WC()->cart->is_empty()) {
                // Loop through the cart items
                foreach (WC()->cart->get_cart() as $cart_item) {
                    // Get the product ID and add it to the array
                    $product_ids[] = $cart_item['product_id'];
                }
            }

            // get the list of available bocs
            $bocs_class = new Bocs_Bocs();
            $bocs_options = array();
            $bocs_list = $bocs_class->get_all_bocs();
            $total = 0;
            if (! empty($bocs_list) && ! empty($product_ids)) {

                foreach ($bocs_list as $bocs_item) {
                    $bocs_wp_ids = array();
                    // loop on its products
                    if (! empty($bocs_item['products'])) {
                        foreach ($bocs_item['products'] as $bocs_product) {
                            $wp_id = $bocs_product['externalSourceId'];
                            $bocs_wp_ids[] = $wp_id;
                            $wc_product = wc_get_product($product_id);
                            $total += $wc_product->get_regular_price();
                        }
                    }
                    if (empty(array_diff($product_ids, $bocs_wp_ids))) {
                        // this bocs is can be an option
                        $bocs_options[] = $bocs_item;
                    }
                }

                if (empty($bocs_options)) {
                    foreach ($bocs_list as $bocs_item) {
                        $bocs_item['products'] = [];
                        foreach ($product_ids as $product_id) {
                            $wc_product = wc_get_product($product_id);
                            $bocs_item['products'][] = array(
                                "description" => $wc_product->get_description(),
                                "externalSource" => "WP",
                                "externalSourceId" => $product_id,
                                "id" => "", // get the bocs id
                                "name" => $wc_product->get_name(), // get the woocoomerce product name
                                "price" => $wc_product->get_regular_price(),
                                "quantity" => 1,
                                "regularPrice" => $wc_product->get_regular_price(),
                                "salePrice" => $wc_product->get_price(),
                                "sku" => $wc_product->get_sku(),
                                "stockQuantity" => 0
                            );
                        }
                    }
                }
            }
        }

        // get the list of the bocs that we can have for the choices/options

        if (file_exists($template_path) && ! empty($bocs_options)) {
            include $template_path;
        }
    }

    public function bocs_cart_totals_before_shipping()
    {
        // Output the custom message
        ?>
        <div class="wc-block-components-totals-wrapper slot-wrapper">
        	<div class="wc-block-components-order-meta">
        		<div class="wcs-recurring-totals-panel">
        			<div class="wc-block-components-totals-item wcs-recurring-totals-panel__title">
        				<span class="wc-block-components-totals-item__label">Monthly recurring total</span>
        				<span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value">$19.48</span>
        				<div class="wc-block-components-totals-item__description">
        					<span>Starting: July 4, 2024 </span>
    					</div>
					</div>
					<div class="wcs-recurring-totals-panel__details wc-block-components-panel">
						<div>
							<button aria-expanded="false" class="wc-block-components-panel__button">
    							<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true" class="wc-block-components-panel__button-icon" focusable="false">
    								<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
    							</svg>
    							Details
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
        <?php
    }

    public function bocs_review_order_after_cart_contents()
    {
        echo 'bocs_review_order_after_cart_contents';
    }

    public function bocs_review_order_before_order_total()
    {
        echo '<tr class="custom-text-before-subtotal"><th>Additional Info:</th><td>Your custom message here.</td></tr>';
    }

    public function bocs_cart_totals_before_order_total()
    {
        error_log('bocs_cart_totals_before_order_total');
        echo '<tr class="custom-text-before-subtotal"><th>Additional Info:</th><td>Your custom message here.</td></tr>';
    }

    public function cart_contains_bocs_subscription(){
        
        // get the current bocs subscription id
        $bocs_id = ! empty($bocs_id) ? $bocs_id : '';

        if (empty($bocs_id) && isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');

            if (empty($bocs_id)) {
                if (isset($_COOKIE['__bocs_id'])) {
                    $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
                }
            }
        }

        return ! empty($bocs_id);
    }
}
