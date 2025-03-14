<?php

class Frontend {

	private $plugin_name;
	private $version;
	public $shipping;
	private $option_name;

	public function __construct()
	{
		$this->plugin_name = BOCS_NAME;
		$this->version = BOCS_VERSION;
		$this->option_name = BOCS_SLUG;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 */
	public function enqueue_styles()
	{
		// Only enqueue BOCS subscription styles when needed
		if (is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('view-order')) {
			// For checkout page, check if cart contains BOCS subscription
			if (is_checkout() && !is_wc_endpoint_url('order-received')) {
				// Initialize Bocs_Cart to check if cart contains a BOCS subscription
				$bocs_cart = new Bocs_Cart();
				if ($bocs_cart->cart_contains_bocs_subscription()) {
					$this->enqueue_bocs_subscription_styles();
				}
			} 
			// For thank you/order page, check if order contains BOCS subscription
			else if (is_wc_endpoint_url('order-received') || is_wc_endpoint_url('view-order')) {
				// Get order ID
				global $wp;
				$order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 
							(isset($wp->query_vars['view-order']) ? $wp->query_vars['view-order'] : 0);
				
				if ($order_id) {
					$order = wc_get_order($order_id);
					if ($order && $order->get_meta('__bocs_bocs_id')) {
						$this->enqueue_bocs_subscription_styles();
					}
				}
			}
		}
	}

	/**
	 * Enqueue BOCS subscription styles
	 */
	private function enqueue_bocs_subscription_styles() 
	{
		wp_enqueue_style(
			'bocs-subscription-style',
			plugin_dir_url(dirname(__FILE__)) . 'assets/css/bocs-subscription-style.css',
			array(),
			BOCS_VERSION,
			'all'
		);
		
		// Add BOCS branding class to body
		add_filter('body_class', function($classes) {
			$classes[] = 'has-bocs-subscription';
			return $classes;
		});
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts()
	{

		wp_enqueue_script( 'bocs', 'https://mypik-widget-build.s3.ap-southeast-2.amazonaws.com/mypik.js', NULL, BOCS_VERSION, true);

	}

    public function bocs_add_to_cart_and_redirect(){

        if( is_page( 'process-bocs' ) ) { // you can also pass a page ID instead of a slug

            WC()->cart->empty_cart();

            // get the products with the quantities to be added

            // WC()->cart->add_to_cart( 74 );

            wp_safe_redirect( wc_get_checkout_url() );
            exit();

        }
    }

}