<?php

/**
 * All of the contacts API related
 */
class Contact {

	private $api_token;
	private $headers;
	private $per_page;

	public function __construct()
	{
		$options = get_option( 'bocs_plugin_options' );
		if ( isset($options['api_key']) )
			$this->api_token = esc_attr( $options['api_key'] );
		$this->headers = array(
			'Authorization' => $this->api_token,
			'Content-Type' => 'application/json'
		);
		$this->per_page = 10;
	}

	/**
	 * Syncs / Adds the user to Bocs when created / added on Wordpress
	 *
	 * @param $user_id integer Wordpress User ID
	 *
	 * @return void
	 */
	public function sync_add_contact( $user_id ){

		$options = get_option( 'bocs_plugin_options' );
		$options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

		// no sync happening if it was disabled
		if ( $options['sync_contacts_to_bocs'] == 0 ) return false;

		// check first if the bocs contact id already exists
		$contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

		// do not proceed to sync to bocs, this maybe a sync from bocs to wodpress
		if ( !empty( $contact_id ) ) return;

		$post_to = BOCS_API_URL.'/contacts';

		$params = $this->get_params($user_id);

		// filter the address, if there is no address it won't be added to Bocs
		//$body = json_decode($params['body'], true);
		//if (trim($body['shipping']['address1']) == "") return false;

		// then we will create a contact on the Bocs end

		try {
			$return = wp_remote_post( $post_to, $params );
			$contact_id = json_decode($return['body'], 2)['data']['contactId'];

			// we will add this to the user meta
			if( !empty($contact_id) ){
				delete_user_meta($user_id, 'bocs_contact_id');
				update_user_meta( $user_id, 'bocs_contact_id', $contact_id );
				return $contact_id;
			}

		} catch (Exception $e) {
			add_settings_error(BOCS_NAME, 'sync_add_contact', $e->getMessage(), 'error');
		}

		return false;

	}

	/**
	 * Syncs the user to Bocs when updated
	 *
	 * @param $user_id integer Wordpres User ID
	 *
	 * @return void
	 */
	public function sync_update_contact( $user_id ){

		$options = get_option( 'bocs_plugin_options' );
		$options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

		// no sync happening if it was disabled
		if ( $options['sync_contacts_to_bocs'] == 0) return false;

		// get the contact id
		$contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

		if (!empty($contact_id)) {

			$put_to = BOCS_API_URL.'/contacts/'.$contact_id;

			$params = $this->get_params($user_id);
			$params['method'] = "PUT";

			// filter the address, if there is no address it won't be added to Bocs
			// $body = json_decode($params['body'], true);
			// if (trim($body['shipping']['address1']) == "") return false;

			try {
				$result = wp_remote_post( $put_to, $params );
			} catch (Exception $e) {
				add_settings_error(BOCS_NAME, 'sync_update_contact', $e->getMessage(), 'error');
			}

		}
	}

