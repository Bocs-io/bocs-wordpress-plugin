<?php

class Sync
{


	/**
	 * Creates a user in the Bocs application system
	 *
	 * Makes an API request to create a new user in Bocs with the provided user data.
	 * Handles data formatting, API communication, and response logging.
	 *
	 * @param array $user {
	 *     Required user data for creation
	 *     @type int    $id         WordPress user ID
	 *     @type string $email      User's email address
	 *     @type string $first_name User's first name
	 *     @type string $last_name  User's last name
	 *     @type string $username   User's login username
	 *     @type string $role       Optional. User's role in the system
	 * }
	 *
	 * @return object|false API response object on success, false on failure
	 *     Response object contains:
	 *     - data->id: string The newly created Bocs contact ID
	 *     - code: int HTTP response code
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

		$data = '{' . implode(',', $params) . '}';

		$url = 'contacts';
		$result = $curl->post($url, $data, 'contacts', $user['id']);

		if ($result->data && 
			((isset($result->data->data) && (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id)) || 
			 (isset($result->data) && (is_array($result->data) ? $result->data[0]->id : $result->data->id)))) {
			
			$bocs_contact_id = isset($result->data->data) ? 
							 (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id) : 
							 (is_array($result->data) ? $result->data[0]->id : $result->data->id);
			
			add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
			return $bocs_contact_id;
		}

		throw new Exception("Failed to create Bocs user for ID: " . intval($user->ID));
	}

	/**
	 * Handles user meta updates and synchronizes changes with Bocs
	 *
	 * Triggered when WordPress user meta is updated. Compares old and new values
	 * for first name, last name, and email to determine if sync is needed.
	 * If changes are detected, updates or creates corresponding Bocs contact.
	 *
	 * @param array   $meta     Array of user meta data
	 * @param WP_User $user     WordPress user object
	 * @param bool    $update   Whether this is an update or new user
	 * @param array   $userdata Array of user data being updated
	 *
	 * @return array Original meta array, potentially modified
	 *
	 * @throws None - Errors are logged via logMessage()
	 * @since 1.0.0
	 */
	public function insert_user_meta($meta, $user, $update, $userdata)
	{

		$this->logMessage('DEBUG', "Processing user meta update", [
			'user_id' => $user->ID,
			'update' => $update
		]);

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
				$url = 'contacts?query=email:"' . $userdata['user_email'] . '"';
				$this->logMessage('DEBUG', "Searching for existing Bocs contact", [
					'email' => $userdata['user_email']
				]);
				
				$get_user = $curl->get($url, 'contacts', $user->ID);

				if ($get_user->data && 
                    ((isset($get_user->data->data) && count($get_user->data->data) > 0) || 
                     (isset($get_user->data) && count($get_user->data) > 0))) {
                    
                    $bocs_contact_id = isset($get_user->data->data) ? 
                                     $get_user->data->data[0]->id : 
                                     $get_user->data[0]->id;
                    
                    $this->logMessage('INFO', "Found existing Bocs user", [
                        'bocs_contact_id' => $bocs_contact_id,
                        'email' => $userdata['user_email']
                    ]);
                    add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
                } else {
					$this->logMessage('DEBUG', "No existing Bocs contact found");
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

				$this->logMessage('INFO', "Creating new Bocs contact", [
					'params' => $params
				]);
				
				$createdUser = $this->_createUser($params);

				if ($createdUser->data && 
                    ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                     (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                    
                    $bocs_contact_id = isset($createdUser->data->data) ? 
                                     (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                     (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                    
                    $this->logMessage('INFO', "Successfully created Bocs user", [
                        'bocs_id' => $bocs_contact_id
                    ]);
                    add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
                } else {
					$this->logMessage('ERROR', "Failed to create Bocs contact", [
						'response' => $createdUser
					]);
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
				$this->logMessage('DEBUG', "Updating existing Bocs contact", [
					'user_id' => $user->ID
				]);
				
				$addedSync = $curl->put($url, $data, 'contacts', $user->ID);

				// in case that the bocs contact id does not exist
				// then possibly if implies that this is related to
				// previous or deleted bocs account
				// thus we may need to re - add this
				if ($addedSync->code != 200) {

					$this->logMessage('WARNING', "PUT sync failed, attempting POST", [
						'response_code' => $addedSync->code
					]);

					// we will do a post
					$url = 'wp/sync/contacts';
					$this->logMessage('DEBUG', "adding sync using POST");

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

						$this->logMessage('INFO', "creating the user on bocs");
						$createdUser = $this->_createUser($params);

						if ($createdUser->data && 
                            ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                             (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                            
                            $bocs_contact_id = isset($createdUser->data->data) ? 
                                             (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                             (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                            
                            $this->logMessage('INFO', "Successfully created Bocs user as fallback", [
                                'bocs_id' => $bocs_contact_id
                            ]);
                            update_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
                        } else {
							$this->logMessage('ERROR', "NOT creating the user on bocs");
						}
					} else {
						$this->logMessage('INFO', "added sync successfully using POST");
					}
				} else {
					$this->logMessage('INFO', "added sync successfully");
				}
			}
		}

		return $meta;
	}

	/**
	 * Synchronizes WordPress profile updates with Bocs
	 *
	 * Monitors changes to user profile data including email, role, and username.
	 * Handles the complete sync workflow:
	 * 1. Detects changed fields
	 * 2. Searches for existing Bocs contact
	 * 3. Creates or updates Bocs contact as needed
	 * 4. Handles various error cases with fallback strategies
	 *
	 * @param int     $user_id       WordPress user ID being updated
	 * @param WP_User $old_user_data Previous user data before update
	 * @param array   $user_data     New user data being saved
	 *
	 * @return void
	 *
	 * @throws None - Errors are logged via logMessage()
	 * @since 1.0.0
	 */
	public function profile_update($user_id, $old_user_data, $user_data) {
		$this->logMessage('DEBUG', "Processing profile update", [
			'user_id' => $user_id,
			'new_email' => $user_data['user_email'] ?? null
		]);

		// Extract old and new data
		$old_user_login = $old_user_data->data->user_login ?? '';
		$old_user_email = $old_user_data->data->user_email ?? '';
		$old_role = isset($old_user_data->roles[0]) ? $old_user_data->roles[0] : '';

		$new_user_login = $user_data['user_login'] ?? '';
		$new_user_email = $user_data['user_email'] ?? '';
		$new_role = !empty($user_data['role']) ? $user_data['role'] : '';

		// Check for changes
		$new_data = array();
		$do_sync = false;

		if ($old_user_email !== $new_user_email) {
			$new_data['user_email'] = $new_user_email;
			$do_sync = true;
			$this->logMessage('DEBUG', "Email change detected", [
				'old' => $old_user_email,
				'new' => $new_user_email
			]);
		}

		if ($old_role !== $new_role && $new_role !== '') {
			$new_data['role'] = $new_role;
			$do_sync = true;
			$this->logMessage('DEBUG', "Role change detected", [
				'old' => $old_role,
				'new' => $new_role
			]);
		}

		if ($new_user_login !== $old_user_login) {
			$new_data['user_login'] = $new_user_login;
			$do_sync = true;
			$this->logMessage('DEBUG', "Username change detected", [
				'old' => $old_user_login,
				'new' => $new_user_login
			]);
		}

		if ($do_sync) {
			$curl = new Curl();
			$bocs_contact_id = get_user_meta($user_id, 'bocs_contact_id', true);

			if (empty($bocs_contact_id)) {
				$url = 'contacts?query=email:"' . $new_user_email . '"';
				$this->logMessage('DEBUG', "Searching for Bocs user by email", [
					'email' => $new_user_email
				]);
				
				$get_user = $curl->get($url, 'contacts', $user_id);

				if ($result->data && 
					((isset($result->data->data) && (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id)) || 
					(isset($result->data) && (is_array($result->data) ? $result->data[0]->id : $result->data->id)))) {
					
					$bocs_contact_id = isset($result->data->data) ? 
									(is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id) : 
									(is_array($result->data) ? $result->data[0]->id : $result->data->id);
					
					$this->logMessage('INFO', "Successfully created Bocs user", [
						'user_id' => $user->ID,
						'bocs_id' => $bocs_contact_id
					]);

					add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
					return $bocs_contact_id;
				} else {
					$this->logMessage('DEBUG', "No existing Bocs user found");
				}
			}

			if (empty($bocs_contact_id)) {
				// Create new user
				$params = array(
					'id' => $old_user_data->ID,
					'username' => $old_user_data->user_login,
					'first_name' => $user_data['first_name'],
					'last_name' => $user_data['last_name']
				);

				if (!empty($user_data['role'])) {
					$params['role'] = $user_data['role'];
				}

				$this->logMessage('INFO', "Creating new Bocs user", [
					'params' => $params
				]);
				
				$createdUser = $this->_createUser($params);

				if ($createdUser->data && 
                    ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                     (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                    
                    $bocs_contact_id = isset($createdUser->data->data) ? 
                                     (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                     (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                    
                    $this->logMessage('INFO', "Successfully created Bocs user", [
                        'bocs_id' => $bocs_contact_id
                    ]);
                    add_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
                } else {
					$this->logMessage('ERROR', "Failed to create Bocs user", [
						'response' => $createdUser
					]);
				}
			} else {
				// Update existing user
				$data = $this->buildJsonData($this->buildUpdateParams($bocs_contact_id, $new_data));

				$url = 'wp/sync/contacts/' . $old_user_data->ID;
				$this->logMessage('DEBUG', "Updating Bocs user", [
					'user_id' => $old_user_data->ID,
					'data' => $data
				]);
				
				$addedSync = $curl->put($url, $data, 'contacts', $old_user_data->ID);

				if ($addedSync->code != 200) {
					$this->logMessage('WARNING', "PUT sync failed, attempting POST", [
						'response_code' => $addedSync->code
					]);

					$url = 'wp/sync/contacts';
					$createdSync = $curl->post($url, $data, 'contacts', $old_user_data->ID);

					if ($createdSync->code != 200) {
						$this->logMessage('ERROR', "POST sync failed, attempting user creation", [
							'response_code' => $createdSync->code
						]);
						
						// Attempt to create new user as fallback
						$params = array(
							'id' => $old_user_data->ID,
							'username' => $old_user_data->user_login,
							'first_name' => $user_data['first_name'],
							'last_name' => $user_data['last_name']
						);

						if (!empty($user_data['role'])) {
							$params['role'] = $user_data['role'];
						}

						$createdUser = $this->_createUser($params);

						if ($createdUser->data && 
                            ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                             (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                            
                            $bocs_contact_id = isset($createdUser->data->data) ? 
                                             (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                             (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                            
                            $this->logMessage('INFO', "Successfully created Bocs user as fallback", [
                                'bocs_id' => $bocs_contact_id
                            ]);
                            update_user_meta($old_user_data->ID, 'bocs_contact_id', $bocs_contact_id);
                        } else {
							$this->logMessage('ERROR', "All sync attempts failed", [
								'final_response' => $createdUser
							]);
						}
					} else {
						$this->logMessage('INFO', "POST sync successful");
					}
				} else {
					$this->logMessage('INFO', "PUT sync successful");
				}
			}
		} else {
			$this->logMessage('DEBUG', "No changes detected, skipping sync");
		}
	}

	/**
	 * Processes account detail updates from the frontend
	 *
	 * Handles user data updates submitted through account forms, ensuring
	 * changes are properly synchronized with Bocs. Includes:
	 * 1. Data sanitization
	 * 2. Existing contact lookup
	 * 3. Create/update operations
	 * 4. Error handling with multiple fallback attempts
	 *
	 * @param int $user_id WordPress user ID being updated
	 *
	 * @return void
	 *
	 * @throws None - Errors are logged via logMessage()
	 * @since 1.0.0
	 */
	public function save_account_details($user_id) {
		// Initialize variables
		$bocs_contact_id = get_user_meta($user_id, 'bocs_contact_id', true);
		$email = isset($_POST['account_email']) ? sanitize_email($_POST['account_email']) : '';
		$first_name = isset($_POST['account_first_name']) ? sanitize_text_field($_POST['account_first_name']) : '';
		$last_name = isset($_POST['account_last_name']) ? sanitize_text_field($_POST['account_last_name']) : '';
		$old_userdata = get_userdata($user_id);

		$this->logMessage('DEBUG', "Processing account details update", [
			'user_id' => $user_id,
			'email' => $email,
			'has_bocs_id' => !empty($bocs_contact_id)
		]);

		// Search for existing Bocs user if no contact ID
		if (empty($bocs_contact_id)) {
			$url = 'contacts?query=email:"' . $email . '"';
			$this->logMessage('DEBUG', "Searching for Bocs user by email", [
				'email' => $email,
				'url' => $url
			]);
			
			$get_user = $curl->get($url, 'contacts', $user_id);

			if ($result->data && 
				((isset($result->data->data) && (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id)) || 
				(isset($result->data) && (is_array($result->data) ? $result->data[0]->id : $result->data->id)))) {
				
				$bocs_contact_id = isset($result->data->data) ? 
								(is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id) : 
								(is_array($result->data) ? $result->data[0]->id : $result->data->id);
				
				$this->logMessage('INFO', "Successfully created Bocs user", [
					'user_id' => $user->ID,
					'bocs_id' => $bocs_contact_id
				]);

				add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
				return $bocs_contact_id;
			} else {
				$this->logMessage('DEBUG', "No existing Bocs user found for email", [
					'email' => $email
				]);
			}
		}

		if (empty($bocs_contact_id)) {
			// Create new Bocs user
			$this->logMessage('INFO', "Initiating new Bocs user creation", [
				'user_id' => $user_id
			]);
			
			$params = $this->buildUserParams($email, $first_name, $last_name, $old_userdata, $user_id);
			$data = $this->buildJsonData($params);
			
			$this->logMessage('DEBUG', "Sending create user request", [
				'params' => $params
			]);
			
			$createdUser = $curl->post('contacts', $data, 'contacts', $user_id);

			if ($createdUser->data && 
                ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                 (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                
                $bocs_contact_id = isset($createdUser->data->data) ? 
                                 (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                 (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                
                $this->logMessage('INFO', "Successfully created Bocs user", [
                    'bocs_contact_id' => $bocs_contact_id,
                    'user_id' => $user_id
                ]);
                add_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
            } else {
				$this->logMessage('ERROR', "Failed to create Bocs user", [
					'user_id' => $user_id,
					'response' => $createdUser
				]);
			}
		} else {
			// Update existing Bocs user
			$this->logMessage('DEBUG', "Processing updates for existing Bocs user", [
				'bocs_contact_id' => $bocs_contact_id,
				'user_id' => $user_id
			]);
			
			$params = $this->buildUpdateParams($user_id, $first_name, $last_name, $email, $bocs_contact_id);
			
			if (!empty($params['do_sync'])) {
				$data = $this->buildJsonData($params['data']);
				
				$this->logMessage('DEBUG', "Sending sync update request", [
					'data' => $data,
					'user_id' => $user_id
				]);
				
				$this->processSyncUpdates($user_id, $data, $old_userdata, $first_name, $last_name);
			} else {
				$this->logMessage('DEBUG', "No changes detected, skipping sync", [
					'user_id' => $user_id
				]);
			}
		}
	}

	// Helper methods to keep the main method clean
	private function buildUserParams($email, $first_name, $last_name, $old_userdata, $user_id) {
		$this->logMessage('DEBUG', "Building user parameters", [
			'user_id' => $user_id,
			'email' => $email
		]);
		
		$params = [
			'"email": "' . $email . '"',
			'"firstName": "' . $first_name . '"',
			'"lastName": "' . $last_name . '"',
			'"fullName": "' . $first_name . ' ' . $last_name . '"',
			'"externalSource": "Wordpress"',
			'"externalSourceId": "' . $user_id . '"'
		];

		if ($old_userdata && !empty($old_userdata->roles[0])) {
			$params[] = '"role": "' . $old_userdata->roles[0] . '"';
			$this->logMessage('DEBUG', "Added role to parameters", [
				'role' => $old_userdata->roles[0]
			]);
		}

		if ($old_userdata) {
			$params[] = '"username": "' . $old_userdata->user_login . '"';
		}

		return $params;
	}

	private function buildJsonData($params) {
		$json = '{' . implode(',', $params) . '}';
		$this->logMessage('DEBUG', "Built JSON data", [
			'json' => $json
		]);
		return $json;
	}

	private function processSyncUpdates($user_id, $data, $old_userdata, $first_name, $last_name) {
		$this->logMessage('INFO', "Processing sync updates", [
			'user_id' => $user_id
		]);

		$url = 'wp/sync/contacts/' . $user_id;
		$addedSync = $curl->put($url, $data, 'contacts', $user_id);

		if ($addedSync->code != 200) {
			$this->logMessage('WARNING', "PUT sync failed, attempting alternative methods", [
				'response_code' => $addedSync->code,
				'user_id' => $user_id
			]);

			$this->handleFailedSync($user_id, $data, $old_userdata, $first_name, $last_name);
		} else {
			$this->logMessage('INFO', "Sync update successful", [
				'user_id' => $user_id
			]);
		}
	}

	private function handleFailedSync($user_id, $data, $old_userdata, $first_name, $last_name) {
		$this->logMessage('DEBUG', "Attempting POST sync after failed PUT", [
			'user_id' => $user_id
		]);

		$url = 'wp/sync/contacts';
		$postedSync = $curl->post($url, $data, 'contacts', $user_id);

		if ($postedSync->code != 200) {
			$this->logMessage('WARNING', "POST sync failed, attempting user creation", [
				'response_code' => $postedSync->code,
				'user_id' => $user_id
			]);

			$params = array(
				'id' => $user_id,
				'username' => $old_userdata->user_login,
				'first_name' => $first_name,
				'last_name' => $last_name
			);

			if (!empty($old_userdata->roles[0])) {
				$params['role'] = $old_userdata->roles[0];
			}

			$this->logMessage('DEBUG', "Attempting user creation as final fallback", [
				'params' => $params
			]);

			$createdUser = $this->_createUser($params);

			if ($createdUser->data && 
                ((isset($createdUser->data->data) && (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id)) || 
                 (isset($createdUser->data) && (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id)))) {
                
                $bocs_contact_id = isset($createdUser->data->data) ? 
                                 (is_array($createdUser->data->data) ? $createdUser->data->data[0]->id : $createdUser->data->data->id) : 
                                 (is_array($createdUser->data) ? $createdUser->data[0]->id : $createdUser->data->id);
                
                $this->logMessage('INFO', "Successfully created user after sync failures", [
                    'bocs_id' => $bocs_contact_id,
                    'user_id' => $user_id
                ]);
                update_user_meta($user_id, 'bocs_contact_id', $bocs_contact_id);
            } else {
				$this->logMessage('ERROR', "All sync attempts failed", [
					'user_id' => $user_id,
					'final_response' => $createdUser
				]);
			}
		} else {
			$this->logMessage('INFO', "POST sync successful after failed PUT", [
				'user_id' => $user_id
			]);
		}
	}

	/**
	 * Handles new user registration synchronization with Bocs
	 *
	 * Complete workflow for syncing newly registered WordPress users:
	 * 1. Validates user data completeness
	 * 2. Searches for existing Bocs contact by email
	 * 3. Creates new Bocs contact if none exists
	 * 4. Stores relationship between WordPress and Bocs IDs
	 *
	 * @param int $user_id WordPress user ID of new registration
	 *
	 * @return void
	 *
	 * @throws Exception On validation or API communication failures
	 * @since 1.0.0
	 */
	public function bocs_user_register($user_id) {
		try {
			// Validate user
			$user = $this->validateUser($user_id);
			
			// Initialize API client
			$curl = new Curl();
			
			// Try to find existing Bocs user
			$bocs_contact_id = $this->findExistingBocsUser($user, $curl);
			
			// Create new Bocs user if none exists
			if (empty($bocs_contact_id)) {
				$this->createBocsUser($user, $curl);
			}
			
			$this->logMessage('INFO', "Completed user registration sync", [
				'user_id' => $user_id,
				'email' => $user->user_email
			]);
			
		} catch (Exception $e) {
			$this->logMessage('ERROR', "User registration sync failed", [
				'user_id' => $user_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
		}
	}

	/**
	 * Validates a WordPress user and ensures required data is present
	 * 
	 * Performs essential validation checks on the WordPress user:
	 * 1. Verifies the user exists in WordPress
	 * 2. Ensures the user has a valid email address
	 * 
	 * @param int $user_id The WordPress user ID to validate
	 * @return WP_User The validated WordPress user object
	 * @throws Exception If validation fails due to:
	 *                   - Invalid/non-existent user ID
	 *                   - Missing email address
	 */
	private function validateUser($user_id) {
		$this->logMessage('DEBUG', "Starting user validation", [
			'user_id' => $user_id
		]);
		
		// Get WordPress user object
		$user = new WP_User($user_id);
		
		// Check if user exists
		if (!$user || !$user->exists()) {
			$this->logMessage('ERROR', "Invalid user ID", [
				'user_id' => $user_id
			]);
			throw new Exception("Invalid user ID: {$user_id}");
		}
		
		// Verify email presence
		if (empty($user->user_email)) {
			$this->logMessage('ERROR', "Empty user email", [
				'user_id' => $user_id
			]);
			throw new Exception("User email is empty for user ID: {$user_id}");
		}
		
		$this->logMessage('DEBUG', "User validation successful", [
			'user_id' => $user_id,
			'email' => $user->user_email
		]);
		
		return $user;
	}

	/**
	 * Searches for an existing user in the Bocs system by email address
	 * 
	 * Makes an API call to Bocs to find a user with matching email.
	 * If found, stores the Bocs contact ID in WordPress user meta.
	 * 
	 * @param WP_User $user WordPress user object to search for
	 * @param Curl $curl The API client instance
	 * @return string|null The Bocs contact ID if found, null otherwise
	 * @throws Exception If the API request fails
	 */
	private function findExistingBocsUser($user, $curl) {
		$this->logMessage('INFO', "Searching for existing Bocs user", [
			'user_id' => $user->ID,
			'email' => $user->user_email
		]);
		
		// Construct API query with proper email escaping
		$url = 'contacts?query=email:"' . $user->user_email . '"';
		$response = $curl->get($url, 'contacts', $user->ID);
		
		// Validate API response
		if (!$response) {
			$this->logMessage('ERROR', "Bocs API request failed", [
				'email' => $user->user_email,
				'url' => $url
			]);
			throw new Exception("API request failed for email: {$user->user_email}");
		}
		
		// Check if user exists in response
		if ($result->data && 
			((isset($result->data->data) && (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id)) || 
			(isset($result->data) && (is_array($result->data) ? $result->data[0]->id : $result->data->id)))) {
			
			$bocs_contact_id = isset($result->data->data) ? 
							(is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id) : 
							(is_array($result->data) ? $result->data[0]->id : $result->data->id);
			
			$this->logMessage('INFO', "Found existing Bocs user", [
				'bocs_contact_id' => $bocs_contact_id,
				'user_id' => $user->ID
			]);
			
			add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
			return $bocs_contact_id;
		}
		
		$this->logMessage('DEBUG', "No existing Bocs user found", [
			'email' => $user->user_email
		]);
		
		return null;
	}

	/**
	 * Creates a new user in the Bocs system
	 * 
	 * Handles the creation of a new user in Bocs with the following steps:
	 * 1. Determines appropriate user role
	 * 2. Prepares user data
	 * 3. Makes API call to create user
	 * 4. Validates response
	 * 5. Stores Bocs contact ID in WordPress
	 * 
	 * @param WP_User $user WordPress user to create in Bocs
	 * @param Curl $curl The API client instance
	 * @throws Exception If user creation fails
	 */
	private function createBocsUser($user, $curl) {
		$this->logMessage('INFO', "Creating new Bocs user", [
			'user_id' => $user->ID,
			'email' => $user->user_email
		]);
		
		$params = [
			'id' => $user->ID,
			'username' => $user->user_login,
			'email' => $user->user_email,
			'first_name' => $user->first_name,
			'last_name' => $user->last_name
		];

		if (!empty($user->roles[0])) {
			$params['role'] = $user->roles[0];
		}

		$result = $this->_createUser($params);

		if ($result->data && 
            ((isset($result->data->data) && (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id)) || 
             (isset($result->data) && (is_array($result->data) ? $result->data[0]->id : $result->data->id)))) {
            
            $bocs_contact_id = isset($result->data->data) ? 
                             (is_array($result->data->data) ? $result->data->data[0]->id : $result->data->data->id) : 
                             (is_array($result->data) ? $result->data[0]->id : $result->data->id);
            
            $this->logMessage('INFO', "Successfully created Bocs user", [
                'user_id' => $user->ID,
                'bocs_id' => $bocs_contact_id
            ]);

            add_user_meta($user->ID, 'bocs_contact_id', $bocs_contact_id);
            return $bocs_contact_id;
        }

        $this->logMessage('ERROR', "Failed to create Bocs user", [
            'user_id' => $user->ID,
            'response' => $result
        ]);
        throw new Exception("Failed to create Bocs user for ID: " . intval($user->ID));
	}

	/**
	 * Structured logging system for Bocs synchronization operations
	 *
	 * Provides consistent, leveled logging for sync operations with context data.
	 * Log levels indicate severity and urgency:
	 * - ERROR: Critical failures requiring immediate attention
	 * - WARNING: Potential issues that may need investigation
	 * - INFO: Important successful operations
	 * - DEBUG: Detailed information for troubleshooting
	 *
	 * @param string $level   Log level (ERROR|WARNING|INFO|DEBUG)
	 * @param string $message Human-readable log message
	 * @param array  $context Additional data to provide context for the log entry
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function logMessage($level, $message, $context = []) {
		$contextStr = !empty($context) ? ' ' . print_r($context, true) : '';
		error_log("[Bocs Sync][{$level}] {$message}{$contextStr}");
	}
}
