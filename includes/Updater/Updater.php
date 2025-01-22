<?php namespace Bocs\Updater;

if ( ! defined('ABSPATH') ) die('You are not allowed to access this page directly.');

/**
 * Plugin remote update
 * rayman813/smashing-updater-plugin
 */
class Updater {

    /**
     * Plugin file path
     *
     * @var string
     */
    private $file;

    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin;

    /**
     * Plugin base name
     *
     * Ex. cru-mu/cru-mu.php
     * @var string
     */
    private $basename;

    /**
     * Determine if plugin is active
     *
     * @var bool
     */
    private $active;

    /**
     * Remote repo
     *
     * @var Repository\AbstractRepository
     */
    private $repo = null;

    /**
     * Class constructor
     *
     * @param $file
     */
    public function __construct($file, Repository\AbstractRepository $repo) {

        $this->file = $file;
        $this->repo = $repo;

        // Get repository details
        // $details = $this->repo->get_details();

        add_action('admin_init', array($this, 'set_plugin_properties'));

    }

    /**
     * Set's plugin properties
     *
     * @return void
     */
    public function set_plugin_properties() {
        $this->plugin	= get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active	= is_plugin_active($this->basename);
    }

    /**
     * Initialize updater
     *
     * @return void
     */
    public function bootstrap() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Add Authorization Token to download_package
        add_filter('upgrader_pre_download', function() {
                $details = $this->repo->get_details();
                add_filter('http_request_args', [$details, 'download_package'], 15, 2);
                return false;
            }
        );
    }

    /**
     * Modify transient data
     *
     * @param $transient
     * @return object
     */
    public function modify_transient($transient) {

        if(property_exists($transient, 'checked')) {
            if($transient->checked) {

                if (empty($this->plugin)) {
                    $this->set_plugin_properties();
                }

                $details = $this->repo->get_details();
                $version = $transient->checked[$this->basename] ?? null;
                $out_of_date = version_compare($details->version(), $version, 'gt');
                if($out_of_date) {

                    $new_files = $details->download_url();
                    $slug = current(explode('/', $this->basename));

                    $plugin = array(
                        'url'         => $this->plugin["PluginURI"],
                        'slug'        => $slug,
                        'package'     => $new_files,
                        'new_version' => $details->version()
                    );

                    $transient->response[$this->basename] = (object) $plugin;
                }
            }
        }

        return $transient;

    }

    /**
     * Load plugin data
     *
     * @param $result
     * @param $action
     * @param $args
     * @return void
     */
    public function plugin_popup($result, $action, $args) {

        if( !empty($args->slug)) {

            if($args->slug == current(explode('/' , $this->basename))) {

                $details = $this->repo->get_details();
                $plugin = array(
                    'name'				=> $this->plugin["Name"],
                    'slug'				=> $this->basename,
                    'version'			=> $details->version(),
                    'author'			=> $this->plugin["AuthorName"],
                    'author_profile'	=> $this->plugin["AuthorURI"],
                    'last_updated'		=> $details->published_at(),
                    'homepage'			=> $this->plugin["PluginURI"],
                    'short_description' => $this->plugin["Description"],
                    'sections'			=> array(
                        'Description'	=> $this->plugin["Description"],
                        'Updates'		=> $details->body(),
                    ),
                    'download_link'		=> $details->download_url()
                );

                return (object) $plugin;
            }

        }

        return $result;
    }

    /**
     * After plugin install
     *
     * @param $response
     * @param $hook_extra
     * @param $result
     * @return array
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