	/**
	 * Prepares the parameters need to be passed to Bocs API
	 *
	 * @param $user_id integer Wordpress User ID
	 * @return array|false
	 */
	private function get_params($user_id){

		if (!empty($user_id)){
			// get the details of the user
			$user = get_userdata($user_id);

			if (!empty($user)) {
				$billing = array(
					'firstName' => $_POST['billing_first_name'] ?? get_user_meta($user_id, 'billing_first_name', true),
					'lastName' => $_POST['billing_last_name'] ?? get_user_meta($user_id, 'billing_last_name', true),
					'company' => $_POST['billing_company'] ?? get_user_meta($user_id, 'billing_company', true),
					'email' => $_POST['billing_email'] ?? get_user_meta($user_id, 'billing_email', true),
					'phone' => $_POST['billing_phone'] ?? get_user_meta($user_id, 'billing_phone', true),
					'country' => $_POST['billing_country'] ?? get_user_meta($user_id, 'billing_country', true),
					'address1' => $_POST['billing_address_1'] ?? get_user_meta($user_id, 'billing_address_1', true),
					'address2' => $_POST['billing_address_2'] ?? get_user_meta($user_id, 'billing_address_2', true),
					'city' => $_POST['billing_city'] ?? get_user_meta($user_id, 'billing_city', true),
					'state' => $_POST['billing_state'] ?? get_user_meta($user_id, 'billing_state', true),
					'postcode' => $_POST['billing_postcode'] ?? get_user_meta($user_id, 'billing_postcode', true),
					'default' => true
				);

				$billing['firstName'] = empty( trim($billing['firstName']) ) ? $user->first_name : trim($billing['firstName']);

				$billing['lastName'] = empty( trim($billing['lastName']) ) ? $user->last_name : trim($billing['lastName']);

				$shipping = array(
					'firstName' => $_POST['shipping_first_name'] ?? get_user_meta($user_id, 'shipping_first_name', true),
					'lastName' => $_POST['shipping_last_name'] ?? get_user_meta($user_id, 'shipping_last_name', true),
					'company' => $_POST['shipping_company'] ?? get_user_meta($user_id, 'shipping_company', true),
					'phone' => $_POST['shipping_phone'] ?? get_user_meta($user_id, 'shipping_phone', true),
					'country' => $_POST['shipping_country'] ?? get_user_meta($user_id, 'shipping_country', true),
					'address1' => $_POST['shipping_address_1'] ?? get_user_meta($user_id, 'shipping_address_1', true),
					'address2' => $_POST['shipping_address_2'] ?? get_user_meta($user_id, 'shipping_address_2', true),
					'city' => $_POST['shipping_city'] ?? get_user_meta($user_id, 'shipping_city', true),
					'state' => $_POST['shipping_state'] ?? get_user_meta($user_id, 'shipping_state', true),
					'postcode' => $_POST['shipping_postcode'] ?? get_user_meta($user_id, 'shipping_postcode', true),
					'default' => true
				);

				$shipping['firstName'] = empty( trim($shipping['firstName']) ) ? $user->first_name : trim($shipping['firstName']);

				$shipping['lastName'] = empty( trim($shipping['lastName']) ) ? $user->last_name : trim($shipping['lastName']);

				if( empty( $shipping['address1'] ) ) $shipping['address1'] = $billing['address1'];
				if( empty( $shipping['address2'] ) ) $shipping['address2'] = $billing['address2'];
				if( empty( $shipping['company'] ) ) $shipping['company'] = $billing['company'];
				if( empty( $shipping['phone'] ) ) $shipping['phone'] = $billing['phone'];
				if( empty( $shipping['country'] ) ) $shipping['country'] = $billing['country'];
				if( empty( $shipping['city'] ) ) $shipping['city'] = $billing['city'];
				if( empty( $shipping['state'] ) ) $shipping['city'] = $billing['state'];
				if( empty( $shipping['postcode'] ) ) $shipping['city'] = $billing['postcode'];


				if( empty( $shipping['address1'] ) || empty( $billing['address1'] ) ) {
					$body = array(
						"email" => $user->user_email,
						"firstName" => $_POST['first_name'] ?? $user->first_name,
						"lastName" => $_POST['last_name'] ?? $user->last_name
					);
				} else {
					$body = array(
						"email" => $user->user_email,
						"firstName" => $_POST['first_name'] ?? $user->first_name,
						"lastName" => $_POST['last_name'] ?? $user->last_name,
						"billing" => $billing,
						"shipping" => $shipping
					);
				}

				return array(
					'headers' => $this->headers,
					'body' => json_encode($body)
				);
			}
		}

		return false;
	}

	/**
	 * Syncs to delete also on the Bocs
	 *
	 * @param $user_id
	 *
	 * @return void
	 */
	public function sync_delete_contact( $user_id ){

		$options = get_option( 'bocs_plugin_options' );
		$options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

		// no sync happening if it was disabled
		if ( $options['sync_contacts_to_bocs'] ) return false;

		if( !empty($user_id) ) {
			// get the contact id
			$contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

			if (!empty($contact_id)) {

				$delete_url = BOCS_API_URL.'/contacts/'.$contact_id;

				$params = array ('headers' => $this->headers, 'method' => "DELETE");

				try {
					$result = wp_remote_request( $delete_url, $params );

				} catch (Exception $e) {
					add_settings_error(BOCS_NAME, 'sync_delete_contact', $e->getMessage(), 'error');
				}

			}
		}
	}

