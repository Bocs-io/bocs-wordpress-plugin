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

// Include plugin functions if not already included
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
     * Plugin path
     *
     * @since  0.0.109
     * @access private
     * @var    string
     */
    private $plugin_path;

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
        $this->basename = plugin_basename($file);
        $this->plugin_path = plugin_dir_path($file);
        $this->active = is_plugin_active($this->basename);
        
        // Force WordPress to check for updates
        delete_site_transient('update_plugins');
        
        // Initialize hooks
        add_action('admin_init', array($this, 'set_plugin_properties'));
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Add hook for duplicate plugin notices
        add_action('admin_notices', array($this, 'show_duplicate_notice'));
        
        // Force an immediate check
        add_action('admin_init', function() {
            wp_update_plugins();
        });
    }

    /**
     * Set plugin properties
     *
     * Initializes plugin data.
     *
     * @since  0.0.109
     * @access public
     * @return void
     */
    public function set_plugin_properties() {
        $this->plugin = get_plugin_data($this->file);
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
     * After installation is complete
     *
     * @access public
     * @param  bool  $response   Installation response
     * @param  array $hook_extra Extra arguments passed to hooked filters
     * @param  array $result     Installation result data
     * @return array             Modified result data
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // First check if the installed plugin is BOCS
        $bocs_main_file = $result['destination'] . '/bocs.php';
        
        // Only continue with duplicate detection if this is the BOCS plugin
        if (file_exists($bocs_main_file)) {
            // Check for duplicate BOCS installations
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            // Get the plugin data from the installed plugin
            $plugin_data = get_plugin_data($bocs_main_file, false, false);
            
            // If this is a BOCS plugin, check for duplicates
            if (!empty($plugin_data) && !empty($plugin_data['Name']) && strtolower($plugin_data['Name']) === 'bocs') {
                // Get the version of the installed plugin
                $installed_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
                
                // Get the current plugin path for comparison
                $current_plugin_path = plugin_basename($bocs_main_file);
                $current_plugin_dir = dirname($current_plugin_path);

                // Now check for existing BOCS installations
                $all_plugins = get_plugins();
                $existing_bocs = array();

                foreach ($all_plugins as $plugin_path => $plugin_info) {
                    // Skip the plugin we just installed
                    if (strpos($plugin_path, $current_plugin_dir) === 0) {
                        continue;
                    }
                    
                    // Check if the plugin file ends with 'bocs.php'
                    if (basename($plugin_path) === 'bocs.php') {
                        $plugin_name = isset($plugin_info['Name']) ? $plugin_info['Name'] : '';
                        
                        // Double-check the name to confirm it's a BOCS plugin
                        if (!empty($plugin_name) && strtolower($plugin_name) === 'bocs') {
                            $existing_bocs[] = array(
                                'path' => $plugin_path,
                                'version' => isset($plugin_info['Version']) ? $plugin_info['Version'] : '0.0.0'
                            );
                        }
                    }
                }

                // If we found other BOCS installations, store them for later notice
                if (!empty($existing_bocs)) {
                    update_option('bocs_duplicate_plugins', $existing_bocs);
                }
            }
        }

        // Continue with the original functionality - move the plugin to its final location
        $wp_filesystem->move($result['destination'], $this->plugin_path);
        $result['destination'] = $this->plugin_path;
        $this->activate_plugin();

        return $result;
    }

    /**
     * Display notice about duplicate BOCS plugins
     */
    public function show_duplicate_notice() {
        $duplicate_plugins = get_option('bocs_duplicate_plugins', array());
        
        if (empty($duplicate_plugins)) {
            return;
        }
        
        // Get current plugin data safely
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Make sure the file exists before trying to get plugin data
        if (file_exists($this->file)) {
            $current_plugin_data = get_plugin_data($this->file, false, false);
            $current_version = isset($current_plugin_data['Version']) ? $current_plugin_data['Version'] : '0.0.0';
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Multiple BOCS Plugin Installations Detected', 'bocs-wordpress') . '</strong></p>';
            echo '<p>' . esc_html__('The following BOCS plugin installations were found:', 'bocs-wordpress') . '</p>';
            echo '<ul>';
            
            // Current plugin
            echo '<li>' . sprintf(
                esc_html__('Current: %s (version %s)', 'bocs-wordpress'),
                plugin_basename($this->file),
                $current_version
            ) . '</li>';
            
            // Other plugins
            foreach ($duplicate_plugins as $plugin) {
                echo '<li>' . sprintf(
                    esc_html__('Additional: %s (version %s)', 'bocs-wordpress'),
                    $plugin['path'],
                    $plugin['version']
                ) . '</li>';
            }
            
            echo '</ul>';
            echo '<p>' . esc_html__('Having multiple BOCS plugins installed may cause conflicts. Please deactivate and remove the older versions.', 'bocs-wordpress') . '</p>';
            echo '<p><a href="' . esc_url(admin_url('plugins.php')) . '" class="button button-primary">';
            echo esc_html__('Manage Plugins', 'bocs-wordpress');
            echo '</a></p>';
            echo '</div>';
        }
        
        // Clear the option after displaying the notice
        delete_option('bocs_duplicate_plugins');
    }
    
    /**
     * Activate the plugin
     */
    public function activate_plugin() {
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        activate_plugin($this->basename);
    }
}