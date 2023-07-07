<?php

if (!defined('WPINC') || !defined('ABSPATH')) {
	die;
}

class Updater {

	private $file;

	private $plugin;

	private $basename;

	private $active;

	private $username;

	private $repository;

	private $authorize_token;

	private $github_response;

	public function __construct( $file ) {

		$this->file = $file;
		return $this;
	}

	public function set_plugin_properties() {

        error_log("set_plugin_properties");

		$this->plugin	= get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active	= is_plugin_active( $this->basename );
	}

	public function set_username( $username ) {
		$this->username = $username;
	}

	public function set_repository( $repository ) {
		$this->repository = $repository;
	}

	public function authorize( $token ) {
		$this->authorize_token = $token;
	}

	private function get_repository_info() {
		if ( is_null( $this->github_response ) ) { // Do we have a response?
			$args = array();
			// $request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases', $this->username, $this->repository ); // Build URI
			$request_uri = sprintf('https://b84gp25mke.execute-api.ap-southeast-2.amazonaws.com/dev/cru-wordpress-plugins-repository-get-latest-version?plugin=%s', 'bocs');

			$args = array();

			if( $this->authorize_token ) { // Is there an access token?
				$args['headers']['Authorization'] = "bearer {$this->authorize_token}"; // Set the headers
			}

			$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_uri, $args ) ), true ); // Get JSON and parse it

			if( is_array( $response ) ) { // If it is an array
				$response = current( $response ); // Get the first item
			}

            error_log(print_r($response, true));

			$this->github_response = $response; // Set it to our property
		}
	}

	public function initialize() {

        // add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
        // add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3);
        // add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

		// Add Authorization Token to download_package
		//add_filter( 'upgrader_pre_download',
		//	function() {
		//		add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
		//		return false; // upgrader_pre_download filter default return value.
		//	}
		//);
	}

	public function modify_transient( $transient ) {

        error_log("modify_transient");

		if( property_exists( $transient, 'checked') ) { // Check if transient has a checked property

			if( $checked = $transient->checked ) { // Did Wordpress check for updates?

				$this->get_repository_info(); // Get the repo info

				$version = $this->github_response['tag_name'] ?? null;
				$version = ltrim($version, 'v');

				$out_of_date = version_compare( $version, $checked[ $this->basename ], 'gt' ); // Check if we're out of date

				if( $out_of_date ) {

					$new_files = $this->github_response['zipball_url']; // Get the ZIP

					$slug = current( explode('/', $this->basename ) ); // Create valid slug

					$plugin = array( // setup our plugin info
						'url' => $this->plugin["PluginURI"],
						'slug' => $slug,
						'package' => $new_files,
						'new_version' => $version
					);

					$transient->response[$this->basename] = (object) $plugin; // Return it in response
				}
			}
		}

		return $transient; // Return filtered transient
	}

	public function plugin_popup( $result, $action, $args ) {

		if( ! empty( $args->slug ) ) { // If there is a slug

			if( $args->slug == current( explode( '/' , $this->basename ) ) ) { // And it's our slug

				$this->get_repository_info(); // Get our repo info

				$version = $this->github_response['tag_name'] ?? null;
				$version = ltrim($version, 'v');

				// Set it to an array
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
					'last_updated'		=> $this->github_response['published_at'],
					'homepage'			=> $this->plugin["PluginURI"],
					'short_description' => $this->plugin["Description"],
					'sections'			=> array(
						'Description'	=> $this->plugin["Description"],
						'Updates'		=> $this->github_response['body'],
					),
					'download_link'		=> $this->github_response['zipball_url']
				);

				return (object) $plugin; // Return the data
			}

		}
		return $result; // Otherwise return default
	}

	public function download_package( $args, $url ) {

		if ( null !== $args['filename'] ) {
			if( $this->authorize_token ) {
				$args = array_merge( $args, array( "headers" => array( "Authorization" => "token {$this->authorize_token}" ) ) );
			}
		}

		remove_filter( 'http_request_args', [ $this, 'download_package' ] );

		return $args;
	}

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