	public function force_sync_to_bocs(){

		if ( !isset( $_POST['user_id'] ) ){
			echo json_encode('invalid user');
			exit;
		}

		if( $_POST['user_id'] == 0 ) {
			echo json_encode('invalid user');
			exit;
		}

		if ( !isset( $_POST['sync_enabled'] ) ){
			echo json_encode('sync option disabled');
			exit;
		}

		if( $_POST['sync_enabled'] !== 'yes' ) {
			echo json_encode('sync option disabled');
			exit;
		}

		if ( !isset( $_POST['_nonce'] )) {
			echo json_encode('access denied');
			exit;
		}

		if ( trim( $_POST['_nonce'] ) !== wp_create_nonce("bocs_plugin_options")) {
			echo json_encode('access denied');
			exit;
		}

		$user_id = trim( $_POST['user_id'] );

		if ($user_id) {
			$bocs_contact_id = get_user_meta( $user_id, 'bocs_contact_id', true );
			if (!empty($bocs_contact_id)) {
				try {
					// update from wp to bocs
					$this->sync_update_contact($user_id);
				} catch (Exception $e) {
					add_settings_error(BOCS_NAME, 'force_sync_to_bocs', $e->getMessage(), 'error');
					echo json_encode('failed: ' . $e->getMessage());
					exit;
				}
			} else {
				$result = $this->sync_add_contact($user_id);
				if ($result === false ) {
					echo json_encode('not synced');
					exit;
				}
			}
		}

		echo json_encode('success');
		exit;
	}

	/**
	 *
	 * @return void
	 */
	public function sync_to_bocs(){

        error_log("Contacts::sync_to_bocs");

		if ( !isset( $_POST['sync_enabled'] ) ){
			echo json_encode(0);
			exit;
		}

		if( $_POST['sync_enabled'] !== 'yes' ) {
			echo json_encode(0);
			exit;
		}

		if ( !isset( $_POST['_nonce'] )) {
			echo json_encode('access denied');
			exit;
		}

		if ( trim( $_POST['_nonce'] ) !== wp_create_nonce("bocs_plugin_options")) {
			echo json_encode('access denied');
			exit;
		}

		// get all the list of the users in wordpress
		$page = 0;
		$total = $this->per_page;

		while( $total == $this->per_page ) {

			$wp_users = get_users( array( 'offset' => $page * $this->per_page, 'number' => $this->per_page ) );

			foreach ($wp_users as $wp_user) {

				// check first its meta if it has a Bocs contact
				$user_id = $wp_user->ID;

				if ($user_id) {

					$bocs_contact_id = get_user_meta( $user_id, 'bocs_contact_id', true );

					if (!empty($bocs_contact_id)) {

						// which is the last update?
						$get_url = BOCS_API_URL.'/contacts/'.$bocs_contact_id;

						try {

							$params = array ('headers' => $this->headers );
							$result = wp_remote_get( $get_url, $params );

							$date_modified_bocs = false;
							$date_modified_wp = get_user_meta( $user_id, 'last_update', true );

							if ($result) {
								if( isset( $result['body'] ) ){
									$bocs_contact = json_decode($result['body'], 2);
									$bocs_contact = isset($bocs_contact['data']) ? $bocs_contact['data'] : false;
									$date_modified_bocs = strtotime($bocs_contact['dateModifiedGMT']);
								}
							}

							if ( $date_modified_bocs && $date_modified_wp ){
								if ( $date_modified_wp > $date_modified_bocs ){
									// update from wp to bocs
									$this->sync_update_contact($user_id);
								}
							}
						} catch (Exception $e) {
							add_settings_error(BOCS_NAME, 'sync_to_bocs', $e->getMessage(), 'error');
						}
					} else {

						// add to bocs
						$added = $this->sync_add_contact($user_id);
					}
				}

			}

			$total = count( $wp_users );
			$page++;

		}

		echo json_encode('success');
		exit;
	}

	public function force_sync_from_bocs(){

		// get the list of contacts
		$list_url = BOCS_API_URL.'/contacts';

		$params = array(
			'headers' => $this->headers
		);
	}

    /**
     * Action Scheduler for the daily sync of contacts to Bocs
     *
     * @return void
     */
    public function as_daily_sync_to_bocs(){
		if ( false === as_has_scheduled_action("daily_sync_to_bocs") ){
            error_log('daily_sync_to_bocs false');
			as_schedule_recurring_action( strtotime('+15 minutes'), 15 * 60, 'daily_sync_to_bocs', array(), '', true);
		} else {
            error_log('daily_sync_to_bocs true');
        }
	}

    /**
     * Action Scheduler for the daily sync of contacts from Bocs
     *
     * @return void
     */
    public function as_daily_sync_from_bocs(){
        if ( false === as_has_scheduled_action("daily_sync_from_bocs") ){
            error_log('daily_sync_from_bocs false');
            as_schedule_recurring_action( strtotime('+15 minutes'), 15 * 60, 'daily_sync_from_bocs', array(), '', true);
        } else {
            error_log('daily_sync_from_bocs true');
        }
    }

