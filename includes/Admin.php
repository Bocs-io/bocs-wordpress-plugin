<?php

class Admin
{
    private $save_widget_nonce = '';

    public function __construct(){
        $this->save_widget_nonce = wp_create_nonce('save-widget-nonce');
    }

	/**
	 * added bocs log handler
	 * 
	 */
	public function bocs_register_log_handlers( $handlers ){

		array_push( $handlers, new Bocs_Log_Handler() );

		return $handlers;
	}

	/**
	 * Updates the bocs product based on the app parameters
	 *
	 * @param $post_id
	 * @return void
	 */
	public function bocs_process_product_meta($post_id){

		if (isset( $_POST['bocs_product_interval'] )){
			update_post_meta( $post_id, 'bocs_product_interval', esc_attr( trim($_POST['bocs_product_interval']) ) );
		}

		if (isset( $_POST['bocs_product_interval_count'] )){
			update_post_meta( $post_id, 'bocs_product_interval_count', esc_attr( trim($_POST['bocs_product_interval_count']) ) );
		}

		if (isset( $_POST['bocs_product_discount_type'] )){
			update_post_meta( $post_id, 'bocs_product_discount_type', esc_attr( trim($_POST['bocs_product_discount_type']) ) );
		}

		if (isset( $_POST['bocs_product_discount'] )){
			update_post_meta( $post_id, 'bocs_product_discount', esc_attr( trim($_POST['bocs_product_discount']) ) );
		}

	}

	public function bocs_admin_custom_js(){
		require_once dirname( __FILE__ ) . '/../views/bocs_admin_custom.php';
	}

	public function bocs_product_panel(){
		require_once dirname( __FILE__ ) . '/../views/bocs_product_panel.php';
	}

	public function bocs_product_tab( $tabs ){

		$tabs['bocs'] = array(
			'label' => __( 'Bocs Product', 'bocs_product' ),
			'target' => 'bocs_product_options',
			'class' => 'show_if_bocs'
		);

		$tabs['general']['class'][] = 'show_if_bocs';
		$tabs['inventory']['class'][] = 'show_if_bocs';

		return $tabs;

	}

	public function register_bocs_product_type(){

		require_once plugin_dir_path(dirname(__FILE__)).'includes/WC_Bocs_Product_Type.php';

		class_exists('WC_Bocs_Product_Type');

		add_filter('product_type_selector', [$this, 'add_custom_product_type']);
	}

	public function add_custom_product_type($types){
		$types['bocs'] = __( 'Bocs Product', 'woocommerce-bocs' ); // The label for your custom product type.
		return $types;
	}

	public function bocs_widget_script_register(){

        // this to make sure that the keys were added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

		// get the settings
		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		wp_enqueue_style('font-awesome', plugin_dir_url(__FILE__) . '../assets/css/font-awesome.min.css', null, '0.0.1');
        wp_enqueue_style("bocs-custom-block-css", plugin_dir_url(__FILE__) . '../assets/css/bocs-widget.css', null, '0.0.15');

		wp_enqueue_script('jquery');

		wp_register_script("bocs-custom-block",
            plugin_dir_url(__FILE__) . '../assets/js/bocs-widget.js',
            array(
                'wp-components',
                'wp-block-editor',
                'wp-blocks',
                'wp-i18n',
                'wp-editor',
                'wp-data',
                'jquery'
            ),
            '0.0.199');

        // get the current post id
		$post_id = get_the_ID();
        $selected_widget_id = get_post_meta( $post_id, 'selected_bocs_widget_id', true );
		$selected_widget_name = get_post_meta( $post_id, 'selected_bocs_widget_name', true );

        $params = array(
	        'bocsURL' => BOCS_API_URL . "bocs",
	        'collectionsURL' => BOCS_API_URL . "collections",
	        'Organization' => $options['bocs_headers']['organization'],
	        'Store' => $options['bocs_headers']['store'],
	        'Authorization' => $options['bocs_headers']['authorization'],
	        'nonce' => $this->save_widget_nonce,
	        'ajax_url' => admin_url('admin-ajax.php'),
            'selected_id' => $selected_widget_id,
            'selected_name' => $selected_widget_name
        );

		wp_enqueue_script("bocs-custom-block");
        wp_localize_script('bocs-custom-block', 'bocs_widget_object', $params);
	}

