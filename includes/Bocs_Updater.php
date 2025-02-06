<?php
/**
 * Bocs Plugin Updater
 *
 * This class handles automatic updates for the Bocs WordPress plugin by checking
 * for new releases on GitHub and managing the update process.
 *
 * @package    Bocs
 * @subpackage Updater
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Bocs_Updater
 *
 * Manages the update process for the Bocs plugin by integrating with GitHub releases.
 * Supports both production and development environments with different update behaviors.
 *
 * @since 1.0.0
 */
class Bocs_Updater {
    /**
     * Plugin file path
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $file;

    /**
     * Plugin data
     *
     * @since  1.0.0
     * @access private
     * @var    array
     */
    private $plugin;

    /**
     * Plugin basename
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $basename;

    /**
     * Plugin activation status
     *
     * @since  1.0.0
     * @access private
     * @var    bool
     */
    private $active;

    /**
     * GitHub API response cache
     *
     * @since  1.0.0
     * @access private
     * @var    object|null
     */
    private $github_response;

    /**
     * GitHub API URL
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $github_url = 'https://api.github.com/repos/Bocs-io/bocs-wordpress-plugin';

    /**
     * Initialize the updater
     *
     * Sets up the class properties and hooks into the WordPress update system.
     *
     * @since 1.0.0
     * @param string $file The main plugin file path.
     */
    public function __construct($file) {
        $this->file = $file;
        
        // Force WordPress to check for updates
        delete_site_transient('update_plugins');
        
        // Initialize hooks
        add_action('admin_init', array($this, 'set_plugin_properties'));
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Force an immediate check
        add_action('admin_init', function() {
            wp_update_plugins();
        });
    }

    /**
     * Set plugin properties
     *
     * Initializes plugin data, basename, and active status.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function set_plugin_properties() {
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
    }

    /**
     * Get repository information
     *
     * Fetches release information from GitHub based on environment:
     * - Production: Gets the latest stable release
     * - Development: Gets the latest pre-release
     *
     * @since  1.0.0
     * @access private
     * @return bool False on failure
     */
    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $environment = defined('BOCS_ENVIRONMENT') ? BOCS_ENVIRONMENT : 'prod';
            $endpoint = $environment === 'dev' ? '/releases' : '/releases/latest';
            $request_uri = $this->github_url . $endpoint;
            
            $response = wp_remote_get($request_uri, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version')
                ),
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                error_log('Bocs Updater: GitHub API request failed - ' . $response->get_error_message());
                return false;
            }

            $response_body = json_decode(wp_remote_retrieve_body($response));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Bocs Updater: JSON decode error - ' . json_last_error_msg());
                return false;
            }

            // For dev environment, find the latest pre-release
            if ($environment === 'dev' && is_array($response_body)) {
                foreach ($response_body as $release) {
                    if ($release->prerelease) {
                        $response_body = $release;
                        break;
                    }
                }
            }

            if (!isset($response_body->tag_name)) {
                error_log('Bocs Updater: No tag_name found in response');
                return false;
            }

            $this->github_response = $response_body;
        }
    }

    /**
     * Modify the WordPress update transient
     *
     * Checks if a new version is available and modifies the update transient accordingly.
     *
     * @since  1.0.0
     * @access public
     * @param  object $transient WordPress plugin update transient.
     * @return object Modified transient with Bocs update information.
     */
    public function modify_transient($transient) {
        if (!property_exists($transient, 'checked')) {
            return $transient;
        }

        $checked = $transient->checked;
        if (!isset($checked[$this->basename])) {
            return $transient;
        }

        $this->get_repository_info();

        if (!$this->github_response) {
            return $transient;
        }

        // Remove 'v' prefix from GitHub tag for version comparison
        $latest_version = ltrim($this->github_response->tag_name, 'v');
        $current_version = $checked[$this->basename];

        $out_of_date = version_compare(
            $latest_version,
            $current_version,
            'gt'
        );

        if ($out_of_date) {
            $plugin = array(
                'url' => $this->plugin['PluginURI'],
                'slug' => current(explode('/', $this->basename)),
                'package' => $this->github_response->zipball_url,
                'new_version' => $latest_version,
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => '',
                'requires_php' => '',
                'compatibility' => new stdClass(),
            );

            $transient->response[$this->basename] = (object) $plugin;
        }

        return $transient;
    }

    /**
     * Generate plugin information for the WordPress updates screen
     *
     * @since  1.0.0
     * @access public
     * @param  object $result The result object.
     * @param  string $action The type of information being requested.
     * @param  object $args   Plugin arguments.
     * @return object Plugin information.
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!empty($args->slug)) {
            if ($args->slug == current(explode('/', $this->basename))) {
                $this->get_repository_info();

                $plugin = array(
                    'name'              => $this->plugin['Name'],
                    'slug'              => $this->basename,
                    'version'           => $this->github_response->tag_name,
                    'author'            => $this->plugin['AuthorName'],
                    'author_profile'    => $this->plugin['AuthorURI'],
                    'last_updated'      => $this->github_response->published_at,
                    'homepage'          => $this->plugin['PluginURI'],
                    'short_description' => $this->plugin['Description'],
                    'sections'          => array(
                        'Description'   => $this->plugin['Description'],
                        'Updates'       => $this->github_response->body,
                    ),
                    'download_link'     => $this->github_response->zipball_url
                );

                return (object) $plugin;
            }
        }

        return $result;
    }

    /**
     * Actions to perform after plugin installation
     *
     * Moves the plugin to the correct location and reactivates it if it was active.
     *
     * @since  1.0.0
     * @access public
     * @param  bool  $response      Installation response.
     * @param  array $hook_extra    Extra arguments passed to hooked filters.
     * @param  array $result        Installation result data.
     * @return array Modified installation result data.
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}