    /**
     *
     * method for the syncing contacts from Bocs
     *
     * @return void
     */
    public function daily_sync_from_bocs(){

        error_log("Contacts::daily_sync_from_bocs");

		// check first if this daily sync was on/enabled

		$options = get_option( 'bocs_plugin_options' );
		$syncing = false;

		if (isset($options['sync_daily_contacts_from_bocs'])){
			if (!empty($options['sync_daily_contacts_from_bocs'])){
				$syncing = true;
			}
		}

		if( $syncing === false ) return;

        error_log("starting sync from bocs...");
		// then do the sync
		$this->sync_from_bocs();
        error_log("done on sync from bocs...");
	}

    public function daily_sync_to_bocs(){

        // check first if this daily sync was on/enabled

        $options = get_option( 'bocs_plugin_options' );
        $syncing = false;

        if (isset($options['sync_daily_contacts_to_bocs'])){
            if (!empty($options['sync_daily_contacts_to_bocs'])){
                $syncing = true;
            }
        }

        if( $syncing === false ) return;

        error_log("starting sync to bocs...");
        // then do the sync
        $this->sync_to_bocs();
        error_log("done on sync to bocs...");
    }

	public function sync_from_bocs() {

        error_log("Contacts::sync_from_bocs");

		// get the list of contacts
		$list_url = BOCS_API_URL.'/contacts';

		$params = array(
			'headers' => $this->headers
		);

		$total_contacts = 25;

		while( $total_contacts == 25 ){

			$contacts = wp_remote_get($list_url, $params);

            error_log(json_encode($contacts, JSON_PRETTY_PRINT));

			$total_contacts = 0;

			if (isset( $contacts['body'] )) {
				$contacts = json_decode($contacts['body'], 2);

				if (isset( $contacts['nextPageLink'] )) {
					$list_url = $contacts['nextPageLink'];
				}

				if (isset( $contacts['data'])) {
					$contacts = $contacts['data'];

					$total_contacts = count($contacts);
					if( $total_contacts > 0 ) {

						foreach ($contacts as $contact) {
							// check if the contact already exists on the store
							$contact_id = $contact['contactId'];
							$bocs_last_update =  $contact['updatedAt'];
							$bocs_last_update = strtotime($bocs_last_update);

							$wp_users = new WP_User_Query(
								array(
									'meta_key' => 'bocs_contact_id',
									'meta_value' => $contact_id,
									'meta_compare' => '='
								)
							);

							if( $wp_users->get_total() > 0 ) {
								// check the modified date
								foreach ( $wp_users->get_results() as $user ){

									$wp_last_update = get_user_meta( $user->ID, 'last_update' );
									$wp_last_update = intval($wp_last_update);

									// if bocs modified date is latest then update wordpress site
									if( $bocs_last_update > $wp_last_update ) {
										update_user_meta( $user->ID, 'first_name', $contact['firstName'] );
										update_user_meta( $user->ID, 'last_name', $contact['lastName'] );
										if ($contact['email'] !== $user->email){
											wp_update_user( array( 'ID' => $user->ID, 'user_email' => $contact['email'] ) );
										}
										break;
									}
								}
							} else {
								// look on the user's email address

								$wp_user = get_user_by_email($contact['email']);

								if ( $wp_user ) {
									$wp_last_update = get_user_meta( $wp_user->ID, 'last_update' );
									if( $bocs_last_update > $wp_last_update ) {
										update_user_meta( $wp_user->ID, 'first_name', $contact['firstName'] );
										update_user_meta( $wp_user->ID, 'last_name', $contact['lastName'] );
									}
								} else {

									$meta_input = array('bocs_contact_id' => $contact['contactId']);

									// adds the bocs user
									$data = array(
										'user_pass' => rand(100000,999999999999),
										'user_login' => $contact['email'],
										'user_nicename' => $contact['email'],
										'user_email' => $contact['email'],
										'display_name' => $contact['firstName'] . " " . $contact['lastName'],
										'nickname' => $contact['firstName'],
										'first_name' => $contact['firstName'],
										'last_name' => $contact['lastName'],
										'meta_input' => $meta_input
									);
									wp_insert_user($data);
								}
							}
						}
					}
				}
			}
		}
	}

	public function user_render_fields(){
		require_once dirname( __FILE__ ) . '/../views/bocs_edit_profile.php';
	}
}