	public function admin_enqueue_scripts(){
		wp_enqueue_style("bocs-admin-css", plugin_dir_url(__FILE__) . '../assets/css/admin.css', null, '0.0.2');

		// get the settings
		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		wp_register_script("bocs-admin-js", plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '0.0.13');
		wp_enqueue_script("bocs-admin-js");

		wp_localize_script('bocs-admin-js', 'ajax_object', array(
			'bocsURL' => BOCS_API_URL . "bocs",
			'collectionsURL' => BOCS_API_URL . "collections",
			'Organization' => $options['bocs_headers']['organization'],
			'Store' => $options['bocs_headers']['store'],
			'Authorization' => $options['bocs_headers']['authorization']
		));

	}

	public function enqueue_scripts(){

        // this to make sure that the keys were added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		wp_enqueue_script( "bocs-widget-script", "https://feature-testing.app.bocs.io/widget/js/script.js", array(), '0.0.7', true );

		if (class_exists('woocommerce')) {
			wp_enqueue_script('wc-add-to-cart');
			wp_enqueue_script('wc-cart-fragments');
		}

        $redirect = wc_get_checkout_url();

		wp_enqueue_script( "bocs-add-to-cart", plugin_dir_url( __FILE__ ) . '../assets/js/add-to-cart.js', array('jquery', 'bocs-widget-script'), '0.0.72', true );
		wp_localize_script('bocs-add-to-cart', 'ajax_object', array(
			'cartNonce' => wp_create_nonce( 'wc_store_api' ),
			'cartURL' => $redirect,
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('ajax-nonce'),
			'search_nonce'    => wp_create_nonce('ajax-search-nonce'),
			'bocsGetUrl' => BOCS_API_URL . 'bocs/',
			'storeId' => $options['bocs_headers']['store'],
			'orgId' => $options['bocs_headers']['organization'],
			'authId' => $options['bocs_headers']['authorization'],
			'update_product_nonce' => wp_create_nonce('ajax-update-product-nonce'),
			'couponNonce' => wp_create_nonce('ajax-create-coupon-nonce')
		));
	}

	public function bocs_homepage(){
		require_once dirname( __FILE__ ) . '/../views/bocs_homepage.php';
	}

	public function bocs_tags(){
		require_once dirname( __FILE__ ) . '/../views/bocs_tag.php';
	}

	public function bocs_categories(){
		require_once dirname( __FILE__ ) . '/../views/bocs_category.php';
	}

	private function _bocs_post_contact(){

		$options = get_option( 'bocs_plugin_options' );

		if (isset($_POST)){
			if( isset( $_POST["bocs_plugin_options"]['sync_contacts_to_bocs'] ) ){
				$options['sync_contacts_to_bocs'] = $_POST["bocs_plugin_options"]["sync_contacts_to_bocs"];
			}
			if( isset( $_POST["bocs_plugin_options"]['sync_contacts_from_bocs'] ) ){
				$options['sync_contacts_from_bocs'] = $_POST["bocs_plugin_options"]["sync_contacts_from_bocs"];
			}
			if( isset( $_POST["bocs_plugin_options"]["sync_daily_contacts_to_bocs"] ) ){
				$options['sync_daily_contacts_to_bocs'] = $_POST["bocs_plugin_options"]["sync_daily_contacts_to_bocs"];
			}
			if( isset( $_POST["bocs_plugin_options"]["sync_daily_contacts_from_bocs"] ) ){
				$options['sync_daily_contacts_from_bocs'] = $_POST["bocs_plugin_options"]["sync_daily_contacts_from_bocs"];
			}
		}

		update_option('bocs_plugin_options', $options);

	}

	public function bocs_contact_page(){
		$this->_bocs_post_contact();
		require_once dirname( __FILE__ ) . '/../views/bocs_setting.php';
	}

	private function _bocs_post_headers_settings(){
		$options = get_option( 'bocs_plugin_options' );

		if (isset($_POST)){
			if( isset( $_POST["bocs_plugin_options"]['bocs_headers'] ) ){
				$options['bocs_headers']['store'] = $_POST["bocs_plugin_options"]["bocs_headers"]['store'];
				$options['bocs_headers']['organization'] = $_POST["bocs_plugin_options"]["bocs_headers"]['organization'];
				$options['bocs_headers']['authorization'] = $_POST["bocs_plugin_options"]["bocs_headers"]['authorization'];
			}
		}

		update_option('bocs_plugin_options', $options);
	}

	private function _bocs_post_sync_options(){
		// @TODO
	}

	/**
	 * The page for the list of errors
	 * 
	 * @return void
	 */
	public function bocs_error_logs_page(){
		
		require_once dirname( __FILE__ ) . '/../views/bocs_error_logs.php';

	}

