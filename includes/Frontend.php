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

	}


	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts()
	{

		wp_register_script( $this->option_name.'_service', 'https://mypik-widget-build.s3.ap-southeast-2.amazonaws.com/mypik.js', array('jquery'), '1.0', true );
		wp_enqueue_script( $this->option_name.'_service');

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