<?php

/**
 *
 */
class Contact {

	private $api_token;
	private $headers;

	public function __construct()
	{
		$options = get_option( 'bocs_plugin_options' );
		$this->api_token = esc_attr( $options['api_key'] );
		$this->headers = array(
			'Authorization' => $this->api_token,
			'Content-Type' => 'application/json'
		);
	}

	/**
	 * Syncs / Adds the user to Bocs when created / added on Wordpress
	 *
	 * @param $user_id integer Wordpress User ID
	 *
	 * @return void
	 */
	public function sync_add_contact( $user_id ){

		// check first if the bocs contact id already exists
		$contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

		// do not proceed to sync to bocs, this maybe a sync from bocs to wodpress
		if ( !empty( $contact_id ) ) return;

		$post_to = BOCS_API_URL.'/contacts';

		$params = $this->get_params($user_id);

        // filter the address, if there is no address it won't be added to Bocs
        $body = json_decode($params['body'], true);
        if (trim($body['shipping']['address1']) == "") return false;

		// then we will create a contact on the Bocs end

		try {
			$return = wp_remote_post( $post_to, $params );
            error_log( $return['body'] );
			$contact_id = json_decode($return['body'], 2)['data']['contactId'];

			// we will add this to the user meta
			if( !empty($contact_id) ){
				delete_user_meta($user_id, 'bocs_contact_id');
				update_user_meta( $user_id, 'bocs_contact_id', $contact_id );
			}

		} catch (Exception $e) {
			add_settings_error(BOCS_NAME, 'sync_add_contact', $e->getMessage(), 'error');
		}

	}

	/**
	 * Syncs the user to Bocs when updated
	 *
	 * @param $user_id integer Wordpres User ID
	 *
	 * @return void
	 */
	public function sync_update_contact( $user_id ){

		// get the contact id
		$contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

		if (!empty($contact_id)) {

			$put_to = BOCS_API_URL.'/contacts/'.$contact_id;

			$params = $this->get_params($user_id);
			$params['method'] = "PUT";

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

				$body = array(
					"email" => $user->user_email,
					"firstName" => $_POST['first_name'] ?? $user->first_name,
					"lastName" => $_POST['last_name'] ?? $user->last_name,
					"billing" => $billing,
					"shipping" => $shipping
				);

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

	public function scheduled_sync_bocs_to_woocommerce(){

		if( false === as_has_scheduled_action([$this, 'sync_from_bocs']) ) {
			as_schedule_recurring_action( strtotime('tomorrow'), DAY_IN_SECONDS, [$this, 'sync_from_bocs'], array(), '', true );
		}
	}


	public function sync_from_bocs() {

		// get the list of contacts
		$list_url = BOCS_API_URL.'/contacts';

		$params = array(
			'headers' => $this->headers
		);

		$total_contacts = 25;

		while( $total_contacts == 25 ){

			$contacts = wp_remote_get($list_url, $params);

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

}