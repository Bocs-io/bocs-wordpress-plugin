<?php

namespace Bocs\Updater\Repository;

if ( ! defined('ABSPATH') ) die('You are not allowed to access this page directly.');

if (!class_exists("Bocs\\Updater\\Repository\\AbstractRepository")){
    require_once dirname(__FILE__).'/AbstractRepository.php';
}

/**
 * CRU Updater
 *
 */
class BocsRepo extends AbstractRepository {

    /**
     * Authorize token
     *
     * @var string
     */
    protected $token;

    /**
     * Class constructor
     *
     * @param $username
     * @param $repository
     * @param $token
     */
    public function __construct($token = null) {
    }

    /**
     * Get repository details
     *
     * @return void
     */
    public function get_details() {

        if (is_null($this->details)) {

            $request_uri = sprintf('https://b84gp25mke.execute-api.ap-southeast-2.amazonaws.com/dev/cru-wordpress-plugins-repository-get-latest-version?plugin=%s', 'bocs');
            $args = array();

            if($this->token) {
                $args['headers']['Authorization'] = "token {$this->token}";
            }

            $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri, $args)), true);
            if(is_array($response)) {
                $response = current($response);
            }

            $this->details = $response;

        }

        return $this;

    }

    /**
     * Get plugin version
     *
     * @return void
     */
    public function version() {
        $version = $this->details['version'] ?? null;
        return ltrim($version, 'v');
    }

    /**
     * Get plugin download url
     *
     * @return void
     */
    public function download_url() {
        return $this->details['url'];
    }

    /**
     * Get plugin body
     *
     * @return void
     */
    public function body() {
        return $this->details['body'] ?? null;
    }

    /**
     * Get plugin published date
     *
     * @return void
     */
    public function published_at() {
        return $this->details['last_updated'] ?? null;
    }

    /**
     * Download plugin package
     *
     * @param $args
     * @param $url
     * @return array
     */
    public function download_package($args, $url) {

        if (null !== $args['filename']) {
            if($this->token) {
                $args = array_merge($args, array("headers" => array("Authorization" => "token {$this->token}")));
            }
        }

        remove_filter('http_request_args', [$this, 'download_package']);

        return $args;

    }

}