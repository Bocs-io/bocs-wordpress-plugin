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
		if ( is_null( $this->github_response ) ) {
			// Use GitHub API to get release info
			$request_uri = sprintf(
				'https://api.github.com/repos/%s/%s/releases',
				$this->username,
				$this->repository
			);

			$args = array();

			if( $this->authorize_token ) {
				$args['headers']['Authorization'] = "token {$this->authorize_token}";
			}

			$response = wp_remote_get( $request_uri, $args );
			if ( is_wp_error( $response ) ) {
				return;
			}

			$releases = json_decode( wp_remote_retrieve_body( $response ), true );
			
			// Filter based on environment
			$release_type = BOCS_ENVIRONMENT === 'dev' ? 'prerelease' : 'release';
			foreach ($releases as $release) {
				if ($release_type === 'prerelease' || (!$release['prerelease'] && $release_type === 'release')) {
					$this->github_response = $release;
					break;
				}
			}
		}
	}

	public function initialize() {
		// Enable update filters
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3);
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

		// Add Authorization Token to download_package
		add_filter( 'upgrader_pre_download',
			function() {
				add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
				return false; // upgrader_pre_download filter default return value.
			}
		);
	}

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