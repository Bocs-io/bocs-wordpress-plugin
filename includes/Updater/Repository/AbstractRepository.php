<?php

namespace Bocs\Updater\Repository;

if ( ! defined('ABSPATH') ) die('You are not allowed to access this page directly.');

/**
 * Github Updater
 *
 */
abstract class AbstractRepository {

    /**
     * Plugin details
     * 
     * @var array
     */
    protected $details;

    /**
     * Get github repository info
     *
     * @return void
     */
    abstract public function get_details();

    /**
     * Get plugin version
     *
     * @return void
     */
    abstract public function version();

    /**
     * Get plugin download url
     *
     * @return void
     */
    abstract public function download_url();

    /**
     * Get plugin body
     *
     * @return void
     */
    abstract public function body();

    /**
     * Get plugin published date
     *
     * @return void
     */
    abstract public function published_at();

    /**
     * Download plugin package
     *
     * @param $args
     * @param $url
     * @return array
     */
    abstract public function download_package($args, $url);

}