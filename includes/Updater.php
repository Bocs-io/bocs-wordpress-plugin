<?php

if (!defined('WPINC') || !defined('ABSPATH')) {
	die;
}

/**
 * WordPress Plugin Update Handler
 *
 * Manages plugin updates by checking for new versions and handling the update process.
 * Supports both release and development versions based on environment settings.
 *
 * @since 0.0.116
 */
class Updater {

	/**
	 * Full path to the plugin file
	 *
	 * @since 0.0.116
	 * @access private
	 * @var string
	 */
	private $file;

	/**
	 * Plugin data array from get_plugin_data()
	 *
	 * @since 0.0.116
	 * @access private
	 * @var array
	 */
	private $plugin;

	/**
	 * Plugin basename (plugin-name/plugin-name.php)
	 *
	 * @since 0.0.116
	 * @access private
	 * @var string
	 */
	private $basename;

	/**
	 * Whether the plugin is currently active
	 *
	 * @since 0.0.116
	 * @access private
	 * @var boolean
	 */
	private $active;

	/**
	 * Repository username
	 *
	 * @since 0.0.116
	 * @access private
	 * @var string
	 */
	private $username;

	/**
	 * Repository name
	 *
	 * @since 0.0.116
	 * @access private
	 * @var string
	 */
	private $repository;

	/**
	 * Authorization token for private repositories
	 *
	 * @since 0.0.116
	 * @access private
	 * @var string
	 */
	private $authorize_token;

	/**
	 * Cached response from the repository API
	 *
	 * @since 0.0.116
	 * @access private
	 * @var array|null
	 */
	private $github_response;

	/**
	 * Initialize the updater with a plugin file path
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param string $file Full path to the main plugin file
	 * @return Updater Returns instance of self for method chaining
	 */
	public function __construct( $file ) {
		$this->file = $file;
		return $this;
	}

	/**
	 * Set up basic plugin properties
	 *
	 * Retrieves and stores plugin data, basename, and active status
	 * for use in update checks.
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @return void
	 */
	public function set_plugin_properties() {
		$this->plugin	= get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active	= is_plugin_active( $this->basename );
	}

	/**
	 * Set the repository username
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param string $username The username for the repository
	 * @return void
	 */
	public function set_username( $username ) {
		$this->username = $username;
	}

