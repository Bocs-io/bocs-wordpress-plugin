<?php

class Admin
{

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

		// get the settings
		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		wp_enqueue_script('jquery');

		wp_register_script("bocs-custom-block", plugin_dir_url(__FILE__) . '../assets/js/bocs-widget.js', array('wp-blocks', 'wp-i18n', 'wp-editor', 'jquery'), '0.0.104');
		wp_enqueue_script("bocs-custom-block");

		wp_localize_script('bocs-custom-block', 'ajax_object', array(
			'url' => BOCS_API_URL . "bocs",
			'Organization' => $options['bocs_headers']['organization'],
			'Store' => $options['bocs_headers']['store'],
			'Authorization' => $options['bocs_headers']['authorization']
		));

	}

	public function enqueue_scripts(){

		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		wp_enqueue_script( "bocs-widget-script", "https://feature-testing.app.bocs.io/widget/js/script.js", array(), '0.0.7', true );

		if (class_exists('woocommerce')) {
			wp_enqueue_script('wc-add-to-cart');
			wp_enqueue_script('wc-cart-fragments');
		}

		wp_enqueue_script( "bocs-add-to-cart", plugin_dir_url( __FILE__ ) . '../assets/js/add-to-cart.js', array('jquery', 'bocs-widget-script'), '0.0.51', true );
		wp_localize_script('bocs-add-to-cart', 'ajax_object', array(
			'cartNonce' => wp_create_nonce( 'wc_store_api' ),
			'cartURL' => wc_get_cart_url(),
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('ajax-nonce'),
            'search_nonce'    => wp_create_nonce('ajax-search-nonce'),
			'bocsGetUrl' => BOCS_API_URL . 'bocs/',
			'storeId' => $options['bocs_headers']['store'],
			'orgId' => $options['bocs_headers']['organization'],
			'authId' => $options['bocs_headers']['authorization']
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
		add_menu_page("Bocs", "Bocs", 'manage_options', 'bocs', [$this, 'bocs_homepage'], 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 37 37" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:1.5;"><g transform="matrix(1,0,0,1,-624.761,-311.761)">< g transform="matrix(1,0,0,1,11.3496,-4.7627)"><g transform="matrix(1.10644,0,0,1.21477,163.343,56.6488)"><circle cx="422.65" cy="228.965" r="7.092" style="fill:white;"/></g><g transform="matrix(0.978341,-0.207001,0.207001,0.978341,447.916,175.721)"><rect x="134.41" y="181.14" width="24.593" height="24.593" style="fill:rgb(0,132,139);stroke:rgb(0,132,139);stroke-width:7px;"/></g><g transform="matrix(1.10644,0,0,1.21477,163.849,56.4596)"><circle cx="422.65" cy="228.965" r="7.092" style="fill:white;"/></g></g></g></svg>'));
		add_submenu_page("bocs", "Subscriptions", "Subscriptions", "manage_options", 'bocs-subscriptions', [$this, 'bocs_list_subscriptions'] );
		add_submenu_page("bocs", "Settings", "Settings", "manage_options", 'bocs-settings', [$this, 'bocs_settings_page'] );
		add_submenu_page("bocs", "Sync Store", "Sync Store", "manage_options", 'bocs-sync-store', [$this, 'bocs_sync_store_page'] );

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

        error_log("create_product_ajax_callback: " . $nonce);

        error_log(wp_create_nonce('ajax-nonce'));

        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
			die('Invalid nonce');
		}

		// Get the product data from the AJAX request
		$product_title = $_POST['title'];
		$product_price = $_POST['price'];
		$product_sku = $_POST['sku'];
		$product_type = isset($_POST['type']) ? $_POST['type'] : "product";
        $bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : "";

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
				$bocs_id = get_post_meta( $item_data['product_id'], 'bocs_widget_id', true );

				$bocsProd = wc_get_product($item_data['product_id']);

				if (!empty( $bocs_id )){
					$is_bocs = true;

					$bocs_name = $bocsProd->get_name();
					$bocs_product_discount = get_post_meta( $item_data['product_id'], 'bocs_product_discount', true );
					$bocs_product_discount_type = get_post_meta( $item_data['product_id'], 'bocs_product_discount_type', true );
					$bocs_product_interval = get_post_meta( $item_data['product_id'], 'bocs_product_interval', true );
					$bocs_product_interval_count = get_post_meta( $item_data['product_id'], 'bocs_product_interval_count', true );
					$bocs_widget_id = get_post_meta( $item_data['product_id'], 'bocs_widget_id', true );

				} else {
					$sub_items[] = '{
							"productId": "'.get_post_meta( $item_data['product_id'], 'bocs_id', true ).'"
						}';
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
				$bocs_customer_id = "20f4d56d-2b58-430b-8d51-93d2b6a53d59";

				// create customer
				$curl = curl_init();

				error_log("create customer");
				error_log(print_r('{
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
					}', true));

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
				error_log($response);
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

			error_log(print_r('{
					"name": "'.$bocs_name.' ('.$bocs_product_interval_count.' '.$bocs_product_interval.')",
					"contact": "'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'",
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
				}',true));

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
					"name": "'.$bocs_name.' ('.$bocs_product_interval_count.' '.$bocs_product_interval.')",
					"contact": "'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'",
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
			error_log($response);

			curl_close($curl);
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
        error_log("search_product_ajax_callback: " . $nonce);
        error_log(wp_create_nonce('ajax-search-nonce'));

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

		// first we need to search by sku and frequency id
		global $wpdb;

        if ($is_bocs == 1){
            $prepare_query = $wpdb->prepare('SELECT 
													postmeta.post_id 
												FROM 
													' . $wpdb->prefix . 'postmeta as postmeta
												INNER JOIN
													' . $wpdb->prefix . 'posts as posts
												ON
													posts.ID = postmeta.post_id
												WHERE 
													postmeta.meta_key = %s AND postmeta.meta_value = %s
												AND 
													postmeta.meta_key = %s AND postmeta.meta_value = %s
												AND
													posts.post_status = %s
												ORDER BY 
													postmeta.post_id ASC',
                "bocs_sku", $bocs_sku, "bocs_frequency_id", $bocs_frequency_id, "publish");

            $products = $wpdb->get_results($prepare_query);

            if (count($products) > 0){
                $product_id = $products[0]->post_id;
            }

            // if not found, then by bocs id and frequency id
            if($product_id === 0){
                $prepare_query = $wpdb->prepare('SELECT 
														postmeta.post_id 
													FROM 
														' . $wpdb->prefix . 'postmeta as postmeta
													INNER JOIN
														' . $wpdb->prefix . 'posts as posts
													ON
														posts.ID = postmeta.post_id
													WHERE 
														postmeta.meta_key = %s AND postmeta.meta_value = %s
													AND 
														postmeta.meta_key = %s AND postmeta.meta_value = %s
													AND
														posts.post_status = %s
													ORDER BY 
														postmeta.post_id ASC',
                    "bocs_bocs_id", $bocs_bocs_id, "bocs_frequency_id", $bocs_frequency_id, "publish");

                $products = $wpdb->get_results($prepare_query);

                if (count($products) > 0){
                    $product_id = $products[0]->post_id;
                }
            }

        } else if($bocs_product_id !== '') {
            // search according to bocs_product_id
            $prepare_query = $wpdb->prepare('SELECT 
														postmeta.post_id 
													FROM 
														' . $wpdb->prefix . 'postmeta as postmeta
													INNER JOIN
														' . $wpdb->prefix . 'posts as posts
													ON
														posts.ID = postmeta.post_id
													WHERE 
														postmeta.meta_key = %s AND postmeta.meta_value = %s
													AND
														posts.post_status = %s
													ORDER BY 
														postmeta.post_id ASC',
                "bocs_product_id", $bocs_product_id, "publish");

            $products = $wpdb->get_results($prepare_query);

            if (count($products) > 0){
                $product_id = $products[0]->post_id;
            }
        }

		// if not found, then by name, and should be only 1
		if($product_id === 0){
			$prepare_query = $wpdb->prepare('SELECT 
														ID
													FROM
														' . $wpdb->prefix . 'posts
													WHERE
														' . $wpdb->prefix . 'posts.post_title = %s
													AND 
														' . $wpdb->prefix . 'posts.post_type = %s
													AND
														' . $wpdb->prefix . 'posts.post_status = %s',
				$name, "product", "publish");

			$products = $wpdb->get_results($prepare_query);

			if (count($products) == 1){
				$product_id = $products[0]->post_id;
			}
		}

		wp_send_json($product_id);
	}

}