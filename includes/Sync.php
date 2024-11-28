<?php

class Sync
{


	/**
	 * Creates a user on the Bocs app
	 *
	 * @param array $user
	 *
	 * @return boolean|object
	 */
	private function _createUser($user)
	{

		$curl = new Curl();

		$result = false;

		$params = array();
		$params[] = '"email": "' . $user['email'] . '"';
		$params[] = '"firstName": "' . $user['first_name'] . '"';
		$params[] = '"lastName": "' . $user['last_name'] . '"';
		$params[] = '"fullName": "' . $user['first_name'] . ' ' . $user['last_name'] .  '"';

		if (!empty($user['role'])) {
			$params[] = '"role": "' . $user['role'] . '"';
		}

		$params[] = '"externalSource": "Wordpress"';
		$params[] = '"externalSourceId": "' . $user['id'] . '"';
		$params[] = '"username": "' . $user['username'] . '"';

		$data = '{';
		$data .= implode(',', $params);

		$data .= '}';

		$url = 'contacts';
		$result = $curl->post($url, $data, 'contacts', $user['id']);

		return $result;
	}

	/**
	 * Hook when the user meta was updated
	 * we will get the first name and last name in case there are changes
	 *
	 * @param array $meta
	 * @param WP_User $user
	 * @param bool $update
	 * @param array $userdata
	 *
	 * @return void
	 *
	 */
	public function insert_user_meta($meta, $user, $update, $userdata)
	{

		error_log("insert user meta");

		// get the firstname before the update
		$old_first_name = get_user_meta($user->ID, 'first_name', true);
		$old_last_name = get_user_meta($user->ID, 'last_name', true);
		$old_email = $user->user_email;

		$do_sync = false;
		$new_data = array();

		if (!isset($userdata['first_name'])) return $meta;
		if (!isset($userdata['last_name'])) return $meta;

		if ($old_first_name != $userdata['first_name']) {
			$do_sync = true;
			$new_data['first_name'] = $userdata['first_name'];
		}

		if ($old_last_name != $userdata['last_name']) {
			$do_sync = true;
			$new_data['last_name'] = $userdata['last_name'];
		}

		if ($old_email != $userdata['user_email']) {
			$new_data['email'] = $userdata['user_email'];
		}

		if ($do_sync) {

			$curl = new Curl();

			// check if the user has a bocs record
			$bocs_contact_id = get_user_meta($user->ID, 'bocs_contact_id', true);

			if (empty($bocs_contact_id)) {
				// search if the user exist using email
				$url = 'contacts?query=email:' . $userdata['user_email'];
				error_log("Getting Bocs contact with email " . $user->user_email);
				$get_user = $curl->get($url, 'contacts', $user->ID);

				if ($get_user->data && count($get_user->data) > 0) {
					error_log("contact found...");
					$bocs_contact_id = $get_user->data[0]->id;
					add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
				} else {
					error_log("contact NOT found");
				}
			}

			// in this case, there is no user on the app's end
			// we will try to add the user to the app

			if (empty($bocs_contact_id)) {

				// we will add  the user here
				// but will not do the sync as the adding is
				// considered as the first syncs
				$params = array(
					'id'			=> $user->ID,
					'username'		=> $user->user_login,
					'email' 		=> $user->user_email,
					'first_name'	=> $userdata['first_name'],
					'last_name'		=> $userdata['last_name']
				);

				if (!empty($userdata['role'])) {
					$params['role'] = $userdata['role'];
				}

				error_log('creating a contact on bocs app');
				$createdUser = $this->_createUser($params);

				if ($createdUser->data) {
					if ($createdUser->data->id) {
						error_log("contact was created");
						$bocs_contact_id = $createdUser->data->id;
						add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
					} else {
						error_log("contact was not created");
					}
				} else {
					error_log("contact was not created");
				}
			} else {

				$data = '{';

				$params = array();

				$params[] = '"id": "' . $user->ID . '"';

				foreach ($new_data as $key => $value) {

					if ($key == 'first_name') $key = 'firstName';
					if ($key == 'last_name') $key = 'lastName';

					$params[] = '"' . $key . '": "' . $value . '"';
				}

				$data .= implode(',', $params);

				$data .= '}';

				$url = 'wp/sync/contacts/' . $user->ID;
				error_log("adding a sync for user " . $user->ID);
				$addedSync = $curl->put($url, $data, 'contacts', $user->ID);

				// in case that the bocs contact id does not exist
				// then possibly if implies that this is related to
				// previous or deleted bocs account
				// thus we may need to re - add this
				if ($addedSync->code != 200) {

					error_log("there was an error on PUT");
					// we will do a post
					$url = 'wp/sync/contacts';
					error_log("adding sync using POST");

					$postedSync = $curl->post($url, $data, 'contacts', $user->ID);

					if ($postedSync->code != 200) {
						$params = array(
							'id'			=> $user->ID,
							'username'		=> $user->user_login,
							'email' 		=> $user->user_email,
							'first_name'	=> $userdata['first_name'],
							'last_name'		=> $userdata['last_name']
						);

						if (!empty($userdata['role'])) {
							$params['role'] = $userdata['role'];
						}

						error_log("creating the user on bocs");
						$createdUser = $this->_createUser($params);

						if ($createdUser->data) {
							if ($createdUser->data->id) {
								error_log("created the user on bocs");
								$bocs_contact_id = $createdUser->data->id;
								update_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
							} else {
								error_log("NOT creating the user on bocs");
							}
						} else {
							error_log("NOT syncing using POST");
						}
					} else {
						error_log("added sync successfully using POST");
					}
				} else {
					error_log("added sync successfully");
				}
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
	public function profile_update($user_id, $old_user_data, $user_data)
	{

		$old_user_login = '';
		$old_user_email = '';
		$old_role = '';

		$new_user_login = '';
		$new_user_email = '';

		if ($user_data) {
			$new_user_login = $user_data['user_login'];
			$new_user_email = $user_data['user_email'];
		}

		if ($old_user_data->data) {

			if ($old_user_data->data->user_login) {
				$old_user_login = $old_user_data->data->user_login;
			}

			if ($old_user_data->data->user_email) {
				$old_user_email = $old_user_data->data->user_email;
			}
		}

		if ($old_user_data->roles) {
			if (isset($old_user_data->roles[0])) {
				$old_role = $old_user_data->roles[0];
			}
		}

		$new_role = !empty($user_data['role']) ? $user_data['role'] : '';

		$new_data = array();
		$do_sync = false;

		if ($old_user_email !== $new_user_email) {
			$new_data['user_email'] = $new_user_email;
			$do_sync = true;
		}

		if ($old_role !== $new_role && $new_role !== '') {
			$new_data['role'] = $new_role;
			$do_sync = true;
		}

		if ($new_user_login !== $old_user_login) {
			$new_data['user_login'] = $new_user_login;
			$do_sync = true;
		}

		if ($do_sync) {

			$curl = new Curl();
			// do a sync to the app's end

			// check if the user has a bocs record
			$bocs_contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

			if (empty($bocs_contact_id)) {
				// search if the user exist using email
				$url = 'contacts?query=email:' . $new_user_email;
				$get_user = $curl->get($url, 'contacts', $user_id);

				error_log("getting the bocs user with email address " . $new_user_email);

				if ($get_user->data && count($get_user->data) > 0) {
					$bocs_contact_id = $get_user->data[0]->id;
					error_log("bocs user was found");
					add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
				} else {
					error_log("bocs user with that email address NOT found");
				}
			}

			if (empty($bocs_contact_id)) {
				// we will add  the user here
				// but will not do the sync as the adding is
				// considered as the first syncs

				$params = array(
					'id'			=> $old_user_data->ID,
					'username'		=> $old_user_data->user_login,
					'first_name'	=> $user_data['first_name'],
					'last_name'		=> $user_data['last_name']
				);

				if (!empty($user_data['role'])) {
					$params['role'] = $user_data['role'];
				}

				error_log("adding user to bocs");
				$createdUser = $this->_createUser($params);

				if ($createdUser->data) {
					if ($createdUser->data->id) {
						error_log("bocs user was created");
						$bocs_contact_id = $createdUser->data->id;
						add_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
					} else {
						error_log("error on creating bocs user");
					}
				} else {
					error_log('error on creating bocs user');
				}
			} else {
				$data = '{';

				$params = array();

				$params[] = '"id": "' . $bocs_contact_id . '"';

				foreach ($new_data as $key => $value) {
					if ($key == 'first_name') $key = 'firstName';
					if ($key == 'last_name') $key = 'lastName';
					$params[] = '"' . $key . '": "' . $value . '"';
				}

				$data .= implode(',', $params);

				$data .= '}';

				error_log("Adding sync of contact " . $old_user_data->ID);
				$url = 'wp/sync/contacts/' . $old_user_data->ID;
				$addedSync = $curl->put($url, $data, 'contacts', $old_user_data->ID);

				// Contact not found
				if ($addedSync->code != 200) {

					error_log("added sync using PUT failed");

					// we will try the POST
					$url = 'wp/sync/contacts';
					error_log("adding sync using POST");
					$createdSync = $curl->post($url, $data, 'contacts', $old_user_data->ID);

					// in case it was also not a success
					// then we will add the user
					// Contact not added
					if ($createdSync->code != 200) {
						error_log("Adding POST sync FAILED");
						$params = array(
							'id'			=> $old_user_data->ID,
							'username'		=> $old_user_data->user_login,
							'first_name'	=> $user_data['first_name'],
							'last_name'		=> $user_data['last_name']
						);

						if (!empty($user_data['role'])) {
							$params['role'] = $user_data['role'];
						}

						error_log("Creating bocs user");
						$createdUser = $this->_createUser($params);

						if ($createdUser->data) {
							if ($createdUser->data->id) {
								error_log("Bocs user was created successfully");
								$bocs_contact_id = $createdUser->data->id;
								update_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
							} else {
								error_log("Bocs user was not created");
							}
						} else {
							error_log("Bocs user was not created");
						}
					} else {
						error_log("Adding sync using POST success");
					}
				} else {
					error_log("Added sync was successful");
				}
			}
		}
	}

	/**
	 * 
	 * Attempts to sync or add if not exists the user data 
	 * when he update his profile
	 * 
	 * @param int $user_id
	 * 
	 * @return void
	 * 
	 */
	public function save_account_details($user_id)
	{

		// check if the user has a bocs record
		$bocs_contact_id = get_user_meta($user_id, 'bocs_contact_id', true);
		$email = isset($_POST['account_email']) ? sanitize_email($_POST['account_email']) : '';
		$first_name = isset($_POST['account_first_name']) ? sanitize_text_field($_POST['account_first_name']) : '';
		$last_name = isset($_POST['account_last_name']) ? sanitize_text_field($_POST['account_last_name']) : '';
		$old_userdata = get_userdata($user_id);

		// in case that the user doesnt have a bocs contact id
		// we will search by email
		if (empty($bocs_contact_id)) {
			// search if the user exist using email
			$url = 'contacts?query=email:' . $email;
			error_log("geting bocs user with email " . $email);
			$get_user = $curl->get($url, 'contacts', $user_id);

			if ($get_user->data && count($get_user->data) > 0) {
				error_log("Bocs user was found");
				$bocs_contact_id = $get_user->data[0]->id;
				add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
			} else {
				error_log("bocs user not found");
			}
		}

		if (empty($bocs_contact_id)) {

			// add only to the app
			// we will add  the user here
			// but will not do the sync as the adding is
			// considered as the first syncs
			$params = array();
			$params[] = '"email": "' . $email . '"';
			$params[] = '"firstName": "' . $first_name . '"';
			$params[] = '"lastName": "' . $last_name . '"';
			$params[] = '"fullName": "' . $first_name . ' ' . $last_name .  '"';

			if ($old_userdata) {

				if (!empty($old_userdata->roles)) {
					if (!empty($old_userdata->roles[0])) {
						$params[] = '"role": "' . $old_userdata->roles[0] . '"';
					}
				}

				$params[] = '"username": "' . $old_userdata->user_login . '"';
			}

			$params[] = '"externalSource": "Wordpress"';
			$params[] = '"externalSourceId": "' . $user_id . '"';


			$data = '{';
			$data .= implode(',', $params);

			$data .= '}';

			$url = 'contacts';

			error_log("creating bocs user");

			$createdUser = $curl->post($url, $data, 'contacts', $user_id);

			if ($createdUser->data) {
				if ($createdUser->data->id) {
					error_log('bocs user was CREATED');
					$bocs_contact_id = $createdUser->data->id;
					add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
				} else {
					error_log('bocs user was not created');
				}
			} else {
				error_log('bocs user was not created');
			}
		} else {

			error_log('bocs user found');
			$do_sync = false;

			$old_first_name = get_user_meta($user_id, 'first_name', true);
			$old_last_name = get_user_meta($user_id, 'last_name', true);
			$old_email = '';

			$params = array();

			$params[] = '"id": "' . $bocs_contact_id . '"';

			if ($old_first_name !== $first_name) {
				$do_sync = true;
				$params[] = '"firstName": "' . $first_name . '"';
			}

			if ($old_last_name !== $last_name) {
				$do_sync = true;
				$params[] = '"lastName": "' . $last_name . '"';
			}

			if ($do_sync) {
				$params[] = '"fullName": "' . $first_name . ' ' . $last_name . '"';
			}

			if ($old_userdata) {
				$old_email = $old_userdata->user_email;
			}

			if ($old_email !== $email) {
				$do_sync = true;
				$params[] = '"email": "' . $email . '"';
			}

			if ($do_sync) {

				$data = '{';
				$data .= implode(',', $params);
				$data .= '}';

				$url = 'wp/sync/contacts/' . $user_id;
				error_log("adding PUT sync");
				$addedSync = $curl->put($url, $data, 'contacts', $user_id);

				// Contact not found
				if ($addedSync->code != 200) {

					// we will try the POST
					$url = 'wp/sync/contacts';
					error_log("Error PUT sync");
					error_log("doing POST sync");
					$createdSync = $curl->post($url, $data, 'contacts', $user_id);

					// in case it was also not a success
					// then we will add the user
					// Contact not added
					if ($createdSync->code != 200) {
						error_log("POST sync FAILED");
						$params = array(
							'id'			=> $old_userdata->ID,
							'username'		=> $old_userdata->user_login,
							'first_name'	=> $first_name,
							'last_name'		=> $last_name
						);

						if ($old_userdata) {

							if (!empty($old_userdata->roles)) {
								if (!empty($old_userdata->roles[0])) {
									$params['role'] = $old_userdata->roles[0];
								}
							}
						}

						error_log('Creating Bocs user');
						$createdUser = $this->_createUser($params);

						if ($createdUser->data) {
							if ($createdUser->data->id) {
								error_log("Created bocs user done");
								$bocs_contact_id = $createdUser->data->id;
								update_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
							} else {
								error_log('Bocs user not created');
							}
						} else {
							error_log("Bosc user not created");
						}
					} else {
						error_log("POST sync success");
					}
				} else {
					error_log('success PUT sync');
				}
			}
		}
	}

	/**
	 * Synchronizes a newly registered WordPress user with the Bocs application.
	 * 
	 * This method is hooked to WordPress's 'user_register' action and performs the following:
	 * 1. Checks if a user with the same email exists in Bocs
	 * 2. If found, stores the Bocs contact ID in WordPress user meta
	 * 3. If not found, creates a new user in Bocs with the WordPress user's details
	 * 
	 * @param int $user_id The WordPress user ID of the newly registered user
	 * 
	 * @uses WP_User
	 * @uses add_user_meta()
	 * @uses Curl For making API requests to Bocs
	 * 
	 * @throws None - Errors are logged using error_log()
	 * @return void
	 * 
	 * @since 1.0.0
	 */
	public function bocs_user_register($user_id) {
		// Create WP_User object from the provided user ID
		$user = new WP_User($user_id);

		if (!$user || !$user->exists()) {
			error_log('[Bocs Sync][ERROR] Invalid user ID: ' . $user_id);
			return;
		}

		error_log('[Bocs Sync][INFO] Starting user registration sync for user ID: ' . $user_id);
		
		// Initialize Bocs contact ID as empty
		$bocs_contact_id = '';
		
		// Retrieve user's email address from WordPress
		$email = $user->user_email;
		if (empty($email)) {
			error_log('[Bocs Sync][ERROR] User email is empty for user ID: ' . $user_id);
			return;
		}

		error_log('[Bocs Sync][DEBUG] Processing user: ' . $email);

		// Construct API query URL to search for existing user in Bocs
		$url = 'contacts?query=email:"' . $email . '"';

		// Initialize Curl object for API communication
		$curl = new Curl();
		error_log('[Bocs Sync][INFO] Checking if user exists in Bocs: ' . $email);
		
		// Make GET request to Bocs API to check if user exists
		$get_user = $curl->get($url, 'contacts', $user_id);

		if (!$get_user) {
			error_log('[Bocs Sync][ERROR] API request failed for email: ' . $email);
			return;
		}

		// Check if user exists in Bocs
		if (isset($get_user->data->data) && is_array($get_user->data->data) && count($get_user->data->data) > 0) {
			error_log('[Bocs Sync][INFO] Existing Bocs user found for email: ' . $email);
			
			// Store Bocs contact ID from first matching result
			$bocs_contact_id = $get_user->data->data[0]->id;
			error_log('[Bocs Sync][DEBUG] Bocs contact ID: ' . $bocs_contact_id);
			
			// Save Bocs contact ID in WordPress user meta
			$meta_result = add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
			if ($meta_result) {
				error_log('[Bocs Sync][INFO] Successfully stored Bocs contact ID in user meta');
			} else {
				error_log('[Bocs Sync][WARNING] Failed to store Bocs contact ID in user meta');
			}
		} else {
			error_log('[Bocs Sync][INFO] No existing Bocs user found, proceeding with creation');
		}

		// If no existing Bocs user was found, create a new one
		if (empty($bocs_contact_id)) {
			// Set default role as 'customer'
			$role = 'customer';
			
			// Get user's WordPress roles
			$roles = $user->roles;
			error_log('[Bocs Sync][DEBUG] User roles: ' . print_r($roles, true));
			
			// Use first assigned role if available
			if (!empty($roles) && is_array($roles) && count($roles) > 0) {
				$role = $roles[0];
				error_log('[Bocs Sync][DEBUG] Using role: ' . $role);
			}

			// Prepare user data for Bocs API
			$params = array(
				'email' => $email,
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'role' => $role,
				'id' => $user_id,
				'username' => $user->user_login
			);
			
			error_log('[Bocs Sync][INFO] Creating new Bocs user with params: ' . print_r($params, true));
			
			// Create new user in Bocs via API
			$created_user = $this->_createUser($params);
			
			if ($created_user && 
				((isset($created_user->data) && isset($created_user->data->id)) || 
				(isset($created_user->data->data) && isset($created_user->data->data->id)))
			) {
				$bocs_contact_id = isset($created_user->data->id) ? 
					$created_user->data->id : 
					$created_user->data->data->id;
					
				error_log('[Bocs Sync][INFO] Successfully created Bocs user with ID: ' . $bocs_contact_id);
				
				// Store the Bocs contact ID in user meta
				add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
			} else {
				error_log('[Bocs Sync][ERROR] Failed to create Bocs user');
			}
		}

		error_log('[Bocs Sync][INFO] Completed user registration sync for: ' . $email);
	}
}
