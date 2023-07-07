<?php namespace Bocs\Updater\Repository;
if ( ! defined('ABSPATH') ) die('You are not allowed to access this page directly.');

/**
 * Github Updater
 *
 */
class Github extends AbstractRepository {

    /**
     * Github username
     *
     * @var string
     */
    protected $username;

    /**
     * Github repository
     *
     * @var string
     */
    protected $repository;

    /**
     * Github authorize token
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
    public function __construct($username, $repository, $token = null) {

        $this->username   = $username;
        $this->repository = $repository;
        $this->token      = $token;

    }

    /**
     * Get repository details
     *
     * @return void
     */
    public function get_details() {

        if (is_null($this->details)) {

            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $this->username, $this->repository);
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
        $version = $this->details['tag_name'] ?? null;
        return ltrim($version, 'v');
    }

    /**
     * Get plugin download url
     *
     * @return void
     */
    public function download_url() {
        return $this->details['zipball_url'];
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
        return $this->details['published_at'] ?? null;
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