	public function bocs_sync_store_page(){

		$this->_bocs_post_sync_options();
		require_once dirname( __FILE__ ) . '/../views/bocs_sync_store.php';

	}

	public function bocs_settings_page(){
		$this->_bocs_post_headers_settings();
		require_once dirname( __FILE__ ) . '/../views/bocs_settings.php';
	}

	/**
	 * @return void
	 */
	public function bocs_add_settings_page() {
		// add_options_page( 'Bocs', 'Bocs', 'manage_options', 'bocs-plugin', [$this, 'bocs_render_plugin_settings_page'] );
		add_menu_page("Bocs", "Bocs", 'manage_options', 'bocs', [$this, 'bocs_homepage'], 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 36 36" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlnsSerif="http://www.serif.com/" aria-hidden="true" focusable="false" style="fill-rule: evenodd; clip-rule: evenodd; stroke-linejoin: round; stroke-miterlimit: 2;"><g transform="matrix(1,0,0,1,-647.753,-303.839)"><g transform="matrix(1,0,0,1,-8.46249,-21.314)"><path d="M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z" style="fill: rgb(0, 132, 139);"></path></g></g></svg>'), 2);
		add_submenu_page("bocs", "Subscriptions", "Subscriptions", "manage_options", 'bocs-subscriptions', [$this, 'bocs_list_subscriptions'] );
		add_submenu_page("bocs", "Settings", "Settings", "manage_options", 'bocs-settings', [$this, 'bocs_settings_page'] );
		// add_submenu_page("bocs", "Sync Store", "Sync Store", "manage_options", 'bocs-sync-store', [$this, 'bocs_sync_store_page'] );
		// add_submenu_page("bocs", "Error Logs", "Error Logs", "manage_options", 'bocs-error-logs', [$this, 'bocs_error_logs_page'] );

		remove_submenu_page('bocs','bocs');
	}

	public function bocs_list_subscriptions(){
		require_once dirname( __FILE__ ) . '/../views/bocs_list_subscriptions.php';
	}

	/**
	 * @return void
	 */
	public function bocs_render_plugin_settings_page() {
		?>
		<h2>Bocs Settings</h2>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'bocs_plugin_options' );
			do_settings_sections( 'bocs_plugin' ); ?>
			<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
		</form>
		<?php
	}

	public function bocs_register_settings() {

		register_setting( 'bocs_plugin_options', 'bocs_plugin_options', [$this, 'bocs_plugin_options_validate'] );

		add_settings_section( 'api_settings', 'API Settings', [$this, 'bocs_plugin_section_text'], 'bocs_plugin' );

		// value for the API Key
		add_settings_field( 'bocs_plugin_setting_api_key', 'Public API Key', [$this, 'bocs_plugin_setting_api_key'], 'bocs_plugin', 'api_settings' );

		// enable/disable the sync from wordpress to bocs
		add_settings_field('bocs_plugin_setting_sync_contacts_to_bocs', 'Sync Contacts to Bocs', [$this, 'bocs_plugin_setting_sync_contacts_to_bocs'], 'bocs_plugin', 'api_settings' );

		// enable/disable the daily auto sync from wordpress to bocs
		add_settings_field('bocs_plugin_setting_sync_daily_contacts_to_bocs', 'Daily Autosync Contacts to Bocs', [$this, 'bocs_plugin_setting_sync_daily_contacts_to_bocs'], 'bocs_plugin', 'api_settings' );

		// enable/disable the daily auto sync from wordpress to bocs
		add_settings_field('bocs_plugin_setting_sync_daily_contacts_from_bocs', 'Daily Autosync Contacts From Bocs', [$this, 'bocs_plugin_setting_sync_daily_contacts_from_bocs'], 'bocs_plugin', 'api_settings' );

	}

	public function bocs_plugin_section_text(){
		echo '<p>Here you can set all the options for using the API</p>';
	}

	/**
	 * API Key setting
	 *
	 * @return void
	 */
	public function bocs_plugin_setting_api_key() {

		$options = get_option( 'bocs_plugin_options' );

		echo "<input id='bocs_plugin_setting_api_key' name='bocs_plugin_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
	}

	/**
	 * Option for enabling/disabling the sync from wordpress to bocs
	 *
	 * @return void
	 */
	public function bocs_plugin_setting_sync_contacts_to_bocs(){

		$options = get_option( 'bocs_plugin_options' );

		$options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

		$html = '<input id="bocs_plugin_setting_sync_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="1"';

		$html .= $options['sync_contacts_to_bocs'] == 1 ? ' checked' : '';

		$html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input  id="bocs_plugin_setting_sync_contacts_to_bocs_no"  type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="0"';

		$html .= $options['sync_contacts_to_bocs'] != 1 ? ' checked' : '';

		$html .= '><label for="0">No</label>';

		$html .= '<br /><button class="button button-primary" id="syncContactsToBocs" type="button">Sync Now</button><p id="syncContactsToBocs-response"></p>';

		echo $html;
	}

