<?php
/**
 * Bocs
 * COPYRIGHT Bocs.io PTY LTD 2019
 * URL: https://bocs.io
 * Email: hello@bocs.io
 */


use Firebase\JWT\JWT;

class Auth {

	private $plugin_name;

	private $version;

	private $namespace;

	private $auth_error = null;

	public function __construct(){

		$this->version = 1;
		$this->plugin_name = BOCS_NAME;
		$this->namespace = $this->plugin_name.'/v'.intval($this->version);
	}

	/**
	 * Add the endpoints to the API
	 */
	public function add_api_routes() {

		register_rest_route($this->namespace, 'token', [
			'methods' => 'POST',
			'callback' => array($this, 'generate_token'),
		]);

		register_rest_route($this->namespace, 'token/validate', array(
			'methods' => 'POST',
			'callback' => array($this, 'validate_token'),
		));

	}

	/**
	 * Add CORs suppot to the request.
	 */
	public function add_cors_support( ){
		$enable_cors = defined('BOCS_AUTH_CORS_ENABLE') ? BOCS_AUTH_CORS_ENABLE : false;

		if ($enable_cors) {
			$headers = apply_filters('bocs_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization');
			header(sprintf('Access-Control-Allow-Headers: %s', $headers));
		}
	}

	/**
	 * Get the user and password in the request body and generate a JWT
	 *
	 * @param [type] $request [description]
	 *
	 * @return [type] [description]
	 */
	public function generate_token($request)
	{
		$secret_key = get_option(BOCS_SLUG.'_key');
		$username = $request->get_param('username');
		$password = $request->get_param('password');

		/** First thing, check the secret key if not exist return a error*/
		if (!$secret_key) {
			return new WP_Error(
				'bocs_auth_bad_config',
				__('Bocs is not configurated properly, please contact the admin', 'wp-api-bocs-auth'),
				array(
					'status' => 403,
				)
			);
		}
		/** Try to authenticate the user with the passed credentials*/
		$user = wp_authenticate($username, $password);

		/** If the authentication fails return a error*/
		if (is_wp_error($user)) {
			return new WP_Error(
				'bocs_auth_failed',
				__('Invalid Credentials.', 'wp-api-bocs-auth'),
				array(
					'status' => 403,
				)
			);
		}

		/** Valid credentials, the user exists create the according Token */
		$issuedAt = time();
		$notBefore = apply_filters('bocs_auth_not_before', $issuedAt, $issuedAt);
		$expire = apply_filters('bocs_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

		$token = array(
			'iss' => get_bloginfo('url'),
			'iat' => $issuedAt,
			'nbf' => $notBefore,
			'exp' => $expire,
			'data' => array(
				'user' => array(
					'id' => $user->data->ID,
				),
			),
		);

		/** Let the user modify the token data before the sign. */
		$token = JWT::encode(apply_filters('bocs_auth_token_before_sign', $token), $secret_key, 'HS256');

		/** The token is signed, now create the object with no sensible user data to the client*/
		$data = array(
			'token' => $token,
			'user_email' => $user->data->user_email,
			'user_nicename' => $user->data->user_nicename,
			'user_display_name' => $user->data->display_name,
		);

		/** Let the user modify the data before send it back */
		return apply_filters('bocs_auth_token_before_dispatch', $data, $user);
	}

	/**
	 * This is our Middleware to try to authenticate the user according to the
	 * token send.
	 *
	 * @param (int|bool) $user Logged User ID
	 *
	 * @return (int|bool)
	 */
	public function determine_current_user($user)
	{
		// if( is_admin() ) return false;
		/*
		 * if the request URI is for validate the token don't do anything,
		 * this avoid double calls to the validate_token function.
		 */
		$validate_uri = strpos($_SERVER['REQUEST_URI'], 'token/validate');
		if ($validate_uri > 0) {
			return $user;
		}


		$token = $this->validate_token(false);

		if( $token === false ) {
			return $user;
		} else if (is_wp_error($token)) {
			if ($token->get_error_code() != 'bocs_auth_no_auth_header') {
				// If there is a error, store it to show it after see rest_pre_dispatch
				$this->auth_error = $token;
				return $user;
			} else {
				return $user;
			}
		}

		// Everything is ok, return the user ID stored in the token
		return $token->data->user->id;


	}

	/**
	 * Main validation function, this function try to get the Autentication
	 * headers and decoded.
	 *
	 * @param bool $output
	 *
	 * @return WP_Error | Object
	 */
	public function validate_token($output = true)
	{
		/*
		 * Looking for the HTTP_AUTHORIZATION header, if not present just
		 * return the user.
		 */
		$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ?  $_SERVER['HTTP_AUTHORIZATION'] : false;
		if (!$auth) {

			/*return new WP_Error(
				'bocs_auth_no_auth_header',
				__('Authorization header not found.', 'wp-api-bocs-auth'),
				array(
					'status' => 403,
				)
				);*/
			return false;
		}

		/*
		 * The HTTP_AUTHORIZATION is present verify the format
		 * if the format is wrong return the user.
		 */
		list($token) = sscanf($auth, 'Bearer %s');
		if (!$token) {
			return new WP_Error(
				'bocs_auth_bad_auth_header',
				__('Authorization header malformed.', 'wp-api-bocs-auth'),
				array(
					'status' => 403,
				)
			);
		}

		/** Get the Secret Key */
		$secret_key = get_option(BOCS_SLUG.'_key');
		if (!$secret_key) {
			return new WP_Error(
				'bocs_auth_bad_config',
				__('Bocs is not configurated properly, please contact the admin', 'wp-api-bocs-auth'),
				array(
					'status' => 403,
				)
			);
		}

		/** Try to decode the token */
		try {
			$token = JWT::decode($token, $secret_key, array('HS256'));
			/** The Token is decoded now validate the iss */
			if ($token->iss != get_bloginfo('url')) {
				/** The iss do not match, return error */
				error_log('-------- LINE 227 ---------');
				return new WP_Error(
					'bocs_auth_bad_iss',
					__('The iss do not match with this server', 'wp-api-bocs-auth'),
					array(
						'status' => 403,
					)
				);
			}
			/** So far so good, validate the user id in the token */
			if (!isset($token->data->user->id)) {
				/** No user id in the token, abort!! */
				return new WP_Error(
					'bocs_auth_bad_request',
					__('User ID not found in the token', 'wp-api-bocs-auth'),
					array(
						'status' => 403,
					)
				);
			}
			/** Everything looks good return the decoded token if the $output is false */
			if (!$output) {
				return $token;
			}
			/** If the output is true return an answer to the request to show it */
			return array(
				'code' => 'bocs_auth_valid_token',
				'data' => array(
					'status' => 200,
				),
			);
		} catch (Exception $e) {
			/** Something is wrong trying to decode the token, send back the error */
			return new WP_Error(
				'bocs_auth_invalid_token',
				$e->getMessage(),
				array(
					'status' => 403,
				)
			);
		}
	}

	/**
	 * Filter to hook the rest_pre_dispatch, if the is an error in the request
	 * send it, if there is no error just continue with the current request.
	 *
	 * @param $request
	 */
	public function rest_pre_dispatch($request)
	{
		if (is_wp_error($this->auth_error)) {
			return $this->auth_error;
		}
		return $request;
	}
}
