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
			'url' => BOCS_API_URL,
			'Organization' => $options['bocs_headers']['organization'],
			'Store' => $options['bocs_headers']['store'],
			'Authorization' => $options['bocs_headers']['authorization']
		));

	}

	public function enqueue_scripts(){

		wp_enqueue_script( "bocs-widget-script", "https://feature-testing.app.bocs.io/widget/js/script.js", array(), '0.0.7', true );

		wp_enqueue_script( "bocs-add-to-cart", plugin_dir_url( __FILE__ ) . '../assets/js/add-to-cart.js', array('jquery', 'bocs-widget-script'), '0.0.37', true );
		wp_localize_script('bocs-add-to-cart', 'ajax_object', array(
			'cartNonce' => wp_create_nonce( 'wc_store_api' ),
			'cartURL' => wc_get_cart_url()
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

}