<?php

class Contact {

	private $url = 'https://hhxamjuhk2.execute-api.ap-southeast-2.amazonaws.com/test';

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

		$post_to = $this->url.'/contacts';

		$params = $this->get_params($user_id);

		// then we will create a contact on the Bocs end

		try {
			$return = wp_remote_post( $post_to, $params );
			$contact_id = json_decode($return['body'], 2)['data']['contact_id'];

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

			$put_to = $this->url.'/contacts/'.$contact_id;

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
					'first_name' => $_POST['billing_first_name'] ?? get_user_meta($user_id, 'billing_first_name', true),
					'last_name' => $_POST['billing_last_name'] ?? get_user_meta($user_id, 'billing_last_name', true),
					'company' => $_POST['billing_company'] ?? get_user_meta($user_id, 'billing_company', true),
					'email' => $_POST['billing_email'] ?? get_user_meta($user_id, 'billing_email', true),
					'phone' => $_POST['billing_phone'] ?? get_user_meta($user_id, 'billing_phone', true),
					'country' => $_POST['billing_country'] ?? get_user_meta($user_id, 'billing_country', true),
					'address_1' => $_POST['billing_address_1'] ?? get_user_meta($user_id, 'billing_address_1', true),
					'address_2' => $_POST['billing_address_2'] ?? get_user_meta($user_id, 'billing_address_2', true),
					'city' => $_POST['billing_city'] ?? get_user_meta($user_id, 'billing_city', true),
					'state' => $_POST['billing_state'] ?? get_user_meta($user_id, 'billing_state', true),
					'postcode' => $_POST['billing_postcode'] ?? get_user_meta($user_id, 'billing_postcode', true)
				);

				$shipping = array(
					'first_name' => $_POST['shipping_first_name'] ?? get_user_meta($user_id, 'shipping_first_name', true),
					'last_name' => $_POST['shipping_last_name'] ?? get_user_meta($user_id, 'shipping_last_name', true),
					'company' => $_POST['shipping_company'] ?? get_user_meta($user_id, 'shipping_company', true),
					'phone' => $_POST['shipping_phone'] ?? get_user_meta($user_id, 'shipping_phone', true),
					'country' => $_POST['shipping_country'] ?? get_user_meta($user_id, 'shipping_country', true),
					'address_1' => $_POST['shipping_address_1'] ?? get_user_meta($user_id, 'shipping_address_1', true),
					'address_2' => $_POST['shipping_address_2'] ?? get_user_meta($user_id, 'shipping_address_2', true),
					'city' => $_POST['shipping_city'] ?? get_user_meta($user_id, 'shipping_city', true),
					'state' => $_POST['shipping_state'] ?? get_user_meta($user_id, 'shipping_state', true),
					'postcode' => $_POST['shipping_postcode'] ?? get_user_meta($user_id, 'shipping_postcode', true)
				);

				$body = array(
					"email" => $user->user_email,
					"first_name" => $_POST['first_name'] ?? $user->first_name,
					"last_name" => $_POST['last_name'] ?? $user->last_name,
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

				$delete_url = $this->url.'/contacts/'.$contact_id;
				$params = array ('method' => "DELETE");

				try {
					$result = wp_remote_post( $delete_url, $params );
				} catch (Exception $e) {
					add_settings_error(BOCS_NAME, 'sync_delete_contact', $e->getMessage(), 'error');
				}

			}
		}
	}

}