	/**
	 * Set the repository name
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param string $repository The name of the repository
	 * @return void
	 */
	public function set_repository( $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Set the authorization token
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param string $token The authorization token for private repository access
	 * @return void
	 */
	public function authorize( $token ) {
		$this->authorize_token = $token;
	}

	/**
	 * Fetches the latest plugin version information from the repository API.
	 * 
	 * Makes an HTTP request to retrieve version information based on environment settings.
	 * For dev environment, fetches the absolute latest version (including pre-releases).
	 * For other environments, only fetches official releases.
	 * 
	 * @since 0.0.116
	 * @access private
	 * 
	 * @return void
	 */
	private function get_repository_info() {
		// Only fetch repository data if not already cached
		if ( is_null( $this->github_response ) ) {
			// Build API request URL with plugin name, environment, and release type
			$request_uri = sprintf(
				'https://b84gp25mke.execute-api.ap-southeast-2.amazonaws.com/dev/cru-wordpress-plugins-repository-get-latest-version?plugin=%s&environment=%s&release_type=%s',
				'bocs',
				BOCS_ENVIRONMENT,
				BOCS_ENVIRONMENT === 'dev' ? 'latest' : 'release'
			);

			$args = array();

			// Add authorization token to request headers if available
			if( $this->authorize_token ) {
				$args['headers']['Authorization'] = "bearer {$this->authorize_token}";
			}

			// Make remote GET request to the API
			$response = wp_remote_get( $request_uri, $args );
			if ( is_wp_error( $response ) ) {
				return;
			}

			// Decode JSON response body
			$response = json_decode( wp_remote_retrieve_body( $response ), true );

			// If response is array, get first item
			if( is_array( $response ) ) {
				$response = current( $response );
			}

			// Store response if valid and contains version tag
			if ( !empty($response) && isset($response['tag_name']) ) {
				$this->github_response = $response;
			}
		}
	}

	/**
	 * Initialize the update checker
	 * 
	 * Sets up the WordPress hooks needed for the update process.
	 * Currently disabled but can be enabled by uncommenting the filters.
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @return void
	 */
	public function initialize() {}

	/**
	 * Modify the transient for plugin updates before WordPress processes it
	 *
	 * Checks if a new version exists and if so, adds the update information
	 * to the WordPress update transient object.
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param object $transient WordPress plugin update transient object
	 * @return object Modified transient object with update information if available
	 */
	public function modify_transient( $transient ) {
		if( property_exists( $transient, 'checked') ) {
			if( $checked = $transient->checked ) {
				$this->get_repository_info();

				if ( empty($this->github_response) ) {
					return $transient;
				}

				$version = $this->github_response['tag_name'] ?? null;
				$version = ltrim($version, 'v');

				if ($version) {
					$current_version = $checked[ $this->basename ];
					$out_of_date = version_compare( $version, $current_version, 'gt' );

					if( $out_of_date ) {
						$new_files = $this->github_response['zipball_url'];
						$slug = current( explode('/', $this->basename ) );

						$plugin = array(
							'url' => $this->plugin["PluginURI"],
							'slug' => $slug,
							'package' => $new_files,
							'new_version' => $version,
							'tested' => $this->github_response['tested'] ?? '',
							'requires' => $this->github_response['requires'] ?? '',
							'compatibility' => true
						);

						$transient->response[$this->basename] = (object) $plugin;
					}
				}
			}
		}

		return $transient;
	}

	/**
	 * Handle the plugin information popup in WordPress admin
	 *
	 * Provides custom plugin information for the update popup/modal
	 * when users click "View version x.x.x details" in the plugins page.
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param object|false $result The result object. Default false.
	 * @param string       $action The type of information being requested from the Plugin Installation API.
	 * @param object       $args   Plugin API arguments.
	 * @return object|false Plugin information or false if not our plugin
	 */
	public function plugin_popup( $result, $action, $args ) {
		if( ! empty( $args->slug ) ) {
			if( $args->slug == current( explode( '/' , $this->basename ) ) ) {
				$this->get_repository_info();

				if (empty($this->github_response)) {
					return $result;
				}

				$version = $this->github_response['tag_name'] ?? null;
				$version = ltrim($version, 'v');

				$plugin = array(
					'name'				=> $this->plugin["Name"],
					'slug'				=> $this->basename,
					'requires'					=> '3.3',
					'tested'						=> '4.4.1',
					'rating'						=> '100.0',
					'num_ratings'				=> '10823',
					'downloaded'				=> '14249',
					'added'							=> '2023-07-06',
					'version'			=> $version,
					'author'			=> $this->plugin["AuthorName"],
					'author_profile'	=> $this->plugin["AuthorURI"],
					'last_updated'		=> $this->github_response['published_at'] ?? '',
					'homepage'			=> $this->plugin["PluginURI"],
					'short_description' => $this->plugin["Description"],
					'sections'			=> array(
						'Description'	=> $this->plugin["Description"],
						'Updates'		=> $this->github_response['body'] ?? '',
					),
					'download_link'		=> $this->github_response['zipball_url'] ?? ''
				);

				return (object) $plugin;
			}
		}
		return $result;
	}

	/**
	 * Modify the download package arguments
	 *
	 * Adds authorization headers to the download request if needed.
	 * This ensures private repositories can be downloaded with proper authentication.
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param array  $args Array of HTTP Request args.
	 * @param string $url  The URL to retrieve.
	 * @return array Modified array of HTTP request arguments
	 */
	public function download_package( $args, $url ) {
		if ( null !== $args['filename'] ) {
			if( $this->authorize_token ) {
				$args = array_merge( $args, array( "headers" => array( "Authorization" => "token {$this->authorize_token}" ) ) );
			}
		}

		remove_filter( 'http_request_args', [ $this, 'download_package' ] );

		return $args;
	}

	/**
	 * Handles post-installation tasks
	 *
	 * After a successful plugin update, this method:
	 * 1. Moves the plugin files to the correct directory
	 * 2. Updates the destination path
	 * 3. Reactivates the plugin if it was active before the update
	 *
	 * @since 0.0.116
	 * @access public
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 * @return array Modified installation result data
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem; // Get global FS object

		$install_directory = plugin_dir_path( $this->file ); // Our plugin directory
		$wp_filesystem->move( $result['destination'], $install_directory ); // Move files to the plugin dir
		$result['destination'] = $install_directory; // Set the destination for the rest of the stack

		if ( $this->active ) { // If it was active
			activate_plugin( $this->basename ); // Reactivate
		}

		return $result;
	}
}