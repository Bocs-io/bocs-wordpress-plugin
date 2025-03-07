<?php
/**
 * Authentication Handler Class
 *
 * Handles JWT authentication for the Bocs plugin.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.1
 */

use Firebase\JWT\JWT;

class Auth {

	/** @var string The plugin name */
	private $plugin_name;

	/** @var int The API version */
	private $version;

	/** @var string The API namespace */
	private $namespace;

	/** @var WP_Error|null Authentication error if any */
	private $auth_error = null;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		$this->version = 1;
		$this->plugin_name = BOCS_NAME;
		$this->namespace = $this->plugin_name . '/v' . intval($this->version);
	}

	/**
	 * Add the endpoints to the API
	 */
	public function add_api_routes() {
		register_rest_route($this->namespace, 'token', [
			'methods'  => 'POST',
			'callback' => array($this, 'generate_token'),
			'permission_callback' => '__return_true',
		]);

		register_rest_route($this->namespace, 'token/validate', array(
			'methods'  => 'POST',
			'callback' => array($this, 'validate_token'),
			'permission_callback' => '__return_true',
		));
	}

	/**
	 * Add CORs support to the request.
	 */
	public function add_cors_support() {
		$enable_cors = defined('BOCS_AUTH_CORS_ENABLE') ? BOCS_AUTH_CORS_ENABLE : false;

		if ($enable_cors) {
			$headers = apply_filters('bocs_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization');
			header(sprintf('Access-Control-Allow-Headers: %s', $headers));
		}
	}

	/**
	 * Generate a JWT token for authenticated users
	 *
	 * @param WP_REST_Request $request The request object
	 * @return WP_Error|array The generated token or error
	 */
	public function generate_token($request) {
		$secret_key = get_option(BOCS_SLUG . '_key');
		$username = $request->get_param('username');
		$password = $request->get_param('password');

		if (!$secret_key) {
			return new WP_Error(
				'bocs_auth_bad_config',
				__('Bocs is not configured properly, please contact the administrator', 'bocs-wordpress'),
				array('status' => 403)
			);
		}

		$user = wp_authenticate($username, $password);

		if (is_wp_error($user)) {
			return new WP_Error(
				'bocs_auth_failed',
				__('Invalid Credentials.', 'bocs-wordpress'),
				array('status' => 403)
			);
		}

		$issuedAt = time();
		$notBefore = apply_filters('bocs_auth_not_before', $issuedAt, $issuedAt);
		$expire = apply_filters('bocs_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

		$token = array(
			'iss'  => get_bloginfo('url'),
			'iat'  => $issuedAt,
			'nbf'  => $notBefore,
			'exp'  => $expire,
			'data' => array(
				'user' => array(
					'id' => $user->data->ID,
				),
			),
		);

		$token = JWT::encode(
			apply_filters('bocs_auth_token_before_sign', $token),
			$secret_key,
			'HS256'
		);

		$data = array(
			'token'             => $token,
			'user_email'        => $user->data->user_email,
			'user_nicename'     => $user->data->user_nicename,
			'user_display_name' => $user->data->display_name,
		);

		return apply_filters('bocs_auth_token_before_dispatch', $data, $user);
	}

	/**
	 * Authenticate the user based on the token
	 *
	 * @param int|bool $user Logged User ID
	 * @return int|bool
	 */
	public function determine_current_user($user) {
		$validate_uri = strpos($_SERVER['REQUEST_URI'], 'token/validate');
		if ($validate_uri > 0) {
			return $user;
		}

		$token = $this->validate_token(false);

		if ($token === false) {
			return $user;
		} elseif (is_wp_error($token)) {
			if ($token->get_error_code() !== 'bocs_auth_no_auth_header') {
				$this->auth_error = $token;
			}
			return $user;
		}

		return $token->data->user->id;
	}

	/**
	 * Validate a JWT token
	 *
	 * @param bool $output Whether to output the validation result
	 * @return WP_Error|object|array|bool
	 */
	public function validate_token($output = true) {
		$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
		
		if (!$auth) {
			return false;
		}

		list($token) = sscanf($auth, 'Bearer %s');
		if (!$token) {
			return new WP_Error(
				'bocs_auth_bad_auth_header',
				__('Authorization header malformed.', 'bocs-wordpress'),
				array('status' => 403)
			);
		}

		$secret_key = get_option(BOCS_SLUG . '_key');
		if (!$secret_key) {
			return new WP_Error(
				'bocs_auth_bad_config',
				__('Bocs is not configured properly, please contact the administrator', 'bocs-wordpress'),
				array('status' => 403)
			);
		}

		try {
			$token = JWT::decode($token, $secret_key, array('HS256'));

			if ($token->iss !== get_bloginfo('url')) {
				return new WP_Error(
					'bocs_auth_bad_iss',
					__('The issuer does not match this server', 'bocs-wordpress'),
					array('status' => 403)
				);
			}

			if (!isset($token->data->user->id)) {
				return new WP_Error(
					'bocs_auth_bad_request',
					__('User ID not found in the token', 'bocs-wordpress'),
					array('status' => 403)
				);
			}

			if (!$output) {
				return $token;
			}

			return array(
				'code' => 'bocs_auth_valid_token',
				'data' => array('status' => 200),
			);

		} catch (Exception $e) {
			return new WP_Error(
				'bocs_auth_invalid_token',
				$e->getMessage(),
				array('status' => 403)
			);
		}
	}

	/**
	 * Filter for rest_pre_dispatch
	 *
	 * @param mixed $request The request object
	 * @return mixed
	 */
	public function rest_pre_dispatch($request) {
		if (is_wp_error($this->auth_error)) {
			return $this->auth_error;
		}
		return $request;
	}
}