	public function bocs_plugin_setting_sync_daily_contacts_to_bocs(){

		$options = get_option( 'bocs_plugin_options' );

		// Daily Autosync

		$options['sync_daily_contacts_to_bocs'] = $options['sync_daily_contacts_to_bocs'] ?? 0;

		$html = '<input id="bocs_plugin_setting_sync_daily_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="1"';

		$html .= $options['sync_daily_contacts_to_bocs'] == 1 ? ' checked' : '';

		$html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input  id="bocs_plugin_setting_sync_daily_contacts_to_bocs_no"  type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="0"';

		$html .= $options['sync_daily_contacts_to_bocs'] != 1 ? ' checked' : '';

		$html .= '><label for="0">No</label>';

		echo $html;
	}

	public function bocs_plugin_setting_sync_daily_contacts_from_bocs(){

		$options = get_option( 'bocs_plugin_options' );

		$options['sync_daily_contacts_from_bocs'] = $options['sync_daily_contacts_from_bocs'] ?? 0;

		$html = '<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="1"';

		$html .= $options['sync_daily_contacts_from_bocs'] == 1 ? ' checked' : '';

		$html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs_no" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="0"';

		$html .= $options['sync_daily_contacts_from_bocs'] != 1 ? ' checked' : '';

		$html .= '><label for="0">No</label>';

		echo $html;
	}

	/**
	 * @param $input
	 * @return array
	 */
	public function bocs_plugin_options_validate( $input ) {

		if( isset($newinput['api_key']) ) {
			$newinput['api_key'] = trim( $input['api_key'] );
			if ( ! preg_match( '/^[-a-z0-9]{36}$/i', $newinput['api_key'] ) ) {
				$newinput['api_key'] = '';
			}
		}

		$newinput['sync_contacts_to_bocs'] = trim( $input['sync_contacts_to_bocs'] ) == '1' ? 1 : 0;
		// $newinput['sync_contacts_from_bocs'] = trim( $input['sync_contacts_from_bocs'] ) == '1' ? 1 : 0;

		$newinput['sync_daily_contacts_to_bocs'] = trim( $input['sync_daily_contacts_to_bocs'] ) == '1' ? 1 : 0;
		$newinput['sync_daily_contacts_from_bocs'] = trim( $input['sync_daily_contacts_from_bocs'] ) == '1' ? 1 : 0;

		return $newinput;
	}

	/**
	 * Creates a product
	 *
	 * @return void
	 */
	public function create_product_ajax_callback() {

		// Verify the AJAX nonce
		$nonce = $_POST['nonce'];

		if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
			die('Invalid nonce');
		}

		// Get the product data from the AJAX request
		$product_title = $_POST['title'];
		$product_price = $_POST['price'];
		$product_sku = $_POST['sku'];
		$product_type = isset($_POST['type']) ? $_POST['type'] : "product";
		$bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : "";
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
		$variation_attributes = array();


		// Create a new WooCommerce product
		$new_product = array(
			'post_title'   => $product_title,
			'post_status'  => 'publish',
			'post_type'    => 'product',
			'post_content' => ''
		);
		$product_id = wp_insert_post($new_product);

		// Set the product price
		if ($product_id) {
			update_post_meta($product_id, '_price', $product_price);
			update_post_meta($product_id, '_regular_price', $product_price);
			update_post_meta($product_id, '_sku', $product_sku);
			update_post_meta($product_id, '_backorders', 'no');
			update_post_meta($product_id, '_download_expiry', '-1');
			update_post_meta($product_id, '_download_limit', '-1');
			update_post_meta($product_id, '_downloadable', 'no');
			update_post_meta($product_id, '_manage_stock', 'no');
			update_post_meta($product_id, '_sold_individually', 'no');
			update_post_meta($product_id, '_stock_status', 'instock');
			update_post_meta($product_id, '_virtual', 'no');
			update_post_meta($product_id, '_wc_average_rating', '0');
			update_post_meta($product_id, '_wc_review_count', '0');
			update_post_meta($product_id, '_wc_review_count', '0');

			if( $product_type == 'bocs' ){

				update_post_meta($product_id, 'bocs_product_discount', $_POST['bocs_product_discount']);
				update_post_meta($product_id, 'bocs_product_discount_type', $_POST['bocs_product_discount_type']);
				update_post_meta($product_id, 'bocs_product_interval', $_POST['bocs_product_interval']);
				update_post_meta($product_id, 'bocs_product_interval_count', $_POST['bocs_product_interval_count']);

				update_post_meta($product_id, 'bocs_bocs_id', $_POST['bocs_bocs_id']);
				update_post_meta($product_id, 'bocs_type', $_POST['bocs_type']);
				update_post_meta($product_id, 'bocs_sku', $_POST['bocs_sku']);
				update_post_meta($product_id, 'bocs_price', $_POST['bocs_price']);
			}

			if (isset($_POST['bocs_id'])){
				update_post_meta($product_id, 'bocs_id', $_POST['bocs_id']);
			}

			if ($bocs_product_id !== ""){
				update_post_meta($product_id, 'bocs_product_id', $bocs_product_id);
			}

		}

