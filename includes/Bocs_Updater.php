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
     * @since  0.0.109
     * @access private
     * @var    string
     */
    private $file;

    /**
     * Plugin data
     *
     * @since  0.0.109
     * @access private
     * @var    array
     */
    private $plugin;

    /**
     * Plugin basename
     *
     * @since  0.0.109
     * @access private
     * @var    string
     */
    private $basename;

    /**
     * Plugin activation status
     *
     * @since  0.0.109
     * @access private
     * @var    bool
     */
    private $active;

    /**
     * GitHub API response cache
     *
     * @since  0.0.109
     * @access private
     * @var    object|null
     */
    private $github_response;

    /**
     * GitHub API URL
     *
     * @since  0.0.109
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
        
        // Add new hooks for duplicate plugin detection
        add_filter('wp_handle_upload_prefilter', array($this, 'check_for_duplicate_plugin'));
        add_action('admin_notices', array($this, 'show_duplicate_notice'));
        
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
     * @since  0.0.109
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
     * @since  0.0.109
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
     * @since  0.0.109
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
     * @since  0.0.109
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
     * @since  0.0.109
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

    /**
     * Check for duplicate plugin uploads
     *
     * Checks for existing BOCS plugin installations.
     * Allows installation only if:
     * 1. No existing BOCS plugin is installed, or
     * 2. The uploading version is newer than the existing version
     *
     * @since  0.0.113
     * @access public
     * @param  string $source Path to the temporary plugin directory
     * @return string Path to the temporary plugin directory
     */
    public function check_for_duplicate_plugin($source) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // First check if the uploading plugin is BOCS
        if (!file_exists($source)) {
            return $source;
        }

        // Get the plugin data from the uploading file
        $plugin_data = get_plugin_data($source . '/bocs.php', false, false);
        if (empty($plugin_data) || empty($plugin_data['Name'])) {
            return $source;
        }

        // If this is not a BOCS plugin upload, return early
        if (strtolower($plugin_data['Name']) !== 'bocs') {
            return $source;
        }

        // Get the version of the uploading plugin
        $uploading_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

        // Now check for existing BOCS installations
        $all_plugins = get_plugins();
        $existing_bocs = null;

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugin_name = isset($plugin_data['Name']) ? $plugin_data['Name'] : '';
            if (empty($plugin_name)) {
                continue;
            }
            
            if (strtolower($plugin_name) === 'bocs') {
                $existing_bocs = array(
                    'path' => $plugin_path,
                    'version' => isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0'
                );
                break;
            }
        }

        // If BOCS exists and the uploading version is not newer
        if ($existing_bocs && version_compare($uploading_version, $existing_bocs['version'], '<=')) {
            // Create a properly formatted error message
            $message = '<div class="error">';
            $message .= '<p>' . sprintf(
                esc_html__('Warning: Another instance of the Bocs plugin (version %s) was detected.', 'bocs-wordpress'),
                $existing_bocs['version']
            ) . '</p>';
            $message .= '<p>' . sprintf(
                esc_html__('The version you are trying to install (%s) is not newer than the existing version.', 'bocs-wordpress'),
                $uploading_version
            ) . '</p>';
            $message .= '<p>' . esc_html__('You can either:', 'bocs-wordpress') . '</p>';
            $message .= '<ul style="list-style-type: disc; margin-left: 20px;">';
            $message .= '<li>' . esc_html__('Deactivate and delete the existing Bocs plugin before installing this version, or', 'bocs-wordpress') . '</li>';
            $message .= '<li>' . esc_html__('Use the Update button if available to update the existing plugin to a newer version', 'bocs-wordpress') . '</li>';
            $message .= '</ul>';
            $message .= '<p><a href="' . esc_url(admin_url('plugins.php')) . '" class="button button-secondary">';
            $message .= esc_html__('← Go back to plugins page', 'bocs-wordpress');
            $message .= '</a></p>';
            $message .= '</div>';

            wp_die(
                $message,
                esc_html__('Installation Stopped', 'bocs-wordpress'),
                array(
                    'back_link' => false,
                    'response'  => 403
                )
            );
        }

        return $source;
    }

    /**
     * Show admin notice for duplicate plugin uploads
     *
     * @since  0.0.113
     * @access public
     * @return void
     */
    public function show_duplicate_notice() {
        if (get_transient('bocs_duplicate_upload_attempt')) {
            delete_transient('bocs_duplicate_upload_attempt');
            ?>
            <div class="error">
                <p><?php _e('Warning: Another instance of the Bocs plugin was detected. You can either:', 'bocs-wordpress'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Deactivate and delete the existing Bocs plugin before installing a new version, or', 'bocs-wordpress'); ?></li>
                    <li><?php _e('Use the Update button if available to update the existing plugin', 'bocs-wordpress'); ?></li>
                </ul>
            </div>
            <?php
        }
    }
}