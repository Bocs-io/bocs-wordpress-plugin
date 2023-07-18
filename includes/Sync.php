<?php

class Sync {

	/**
	 * @param array $meta
	 * @param WP_User $user
	 * @param bool $update
	 * @param array $userdata
	 *
	 * @return void
	 *
	 */
	public function insert_user_meta($meta, $user, $update, $userdata){

		// get the firstname before the update
		$old_first_name = get_user_meta( $user->ID, 'first_name', true );
		$old_last_name = get_user_meta( $user->ID, 'last_name', true );

		$do_sync = false;
		$new_data = array();

		if ($old_first_name !== $userdata['first_name']){
			$do_sync = true;
			$new_data['first_name'] = $userdata['first_name'];
		}

		if ($old_last_name !== $userdata['last_name']){
			$do_sync = true;
			$new_data['last_name'] = $userdata['last_name'];
		}

		if ($do_sync){

			$curl = new Curl();

			// check if the user has a bocs record
			$bocs_contact_id = get_user_meta($user->ID, 'bocs_contact_id', true);

			if (empty($bocs_contact_id)){
				// search if the user exist using email
				$url = 'contacts?query=email:' . $user->user_email;
				$get_user = $curl->get($url);

				if ($get_user){

					$result = json_decode($get_user);

					if ($result->data && count($result->data) > 0){
						$bocs_contact_id = $result->data[0]->contactId;
						add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
					}
				}
			}

			// in this case, there is no user on the app's end
			// we will try to add the user to the app

			if (empty($bocs_contact_id))  {

				// we will add  the user here
				// but will not do the sync as the adding is
				// considered as the first syncs
				$params = array();
				$params[] = '"email": "'. $user->user_email .'"';
				$params[] = '"firstName": "'. $userdata['first_name'] .'"';
				$params[] = '"lastName": "'. $userdata['last_name'] .'"';
				$params[] = '"fullName": "'. $userdata['first_name'] . ' ' . $userdata['last_name'] .  '"';
				$params[] = '"role": "'. $userdata['role'] .'"';
				$params[] = '"externalSource": "Wordpress"';
				$params[] = '"externalSourceId": "'. $user->ID .'"';
				$params[] = '"username": "'. $user->user_login .'"';

				$data = '{';
				$data .= implode(',', $params);

				$data .= '}';

				$url = 'contacts';
				$createdUser = $curl->post($url, $data);

				if ($createdUser->data){
					if ($createdUser->data[0]->contactId){
						$bocs_contact_id = $createdUser->data[0]->contactId;
						add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
					}
				}

			} else {

				$data = '{';

				$params = array();

				$params[] = '"id": "'. $bocs_contact_id .'"';

				foreach ($new_data as $key => $value){
					$params[] = '"'. $key .'": "'. $value .'"';
				}

				$data .= implode(',', $params);

				$data .= '}';

				$url = 'sync/contacts/' . $bocs_contact_id ;
				$curl->put($url, $data);
			}
		}

		return $meta;

	}

	/**
	 * Updates the user on app's end when the wordpress user is updated
	 *
	 * @param int $user_id
	 * @param WP_User $old_user_data
	 * @param array $user_data
	 * @return void
	 */
	public function profile_update( $user_id, $old_user_data, $user_data ){

		$old_user_login = '';
		$old_user_email = '';
		$old_role = '';

		$new_user_login = '';
		$new_user_email = '';

		if ($user_data){
			$new_user_login = $user_data['user_login'];
			$new_user_email = $user_data['user_email'];
		}

		if ($old_user_data->data){

			if ( $old_user_data->data->user_login ){
				$old_user_login = $old_user_data->data->user_login;
			}

			if ( $old_user_data->data->user_email ){
				$old_user_email = $old_user_data->data->user_email;
			}

		}

		if ($old_user_data->roles){
			if (isset($old_user_data->roles[0])){
				$old_role = $old_user_data->roles[0];
			}
		}

		$new_role = $user_data['role'];

		$new_data = array();
		$do_sync = false;

		if ($old_user_email !== $new_user_email){
			$new_data['user_email'] = $new_user_email;
			$do_sync = true;
		}

		if ($old_role !== $new_role){
			$new_data['role'] = $new_role;
			$do_sync = true;
		}

		if($new_user_login !== $old_user_login){
			$new_data['user_login'] = $new_user_login;
			$do_sync = true;
		}

		if ($do_sync){

			$curl = new Curl();
			// do a sync to the app's end

			// check if the user has a bocs record
			$bocs_contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

			if (empty($bocs_contact_id)){
				// search if the user exist using email
				$url = 'contacts?query=email:' . $new_user_email;
				$get_user = $curl->get($url);

				if ($get_user){

					$result = json_decode($get_user);

					if ($result->data && count($result->data) > 0){
						$bocs_contact_id = $result->data[0]->contactId;
						add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
					}
				}
			}

			if (empty($bocs_contact_id)) {
				// we will add  the user here
				// but will not do the sync as the adding is
				// considered as the first syncs
				$params = array();
				$params[] = '"email": "'. $new_user_email .'"';
				$params[] = '"firstName": "'. $user_data['first_name'] .'"';
				$params[] = '"lastName": "'. $user_data['last_name'] .'"';
				$params[] = '"fullName": "'. $user_data['first_name'] . ' ' . $user_data['last_name'] .  '"';
				$params[] = '"role": "'. $user_data['role'] .'"';
				$params[] = '"externalSource": "Wordpress"';
				$params[] = '"externalSourceId": "'. $old_user_data->ID .'"';
				$params[] = '"username": "'. $old_user_data->user_login .'"';

				$data = '{';
				$data .= implode(',', $params);

				$data .= '}';

				$url = 'contacts';
				$createdUser = $curl->post($url, $data);

				if ($createdUser->data){
					if ($createdUser->data[0]->contactId){
						$bocs_contact_id = $createdUser->data[0]->contactId;
						add_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
					}
				}
			} else {
				$data = '{';

				$params = array();

				$params[] = '"id": "'. $bocs_contact_id .'"';

				foreach ($new_data as $key => $value){
					$params[] = '"'. $key .'": "'. $value .'"';
				}

				$data .= implode(',', $params);

				$data .= '}';

				$url = 'sync/contacts/' . $bocs_contact_id ;
				$curl->put($url, $data);
			}

		}

	}

}