		// Return the product ID as the response
		wp_send_json($product_id);
	}


	/**
	 *
	 * Creates an order and a subscription on Bocs
	 * once the WooCommerce order is in processing mode
	 *
	 * @param integer $order_id
	 * @return false|void
	 */
	public function bocs_order_status_processing( $order_id = 0 ){

		if ( empty($order_id)) return false;
		// get the order details
		$order = wc_get_order( $order_id );
		$bocs_product_discount = 0;
		$bocs_product_discount_type = 'percent';
		$bocs_product_interval = 'month';
		$bocs_product_interval_count = 1;
		$bocs_name = "";
		$bocs_widget_id = "";

		$sub_items = [];

		$is_bocs = false;

		foreach( $order->get_items() as $item_id => $item ){
			$item_data = $item->get_data();
			if (isset($item_data['product_id'])){
				// meta bocs_id for this product
				$bocs_id = get_post_meta( $item_data['product_id'], 'bocs_bocs_id', true );

				$bocsProd = wc_get_product($item_data['product_id']);

				if (!empty( $bocs_id )){
					$is_bocs = true;

					$bocs_name = $bocsProd->get_name();
					$bocs_product_discount = get_post_meta( $item_data['product_id'], 'bocs_product_discount', true );
					$bocs_product_discount_type = get_post_meta( $item_data['product_id'], 'bocs_product_discount_type', true );
					$bocs_product_interval = get_post_meta( $item_data['product_id'], 'bocs_product_interval', true );
					$bocs_product_interval_count = get_post_meta( $item_data['product_id'], 'bocs_product_interval_count', true );
					$bocs_widget_id = get_post_meta( $item_data['product_id'], 'bocs_bocs_id', true );

				} else {

					$product_id = get_post_meta( $item_data['product_id'], 'bocs_product_id', true );

					if (empty($product_id)){
						$product_id = get_post_meta( $item_data['product_id'], 'bocs_id', true );
					}

					if(!empty($product_id)){
						$sub_items[] = '{"productId": "'.$product_id.'"}';
					}

				}

			}
		}

		if ($is_bocs){

			$options = get_option( 'bocs_plugin_options' );
			$options['bocs_headers'] = $options['bocs_headers'] ?? array();

			$customer_id = $order->get_customer_id();
			$bocs_customer_id = '';
			if (!empty($customer_id)){
				$bocs_customer_id = get_user_meta($customer_id, 'bocs_id', true);
			}

			if (empty($bocs_customer_id)) {
				$bocs_customer_id = "1ee14ce2-7e01-6740-83f0-9e114d6e82ff";

				// create customer
				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => BOCS_API_URL . 'contacts',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => '{
						"email": "'.$order->get_billing_email().'",
						"fullName": "'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'",
						"firstName": "'.$order->get_billing_first_name().'",
						"lastName": "'.$order->get_billing_last_name().'",
						"role": "customer",
						"externalSource": "woocommerce",
						"externalSourceId": "'.$order->get_customer_id().'",
						"billing": {
							"firstName": "'.$order->get_billing_first_name().'",
							"lastName":  "'.$order->get_billing_last_name().'",
							"company":  "'.$order->get_billing_company().'",
							"address1": "'.$order->get_billing_address_1().'",
							"address2": "'.$order->get_billing_address_2().'",
							"city": "'.$order->get_billing_city().'",
							"state":  "'.$order->get_billing_state().'",
							"country": "'.$order->get_billing_country().'",
							"postcode": "'.$order->get_billing_postcode().'",
							"phone":  "'.$order->get_billing_phone().'",
							"email":  "'.$order->get_billing_email().'",
							"default": true
						},
						"shipping": {
							"firstName": "'.$order->get_shipping_first_name().'",
							"lastName":  "'.$order->get_shipping_last_name().'",
							"company":  "'.$order->get_shipping_company().'",
							"address1": "'.$order->get_shipping_address_1().'",
							"address2": "'.$order->get_shipping_address_2().'",
							"city": "'.$order->get_shipping_city().'",
							"state":  "'.$order->get_shipping_state().'",
							"country": "'.$order->get_shipping_country().'",
							"postcode": "'.$order->get_shipping_postcode().'",
							"phone":  "'.$order->get_shipping_phone().'",
							"default": true
							}
					}',
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

				if($object){
					if (isset($object->data)){
						if (isset($object->data->contactId)){
							// add meta
							update_user_meta($order->get_customer_id(), "bocs_id", $object->data->contactId);
							$bocs_customer_id = $object->data->contactId;
						}
					}
				}
			}

			// then we will create an order
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => BOCS_API_URL . 'orders',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => '{
					"total": ' . $order->get_total() .',
					"discount": '. $order->get_discount_total() .',
					"shippingRate": 1,
					"currency": "'.$order->get_currency().'",
					"paymentDate": "'.$order->get_date_paid().'",
					"isPaid": true,
					"platform": "woocommerce",
					"customer" : {
						"customerId": "'.$bocs_customer_id.'",
						"email": "'.$order->get_billing_email().'",
						"firstName": "'.$order->get_billing_first_name().'",
						"lastName": "'.$order->get_billing_last_name().'"
					}
				}',
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

			if($object){
				if (isset($object->data)){
					if (isset($object->data->orderId)){
						// add meta
						update_post_meta( $order->get_id(), "bocs_id", $object->data->orderId);
					}
				}
			}

			// done creating the order

			// next we will create subscription
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => BOCS_API_URL . 'subscriptions',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS =>'{
					"name": "'.$bocs_name.'",
					"contact": "'.$bocs_customer_id.'",
					"price": ' . $order->get_total() .',
					"bocs": "'.$bocs_widget_id.'",
					"startDate": "'.$order->get_date_paid().'",
					"nextPaymentDate": "'.$order->get_date_paid().'",
					"frequency": {
						"discount": '.$bocs_product_discount.',
						"discountType": "'.$bocs_product_discount_type.'",
						"timeUnit": "'.$bocs_product_interval.'",
						"frequency": '.$bocs_product_interval_count.'
					},
					"products": [
						'.implode(',', $sub_items).'
					]
				}',
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

			// and create a subscription in woocommerce
			$order_post_data = get_post($order_id);

			if ($order_post_data) {
				$title = "Bocs Subscription";



				// Create new post array.
				$new_post = [
					'post_title'  => $title,
					'post_name'   => sanitize_title($title),
					'post_status' => 'active',
					'post_type'   => "bocs_subscription",
					'post_parent' => $order_id,
					'post_author' => $customer_id
				];

				// Insert new post.
				$new_post_id = wp_insert_post($new_post);

				// Copy post meta.
				$post_meta = get_post_custom($order_id);
				foreach ($post_meta as $key => $values) {
					foreach ($values as $value) {
						add_post_meta($new_post_id, $key, maybe_unserialize($value));
					}
				}

				// Copy post taxonomies.
				$taxonomies = get_post_taxonomies($order_id);
				foreach ($taxonomies as $taxonomy) {
					$term_ids = wp_get_object_terms($order_id, $taxonomy, ['fields' => 'ids']);
					wp_set_object_terms($new_post_id, $term_ids, $taxonomy);
				}

				add_post_meta( $new_post_id, 'bocs_product_discount', $bocs_product_discount );
				add_post_meta( $new_post_id, 'bocs_product_discount_type', $bocs_product_discount_type );
				add_post_meta( $new_post_id, 'bocs_product_interval', $bocs_product_interval );
				add_post_meta( $new_post_id, 'bocs_product_interval_count', $bocs_product_interval_count );
				add_post_meta( $new_post_id, 'bocs_bocs_id', $bocs_widget_id );

				// get paid date
				$start_date = "";
				$paid_date = get_post_meta( $new_post_id, '_paid_date', true );

				if ($paid_date){
					if ( !empty($paid_date) ){
						$start_date = $paid_date;
					}
				}

				$time_unit = 'year';
				if ( str_contains($bocs_product_interval, 'day') ){
					$time_unit = 'day';
				} else if ( str_contains($bocs_product_interval, 'week') ){
					$time_unit = 'week';
				} else if ( str_contains($bocs_product_interval, 'month') ){
					$time_unit = 'month';
				}

				$next_payment = New DateTime();
				$next_payment->modify("+" . $bocs_product_interval_count . " " . $time_unit );

				add_post_meta( $new_post_id, 'bocs_subscription_trial_end', 0 );
				add_post_meta( $new_post_id, 'bocs_subscription_next_payment', $next_payment->format('Y-m-d H:i:s') );
				add_post_meta( $new_post_id, 'bocs_subscription_cancelled', 0 );
				add_post_meta( $new_post_id, 'bocs_subscription_end', 0 );
				add_post_meta( $new_post_id, 'bocs_subscription_payment_retry', 0 );
				add_post_meta( $new_post_id, 'bocs_subscription_start', $start_date);

				// remove not need meta keys
				delete_post_meta($new_post_id, '_order_key');
				delete_post_meta($new_post_id, '_cart_hash');
				delete_post_meta($new_post_id, '_new_order_email_sent');
				delete_post_meta($new_post_id, '_order_stock_reduced');
				delete_post_meta($new_post_id, '_transaction_id');

				update_post_meta( $order_id, 'bocs_subscription_renewal_id', $new_post_id );

			}
		}

	}

	/**
	 * Search for the product
	 *
	 * @return void
	 */
	public function search_product_ajax_callback(){

		// Verify the AJAX nonce
		$nonce = $_POST['nonce'];

		if (!wp_verify_nonce($nonce, 'ajax-search-nonce')) {
			die('Invalid nonce');
		}

		$product_id = 0;

		$name = isset($_POST['name']) ? $_POST['name'] : '';
		$bocs_frequency_id = isset($_POST['bocs_frequency_id']) ? $_POST['bocs_frequency_id'] : 0;
		$bocs_bocs_id = isset($_POST['bocs_bocs_id']) ? $_POST['bocs_bocs_id'] : 0;
		$bocs_sku = isset($_POST['bocs_sku']) ? $_POST['bocs_sku'] : 0;
		$is_bocs = isset($_POST['is_bocs']) ? $_POST['is_bocs'] : 0;
		$bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : '';
        $bocs_product_type = 'bocs_bocs_id';

		// first we need to search by sku and frequency id
		global $wpdb;

        if ($bocs_bocs_id !== 0){
	        $bocs_product_type = 'bocs_bocs_id';
        } else if ( $bocs_product_id !== '' ){
	        $bocs_product_type = 'bocs_product_id';
        }

		$prepare_query = $wpdb->prepare("SELECT meta.post_id FROM  " . $wpdb->prefix . "postmeta as meta 
                                                    INNER JOIN " . $wpdb->prefix . "posts as posts 
                                                    ON posts.ID = meta.post_id 
                                                    WHERE meta.meta_key = %s 
                                                    AND meta.meta_value = %s  
                                                    AND posts.post_status = %s 
                                                    ORDER BY  meta.post_id ASC",
			$bocs_product_type, $bocs_bocs_id, 'publish'
		);

		$products = $wpdb->get_results($prepare_query);


		if (count($products) > 0){
			$product_id = $products[0]->post_id;
		}


		wp_send_json($product_id);
	}

	public function update_product_ajax_callback() {

		// Verify the AJAX nonce
		$nonce = $_POST['nonce'];

		if (!wp_verify_nonce($nonce, 'ajax-update-product-nonce')) {
			die('Invalid nonce');
		}

		// Get the product data from the AJAX request
		$bocs_product_id = $_POST['bocs_product_id'];
		$product_id = $_POST['id'];

		// Set the product price
		if ($product_id) {
			update_post_meta($product_id, 'bocs_product_id', $bocs_product_id);
		}

		// Return the product ID as the response
		wp_send_json($product_id);
	}

	public function create_coupon_ajax_callback(){

		// Verify the AJAX nonce
		$nonce = $_POST['nonce'];

		if (!wp_verify_nonce($nonce, 'ajax-create-coupon-nonce')) {
			die('Invalid nonce');
		}

		$coupon_code = sanitize_text_field($_POST['coupon_code']);
		$discount_type = sanitize_text_field($_POST['discount_type']);
		$amount = floatval($_POST['amount']);

		$coupon = array(
			'post_title' => $coupon_code,
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => 1,
			'post_type' => 'shop_coupon'
		);

		$new_coupon_id = wp_insert_post($coupon);

		if ($new_coupon_id) {
			update_post_meta($new_coupon_id, 'discount_type', $discount_type);
			update_post_meta($new_coupon_id, 'coupon_amount', $amount);
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

	}

    /**
     * Saved the current bocs and collections,
     * and also the one that is selected by the user
     *
     * @return void
     */
    public function save_widget_options_callback(){

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (!wp_verify_nonce($nonce, 'save-widget-nonce')) {
            die('Invalid nonce');
        }

        $selectedOption = isset( $_POST['selectedOption'] ) ? $_POST['selectedOption'] : '';
	    $selectedOptionName = isset( $_POST['selectedOptionName'] ) ? $_POST['selectedOptionName'] : '';
        $postId = isset( $_POST['postId'] ) ? $_POST['postId'] : '';

        if (!empty($postId) && !empty( $selectedOption )){
            update_post_meta( $postId, 'selected_bocs_widget_id', $selectedOption );
	        update_post_meta( $postId, 'selected_bocs_widget_name', $selectedOptionName );
            echo 'success';
            die();
        }

	    echo 'failed';
	    die();

    }

	public function custom_user_admin_icon_css(){

		// adds the modified styling here
		// echo '<style></style>';
		echo "";

	}

	public function custom_add_user_column( $columns ){
		$columns['source'] = "Source";
		return $columns;
	}

	/**
	 * Adds an icon before the user's full name
	 * 
	 * 
	 */
	public function custom_admin_user_icon( $val, $column_name, $user_id ) {

		if( $column_name == 'source' ){
			// check user's meta if from bocs
			$bocs_source = get_user_meta( $user_id, IS_BOCS_META_KEY, true );

			$val = "Wordpress";

			if( $bocs_source ){
				if( $bocs_source == 1 || $bocs_source == "true"){
					// we will consider this as source from bocs
					$val = "Bocs";
				}
			}
		}

		return $val;

	}
	
	public function custom_add_source_filter(){

		// @TODO appending the source on the url
		$current_source = isset( $_GET['source'] ) ? $_GET['source'] : '';

		echo '<select name="source" id="source">
				<option value=""></option>
				<option value="select">Select Source</option>
				<option value="both">Both</option>
				<option value="bocs">Bocs</option>
				<option value="wordpress">WordPress</option>
			</select>';
	}

	/**
	 * Filter the query by source
	 */
	public function custom_filter_users_by_source($query){
        
		// if( !is_admin() || !$query->is_main_query() ) return;
		if( !is_admin() ) return;

		$current_source = isset( $_GET['source'] ) ? $_GET['source'] : '';
	
		if( $current_source != 'bocs' && $current_source != 'wordpress' ) return;

		$meta_queries = array();

		if( $current_source == 'bocs' ){

			$meta_queries[] = array(
				'key' => IS_BOCS_META_KEY,
				'value' => true,
				'compare' => '='
			);

			$meta_queries[] = array(
				'key' => IS_BOCS_META_KEY,
				'value' => 'true',
				'compare' => '='
			);

			$meta_queries[] = array(
				'key' => IS_BOCS_META_KEY,
				'value' => 1,
				'compare' => '='
			);

			$meta_queries[] = array(
				'key' => IS_BOCS_META_KEY,
				'value' => '1',
				'compare' => '='
			);

			$query->set('meta_query', array('relation' => 'OR') + $meta_queries);

		} else {

			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => '=',
					'value'   => '',
				),
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => '=',
					'value'   => '0',
				),
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => '=',
					'value'   => 0,
				),
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => '=',
					'value'   => false,
				),
				array(
					'key'     => IS_BOCS_META_KEY,
					'compare' => '=',
					'value'   => 'false',
				)
			);

			$query->set('meta_query', $meta_query);

		}
	}

    public function bocs_widget_metabox_content($page){

        echo "<div id='bocs-page-sidebar'>
                <label>Collections</label><br /><select id='bocs-page-sidebar-collections' name='collections'></select><br />
                <br /><label>Bocs</label><br /><select id='bocs-page-sidebar-bocs' name='bocs'></select><br />
                <br />
                <br />
                <label><b>Copy the shortcode below</b></label><br />
                <code id='bocs-shortcode-copy'></code>
            </div>";

    }

	/**
     * Adds metabox on the right side of the Edit Page
     *
	 * @return void
	 */
    public function add_bocs_widget_metabox(){
        add_meta_box(
                'bocs_widget_metabox',
            'Bocs Widget Shortcode',
            array( $this, 'bocs_widget_metabox_content' ),
            'page',
            'side',
            'high'
        );
    }

}